#!/usr/bin/env node
/**
 * Detects which facets of references/manifest.json apply to a project, with the
 * evidence that triggered each one, and suggests an ASVS level from project docs.
 *
 * Usage: node scripts/detect-stack.mjs --root <project> [--scope <sub-path>] [--json]
 */

import { resolve, dirname as pdirname } from 'node:path';
import { listFiles, readText, readJson, basename, matchAny, lineAt } from './lib/files.mjs';
import { catalog, parseArgs, isMain } from './lib/catalog.mjs';
import { isTest } from './rules/engine.mjs';

const MAX_EVIDENCE = 8;
const MAX_CONTENT_FILES = 3000;

function npmDeps(root, files) {
  const deps = new Map();
  for (const f of files.filter((p) => basename(p) === 'package.json')) {
    const pkg = readJson(root, f);
    if (!pkg) continue;
    for (const k of ['dependencies', 'devDependencies', 'peerDependencies', 'optionalDependencies']) {
      for (const name of Object.keys(pkg[k] || {})) deps.set(name, [...(deps.get(name) || []), f]);
    }
  }
  return deps;
}

function pipDeps(root, files) {
  const deps = new Map();
  for (const f of files.filter((p) => /(^|\/)requirements[^/]*\.txt$|(^|\/)pyproject\.toml$|(^|\/)Pipfile$/.test(p))) {
    const text = readText(root, f) || '';
    const re = f.endsWith('.txt')
      ? /^\s*([A-Za-z0-9][A-Za-z0-9_.-]*)/gm
      : /["']\s*([A-Za-z0-9][A-Za-z0-9_.-]*)\s*(?:[<>=~!;\[]|["'])/g;
    for (const m of text.matchAll(re)) {
      const name = m[1].toLowerCase().replace(/_/g, '-');
      deps.set(name, [...(deps.get(name) || []), f]);
    }
  }
  return deps;
}

function composerDeps(root, files) {
  const deps = new Map();
  for (const f of files.filter((p) => basename(p) === 'composer.json')) {
    const c = readJson(root, f);
    for (const k of ['require', 'require-dev']) {
      for (const name of Object.keys(c?.[k] || {})) deps.set(name, [...(deps.get(name) || []), f]);
    }
  }
  return deps;
}

export function detect(root, { scope } = {}) {
  const { manifest } = catalog();
  const files = listFiles(root, { scope });
  const deps = { npm: npmDeps(root, files), pip: pipDeps(root, files), composer: composerDeps(root, files) };
  const facets = {};
  const add = (id, file, signal) => {
    const f = (facets[id] ??= { title: manifest.facets[id].title, evidence: [] });
    if (f.evidence.length < MAX_EVIDENCE && !f.evidence.some((e) => e.file === file && e.signal === signal)) {
      f.evidence.push({ file, signal });
    }
  };

  for (const [id, def] of Object.entries(manifest.facets)) {
    if (def.always) { facets[id] = { title: def.title, evidence: [{ file: null, signal: 'siempre aplica' }] }; continue; }
    const d = def.detect || {};
    if (d.files) for (const f of files) if (matchAny(f, d.files)) add(id, f, `archivo ${basename(f)}`);
    for (const eco of ['npm', 'pip', 'composer']) {
      for (const name of d[eco] || []) {
        if (name.endsWith('/*')) {
          for (const [k, owners] of deps[eco]) {
            if (k.startsWith(name.slice(0, -1))) for (const o of owners) add(id, o, `dependencia ${eco}:${k}`);
          }
          continue;
        }
        const key = eco === 'pip' ? name.toLowerCase() : name;
        for (const o of deps[eco].get(key) || []) add(id, o, `dependencia ${eco}:${name}`);
      }
    }
    for (const rule of d.content || []) {
      const re = new RegExp(rule.pattern, 'i');
      // Test files and fixtures describe other stacks on purpose: they never define the project's facets.
      const candidates = files.filter((f) => matchAny(f, rule.files) && !isTest(f)).slice(0, MAX_CONTENT_FILES);
      for (const f of candidates) {
        const text = readText(root, f);
        const m = text && re.exec(text);
        if (m) { add(id, `${f}:${lineAt(text, m.index)}`, `contenido /${rule.pattern.slice(0, 40)}/`); break; }
      }
    }
  }

  return { root, scope: scope || null, scanned_files: files.length, facets, level: suggestLevel(root, files, facets), components: components(files, facets) };
}

function suggestLevel(root, files, facets) {
  const { manifest } = catalog();
  const { docs, signals } = manifest.level_signals;
  const docFiles = files.filter((f) => matchAny(f, docs)).slice(0, 60);
  const found = {};
  for (const [name, pattern] of Object.entries(signals)) {
    const re = new RegExp(pattern, 'gi');
    for (const f of docFiles) {
      const text = readText(root, f) || '';
      for (const m of text.matchAll(re)) {
        (found[name] ??= []).push({ file: `${f}:${lineAt(text, m.index)}`, match: m[0] });
        if (found[name].length >= 3) break;
      }
      if ((found[name]?.length || 0) >= 3) break;
    }
  }
  for (const [id, def] of Object.entries(manifest.facets)) {
    if (def.level_signal && facets[id]) (found[def.level_signal] ??= []).push({ file: facets[id].evidence[0].file, match: `faceta ${id}` });
  }
  return { suggested: Object.keys(found).length ? 2 : 1, signals: found };
}

/** Groups facet evidence by the directory of each package manifest, for monorepos. */
function components(files, facets) {
  const roots = [...new Set(files
    .filter((f) => /(^|\/)(package\.json|composer\.json|requirements[^/]*\.txt|pyproject\.toml|Dockerfile|angular\.json|serverless\.ya?ml)$/.test(f))
    .map((f) => pdirname(f)))].sort((a, b) => b.length - a.length);
  const out = {};
  for (const [id, f] of Object.entries(facets)) {
    for (const e of f.evidence) {
      if (!e.file) continue;
      const path = e.file.replace(/:\d+$/, '');
      const owner = roots.find((r) => r === '.' || path.startsWith(r + '/')) ?? '.';
      (out[owner] ??= new Set()).add(id);
    }
  }
  return Object.fromEntries(Object.entries(out).sort().map(([k, v]) => [k, [...v].sort()]));
}

function printHuman(r) {
  console.log(`Proyecto: ${r.root}${r.scope ? ` (alcance: ${r.scope})` : ''} · ${r.scanned_files} archivos`);
  console.log(`Nivel ASVS sugerido: L${r.level.suggested}`);
  for (const [s, hits] of Object.entries(r.level.signals)) console.log(`  señal ${s}: ${hits.map((h) => `${h.match} (${h.file})`).join('; ')}`);
  console.log('\nFacetas:');
  for (const [id, f] of Object.entries(r.facets)) {
    console.log(`  ${id.padEnd(20)} ${f.evidence.map((e) => e.file ?? e.signal).slice(0, 3).join(', ')}`);
  }
  if (Object.keys(r.components).length > 1) {
    console.log('\nComponentes:');
    for (const [c, ids] of Object.entries(r.components)) console.log(`  ${c.padEnd(40)} ${ids.join(', ')}`);
  }
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const r = detect(resolve(args.root || args._[0] || '.'), { scope: args.scope });
  if (args.json) console.log(JSON.stringify(r, null, 2)); else printHuman(r);
}
