#!/usr/bin/env node
/**
 * Records the agent's verdict for a review item. This is the "no invented data" gate:
 * a verdict is rejected unless every piece of evidence points at something that exists
 * (a file in the project and a line inside it, an allowed live URL, or an AWS API call).
 *
 * Usage:
 *   node scripts/findings.mjs record --root <project> --check BASE-19 --status pass|fail|not-applicable \
 *        --evidence rund-api/app/src/Middleware/AuthMiddleware.php:42 [--evidence ...] --note "..." [--out <dir>]
 *   node scripts/findings.mjs pending --root <project> [--out <dir>]
 *
 * Evidence formats: `path:line`, `path:start-end`, `https://…` (live mode), `aws:<service>:<Operation>[:resource]`.
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve, normalize } from 'node:path';
import { catalog, parseArgs, isMain } from './lib/catalog.mjs';
import { OUTPUT_DIR, readText } from './lib/files.mjs';

const STATUSES = new Set(['pass', 'fail', 'not-applicable']);

export function validateEvidence(root, items, { liveUrls = [] } = {}) {
  const errors = [];
  const ok = [];
  for (const raw of items) {
    const e = String(raw).trim();
    if (/^https?:\/\//.test(e)) {
      const host = (() => { try { return new URL(e).host; } catch { return null; } })();
      if (!host || !liveUrls.some((u) => new URL(u).host === host)) errors.push(`${e}: URL fuera del allowlist del modo live`);
      else ok.push({ url: e });
      continue;
    }
    if (/^aws:/.test(e)) {
      if (!/^aws:[a-z0-9-]+:[A-Z][A-Za-z]+(:.+)?$/.test(e)) errors.push(`${e}: formato esperado aws:<servicio>:<Operación>[:recurso]`);
      else ok.push({ aws: e });
      continue;
    }
    const m = /^(.+?):(\d+)(?:-(\d+))?$/.exec(e);
    if (!m) { errors.push(`${e}: formato esperado ruta:línea`); continue; }
    const rel = normalize(m[1]).replace(/^\.\//, '');
    if (rel.startsWith('..') || rel.startsWith('/')) { errors.push(`${e}: la ruta debe ser relativa al proyecto`); continue; }
    if (!existsSync(join(root, rel))) { errors.push(`${e}: el archivo no existe`); continue; }
    const text = readText(root, rel);
    const lines = text == null ? 0 : text.replace(/\n$/, '').split('\n').length;
    const start = Number(m[2]), end = Number(m[3] || m[2]);
    if (start < 1 || end < start || end > lines) { errors.push(`${e}: línea fuera de rango (el archivo tiene ${lines})`); continue; }
    ok.push({ file: rel, line: start, end: end !== start ? end : undefined, snippet: text.split('\n')[start - 1].trim().slice(0, 160) });
  }
  return { ok, errors };
}

function load(root, out) {
  const file = join(out ? resolve(out) : join(root, OUTPUT_DIR), 'results.json');
  if (!existsSync(file)) throw new Error(`No hay ${file}: ejecuta primero scripts/verify.mjs`);
  return { file, doc: JSON.parse(readFileSync(file, 'utf8')) };
}

export function record(root, { check, status, evidence = [], note = '', out, by = 'agent' }) {
  const { file, doc } = load(root, out);
  const errors = [];
  const c = catalog().checks.find((x) => x.id === check);
  if (!c) errors.push(`Chequeo inexistente: ${check}`);
  else if (!doc.checks.includes(check)) errors.push(`${check} no aplica en esta corrida (no está en el plan)`);
  if (!STATUSES.has(status)) errors.push(`Estado inválido "${status}": usa pass, fail o not-applicable`);
  const ev = validateEvidence(root, [].concat(evidence).filter(Boolean), { liveUrls: doc.profile?.live?.urls?.map((u) => u.url ?? u) || [] });
  errors.push(...ev.errors);
  if ((status === 'pass' || status === 'fail') && !ev.ok.length) errors.push('Un veredicto pass o fail necesita al menos una evidencia verificable');
  if (status !== 'pass' && !note.trim()) errors.push('Explica el veredicto en --note');
  if (errors.length) return { ok: false, errors };
  doc.verdicts[check] = { status, evidence: ev.ok, note: note.trim(), by, at: new Date().toISOString() };
  doc.queue = doc.queue.filter((id) => id !== check);
  writeFileSync(file, JSON.stringify(doc, null, 2));
  return { ok: true, remaining: doc.queue.length };
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const root = resolve(args.root || '.');
  const cmd = args._[0];
  if (cmd === 'record') {
    const r = record(root, { check: args.check, status: args.status, evidence: [].concat(args.evidence || []), note: typeof args.note === 'string' ? args.note : '', out: args.out });
    if (!r.ok) { console.error(`Veredicto rechazado:\n${r.errors.map((e) => `  ✗ ${e}`).join('\n')}`); process.exit(1); }
    console.log(`✓ ${args.check} registrado · quedan ${r.remaining} por revisar`);
  } else if (cmd === 'pending') {
    const { doc } = load(root, args.out);
    const byId = Object.fromEntries(catalog().checks.map((c) => [c.id, c]));
    if (!doc.queue.length) console.log('Sin pendientes.');
    for (const id of doc.queue) console.log(`${id}  ${byId[id].title}`);
  } else {
    console.error('Uso: findings.mjs record|pending --root <proyecto> …');
    process.exit(2);
  }
}
