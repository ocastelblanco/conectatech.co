/**
 * conectatech-email-forwarder — Node.js 24.x
 *
 * Lee el correo entrante desde S3, elimina las cabeceras DKIM-Signature
 * del original (SES añade las suyas al reenviar), reemplaza el From por
 * forwarder@conectatech.co y reenvía al Gmail correspondiente.
 */

import { S3Client, GetObjectCommand } from '@aws-sdk/client-s3';
import { SESClient, SendRawEmailCommand } from '@aws-sdk/client-ses';

const s3  = new S3Client({ region: 'us-east-1' });
const ses = new SESClient({ region: 'us-east-1' });

const BUCKET            = 'conectatech-ses-incoming-emails';
const FORWARDER_ADDRESS = 'forwarder@conectatech.co';

const FORWARD_MAP = [
  { match: 'info@conectatech.co',                dest: 'somos.conectatech@gmail.com' },
  { match: 'digital@conectatech.co',             dest: 'ocastelblanco@gmail.com'     },
  { match: 'ana.mora@conectatech.co',            dest: 'ajumoto@gmail.com'           },
  { match: 'oliver.castelblanco@conectatech.co', dest: 'ocastelblanco@gmail.com'     },
  { match: '@conectatech.co',                    dest: 'somos.conectatech@gmail.com' },
];

function resolveDestination(recipients) {
  for (const recipient of recipients) {
    const addr = recipient.toLowerCase();
    for (const rule of FORWARD_MAP) {
      if (addr === rule.match || (rule.match.startsWith('@') && addr.endsWith(rule.match))) {
        return rule.dest;
      }
    }
  }
  return FORWARD_MAP.at(-1).dest;
}

function streamToBuffer(stream) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    stream.on('data', chunk => chunks.push(chunk));
    stream.on('end', () => resolve(Buffer.concat(chunks)));
    stream.on('error', reject);
  });
}

// Cabeceras que deben eliminarse antes de reenviar:
// - DKIM-Signature: el reenvío invalida la firma; SES añade la suya
// - Return-Path: contiene la dirección de rebote del remitente original
//   (e.g. ...@amazonses.com) que en sandbox SES verifica y rechaza
const STRIP_HEADERS = ['DKIM-Signature', 'Return-Path'];

function stripProblematicHeaders(rawEmail) {
  const sepMatch = rawEmail.match(/\r?\n\r?\n/);
  if (!sepMatch) return rawEmail;

  const headersPart = rawEmail.slice(0, sepMatch.index);
  const bodyPart    = rawEmail.slice(sepMatch.index);

  const lines    = headersPart.split(/\r?\n/);
  const filtered = [];
  let skipping   = false;

  for (const line of lines) {
    const isStrippedHeader = STRIP_HEADERS.some(h => new RegExp(`^${h}:`, 'i').test(line));
    if (isStrippedHeader) {
      skipping = true;
      continue;
    }
    if (skipping && /^[ \t]/.test(line)) {
      continue;
    }
    skipping = false;
    filtered.push(line);
  }

  return filtered.join('\n') + bodyPart;
}

function rewriteFrom(rawEmail, originalFrom) {
  const forwarderFrom = `"${originalFrom}" <${FORWARDER_ADDRESS}>`;
  return rawEmail.replace(/^From:.*$/im, `From: ${forwarderFrom}`);
}

export const handler = async (event) => {
  const s3Record = event.Records?.[0]?.s3;
  if (!s3Record) {
    console.error('Evento sin registro S3:', JSON.stringify(event));
    return;
  }

  const key = decodeURIComponent(s3Record.object.key.replace(/\+/g, ' '));
  console.log(`Procesando correo: s3://${BUCKET}/${key}`);

  const s3Obj    = await s3.send(new GetObjectCommand({ Bucket: BUCKET, Key: key }));
  const rawBuffer = await streamToBuffer(s3Obj.Body);
  const rawEmail  = rawBuffer.toString('utf-8');

  // Extraer destinatarios @conectatech.co
  const toMatch  = rawEmail.match(/^To:\s*(.+)$/im);
  const toHeader = toMatch ? toMatch[1] : '';
  const recipients = toHeader
    .split(',')
    .map(r => r.replace(/.*<(.+)>/, '$1').trim().toLowerCase())
    .filter(r => r.includes('@conectatech.co'));

  if (recipients.length === 0) {
    console.warn('Sin destinatarios @conectatech.co — usando catch-all');
    recipients.push('info@conectatech.co');
  }

  const fromMatch    = rawEmail.match(/^From:\s*(.+)$/im);
  const originalFrom = fromMatch ? fromMatch[1].trim() : 'unknown';

  const destination   = resolveDestination(recipients);
  const cleanedEmail  = stripProblematicHeaders(rawEmail);
  const rewrittenEmail = rewriteFrom(cleanedEmail, originalFrom);

  await ses.send(new SendRawEmailCommand({
    Source:       FORWARDER_ADDRESS,
    Destinations: [destination],
    RawMessage:   { Data: Buffer.from(rewrittenEmail) },
  }));

  console.log(`✓ Reenviado ${recipients.join(', ')} → ${destination}`);
};
