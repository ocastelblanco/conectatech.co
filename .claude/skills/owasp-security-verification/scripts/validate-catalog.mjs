#!/usr/bin/env node
/**
 * Validates references/checks.json against the pinned standards. Fails loudly when a
 * check cites an ASVS ID, Top 10 category or cheat sheet that does not exist, or a
 * rule/probe that is not implemented.
 *
 * Usage: node scripts/validate-catalog.mjs
 */

import { catalog, asvsKey, isMain } from './lib/catalog.mjs';
import { RULES } from './rules/index.mjs';
import { PROBES } from './live.mjs';

const TYPES = new Set(['auto', 'review', 'live']);
const SEVERITIES = new Set(['critical', 'high', 'medium', 'low']);
const SIGNALS = new Set(['pii', 'payments', 'custom_auth']);

export function validateCatalog() {
  const { checks, asvs, top10, cheatsheets, manifest } = catalog();
  const errors = [];
  const ids = new Set();
  const slugs = new Set(cheatsheets.slugs);
  for (const c of checks) {
    const e = (msg) => errors.push(`${c.id}: ${msg}`);
    if (ids.has(c.id)) e('ID duplicado');
    ids.add(c.id);
    if (!TYPES.has(c.type)) e(`tipo inválido ${c.type}`);
    if (!SEVERITIES.has(c.severity)) e(`severidad inválida ${c.severity}`);
    if (![1, 2, 3].includes(c.level)) e(`nivel inválido ${c.level}`);
    if (!c.title) e('sin título');
    if (!top10.categories[c.top10]) e(`categoría Top 10 inexistente ${c.top10}`);
    if (!slugs.has(c.cheatsheet)) e(`cheat sheet inexistente ${c.cheatsheet}`);
    if (c.when && !SIGNALS.has(c.when)) e(`señal desconocida ${c.when}`);
    if (!c.facets?.length) e('sin facetas');
    for (const f of c.facets || []) if (!manifest.facets[f]) e(`faceta inexistente ${f}`);
    if (!Array.isArray(c.asvs)) e('asvs debe ser una lista');
    else if (!c.asvs.length && !c.asvs_gap) e('sin requisitos ASVS y sin asvs_gap: true');
    const levels = [];
    for (const id of c.asvs || []) {
      if (!/^v5\.0\.0-\d+\.\d+\.\d+$/.test(id)) { e(`formato ASVS inválido ${id}`); continue; }
      const req = asvs.requirements[asvsKey(id)];
      if (!req) e(`requisito ASVS inexistente ${id}`); else levels.push(req.level);
    }
    if (levels.length && c.level < Math.min(...levels) && !c.always) {
      e(`nivel L${c.level} menor que el de sus requisitos ASVS (L${Math.min(...levels)})`);
    }
    if (c.type === 'auto') {
      if (!c.rule) e('auto sin rule');
      else if (!c.rule.startsWith('tool:') && !RULES[c.rule]) e(`regla no implementada ${c.rule}`);
    }
    if (c.type === 'review' && (!c.verify || !c.look?.length)) e('review sin verify o look');
    if (c.type === 'live') {
      if (!c.probe) e('live sin probe');
      else if (c.probe !== 'aws' && !PROBES[c.probe]) e(`sonda no implementada ${c.probe}`);
      if (c.probe === 'aws' && !c.verify) e('chequeo AWS sin verify');
    }
  }
  return { errors, total: checks.length };
}

if (isMain(import.meta.url)) {
  const { errors, total } = validateCatalog();
  const { checks } = catalog();
  const by = (k) => Object.entries(checks.reduce((a, c) => ({ ...a, [c[k]]: (a[c[k]] || 0) + 1 }), {}))
    .map(([x, n]) => `${x}=${n}`).join(' ');
  console.log(`${total} chequeos · tipo: ${by('type')} · nivel: ${by('level')}`);
  if (errors.length) { console.error(errors.map((x) => `  ✗ ${x}`).join('\n')); process.exit(1); }
  console.log('  ✓ catálogo válido contra ASVS 5.0.0, Top 10:2025 y el índice de cheat sheets');
}
