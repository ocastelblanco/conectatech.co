/**
 * Rule engine primitives. A rule returns a RuleResult:
 *   { status: 'pass'|'fail'|'review'|'not-verified'|'not-applicable', findings: Finding[], note?, scanned }
 * A Finding is { file, line, snippet, confidence: 'high'|'medium'|'low', message }.
 *
 * Status semantics (shared by every deny-style rule):
 *   - any high/medium-confidence finding -> 'fail'
 *   - only low-confidence findings       -> 'review' (the agent must confirm with evidence)
 *   - nothing found in >=1 scanned file  -> 'pass'
 *   - no file to scan                    -> 'not-applicable'
 */

import { matchAny, readText, lineAt, basename } from '../lib/files.mjs';

export const CODE_JS = ['**/*.js', '**/*.mjs', '**/*.cjs', '**/*.ts', '**/*.tsx', '**/*.jsx'];
export const CODE_PY = ['**/*.py'];
export const CODE_PHP = ['**/*.php'];
export const CODE = [...CODE_JS, ...CODE_PY, ...CODE_PHP];
export const COMPOSE = ['docker-compose*.yml', 'docker-compose*.yaml', 'compose.yml', 'compose.yaml', 'compose.*.yml', 'compose.*.yaml'];
export const DOCKERFILES = ['Dockerfile', 'Dockerfile.*', '*.Dockerfile', '*.dockerfile'];
export const WORKFLOWS = ['.github/workflows/*.yml', '.github/workflows/*.yaml'];
export const PROXY = ['nginx*.conf', '**/nginx/**/*.conf', '**/conf.d/*.conf', '**/sites-*/*', 'Caddyfile', '**/apache*/**/*.conf', '**/httpd*.conf'];

const TEST_PATH = /(^|\/)tests?\.[cm]?[jt]s$|(^|\/)(tests?|__tests__|spec|specs|e2e|fixtures?|mocks?|__mocks__|testdata|cypress|playwright)\/|\.(spec|test|e2e)\.[cm]?[jt]sx?$|(^|\/)test_[^/]*\.py$|_test\.py$|Test\.php$/i;
const GENERATED = /\.min\.(js|css)$|\.d\.ts$|\.map$|(^|\/)(package-lock\.json|yarn\.lock|pnpm-lock\.yaml|composer\.lock|poetry\.lock|uv\.lock)$/;
export const EXAMPLE_FILE = /\.(example|sample|template|dist|tpl)(\.|$)|(^|\/)example[s]?\//i;
export const DEV_FILE = /(^|[/._-])(dev|develop|development|local|test|testing|override)([._-]|\.ya?ml$)/i;
const SUPPRESS = /owasp-ignore\b/;
/** A file whose first lines contain `owasp-ignore-file: <motivo>` is skipped by every rule (scanners, fixtures). */
const IGNORE_FILE = /owasp-ignore-file\b/;
const COMMENT_LINE = /^\s*(\/\/|#(?!!)|\*|\/\*|<!--|--\s)/;

export const isTest = (p) => TEST_PATH.test(p);

/** Selects the files a rule applies to. */
export function select(ctx, { files, exclude = [], skipTests = true, allowGenerated = false, contains }) {
  return ctx.files.filter((f) =>
    matchAny(f, files) && !matchAny(f, exclude)
    && (allowGenerated || !GENERATED.test(f))
    && (!skipTests || !isTest(f))
    && !IGNORE_FILE.test((ctx.read(f) || '').slice(0, 600))
    && (!contains || (ctx.read(f) || '').match(contains)));
}

export function snippet(text, index) {
  const start = text.lastIndexOf('\n', index - 1) + 1;
  const end = text.indexOf('\n', index);
  return text.slice(start, end === -1 ? undefined : end).trim().slice(0, 160);
}

function suppressed(text, index) {
  const start = text.lastIndexOf('\n', index - 1) + 1;
  const prevStart = text.lastIndexOf('\n', start - 2) + 1;
  const end = text.indexOf('\n', index);
  return SUPPRESS.test(text.slice(prevStart, end === -1 ? undefined : end));
}

/**
 * Scans `files` with `patterns` ({ re, confidence, message, skipLine?, onlyIf?, downgrade?, comments? }).
 * `re` must be global; `skipLine(line, file, match)` filters false positives on the matched line;
 * `downgrade(file, text, line)` lowers the confidence to 'low' (agent review) when context suggests
 * a mitigation the regex cannot see. Commented-out lines are skipped unless `comments: true`.
 */
export function scan(ctx, files, patterns, { maxPerFile = 10 } = {}) {
  const findings = [];
  for (const file of files) {
    const text = ctx.read(file);
    if (text == null) continue;
    let perFile = 0;
    for (const p of patterns) {
      if (p.onlyIf && !p.onlyIf(file, text)) continue;
      p.re.lastIndex = 0;
      for (const m of text.matchAll(p.re)) {
        const line = snippet(text, m.index);
        if (!p.comments && COMMENT_LINE.test(line)) continue;
        if (p.skipLine && p.skipLine(line, file, m)) continue;
        if (suppressed(text, m.index)) { ctx.suppressed++; continue; }
        const confidence = p.downgrade && p.downgrade(file, text, line) ? 'low' : p.confidence;
        findings.push({ file, line: lineAt(text, m.index), snippet: line, confidence, message: p.message });
        if (++perFile >= maxPerFile) break;
      }
      if (perFile >= maxPerFile) break;
    }
  }
  return findings;
}

export function verdict(findings, scanned, extra = {}) {
  if (!scanned) return { status: 'not-applicable', findings: [], scanned: 0, ...extra };
  if (findings.some((f) => f.confidence !== 'low')) return { status: 'fail', findings, scanned, ...extra };
  if (findings.length) return { status: 'review', findings, scanned, ...extra };
  return { status: 'pass', findings, scanned, ...extra };
}

/** Declarative deny rule: any match is a finding. */
export function deny(selector, patterns, opts) {
  return (ctx) => {
    const files = select(ctx, selector);
    return verdict(scan(ctx, files, patterns, opts), files.length);
  };
}

export function finding(file, text, index, confidence, message) {
  return { file, line: text == null ? 1 : lineAt(text, index), snippet: text == null ? '' : snippet(text, index), confidence, message };
}

/** Directory components (dirs holding a manifest) used by rules that reason per service. */
export function componentOf(ctx, file, manifests = ['package.json', 'composer.json', 'requirements.txt', 'pyproject.toml', 'angular.json']) {
  const roots = ctx.files.filter((f) => manifests.includes(basename(f))).map((f) => f.includes('/') ? f.slice(0, f.lastIndexOf('/')) : '.');
  const owner = roots.filter((r) => r === '.' || file.startsWith(r + '/')).sort((a, b) => b.length - a.length)[0];
  return owner ?? '.';
}

export const inDir = (dir, file) => dir === '.' || file.startsWith(dir + '/');

/** Iterates `key {` … `}` blocks in HCL-like text, yielding { type, name, body, index }. */
export function* hclBlocks(text, typeRe) {
  const re = new RegExp(`(?:^|\\n)\\s*resource\\s+"(${typeRe})"\\s+"([^"]+)"\\s*\\{`, 'g');
  for (const m of text.matchAll(re)) {
    const open = m.index + m[0].length - 1;
    let depth = 0, i = open;
    for (; i < text.length; i++) {
      if (text[i] === '{') depth++;
      else if (text[i] === '}' && --depth === 0) break;
    }
    yield { type: m[1], name: m[2], body: text.slice(open, i + 1), index: m.index + (m[0].startsWith('\n') ? 1 : 0) };
  }
}

/** Returns the `{ … }` body following position `start` (which must point at or before `{`). */
export function braceBody(text, start) {
  const open = text.indexOf('{', start);
  if (open === -1) return '';
  let depth = 0;
  for (let i = open; i < text.length; i++) {
    if (text[i] === '{') depth++;
    else if (text[i] === '}' && --depth === 0) return text.slice(open, i + 1);
  }
  return text.slice(open);
}

export { readText };
