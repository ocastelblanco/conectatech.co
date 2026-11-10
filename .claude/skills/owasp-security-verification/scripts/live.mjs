#!/usr/bin/env node
/**
 * Live mode (opt-in): read-only probes against URLs the user explicitly allowed.
 *
 * Guarantees: only GET, HEAD and OPTIONS requests; only hosts present in the allowlist
 * (profile.live.urls or --url given on the command line); at most 1 request per second;
 * no port scanning, no fuzzing, no payloads. Responses are judged by content, never by
 * status alone, because SPAs answer 200 to every path.
 *
 * Usage: node scripts/live.mjs --url https://example.com [--url ...] [--out <dir>] [--json]
 *        node scripts/live.mjs --root <project>          (reads .owasp/profile.json)
 */

import tls from 'node:tls';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { parseArgs, isMain } from './lib/catalog.mjs';
import { OUTPUT_DIR } from './lib/files.mjs';

const UA = 'owasp-security-verification/1 (+read-only live checks)';
const MIN_INTERVAL_MS = 1000;
const TIMEOUT_MS = 10_000;
let last = 0;

async function throttle() {
  const wait = last + MIN_INTERVAL_MS - Date.now();
  if (wait > 0) await new Promise((r) => setTimeout(r, wait));
  last = Date.now();
}

export function makeClient(allowlist) {
  const hosts = new Set(allowlist.map((u) => new URL(u).host));
  return async function request(url, { method = 'GET', headers = {}, redirect = 'manual' } = {}) {
    const u = new URL(url);
    if (!hosts.has(u.host)) throw new Error(`Host fuera del allowlist: ${u.host}`);
    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) throw new Error(`Método no permitido en modo live: ${method}`);
    await throttle();
    const res = await fetch(u, { method, headers: { 'user-agent': UA, ...headers }, redirect, signal: AbortSignal.timeout(TIMEOUT_MS) });
    const body = method === 'GET' ? (await res.text()).slice(0, 200_000) : '';
    return { status: res.status, headers: res.headers, body, url: u.href };
  };
}

const ev = (url, snippet) => ({ file: url, line: null, snippet: String(snippet ?? '').slice(0, 160) });
const fail = (url, snippet, message, confidence = 'high') => ({ ...ev(url, snippet), confidence, message });
const result = (findings, extra = {}) => ({
  status: findings.some((f) => f.confidence !== 'low') ? 'fail' : findings.length ? 'review' : 'pass',
  findings, scanned: 1, ...extra,
});
const isHtml = (r) => /text\/html/i.test(r.headers.get('content-type') || '');

/** Each probe receives (target, request, cache) and returns a RuleResult. */
export const PROBES = {
  'header-hsts': async ({ url }, _req, get) => {
    const r = await get(url);
    const h = r.headers.get('strict-transport-security');
    if (!h) return result([fail(url, '(ausente)', 'Falta Strict-Transport-Security', 'medium')]);
    const age = Number(/max-age=(\d+)/i.exec(h)?.[1] ?? 0);
    return result(age < 31536000 ? [fail(url, h, `max-age=${age} menor a un año`, 'medium')] : [], { evidence: [ev(url, `strict-transport-security: ${h}`)] });
  },

  'header-csp': async ({ url }, _req, get) => {
    const r = await get(url);
    if (!isHtml(r)) return { status: 'not-applicable', findings: [], scanned: 1, note: 'La respuesta no es HTML.' };
    const csp = r.headers.get('content-security-policy');
    if (!csp) {
      const ro = r.headers.get('content-security-policy-report-only');
      return result([fail(url, ro ? `report-only: ${ro}` : '(ausente)', ro ? 'CSP solo en modo report-only' : 'Falta Content-Security-Policy', 'medium')]);
    }
    const f = [];
    const has = (d) => new RegExp(`(^|;)\\s*${d}\\b`, 'i').test(csp);
    if (!has('frame-ancestors')) f.push(fail(url, csp, r.headers.get('x-frame-options') ? 'Sin frame-ancestors (solo X-Frame-Options)' : 'Sin frame-ancestors', r.headers.get('x-frame-options') ? 'low' : 'medium'));
    if (!has('object-src') && !/default-src\s+'none'/i.test(csp)) f.push(fail(url, csp, "Sin object-src (o default-src 'none')", 'low'));
    if (!has('base-uri')) f.push(fail(url, csp, 'Sin base-uri', 'low'));
    if (/script-src[^;]*'unsafe-inline'/i.test(csp) && !/'nonce-|'strict-dynamic'/i.test(csp)) f.push(fail(url, csp, "script-src permite 'unsafe-inline' sin nonce", 'medium'));
    return result(f, { evidence: [ev(url, `content-security-policy: ${csp}`)] });
  },

  'header-basic': async ({ url }, _req, get) => {
    const r = await get(url);
    const f = [];
    if ((r.headers.get('x-content-type-options') || '').toLowerCase() !== 'nosniff') f.push(fail(url, r.headers.get('x-content-type-options') || '(ausente)', 'Falta X-Content-Type-Options: nosniff', 'medium'));
    if (isHtml(r) && !r.headers.get('referrer-policy')) f.push(fail(url, '(ausente)', 'Falta Referrer-Policy', 'low'));
    if (!r.headers.get('content-type')) f.push(fail(url, '(ausente)', 'Falta Content-Type', 'medium'));
    return result(f);
  },

  'cors-reflection': async ({ url }, req) => {
    const origin = 'https://owasp-live-check.invalid';
    const r = await req(url, { headers: { origin } });
    const acao = r.headers.get('access-control-allow-origin');
    const creds = r.headers.get('access-control-allow-credentials') === 'true';
    if (acao === origin) return result([fail(url, `access-control-allow-origin: ${acao}${creds ? ' + credentials' : ''}`, 'Refleja un Origin arbitrario', 'high')]);
    if (acao === 'null') return result([fail(url, 'access-control-allow-origin: null', 'Acepta el origen null', 'medium')]);
    if (acao === '*') return result([fail(url, 'access-control-allow-origin: *', 'Permite cualquier origen: aceptable solo para contenido público', 'low')]);
    return result([], { evidence: [ev(url, `access-control-allow-origin: ${acao ?? '(ausente)'}`)] });
  },

  'cookie-flags': async ({ url }, _req, get) => {
    const r = await get(url);
    const cookies = typeof r.headers.getSetCookie === 'function' ? r.headers.getSetCookie() : [];
    if (!cookies.length) return { status: 'not-applicable', findings: [], scanned: 1, note: 'La respuesta no emite cookies (las cookies de sesión pueden aparecer solo tras autenticarse).' };
    const f = [];
    for (const c of cookies) {
      const name = c.split('=')[0];
      if (!/;\s*secure/i.test(c)) f.push(fail(url, name, `Cookie ${name} sin Secure`, 'medium'));
      if (!/;\s*httponly/i.test(c)) f.push(fail(url, name, `Cookie ${name} sin HttpOnly (aceptable si el script debe leerla)`, 'low'));
      if (!/;\s*samesite=/i.test(c)) f.push(fail(url, name, `Cookie ${name} sin SameSite explícito`, 'low'));
    }
    return result(f);
  },

  'tls-legacy': async ({ url }) => {
    const u = new URL(url);
    if (u.protocol !== 'https:') return { status: 'not-applicable', findings: [], scanned: 0 };
    await throttle();
    const outcome = await new Promise((done) => {
      const s = tls.connect({ host: u.hostname, port: Number(u.port || 443), servername: u.hostname, minVersion: 'TLSv1', maxVersion: 'TLSv1.1', ciphers: 'DEFAULT@SECLEVEL=0', rejectUnauthorized: false, timeout: TIMEOUT_MS });
      s.once('secureConnect', () => { const v = s.getProtocol(); s.destroy(); done({ ok: true, v }); });
      s.once('error', (e) => done({ ok: false, code: e.code || e.message }));
      s.once('timeout', () => { s.destroy(); done({ ok: false, code: 'ETIMEDOUT' }); });
    });
    if (outcome.ok) return result([fail(url, outcome.v, `El servidor acepta ${outcome.v}`, 'high')]);
    if (/NO_PROTOCOLS_AVAILABLE|ENOTFOUND|ETIMEDOUT|ECONNREFUSED|EAI_AGAIN/.test(outcome.code)) {
      return { status: 'not-verified', findings: [], scanned: 1, note: `No se pudo probar TLS 1.0/1.1 desde este equipo (${outcome.code}).` };
    }
    return result([], { evidence: [ev(url, `handshake TLS ≤1.1 rechazado (${outcome.code})`)] });
  },

  'http-redirect': async ({ url }, req) => {
    const u = new URL(url);
    const plain = `http://${u.host}${u.pathname}`;
    try {
      const r = await req(plain, { method: 'HEAD' });
      const loc = r.headers.get('location') || '';
      if ([301, 302, 307, 308].includes(r.status) && loc.startsWith('https://')) return result([], { evidence: [ev(plain, `${r.status} → ${loc}`)] });
      return result([fail(plain, `${r.status} ${loc}`, 'HTTP no redirige a HTTPS', 'medium')]);
    } catch (e) {
      return result([], { evidence: [ev(plain, `puerto 80 no responde (${e.cause?.code || e.name})`)] });
    }
  },

  'exposed-paths': async ({ url }, req, get) => {
    const base = new URL(url);
    const at = (p) => new URL(p, base).href;
    const f = [];
    const git = await req(at('/.git/HEAD'));
    if (git.status === 200 && /^ref:\s*refs\//.test(git.body)) f.push(fail(git.url, git.body.split('\n')[0], 'Repositorio .git expuesto'));
    const env = await req(at('/.env'));
    if (env.status === 200 && !/<html/i.test(env.body) && /^[A-Z_][A-Z0-9_]*\s*=/m.test(env.body)) f.push(fail(env.url, '(contenido redactado)', 'Archivo .env expuesto'));
    const home = await get(url);
    const script = isHtml(home) && /<script[^>]+src=["']([^"']*main[^"']*\.js)["']/i.exec(home.body)?.[1];
    if (script) {
      const map = await req(new URL(`${script}.map`, base).href);
      if (map.status === 200 && /"sources"\s*:/.test(map.body)) f.push(fail(map.url, 'source map accesible', 'Source map de producción publicado', 'medium'));
    }
    for (const p of ['/swagger-ui.html', '/api-docs', '/server-status', '/phpinfo.php', '/actuator/health']) {
      const r = await req(at(p));
      const sig = /swagger|openapi|Apache Server Status|phpinfo\(\)|PHP Version|"status"\s*:\s*"UP"/i;
      if (r.status === 200 && sig.test(r.body)) f.push(fail(r.url, r.body.match(sig)?.[0], `Endpoint interno expuesto: ${p}`, 'low'));
    }
    return result(f);
  },

  'trace-method': async ({ url }, req) => {
    const r = await req(url, { method: 'OPTIONS' });
    const allow = r.headers.get('allow') || r.headers.get('access-control-allow-methods');
    if (!allow) return { status: 'not-verified', findings: [], scanned: 1, note: 'El servidor no anuncia métodos permitidos; TRACE no se prueba directamente en modo live.' };
    return result(/\bTRACE\b/i.test(allow) ? [fail(url, `allow: ${allow}`, 'TRACE anunciado como permitido', 'medium')] : [], { evidence: [ev(url, `allow: ${allow}`)] });
  },
};

/** Runs the selected live checks against every allowed URL. */
export async function runLive(checks, urls) {
  const request = makeClient(urls);
  const cache = new Map();
  const get = (u) => { if (!cache.has(u)) cache.set(u, request(u)); return cache.get(u); };
  // A URL that answers 3xx is evaluated as a redirect, not as the application behind it:
  // HSTS and nosniff still apply to every response (ASVS 3.4.1, 3.4.4), but the report must say so.
  const redirects = {};
  for (const url of urls) {
    try {
      const r = await get(url);
      if (r.status >= 300 && r.status < 400) redirects[url] = `${r.status} → ${r.headers.get('location') || '(sin Location)'}`;
    } catch { /* reported by each probe */ }
  }
  const RESPONSE_PROBES = new Set(['header-hsts', 'header-csp', 'header-basic', 'cookie-flags', 'cors-reflection']);
  const out = {};
  for (const c of checks) {
    if (!PROBES[c.probe]) continue;
    const rank = { fail: 4, review: 3, 'not-verified': 2, pass: 1, 'not-applicable': 0 };
    const merged = { status: 'not-applicable', findings: [], scanned: 0, evidence: [], notes: [] };
    for (const url of urls) {
      let r;
      try { r = await PROBES[c.probe]({ url }, request, get); }
      catch (e) { r = { status: 'not-verified', findings: [], scanned: 0, note: `${url}: ${e.cause?.code || e.message}` }; }
      merged.findings.push(...r.findings);
      merged.evidence.push(...(r.evidence || []));
      if (r.note) merged.notes.push(r.note);
      if (redirects[url] && RESPONSE_PROBES.has(c.probe)) merged.notes.push(`${url} responde ${redirects[url]}: se evaluó la redirección; agrega el destino al allowlist para evaluar la app.`);
      merged.scanned += r.scanned;
      if (rank[r.status] > rank[merged.status]) merged.status = r.status;
    }
    out[c.id] = { ...merged, note: merged.notes.join(' ') || undefined, source: 'live' };
  }
  return out;
}

if (isMain(import.meta.url)) {
  const args = parseArgs();
  const { catalog } = await import('./lib/catalog.mjs');
  const root = resolve(args.root || '.');
  let urls = [].concat(args.url || []).filter((u) => u !== true);
  if (!urls.length) {
    try { urls = JSON.parse(readFileSync(join(root, OUTPUT_DIR, 'profile.json'), 'utf8')).live?.urls?.map((u) => u.url ?? u) ?? []; } catch { /* no profile */ }
  }
  if (!urls.length) { console.error('Sin URLs: pasa --url o define live.urls en .owasp/profile.json'); process.exit(2); }
  const checks = catalog().checks.filter((c) => c.type === 'live' && c.probe !== 'aws');
  const res = await runLive(checks, urls);
  const out = args.out ? resolve(args.out) : join(root, OUTPUT_DIR);
  mkdirSync(out, { recursive: true });
  writeFileSync(join(out, 'live.json'), JSON.stringify({ urls, at: new Date().toISOString(), results: res }, null, 2));
  if (args.json) console.log(JSON.stringify(res, null, 2));
  else for (const [id, r] of Object.entries(res)) console.log(`${id.padEnd(8)} ${r.status.padEnd(15)} ${r.findings.map((f) => f.message).join('; ')}${r.note ? `\n         nota: ${r.note}` : ''}`);
}
