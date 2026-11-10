/**
 * External tool adapters. Each returns a RuleResult like the static rules, so the
 * report treats tool output and rule output the same way (`source: "tool"`).
 *
 * npm-audit and composer-audit are verified against real projects. pip-audit is
 * implemented from its documented JSON format and pending empirical validation.
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { join } from 'node:path';
import { basename, lineAt } from '../lib/files.mjs';

const run = promisify(execFile);
const TIMEOUT = 120_000;

async function exec(cmd, args, cwd) {
  try {
    const { stdout } = await run(cmd, args, { cwd, timeout: TIMEOUT, maxBuffer: 64 * 1024 * 1024 });
    return { ok: true, stdout };
  } catch (e) {
    // npm/composer audit exit non-zero when they find vulnerabilities; stdout still holds the report.
    if (e.stdout) return { ok: true, stdout: e.stdout };
    return { ok: false, error: e.code === 'ENOENT' ? `${cmd} no está instalado` : (e.stderr || e.message).toString().slice(0, 300) };
  }
}

const dirOf = (f) => (f.includes('/') ? f.slice(0, f.lastIndexOf('/')) : '.');
const SEV_RANK = { critical: 4, high: 3, moderate: 2, medium: 2, low: 1, info: 0 };

function summarize(findings, scanned, notes) {
  const status = !scanned ? 'not-verified' : findings.some((f) => f.confidence !== 'low') ? 'fail' : findings.length ? 'review' : 'pass';
  return { status, findings, scanned, source: 'tool', note: notes.filter(Boolean).join(' ') || undefined };
}

export const TOOLS = {
  async 'npm-audit'(ctx) {
    const locks = ctx.files.filter((f) => basename(f) === 'package-lock.json');
    if (!locks.length) return { status: 'not-verified', findings: [], scanned: 0, source: 'tool', note: 'No hay package-lock.json: npm audit necesita lockfile.' };
    const findings = [], notes = [];
    let scanned = 0;
    for (const lock of locks) {
      const r = await exec('npm', ['audit', '--json', '--package-lock-only'], join(ctx.root, dirOf(lock)));
      if (!r.ok) { notes.push(`${lock}: ${r.error}`); continue; }
      let data; try { data = JSON.parse(r.stdout); } catch { notes.push(`${lock}: salida no JSON`); continue; }
      if (data.error) { notes.push(`${lock}: ${data.error.summary || data.error.code}`); continue; }
      scanned++;
      const text = ctx.read(lock) || '';
      const counts = data.metadata?.vulnerabilities || {};
      const minor = (counts.moderate || 0) + (counts.low || 0);
      if (minor) notes.push(`${lock}: ${minor} vulnerabilidades moderadas o bajas no listadas.`);
      for (const [name, v] of Object.entries(data.vulnerabilities || {})) {
        if (SEV_RANK[v.severity] < SEV_RANK.high) continue;
        const advisory = (v.via || []).find((x) => typeof x === 'object');
        const i = text.indexOf(`"node_modules/${name}"`);
        findings.push({
          file: lock, line: i >= 0 ? lineAt(text, i) : 1,
          snippet: `${name} ${v.range || ''} (${v.severity})${v.fixAvailable ? ' · fix disponible' : ''}`.trim(),
          confidence: 'high',
          message: advisory ? `${advisory.title} ${advisory.url || ''}`.trim() : `Vulnerable vía ${(v.via || []).join(', ')}`,
        });
      }
    }
    return summarize(findings, scanned, notes);
  },

  async 'composer-audit'(ctx) {
    const locks = ctx.files.filter((f) => basename(f) === 'composer.lock');
    if (!locks.length) return { status: 'not-verified', findings: [], scanned: 0, source: 'tool', note: 'No hay composer.lock: composer audit necesita lockfile.' };
    const findings = [], notes = [];
    let scanned = 0;
    for (const lock of locks) {
      const r = await exec('composer', ['audit', '--format=json', '--locked', '--no-interaction'], join(ctx.root, dirOf(lock)));
      if (!r.ok) { notes.push(`${lock}: ${r.error}`); continue; }
      let data; try { data = JSON.parse(r.stdout); } catch { notes.push(`${lock}: salida no JSON`); continue; }
      scanned++;
      const text = ctx.read(lock) || '';
      for (const [pkg, list] of Object.entries(data.advisories || {})) {
        for (const a of Object.values(list)) {
          const sev = (a.severity || 'unknown').toLowerCase();
          const i = text.indexOf(`"name": "${pkg}"`);
          findings.push({
            file: lock, line: i >= 0 ? lineAt(text, i) : 1,
            snippet: `${pkg} ${a.affectedVersions || ''} (${sev})`.trim(),
            confidence: SEV_RANK[sev] >= SEV_RANK.high || sev === 'unknown' ? 'high' : 'low',
            message: `${a.title || a.advisoryId} ${a.cve || ''} ${a.link || ''}`.replace(/\s+/g, ' ').trim(),
          });
        }
      }
      const abandoned = Object.keys(data.abandoned || {});
      if (abandoned.length) notes.push(`Paquetes abandonados: ${abandoned.join(', ')}.`);
    }
    return summarize(findings, scanned, notes);
  },

  async 'pip-audit'(ctx) {
    const reqs = ctx.files.filter((f) => /(^|\/)requirements[^/]*\.txt$/.test(f));
    if (!reqs.length) return { status: 'not-applicable', findings: [], scanned: 0, source: 'tool' };
    const findings = [], notes = [];
    let scanned = 0;
    for (const req of reqs) {
      const r = await exec('pip-audit', ['-r', basename(req), '-f', 'json', '--progress-spinner', 'off'], join(ctx.root, dirOf(req)));
      if (!r.ok) { notes.push(r.error.includes('no está instalado') ? 'pip-audit no está instalado (pipx install pip-audit).' : `${req}: ${r.error}`); break; }
      let data; try { data = JSON.parse(r.stdout); } catch { notes.push(`${req}: salida no JSON`); continue; }
      scanned++;
      const text = ctx.read(req) || '';
      for (const d of data.dependencies || []) {
        for (const v of d.vulns || []) {
          const m = new RegExp(`^\\s*${d.name.replace(/[-_.]/g, '[-_.]')}\\b`, 'im').exec(text);
          findings.push({ file: req, line: m ? lineAt(text, m.index) : 1, snippet: `${d.name}==${d.version}`, confidence: 'high', message: `${v.id}${v.aliases?.length ? ` (${v.aliases.join(', ')})` : ''}${v.fix_versions?.length ? ` · corregido en ${v.fix_versions.join(', ')}` : ''}` });
        }
      }
    }
    return summarize(findings, scanned, notes);
  },
};
