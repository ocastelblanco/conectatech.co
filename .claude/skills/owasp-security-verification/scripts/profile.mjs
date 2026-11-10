#!/usr/bin/env node
/**
 * Creates or confirms <project>/.owasp/profile.json after asking the user what the
 * repository cannot tell (level, live URLs, facets to disable).
 *
 * Usage:
 *   node scripts/profile.mjs propose --root <project>            # prints the detected profile
 *   node scripts/profile.mjs confirm --root <project> [--level 2] [--disable <facet>]...
 *        [--url https://app.example.com]... [--aws-profile <name> --aws-region <region>]
 *   node scripts/profile.mjs accept --root <project> --check CTR-02 --path "docker-compose.dev.yml" \
 *        --reason "Solo desarrollo local" [--expires 2027-01-31]
 */

import { resolve } from 'node:path';
import { catalog, parseArgs, isMain } from './lib/catalog.mjs';
import { detect } from './detect-stack.mjs';
import { loadProfile, profileFromDetection, saveProfile, profilePath } from './lib/profile.mjs';

const list = (v) => [].concat(v ?? []).filter((x) => typeof x === 'string');

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const root = resolve(args.root || '.');
  const cmd = args._[0];
  const current = loadProfile(root);

  if (cmd === 'propose') {
    const p = profileFromDetection(detect(root, { scope: args.scope }));
    console.log(JSON.stringify(current ? { current, detected: p } : p, null, 2));
  } else if (cmd === 'confirm') {
    const p = current || profileFromDetection(detect(root, { scope: args.scope }));
    if (args.level) p.level = Number(args.level);
    if (![1, 2, 3].includes(p.level)) { console.error('Nivel inválido'); process.exit(2); }
    const disable = list(args.disable);
    const unknown = disable.filter((f) => !catalog().manifest.facets[f]);
    if (unknown.length) { console.error(`Facetas desconocidas: ${unknown.join(', ')}`); process.exit(2); }
    p.facets_disabled = [...new Set([...(p.facets_disabled || []), ...disable])];
    for (const u of list(args.url)) {
      try { new URL(u); } catch { console.error(`URL inválida: ${u}`); process.exit(2); }
      if (!p.live.urls.some((x) => (x.url ?? x) === u)) p.live.urls.push({ url: u });
    }
    if (args['aws-profile'] || args['aws-region']) p.live.aws = { profile: args['aws-profile'] || null, region: args['aws-region'] || null };
    for (const s of list(args.signal)) p.signals[s] = true;
    for (const s of list(args['no-signal'])) p.signals[s] = false;
    p.confirmed = true;
    p.confirmed_at = new Date().toISOString().slice(0, 10);
    delete p.facets_added;
    saveProfile(root, p);
    console.log(`✓ Perfil confirmado: L${p.level} · ${p.facets.filter((f) => !p.facets_disabled.includes(f)).join(', ')} → ${profilePath(root)}`);
  } else if (cmd === 'accept') {
    if (!current) { console.error('Primero confirma el perfil (profile.mjs confirm).'); process.exit(2); }
    if (!catalog().checks.some((c) => c.id === args.check)) { console.error(`Chequeo inexistente: ${args.check}`); process.exit(2); }
    if (typeof args.reason !== 'string' || args.reason.trim().length < 10) { console.error('La excepción necesita --reason con una justificación real.'); process.exit(2); }
    current.accepted.push({ check: args.check, paths: list(args.path), reason: args.reason.trim(), expires: typeof args.expires === 'string' ? args.expires : null, at: new Date().toISOString().slice(0, 10) });
    saveProfile(root, current);
    console.log(`✓ Excepción registrada para ${args.check}`);
  } else {
    console.error('Uso: profile.mjs propose|confirm|accept --root <proyecto> …');
    process.exit(2);
  }
}
