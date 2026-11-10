#!/usr/bin/env node
/**
 * Builds references/asvs-5.0.0.json from the official ASVS CSV export.
 * Usage: node scripts/build-asvs-index.mjs <path-to-OWASP_..._5.0.0_en.csv>
 *
 * Source: https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/docs_en (CC BY-SA 4.0).
 * The output is derived content and therefore lives in references/ under CC BY-SA 4.0.
 */

import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

const VERSION = '5.0.0';
const here = dirname(fileURLToPath(import.meta.url));

// Minimal RFC 4180 parser: quoted fields, escaped quotes, CRLF.
export function parseCsv(text) {
  const rows = [];
  let row = [], field = '', quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') { field += '"'; i++; }
      else if (c === '"') quoted = false;
      else field += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') { row.push(field); field = ''; }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(field); field = '';
      if (row.some((f) => f !== '')) rows.push(row);
      row = [];
    } else field += c;
  }
  if (field !== '' || row.length) { row.push(field); rows.push(row); }
  return rows;
}

function main() {
  const csvPath = process.argv[2];
  if (!csvPath) { console.error('Usage: build-asvs-index.mjs <asvs.csv>'); process.exit(2); }
  const [header, ...rows] = parseCsv(readFileSync(csvPath, 'utf8').replace(/^﻿/, ''));
  const col = Object.fromEntries(header.map((h, i) => [h, i]));
  const requirements = {};
  const chapters = {};
  for (const r of rows) {
    const id = r[col.req_id].replace(/^V/, '');
    chapters[r[col.chapter_id].replace(/^V/, '')] = r[col.chapter_name];
    requirements[id] = {
      level: Number(r[col.L]),
      section: r[col.section_name],
      text: r[col.req_description].replace(/^Verify that /, ''),
    };
  }
  const out = {
    standard: 'OWASP ASVS',
    version: VERSION,
    id_format: `v${VERSION}-<chapter>.<section>.<requirement>`,
    source: `https://github.com/OWASP/ASVS/tree/v${VERSION}/5.0/docs_en`,
    license: 'CC BY-SA 4.0',
    chapters,
    requirements,
  };
  const dest = join(here, '..', 'references', `asvs-${VERSION}.json`);
  writeFileSync(dest, JSON.stringify(out, null, 1) + '\n');
  console.log(`${Object.keys(requirements).length} requisitos -> ${dest}`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main();
