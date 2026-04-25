/**
 * conectatech-email-forwarder — Node.js 24.x
 *
 * Lee el correo entrante desde S3, reemplaza el From por forwarder@conectatech.co
 * y reenvía al Gmail correspondiente según FORWARD_MAP.
 *
 * Activado por: S3 ObjectCreated en prefix incoming/ → SES receipt rule
 */

import { S3Client, GetObjectCommand } from '@aws-sdk/client-s3';
import { SESClient, SendRawEmailCommand } from '@aws-sdk/client-ses';

const s3  = new S3Client({ region: 'us-east-1' });
const ses = new SESClient({ region: 'us-east-1' });

const BUCKET            = 'conectatech-ses-incoming-emails';
const FORWARDER_ADDRESS = 'forwarder@conectatech.co';

// Mapa de redirección (dominio en minúsculas). Catch-all al final.
const FORWARD_MAP = [
  { match: 'info@conectatech.co',                dest: 'somos.conectatech@gmail.com' },
  { match: 'digital@conectatech.co',             dest: 'ocastelblanco@gmail.com'     },
  { match: 'ana.mora@conectatech.co',            dest: 'ajumoto@gmail.com'           },
  { match: 'oliver.castelblanco@conectatech.co', dest: 'ocastelblanco@gmail.com'     },
  { match: '@conectatech.co',                    dest: 'somos.conectatech@gmail.com' }, // catch-all
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
  return FORWARD_MAP.at(-1).dest; // fallback: catch-all
}

function streamToBuffer(stream) {
  return new Promise((resolve, reject) => {
    const chunks = [];
    stream.on('data', chunk => chunks.push(chunk));
    stream.on('end', () => resolve(Buffer.concat(chunks)));
    stream.on('error', reject);
  });
}

function rewriteFrom(rawEmail, originalFrom) {
  // Reemplaza la cabecera From para pasar la validación DKIM/SPF del remitente
  const forwarderFrom = `"${originalFrom}" <${FORWARDER_ADDRESS}>`;
  return rawEmail.replace(
    /^From:.*$/im,
    `From: ${forwarderFrom}`
  );
}

export const handler = async (event) => {
  const s3Record = event.Records?.[0]?.s3;
  if (!s3Record) {
    console.error('Evento sin registro S3:', JSON.stringify(event));
    return;
  }

  const key = decodeURIComponent(s3Record.object.key.replace(/\+/g, ' '));
  console.log(`Procesando correo: s3://${BUCKET}/${key}`);

  // 1. Obtener el correo raw de S3
  const s3Obj = await s3.send(new GetObjectCommand({ Bucket: BUCKET, Key: key }));
  const rawBuffer = await streamToBuffer(s3Obj.Body);
  const rawEmail  = rawBuffer.toString('utf-8');

  // 2. Extraer destinatarios originales del encabezado To/CC
  const toMatch  = rawEmail.match(/^To:\s*(.+)$/im);
  const toHeader = toMatch ? toMatch[1] : '';
  const recipients = toHeader
    .split(',')
    .map(r => r.replace(/.*<(.+)>/, '$1').trim().toLowerCase())
    .filter(r => r.includes('@conectatech.co'));

  if (recipients.length === 0) {
    console.warn('No se encontraron destinatarios @conectatech.co — usando catch-all');
    recipients.push('info@conectatech.co');
  }

  // 3. Extraer From original para mantenerlo como referencia en el reenvío
  const fromMatch = rawEmail.match(/^From:\s*(.+)$/im);
  const originalFrom = fromMatch ? fromMatch[1].trim() : 'unknown';

  // 4. Resolver destino y reescribir From
  const destination = resolveDestination(recipients);
  const rewrittenEmail = rewriteFrom(rawEmail, originalFrom);

  // 5. Reenviar via SES
  await ses.send(new SendRawEmailCommand({
    Source:       FORWARDER_ADDRESS,
    Destinations: [destination],
    RawMessage:   { Data: Buffer.from(rewrittenEmail) },
  }));

  console.log(`✓ Reenviado ${recipients.join(', ')} → ${destination}`);
};
