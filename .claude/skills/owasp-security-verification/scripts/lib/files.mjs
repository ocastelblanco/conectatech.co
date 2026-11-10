/**
 * File discovery shared by every script.
 *
 * Inside a git repository the listing comes from `git ls-files` (tracked + untracked,
 * minus ignored), so gitignored secrets and build output are never scanned. Nested
 * repositories (one per microservice is common) are descended into recursively.
 * Outside git, a walk with a conservative ignore list is used instead.
 */

import { execFileSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

export const IGNORED_DIRS = new Set([
  'node_modules', '.git', 'vendor', 'dist', 'build', 'out', '.angular', '.next', '.nuxt',
  'coverage', '.serverless', 'cdk.out', '.terraform', '.venv', 'venv', 'env', '__pycache__',
  '.pytest_cache', '.mypy_cache', '.cache', '.idea', '.vscode', '.omc',
  // Agent tooling folders: configuration and vendored skills, not application code.
  '.claude', '.cursor', '.windsurf', '.codex', '.gemini', '.agents',
]);

/** Output directory the skill writes into the audited project; never scanned. */
export const OUTPUT_DIR = '.owasp';

const MAX_FILES = 50_000;
export const MAX_READ_BYTES = 512 * 1024;

function gitList(dir) {
  try {
    const out = execFileSync('git', ['-C', dir, 'ls-files', '-co', '--exclude-standard', '-z'],
      { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] });
    return out.split('\0').filter(Boolean);
  } catch { return null; }
}

function isGitRoot(dir) { return existsSync(join(dir, '.git')); }

function walk(root, dir, acc) {
  let entries;
  try { entries = readdirSync(dir, { withFileTypes: true }); } catch { return; }
  for (const e of entries) {
    if (acc.length >= MAX_FILES) return;
    const abs = join(dir, e.name);
    if (e.isDirectory()) {
      if (IGNORED_DIRS.has(e.name)) continue;
      if (isGitRoot(abs)) { collect(root, abs, acc); continue; }
      walk(root, abs, acc);
    } else if (e.isFile()) acc.push(relative(root, abs).split(sep).join('/'));
  }
}

/** Nested repositories that the parent ignores (e.g. a `rund-*` pattern in .gitignore) are still components. */
function nestedRepos(dir, depth = 0, found = []) {
  if (depth > 3) return found;
  let entries;
  try { entries = readdirSync(dir, { withFileTypes: true }); } catch { return found; }
  for (const e of entries) {
    if (!e.isDirectory() || IGNORED_DIRS.has(e.name)) continue;
    const abs = join(dir, e.name);
    if (isGitRoot(abs)) found.push(abs); else nestedRepos(abs, depth + 1, found);
  }
  return found;
}

function collect(root, dir, acc) {
  const listed = isGitRoot(dir) ? gitList(dir) : null;
  if (!listed) return walk(root, dir, acc);
  for (const nested of nestedRepos(dir)) collect(root, nested, acc);
  for (const p of listed) {
    if (acc.length >= MAX_FILES) return;
    const abs = join(dir, p);
    if (p.endsWith('/')) continue; // untracked nested repository, already collected above
    if (p.split('/').some((seg) => IGNORED_DIRS.has(seg))) continue;
    acc.push(relative(root, abs).split(sep).join('/'));
  }
}

/** Returns project-relative POSIX paths, optionally restricted to a sub-path (`scope`). */
export function listFiles(root, { scope } = {}) {
  const acc = [];
  collect(root, root, acc);
  const files = [...new Set(acc)].filter((f) => !f.startsWith(OUTPUT_DIR + '/')).sort();
  if (!scope) return files;
  const prefix = scope.replace(/^\.\//, '').replace(/\/$/, '');
  return files.filter((f) => f === prefix || f.startsWith(prefix + '/'));
}

/** Reads a text file; returns null for binaries, unreadable or oversized files. */
export function readText(root, rel) {
  const abs = join(root, rel);
  try {
    if (statSync(abs).size > MAX_READ_BYTES) return null;
    const buf = readFileSync(abs);
    if (buf.subarray(0, 8000).includes(0)) return null;
    return buf.toString('utf8');
  } catch { return null; }
}

export function readJson(root, rel) {
  const t = readText(root, rel);
  if (t == null) return null;
  try { return JSON.parse(t); } catch { return null; }
}

export const basename = (p) => p.slice(p.lastIndexOf('/') + 1);

/** Converts a simple glob (`**`, `*`, `?`) into a RegExp matched against the full path. */
export function globToRegExp(glob) {
  let re = '';
  for (let i = 0; i < glob.length; i++) {
    const c = glob[i];
    if (c === '*' && glob[i + 1] === '*') {
      i++;
      if (glob[i + 1] === '/') { re += '(?:.*/)?'; i++; } else re += '.*'; // `**/x` vs trailing `dir/**`
    }
    else if (c === '*') re += '[^/]*';
    else if (c === '?') re += '[^/]';
    else re += c.replace(/[.+^${}()|[\]\\]/g, '\\$&');
  }
  return new RegExp(`^${re}$`, 'i');
}

export function matchAny(path, globs) {
  return globs.some((g) => globToRegExp(g.includes('/') ? g : `**/${g}`).test(path));
}

/** Line number (1-based) of a character offset. */
export function lineAt(text, index) {
  let n = 1;
  for (let i = 0; i < index && i < text.length; i++) if (text.charCodeAt(i) === 10) n++;
  return n;
}
