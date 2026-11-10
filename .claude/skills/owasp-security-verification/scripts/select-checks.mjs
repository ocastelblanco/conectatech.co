#!/usr/bin/env node
/**
 * Facets × level × signals -> the checks that apply. The agent never reads checks.json
 * whole; it receives this filtered plan.
 *
 * Usage: node scripts/select-checks.mjs --root <project> [--level 1|2] [--live] [--json]
 */

import { resolve } from 'node:path';
import { catalog, parseArgs, isMain } from './lib/catalog.mjs';
import { detect } from './detect-stack.mjs';
import { loadProfile, profileFromDetection } from './lib/profile.mjs';

export function selectChecks(profile, { live = false } = {}) {
  const facets = new Set(profile.facets.filter((f) => !profile.facets_disabled?.includes(f)));
  facets.add('baseline');
  return catalog().checks.filter((c) =>
    c.facets.some((f) => facets.has(f))
    && (c.level <= profile.level || c.always)
    && (!c.when || profile.signals?.[c.when])
    && (live || c.type !== 'live'));
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const root = resolve(args.root || '.');
  const profile = loadProfile(root) || profileFromDetection(detect(root, { scope: args.scope }));
  if (args.level) profile.level = Number(args.level);
  const checks = selectChecks(profile, { live: Boolean(args.live) });
  if (args.json) { console.log(JSON.stringify(checks, null, 2)); process.exit(0); }
  const by = (t) => checks.filter((c) => c.type === t).length;
  console.log(`L${profile.level} · facetas: ${profile.facets.join(', ')}`);
  console.log(`${checks.length} chequeos aplicables (auto ${by('auto')}, review ${by('review')}, live ${by('live')}) de ${catalog().checks.length}`);
  for (const c of checks) console.log(`  ${c.id.padEnd(8)} ${c.type.padEnd(6)} L${c.level} ${c.title}`);
}
