/** Loads the reference data (manifest, checks, ASVS, Top 10, cheat sheets) and small CLI helpers. */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';

export const SKILL_DIR = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ref = (name) => JSON.parse(readFileSync(join(SKILL_DIR, 'references', name), 'utf8'));

let cache;
export function catalog() {
  if (cache) return cache;
  const asvs = ref('asvs-5.0.0.json');
  cache = {
    manifest: ref('manifest.json'),
    checks: ref('checks.json').checks,
    asvs,
    top10: ref('top10-2025.json'),
    cheatsheets: ref('cheatsheets.json'),
  };
  return cache;
}

export const cheatsheetUrl = (slug) =>
  `https://cheatsheetseries.owasp.org/cheatsheets/${slug}_Cheat_Sheet.html`;

/** `v5.0.0-1.2.4` -> `1.2.4` */
export const asvsKey = (id) => id.replace(/^v\d+\.\d+\.\d+-/, '');

/** Minimal `--flag value` / `--flag` parser. Positional args land in `_`; repeated flags become arrays. */
export function parseArgs(argv = process.argv.slice(2)) {
  const args = { _: [] };
  for (let i = 0; i < argv.length; i++) {
    const a = argv[i];
    if (!a.startsWith('--')) { args._.push(a); continue; }
    const eq = a.indexOf('=');
    const k = eq === -1 ? a.slice(2) : a.slice(2, eq);
    const inline = eq === -1 ? undefined : a.slice(eq + 1);
    const v = inline !== undefined ? inline : argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[++i] : true;
    // Repeated flags (e.g. several --evidence) accumulate into an array.
    args[k] = k in args ? [].concat(args[k], v) : v;
  }
  return args;
}

export const isMain = (metaUrl) => process.argv[1] === fileURLToPath(metaUrl);
