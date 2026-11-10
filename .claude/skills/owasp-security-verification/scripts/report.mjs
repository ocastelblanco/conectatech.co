#!/usr/bin/env node
/**
 * Builds the final report from results.json (+ agent verdicts, + live.json if present):
 * report.md (Spanish), report.sarif (SARIF 2.1.0) and a diff against the previous run.
 *
 * Usage: node scripts/report.mjs --root <project> [--out <dir>]
 */

import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { catalog, cheatsheetUrl, asvsKey, parseArgs, isMain } from './lib/catalog.mjs';
import { OUTPUT_DIR } from './lib/files.mjs';

const SEV = { critical: 4, high: 3, medium: 2, low: 1 };
const LABEL = { fail: 'Falla', pass: 'Cumple', pending: 'Pendiente de revisión', 'not-verified': 'No verificado', 'not-applicable': 'No aplica' };
const SOURCE = { rule: 'regla estática', tool: 'herramienta', agent: 'revisión del agente', live: 'modo live' };

/** Merges rule/tool results, live results and agent verdicts into one status per check. */
export function finalize(doc, live) {
  const byId = Object.fromEntries(catalog().checks.map((c) => [c.id, c]));
  const out = {};
  for (const id of doc.checks) {
    const c = byId[id];
    const r = (c.type === 'live' ? live?.results?.[id] : doc.results[id]) || { status: c.type === 'live' ? 'not-verified' : 'pending', findings: [] };
    const v = doc.verdicts?.[id];
    let status = r.status === 'review' ? 'pending' : r.status;
    let source = r.source || (c.type === 'review' ? 'agent' : c.type === 'live' ? 'live' : 'rule');
    let findings = (r.findings || []).filter((f) => !f.accepted);
    if (c.type === 'live' && !live) status = 'not-verified';
    if (v) {
      status = v.status;
      source = r.source && c.type !== 'review' ? `${r.source}+agent` : 'agent';
      if (v.status === 'fail') findings = v.evidence.map((e) => ({ file: e.file || e.url || e.aws, line: e.line ?? null, snippet: e.snippet || '', confidence: 'high', message: '' }));
      else findings = [];
    }
    out[id] = { check: c, status, source, findings, accepted: (r.findings || []).filter((f) => f.accepted), evidence: v?.evidence || r.evidence || [], note: v?.note || r.note || (c.type === 'live' && !live ? 'Requiere modo live (opt-in).' : undefined) };
  }
  return out;
}

function previousRun(dir) {
  const h = join(dir, 'history');
  if (!existsSync(h)) return null;
  const files = readdirSync(h).filter((f) => f.endsWith('.json')).sort();
  if (!files.length) return null;
  return JSON.parse(readFileSync(join(h, files[files.length - 1]), 'utf8'));
}

export function diff(current, prevDoc) {
  if (!prevDoc) return null;
  const prev = finalize(prevDoc, null);
  const failing = (m) => new Set(Object.entries(m).filter(([, x]) => x.status === 'fail').map(([id]) => id));
  const a = failing(prev), b = failing(current);
  return { at: prevDoc.meta?.at, nuevos: [...b].filter((x) => !a.has(x)), resueltos: [...a].filter((x) => !b.has(x)) };
}

const asvsLine = (ids) => ids.map((id) => {
  const r = catalog().asvs.requirements[asvsKey(id)];
  return `\`${id}\` (L${r.level})`;
}).join(', ');

export function markdown(doc, fin, d, live) {
  const { top10 } = catalog();
  const rows = Object.values(fin);
  const n = (s) => rows.filter((x) => x.status === s).length;
  const verified = n('pass') + n('fail') + n('not-applicable');
  const L = [];
  L.push(`# Verificación de seguridad OWASP — ${doc.profile.project}`, '');
  L.push(`| | |`, `|---|---|`);
  L.push(`| Fecha | ${doc.meta.at.slice(0, 16).replace('T', ' ')} UTC |`);
  L.push(`| Alcance | \`${doc.meta.scope || '(proyecto completo)'}\` · ${doc.meta.scanned_files} archivos |`);
  const sig = Object.entries(doc.profile.signals || {}).filter(([, v]) => v).map(([k]) => k);
  L.push(`| Nivel ASVS | L${doc.profile.level}${sig.length ? ` (señales: ${sig.join(', ')})` : ''}${doc.profile.confirmed ? '' : ' · **perfil sin confirmar**'} |`);
  L.push(`| Facetas | ${doc.profile.facets.join(', ')} |`);
  L.push(`| Estándares | ASVS ${doc.meta.standards.asvs} · OWASP Top 10:${doc.meta.standards.top10} · OWASP Cheat Sheet Series |`);
  if (live) L.push(`| Modo live | ${live.urls.join(', ')} (${live.at.slice(0, 10)}) |`);
  L.push('');
  L.push('## Resumen', '');
  L.push(`**${n('fail')} fallas** · ${n('pass')} cumple · ${n('pending')} pendientes de revisión · ${n('not-verified')} no verificados · ${n('not-applicable')} no aplican`, '');
  L.push(`Cobertura: **${Math.round((verified / rows.length) * 100)} %** de ${rows.length} chequeos aplicables tienen veredicto con evidencia.`, '');
  if (d) L.push(`Respecto a la corrida del ${d.at?.slice(0, 10)}: ${d.nuevos.length} fallas nuevas${d.nuevos.length ? ` (${d.nuevos.join(', ')})` : ''}, ${d.resueltos.length} resueltas${d.resueltos.length ? ` (${d.resueltos.join(', ')})` : ''}.`, '');
  L.push('| Top 10:2025 | Falla | Cumple | Pendiente / no verificado |', '|---|---:|---:|---:|');
  for (const [k, name] of Object.entries(top10.categories)) {
    const r = rows.filter((x) => x.check.top10 === k);
    if (!r.length) continue;
    const c = (s) => r.filter((x) => s.includes(x.status)).length;
    L.push(`| ${k} ${name} | ${c(['fail']) || ''} | ${c(['pass']) || ''} | ${c(['pending', 'not-verified']) || ''} |`);
  }
  L.push('');

  const fails = rows.filter((x) => x.status === 'fail').sort((a, b) => SEV[b.check.severity] - SEV[a.check.severity] || a.check.id.localeCompare(b.check.id));
  L.push('## Fallas', '');
  if (!fails.length) L.push('_Sin fallas con evidencia._', '');
  for (const x of fails) {
    const c = x.check;
    L.push(`### ${c.severity.toUpperCase()} · ${c.id} — ${c.title}`, '');
    L.push(`- **Riesgo:** ${c.top10}:2025 ${top10.categories[c.top10]} · **Fuente:** ${SOURCE[x.source] || x.source}`);
    L.push(`- **ASVS:** ${c.asvs.length ? asvsLine(c.asvs) : '_sin requisito ASVS directo; criterio de la cheat sheet_'}${c.always ? ' · aplicado en todo nivel' : ''}`);
    L.push(`- **Corrección:** [${c.cheatsheet.replace(/_/g, ' ')} Cheat Sheet](${cheatsheetUrl(c.cheatsheet)})`);
    L.push('- **Evidencia:**');
    for (const f of x.findings.slice(0, 15)) L.push(`  - \`${f.file}${f.line ? `:${f.line}` : ''}\`${f.message ? ` — ${f.message}` : ''}${f.snippet ? `<br>\`${f.snippet.replace(/`/g, "'")}\`` : ''}`);
    if (x.findings.length > 15) L.push(`  - … ${x.findings.length - 15} más en results.json`);
    if (x.note && x.source.includes('agent')) L.push(`- **Nota:** ${x.note}`);
    L.push('');
  }

  const pending = rows.filter((x) => x.status === 'pending');
  if (pending.length) {
    L.push('## Pendientes de revisión', '', 'Sin veredicto con evidencia todavía. **No cuentan como aprobados.**', '');
    for (const x of pending) L.push(`- **${x.check.id}** ${x.check.title}${x.findings.length ? ` (${x.findings.length} hallazgos de baja confianza por confirmar)` : ''}`);
    L.push('');
  }
  const nv = rows.filter((x) => x.status === 'not-verified');
  if (nv.length) {
    L.push('## No verificados', '');
    for (const x of nv) L.push(`- **${x.check.id}** ${x.check.title}${x.note ? ` — ${x.note}` : ''}`);
    L.push('');
  }
  const acc = rows.filter((x) => x.accepted.length);
  if (acc.length) {
    L.push('## Excepciones aceptadas', '');
    for (const x of acc) for (const f of x.accepted) L.push(`- **${x.check.id}** \`${f.file}:${f.line}\` — ${f.accepted.reason}${f.accepted.expires ? ` (vence ${f.accepted.expires})` : ''}`);
    L.push('');
  }
  const ok = rows.filter((x) => x.status === 'pass');
  if (ok.length) {
    L.push('## Cumple', '');
    for (const x of ok) L.push(`- ${x.check.id} ${x.check.title}${x.evidence?.[0]?.file ? ` · \`${x.evidence[0].file}${x.evidence[0].line ? `:${x.evidence[0].line}` : ''}\`` : ''} _(${SOURCE[x.source] || x.source})_`);
    L.push('');
  }
  L.push('---', '');
  L.push('Cada falla trae evidencia verificable (archivo y línea, URL o llamada de API). Los veredictos del agente se aceptaron solo después de validar que la evidencia existe. "Pendiente" y "No verificado" significan que no hay evidencia: no son aprobados.', '');
  L.push('Contenido de requisitos adaptado de OWASP ASVS, OWASP Top 10 y OWASP Cheat Sheet Series (CC BY-SA 4.0).');
  return L.join('\n') + '\n';
}

export function sarif(doc, fin) {
  const fails = Object.values(fin).filter((x) => x.status === 'fail');
  const level = { critical: 'error', high: 'error', medium: 'warning', low: 'note' };
  const score = { critical: '9.5', high: '8.0', medium: '5.5', low: '3.0' };
  return {
    $schema: 'https://json.schemastore.org/sarif-2.1.0.json',
    version: '2.1.0',
    runs: [{
      tool: { driver: {
        name: 'owasp-security-verification',
        informationUri: 'https://github.com/ocastelblanco/ia-orchestration-skills/tree/main/skills/owasp-security-verification',
        rules: fails.map(({ check: c }) => ({
          id: c.id, name: c.id.replace(/-/g, ''),
          shortDescription: { text: c.title },
          helpUri: cheatsheetUrl(c.cheatsheet),
          properties: { tags: ['security', `OWASP-${c.top10}:2025`, ...c.asvs], 'security-severity': score[c.severity] },
        })),
      } },
      results: fails.flatMap(({ check: c, findings }) => findings.map((f) => ({
        ruleId: c.id,
        level: level[c.severity],
        message: { text: `${c.title}: ${f.message || fin[c.id]?.note || ''}`.replace(/: $/, '') },
        locations: [{ physicalLocation: { artifactLocation: { uri: f.file }, ...(f.line ? { region: { startLine: f.line } } : {}) } }],
      }))),
    }],
  };
}

export function buildReport(root, { out } = {}) {
  const dir = out ? resolve(out) : join(root, OUTPUT_DIR);
  const doc = JSON.parse(readFileSync(join(dir, 'results.json'), 'utf8'));
  const live = existsSync(join(dir, 'live.json')) ? JSON.parse(readFileSync(join(dir, 'live.json'), 'utf8')) : null;
  const fin = finalize(doc, live);
  const d = diff(fin, previousRun(dir));
  writeFileSync(join(dir, 'report.md'), markdown(doc, fin, d, live));
  writeFileSync(join(dir, 'report.sarif'), JSON.stringify(sarif(doc, fin), null, 2));
  return { dir, fin, diff: d };
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const { dir, fin } = buildReport(resolve(args.root || '.'), { out: args.out });
  const rows = Object.values(fin);
  const n = (s) => rows.filter((x) => x.status === s).length;
  console.log(`fail ${n('fail')} · pass ${n('pass')} · pendiente ${n('pending')} · no verificado ${n('not-verified')} · n/a ${n('not-applicable')}`);
  console.log(`${join(dir, 'report.md')}\n${join(dir, 'report.sarif')}`);
}
