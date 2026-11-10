#!/usr/bin/env node
/**
 * Static verification: detect -> select -> run rules/tools -> write results and the
 * review queue the agent must resolve with evidence.
 *
 * Usage: node scripts/verify.mjs --root <project> [--scope <sub-path>] [--level 1|2]
 *                                [--out <dir>] [--no-tools] [--json]
 *
 * Writes <out>/results.json (default <project>/.owasp/). Never modifies project files.
 */

import { existsSync, mkdirSync, readFileSync, renameSync, writeFileSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { catalog, parseArgs, isMain } from './lib/catalog.mjs';
import { OUTPUT_DIR, matchAny } from './lib/files.mjs';
import { loadProfile, profileFromDetection } from './lib/profile.mjs';
import { detect } from './detect-stack.mjs';
import { selectChecks } from './select-checks.mjs';
import { buildContext, runRules } from './run-rules.mjs';
import { isTest } from './rules/engine.mjs';

const MAX_CANDIDATES = 8;

export async function verify(root, { scope, level, out, tools = true } = {}) {
  const det = detect(root, { scope });
  const saved = loadProfile(root);
  const profile = saved ? { ...saved } : profileFromDetection(det);
  if (saved) {
    // New facets that appeared since the profile was confirmed are added, never silently dropped.
    const added = Object.keys(det.facets).filter((f) => !saved.facets.includes(f) && !saved.facets_disabled?.includes(f));
    if (added.length) { profile.facets = [...saved.facets, ...added]; profile.facets_added = added; }
  }
  if (level) profile.level = Number(level);

  const checks = selectChecks(profile);
  const ctx = buildContext(root, { scope, exclude: profile.exclude_paths || [] });
  const results = await runRules(ctx, checks, { profile, tools });

  const queue = [];
  for (const c of checks) {
    if (c.type === 'review') {
      const docs = c.look.some((g) => g.endsWith('.md'));
      const candidates = ctx.files
        .filter((f) => matchAny(f, c.look) && !isTest(f) && (docs || !/\.(md|txt|rst)$/i.test(f)))
        .slice(0, MAX_CANDIDATES);
      results[c.id] = { status: 'pending', findings: [], source: 'agent', candidates };
      queue.push(c.id);
    } else if (results[c.id]?.status === 'review') queue.push(c.id);
  }

  const dir = out ? resolve(out) : join(root, OUTPUT_DIR);
  mkdirSync(join(dir, 'history'), { recursive: true });
  const file = join(dir, 'results.json');
  if (existsSync(file)) {
    const prev = JSON.parse(readFileSync(file, 'utf8'));
    renameSync(file, join(dir, 'history', `${(prev.meta?.at || 'prev').replace(/[:.]/g, '-')}.json`));
  }
  const doc = {
    meta: {
      at: new Date().toISOString(), root, scope: scope || null, tool: 'owasp-security-verification', schema: 1,
      standards: { asvs: catalog().asvs.version, top10: catalog().top10.version },
      scanned_files: ctx.files.length, suppressed_inline: ctx.suppressed,
    },
    profile,
    detection: { facets: det.facets, components: det.components, level: det.level },
    checks: checks.map((c) => c.id),
    results,
    verdicts: {},
    queue,
  };
  writeFileSync(file, JSON.stringify(doc, null, 2));
  if (!saved) writeFileSync(join(dir, 'profile.proposed.json'), JSON.stringify(profile, null, 2) + '\n');
  return { doc, file, dir, profileConfirmed: Boolean(saved?.confirmed) };
}

/** Compact console summary: what the agent needs to act on, nothing else. */
export function summary({ doc, file, profileConfirmed }) {
  const { checks: byId } = { checks: Object.fromEntries(catalog().checks.map((c) => [c.id, c])) };
  const count = (s) => Object.values(doc.results).filter((r) => r.status === s).length;
  const lines = [];
  lines.push(`Verificación OWASP · ${doc.profile.project} · L${doc.profile.level} · ${doc.meta.scanned_files} archivos · ASVS ${doc.meta.standards.asvs} / Top 10:${doc.meta.standards.top10}`);
  if (!profileConfirmed) lines.push('⚠ Perfil sin confirmar: revisa .owasp/profile.proposed.json con el usuario (nivel, facetas, señales).');
  if (doc.profile.facets_added?.length) lines.push(`⚠ Facetas nuevas desde la última confirmación: ${doc.profile.facets_added.join(', ')}`);
  lines.push(`Chequeos: ${doc.checks.length} · fail ${count('fail')} · pass ${count('pass')} · por revisar ${doc.queue.length} · no verificado ${count('not-verified')} · n/a ${count('not-applicable')}`);
  const fails = Object.entries(doc.results).filter(([, r]) => r.status === 'fail');
  if (fails.length) {
    lines.push('\nFallos (regla o herramienta):');
    for (const [id, r] of fails) {
      const active = r.findings.filter((f) => !f.accepted);
      lines.push(`  ${id} [${byId[id].severity}] ${byId[id].title}`);
      for (const f of active.slice(0, 3)) lines.push(`      ${f.file}${f.line ? `:${f.line}` : ''} — ${f.message}`);
      if (active.length > 3) lines.push(`      … y ${active.length - 3} más`);
    }
  }
  if (doc.queue.length) {
    lines.push('\nCola de revisión del agente (registra cada veredicto con scripts/findings.mjs record):');
    for (const id of doc.queue) {
      const c = byId[id], r = doc.results[id];
      if (c.type === 'review') lines.push(`  ${id} ${c.title}\n      verificar: ${c.verify}\n      candidatos: ${r.candidates.join(', ') || '(ninguno; busca según la guía de la faceta)'}`);
      else lines.push(`  ${id} confirmar ${r.findings.length} hallazgo(s) de baja confianza: ${r.findings.slice(0, 3).map((f) => `${f.file}:${f.line}`).join(', ')}`);
    }
  }
  lines.push(`\nResultados: ${file}`);
  return lines.join('\n');
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const root = resolve(args.root || args._[0] || '.');
  const res = await verify(root, { scope: args.scope, level: args.level, out: args.out, tools: !args['no-tools'] });
  console.log(args.json ? JSON.stringify(res.doc, null, 2) : summary(res));
}
