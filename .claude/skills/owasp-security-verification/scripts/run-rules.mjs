/**
 * Executes the auto checks of a plan: static rules and external tools. Applies inline
 * suppressions, accepted exceptions and secret redaction before anything is written.
 */

import { createHash } from 'node:crypto';
import { existsSync } from 'node:fs';
import { join } from 'node:path';
import { listFiles, readText, matchAny } from './lib/files.mjs';
import { acceptedFor } from './lib/profile.mjs';
import { RULES } from './rules/index.mjs';
import { TOOLS } from './adapters/index.mjs';

const REDACT_RULES = new Set(['secrets-hardcoded', 'frontend-privileged-keys', 'iac-plain-secrets']);
const CONF_RANK = { high: 3, medium: 2, low: 1 };

/**
 * Masks secret-looking tokens (>= 20 chars, or >= 12 mixing letters and digits): keeps the
 * first 4 characters plus a short hash so the same secret can be recognised across runs.
 */
export function redact(s) {
  return s.replace(/[A-Za-z0-9/+_-]{12,}/g, (tok) => {
    if (tok.length < 20 && !(/\d/.test(tok) && /[A-Za-z]/.test(tok))) return tok;
    return `${tok.slice(0, 4)}…[${createHash('sha256').update(tok).digest('hex').slice(0, 8)}]`;
  });
}

export function buildContext(root, { scope, exclude = [] } = {}) {
  const files = listFiles(root, { scope }).filter((f) => !exclude.length || !matchAny(f, exclude));
  const cache = new Map();
  return {
    root,
    files,
    fileSet: new Set(files),
    isGit: existsSync(join(root, '.git')),
    suppressed: 0,
    read(f) { if (!cache.has(f)) cache.set(f, readText(root, f)); return cache.get(f); },
  };
}

function dedupe(findings) {
  const best = new Map();
  for (const f of findings) {
    const k = `${f.file}:${f.line}`;
    const prev = best.get(k);
    if (!prev || CONF_RANK[f.confidence] > CONF_RANK[prev.confidence]) best.set(k, f);
  }
  return [...best.values()];
}

function restatus(r) {
  if (!['fail', 'review', 'pass'].includes(r.status)) return r.status;
  const active = r.findings.filter((f) => !f.accepted);
  if (active.some((f) => f.confidence !== 'low')) return 'fail';
  if (active.length) return 'review';
  return r.status === 'not-applicable' ? r.status : 'pass';
}

export async function runRules(ctx, checks, { profile, tools = true } = {}) {
  const results = {};
  for (const c of checks.filter((x) => x.type === 'auto')) {
    let r;
    const started = Date.now();
    try {
      if (c.rule.startsWith('tool:')) {
        const name = c.rule.slice(5);
        r = tools ? await TOOLS[name](ctx) : { status: 'not-verified', findings: [], scanned: 0, note: 'Herramientas externas desactivadas (--no-tools).' };
        r.source = 'tool';
      } else {
        r = await RULES[c.rule](ctx);
        r.source = 'rule';
      }
    } catch (e) {
      r = { status: 'not-verified', findings: [], scanned: 0, source: 'rule', note: `Error en la regla: ${e.message}` };
    }
    r.findings = dedupe(r.findings || []).map((f) => {
      const out = REDACT_RULES.has(c.rule) ? { ...f, snippet: redact(f.snippet) } : f;
      const acc = acceptedFor(profile, c.id, f.file);
      return acc ? { ...out, accepted: { reason: acc.reason, expires: acc.expires || null } } : out;
    });
    r.status = restatus(r);
    r.ms = Date.now() - started;
    results[c.id] = r;
  }
  return results;
}
