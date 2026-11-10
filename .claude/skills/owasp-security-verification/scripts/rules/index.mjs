/**
 * owasp-ignore-file: este archivo contiene patrones y fixtures vulnerables a propósito.
 *
 * Static rules. Each entry is `(ctx) => RuleResult` (see engine.mjs). Patterns are
 * deliberately conservative: a low-confidence match never fails a check on its own,
 * it becomes a 'review' item the agent must confirm with evidence.
 */

import {
  CODE, CODE_JS, CODE_PY, CODE_PHP, COMPOSE, DOCKERFILES, WORKFLOWS, PROXY,
  EXAMPLE_FILE, DEV_FILE, select, scan, verdict, deny, finding, componentOf, inDir, hclBlocks, isTest,
} from './engine.mjs';
import { basename, lineAt } from '../lib/files.mjs';

const ALL_TEXT = ['**/*'];
const IAC = ['serverless.yml', 'serverless.yaml', '*.tf', 'template.yaml', 'template.yml', '**/cdk/**/*.ts', 'lib/**/*.ts', 'bin/**/*.ts', '*.cfn.yaml', '*.cfn.json'];
const SECRET_SKIP = ['**/*.svg', '**/*.png', '**/*.jpg', '**/*.lock', '**/package-lock.json', '**/*.min.js', '**/*.map'];
const PLACEHOLDER = /^(x+|pass|user|usuario|clave|contrase(ñ|n)a|pwd|admin|root|\*+|changeme|change_me|password|secret|example|your[_-]?\w*|<[^>]+>|\$\{[^}]*\}|\{\{[^}]*\}\}|test\w*|dummy\w*|fake\w*|sample\w*|placeholder|null|none|undefined|todo|replace\w*|xxx\w*|\.\.\.|process\.env.*|env\(.*)$/i;
const SECRET_WORDS = /(token|secret|password|passwd|pwd|otp|nonce|salt|session|api[_-]?key|codigo|code|pin|clave)/i;

const looksPlaceholder = (v) => PLACEHOLDER.test(v.trim()) || /^[\s.*x-]+$/i.test(v);

export const RULES = {
  // ------------------------------------------------------------------ baseline

  'secrets-hardcoded': (ctx) => {
    const files = select(ctx, { files: ALL_TEXT, exclude: SECRET_SKIP, skipTests: false })
      .filter((f) => !EXAMPLE_FILE.test(f));
    const publicKeyFile = (f) => /environments?\/|firebase(-config)?\.(ts|js|json)$|google-services\.json$/i.test(f);
    const patterns = [
      { re: /\b(AKIA|ASIA)[0-9A-Z]{16}\b/g, confidence: 'high', message: 'Llave de acceso de AWS' },
      { re: /aws_secret_access_key\s*[:=]\s*['"]?[A-Za-z0-9/+=]{40}/gi, confidence: 'high', message: 'Secreto de AWS' },
      { re: /-----BEGIN (RSA |EC |DSA |OPENSSH |ENCRYPTED |PGP )?PRIVATE KEY( BLOCK)?-----/g, confidence: 'high', message: 'Llave privada' },
      { re: /\b(ghp|gho|ghu|ghs|ghr)_[A-Za-z0-9]{36}\b|\bgithub_pat_[A-Za-z0-9_]{60,}/g, confidence: 'high', message: 'Token de GitHub' },
      { re: /\bxox[baprs]-[A-Za-z0-9-]{10,}/g, confidence: 'high', message: 'Token de Slack' },
      { re: /\b(sk|rk)_live_[A-Za-z0-9]{20,}/g, confidence: 'high', message: 'Llave live de Stripe' },
      { re: /\bsk-ant-[A-Za-z0-9_-]{20,}/g, confidence: 'high', message: 'Llave de API de Anthropic' },
      { re: /\bsk-(proj-)?[A-Za-z0-9_-]{32,}\b/g, confidence: 'medium', message: 'Posible llave de API de OpenAI' },
      { re: /\b\d{8,10}:AA[A-Za-z0-9_-]{33}\b/g, confidence: 'high', message: 'Token de bot de Telegram' },
      { re: /\bAIza[0-9A-Za-z_-]{35}\b/g, confidence: 'medium', message: 'Llave de API de Google (fuera de la config pública de Firebase)', skipLine: (_l, f) => publicKeyFile(f) },
      { re: /\beyJ[A-Za-z0-9_-]{10,}\.eyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}/g, confidence: 'low', message: 'JWT literal (confirma si es una llave pública anon o un token privilegiado)' },
      { re: /\b(postgres(?:ql)?|mysql|mariadb|mongodb(?:\+srv)?|redis|amqp):\/\/[^:\s/'"]+:([^@\s/'"]{3,})@/g, confidence: 'medium', message: 'Cadena de conexión con contraseña', skipLine: (l, _f, m) => looksPlaceholder(m[2]) || /\$\{|%\(|\$[A-Z_]/.test(m[2]) },
      {
        re: /(?:password|passwd|pwd|secret|api[_-]?key|apikey|access[_-]?token|auth[_-]?token|client[_-]?secret|private[_-]?key)["']?\s*[:=]\s*["']([^"'\s]{8,})["']/gi,
        confidence: 'low', message: 'Asignación literal con nombre de secreto',
        // Firebase web API keys (AIza…) are public identifiers by design; BASE-01 covers other Google keys.
        skipLine: (l, f, m) => looksPlaceholder(m[1]) || /^AIza/.test(m[1]) || /process\.env|getenv|os\.environ|\$\{|config\(|env\(/i.test(l) || /\.(md|html)$/.test(f),
      },
    ];
    // In tests and fixtures a secret-shaped string is usually fake: the agent confirms (low confidence).
    const tuned = patterns.map((p) => ({ ...p, comments: true, downgrade: (f) => isTest(f) }));
    return verdict(scan(ctx, files, tuned, { maxPerFile: 5 }), files.length);
  },

  'secret-files-tracked': (ctx) => {
    const risky = /(^|\/)(\.env(\.(?!example|sample|template|dist)[\w.-]+)?|.*\.(pem|key|p12|pfx|jks|keystore|ppk|tfstate|tfstate\.backup)|id_(rsa|dsa|ecdsa|ed25519)|credentials\.json|service[-_]?account[^/]*\.json|[^/]*firebase-adminsdk[^/]*\.json|jwks-private\.json|\.pgpass|\.htpasswd)$/i;
    const findings = [];
    for (const f of ctx.files) {
      if (risky.test(f) && !/public|\.pub$/i.test(basename(f)) && !EXAMPLE_FILE.test(f)) {
        findings.push({ file: f, line: 1, snippet: basename(f), confidence: 'high', message: 'Archivo de secretos versionado o sin ignorar' });
      } else if (basename(f) === '.npmrc' && /_authToken\s*=\s*(?!\$\{)/.test(ctx.read(f) || '')) {
        findings.push({ file: f, line: 1, snippet: '.npmrc con _authToken', confidence: 'high', message: 'Token de registro npm versionado' });
      }
    }
    return verdict(findings, ctx.files.length);
  },

  'gitignore-secrets': (ctx) => {
    if (!ctx.isGit) return { status: 'not-applicable', findings: [], scanned: 0, note: 'El proyecto no es un repositorio git.' };
    const gi = ctx.read('.gitignore');
    if (gi == null) return verdict([{ file: '.gitignore', line: 1, snippet: '', confidence: 'medium', message: 'No existe .gitignore en la raíz' }], 1);
    const missing = [];
    if (!/^\s*\/?\*?\.env(\*|\.\*|\b)/m.test(gi) && !/^\s*\*\.env/m.test(gi)) missing.push('.env');
    if (!/\.pem|\*\.key|\bkeys?\//m.test(gi)) missing.push('*.pem / *.key');
    return verdict(missing.length ? [{ file: '.gitignore', line: 1, snippet: `faltan patrones: ${missing.join(', ')}`, confidence: missing.includes('.env') ? 'medium' : 'low', message: 'El .gitignore no cubre archivos de secretos comunes' }] : [], 1);
  },

  'lockfile-present': (ctx) => {
    const has = (dir, names) => {
      for (let d = dir; ; d = d.includes('/') ? d.slice(0, d.lastIndexOf('/')) : '.') {
        if (names.some((n) => ctx.fileSet.has(d === '.' ? n : `${d}/${n}`))) return true;
        if (d === '.') return false;
      }
    };
    const dirOf = (f) => (f.includes('/') ? f.slice(0, f.lastIndexOf('/')) : '.');
    const findings = [];
    let scanned = 0;
    for (const f of ctx.files) {
      const b = basename(f);
      if (isTest(f)) continue;
      if (b === 'package.json') {
        let pkg; try { pkg = JSON.parse(ctx.read(f) || '{}'); } catch { continue; }
        if (!Object.keys({ ...pkg.dependencies, ...pkg.devDependencies }).length) continue;
        scanned++;
        if (!has(dirOf(f), ['package-lock.json', 'npm-shrinkwrap.json', 'yarn.lock', 'pnpm-lock.yaml', 'bun.lockb', 'bun.lock'])) {
          findings.push({ file: f, line: 1, snippet: b, confidence: 'medium', message: 'package.json sin lockfile' });
        }
      } else if (b === 'composer.json') {
        scanned++;
        if (!has(dirOf(f), ['composer.lock'])) findings.push({ file: f, line: 1, snippet: b, confidence: 'medium', message: 'composer.json sin composer.lock' });
      } else if (/^requirements[^/]*\.txt$/.test(b)) {
        scanned++;
        const text = ctx.read(f) || '';
        const loose = text.split('\n').map((l, i) => [l.replace(/#.*/, '').trim(), i + 1])
          .filter(([l]) => l && !l.startsWith('-') && !/==|@\s*(https?|git)\+?|--hash/.test(l));
        for (const [l, n] of loose.slice(0, 5)) findings.push({ file: f, line: n, snippet: l, confidence: 'medium', message: 'Dependencia Python sin versión fijada (==)' });
      }
    }
    return verdict(findings, scanned);
  },

  'dynamic-code-exec': deny({ files: CODE }, [
    { re: /(?<![\w.$])eval\s*\(/g, confidence: 'medium', message: 'eval()', onlyIf: (f) => !f.endsWith('.py') },
    { re: /\bnew\s+Function\s*\(/g, confidence: 'medium', message: 'new Function()' },
    { re: /\bsetTimeout\s*\(\s*['"`]/g, confidence: 'low', message: 'setTimeout con string' },
    { re: /\bcreate_function\s*\(|preg_replace\s*\(\s*['"][^'"]*\/[a-z]*e[a-z]*['"]/g, confidence: 'high', message: 'Ejecución dinámica en PHP' },
    { re: /(?<![\w.])(eval|exec)\s*\(/g, confidence: 'medium', message: 'eval()/exec() en Python', onlyIf: (f) => f.endsWith('.py') },
  ]),

  'sql-concat': deny({ files: CODE }, [
    { re: /\b(query|execute|raw|unsafe|\$queryRawUnsafe|\$executeRawUnsafe)\s*\(\s*`[^`]*\b(SELECT|INSERT|UPDATE|DELETE|WHERE)\b[^`]*\$\{/gi, confidence: 'high', message: 'SQL con template literal interpolado' },
    { re: /\b(query|execute)\s*\(\s*['"][^'"\n]*\b(SELECT|INSERT|UPDATE|DELETE)\b[^'"\n]*['"]\s*\+/gi, confidence: 'high', message: 'SQL concatenado con +' },
    { re: /->(query|exec|prepare)\s*\(\s*"[^"\n]*\b(SELECT|INSERT|UPDATE|DELETE)\b[^"\n]*\$\w+/gi, confidence: 'medium', message: 'SQL con variable interpolada en PHP' },
    { re: /->(query|exec)\s*\([^;\n]*\b(SELECT|INSERT|UPDATE|DELETE)\b[^;\n]*['"]\s*\.\s*\$/gi, confidence: 'medium', message: 'SQL concatenado en PHP' },
    { re: /\bmysqli_query\s*\([^,]+,\s*["'][^"'\n]*\$\w+/gi, confidence: 'high', message: 'mysqli_query con variable' },
    { re: /\$DB->(get_records_sql|get_record_sql|get_field_sql|get_fieldset_sql|get_recordset_sql|count_records_sql|execute|get_records_select|get_records_sql_menu)\s*\(\s*("[^"\n]*\$\w+|[^;\n]*['"]\s*\.\s*\$\w+)/g, confidence: 'medium', message: 'SQL de Moodle con variable concatenada (usar placeholders ? o :nombre)', downgrade: (_f, text) => /get_in_or_equal|sql_like/.test(text) },
    { re: /\.execute(many)?\s*\(\s*f["']/g, confidence: 'high', message: 'SQL con f-string' },
    { re: /\.execute(many)?\s*\(\s*["'][^"'\n]*["']\s*(%|\+|\.format\()/g, confidence: 'high', message: 'SQL formateado con % / + / format' },
  ]),

  'os-command-injection': deny({ files: CODE }, [
    { re: /(?:(?<![\w.])|(?:child_process|cp|childProcess)\.)(exec|execSync)\s*\(\s*`[^`]*\$\{/g, confidence: 'high', message: 'exec() con template literal' },
    { re: /(?:(?<![\w.])|(?:child_process|cp|childProcess)\.)(exec|execSync)\s*\(\s*['"][^'"\n]*['"]\s*\+/g, confidence: 'medium', message: 'exec() con concatenación' },
    { re: /\bspawn(Sync)?\s*\([^)]*shell\s*:\s*true/g, confidence: 'medium', message: 'spawn con shell: true' },
    { re: /(?<![\w>:$])(shell_exec|system|passthru|popen|proc_open|exec)\s*\([^;\n]*\$(?!this\b)\w+/g, confidence: 'medium', message: 'Comando de sistema con variable en PHP', onlyIf: (f) => f.endsWith('.php'), skipLine: (l) => /escapeshell(arg|cmd)/.test(l), downgrade: (_f, text) => /escapeshell(arg|cmd)/.test(text) },
    { re: /subprocess\.\w+\([^)]*shell\s*=\s*True/g, confidence: 'medium', message: 'subprocess con shell=True' },
    { re: /\bos\.(system|popen)\s*\(/g, confidence: 'medium', message: 'os.system / os.popen' },
  ]),

  'weak-hash': deny({ files: CODE }, [
    { re: /createHash\(\s*['"](md5|sha1)['"]/gi, confidence: 'low', message: 'MD5/SHA-1 (aceptable solo para fines no criptográficos)' },
    { re: /\b(md5|sha1)\s*\(/gi, confidence: 'low', message: 'md5()/sha1() en PHP', onlyIf: (f) => f.endsWith('.php') },
    { re: /hashlib\.(md5|sha1)\s*\((?![^)]*usedforsecurity\s*=\s*False)/g, confidence: 'low', message: 'hashlib md5/sha1' },
    { re: /(createHash\(\s*['"](md5|sha1)['"]|\b(md5|sha1)\s*\(|hashlib\.(md5|sha1))[^\n]*(pass|pwd|clave|contrase|token|secret)/gi, confidence: 'high', message: 'MD5/SHA-1 aplicado a contraseñas o tokens' },
  ]),

  'insecure-random': deny({ files: CODE }, [
    { re: /Math\.random\(\)/g, confidence: 'medium', message: 'Math.random() para un valor que parece secreto', skipLine: (l) => !SECRET_WORDS.test(l) },
    { re: /\brandom\.(random|randint|choice|choices|randrange|getrandbits)\(/g, confidence: 'medium', message: 'random de Python para un valor que parece secreto', skipLine: (l) => !SECRET_WORDS.test(l) },
    { re: /\b(rand|mt_rand|uniqid|lcg_value)\s*\(/g, confidence: 'medium', message: 'Generador no criptográfico en PHP para un valor que parece secreto', onlyIf: (f) => f.endsWith('.php'), skipLine: (l) => !SECRET_WORDS.test(l) },
  ]),

  'tls-verify-disabled': deny({ files: [...CODE, ...COMPOSE, '**/*.env', '**/*.yml', '**/*.yaml'], exclude: ['**/*.example'] }, [
    { re: /rejectUnauthorized\s*:\s*false/g, confidence: 'high', message: 'rejectUnauthorized: false' },
    { re: /NODE_TLS_REJECT_UNAUTHORIZED['"]?\s*[=:]\s*['"]?0/g, confidence: 'high', message: 'NODE_TLS_REJECT_UNAUTHORIZED=0' },
    { re: /\bverify\s*=\s*False\b/g, confidence: 'high', message: 'requests con verify=False', onlyIf: (f) => f.endsWith('.py') },
    { re: /ssl\._create_unverified_context|check_hostname\s*=\s*False/g, confidence: 'high', message: 'Contexto SSL sin verificación' },
    { re: /CURLOPT_SSL_VERIFY(PEER|HOST)\s*,\s*(false|0)\b/gi, confidence: 'high', message: 'cURL sin verificación TLS' },
  ]),

  'debug-enabled': deny({ files: [...CODE, ...COMPOSE, ...DOCKERFILES, '**/*.ini', '**/.env.production', '**/*.prod.*'], exclude: [] }, [
    { re: /app\.run\([^)]*debug\s*=\s*True/g, confidence: 'high', message: 'Flask app.run(debug=True)' },
    { re: /^\s*DEBUG\s*=\s*True\b/gm, confidence: 'medium', message: 'DEBUG = True', onlyIf: (f) => f.endsWith('.py') },
    { re: /FLASK_(DEBUG|ENV)['"]?\s*[=:]\s*['"]?(1|true|development)\b/gi, confidence: 'medium', message: 'Flask en modo debug/development' },
    { re: /ini_set\(\s*['"]display_errors['"]\s*,\s*['"]?(1|on|true)/gi, confidence: 'high', message: 'display_errors activado' },
    { re: /^\s*display_errors\s*=\s*(On|1)\b/gim, confidence: 'high', message: 'display_errors = On en php.ini' },
    { re: /\$CFG->debugdisplay\s*=\s*(1|true)/g, confidence: 'high', message: 'Moodle debugdisplay activo' },
    { re: /\$CFG->debug\s*=\s*(E_ALL|32767|DEBUG_DEVELOPER|6143)/g, confidence: 'medium', message: 'Moodle en modo DEVELOPER' },
  ].map((p) => ({ ...p, skipLine: (l, f) => DEV_FILE.test(f) }))),

  'cors-wildcard': deny({ files: [...CODE, ...PROXY, 'serverless.yml', 'serverless.yaml', 'firebase.json', '*.tf', 'template.yaml'] }, [
    { re: /Access-Control-Allow-Origin['"]?\s*[:,]\s*['"]\*['"]/gi, confidence: 'medium', message: 'Access-Control-Allow-Origin: * (aceptable solo para contenido público sin credenciales)' },
    { re: /(?<![\w.:$])cors\(\s*\)/g, confidence: 'medium', message: 'cors() sin opciones permite cualquier origen', onlyIf: (f) => /\.[cm]?[jt]sx?$/.test(f) },
    { re: /\borigin\s*:\s*(['"]\*['"]|true)\s*[,}]/g, confidence: 'medium', message: 'CORS origin "*" o true (refleja cualquier origen)' },
    { re: /\bCORS\(\s*app\s*\)/g, confidence: 'medium', message: 'Flask-CORS sin restricción de orígenes' },
    { re: /origins["']?\s*[:=]\s*["']\*["']/g, confidence: 'medium', message: 'CORS origins = "*"' },
    { re: /header\(\s*['"]Access-Control-Allow-Origin:\s*\*/gi, confidence: 'medium', message: 'header() con origen *' },
    { re: /Access-Control-Allow-Origin[^\n]*\$(_SERVER\[['"]HTTP_ORIGIN|http_origin)/gi, confidence: 'high', message: 'CORS refleja el Origin de la petición' },
    { re: /add_header\s+Access-Control-Allow-Origin\s+['"]?\*/gi, confidence: 'medium', message: 'nginx con origen *' },
    { re: /^\s*cors\s*:\s*true\b/gm, confidence: 'medium', message: 'serverless cors: true (permite cualquier origen)', onlyIf: (f) => /serverless\.ya?ml$/.test(f) },
    { re: /allowedOrigins\s*:\s*\n?\s*-?\s*\[?\s*['"]?\*['"]?/g, confidence: 'medium', message: 'allowedOrigins *' },
    { re: /allow_origins\s*=\s*\[\s*"\*"/g, confidence: 'medium', message: 'allow_origins ["*"]' },
  ]),

  'unsafe-deserialization': deny({ files: CODE }, [
    { re: /\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE)/g, confidence: 'high', message: 'unserialize() de entrada del usuario' },
    { re: /\bunserialize\s*\((?![^;\n]*allowed_classes)/g, confidence: 'low', message: 'unserialize() sin allowed_classes', onlyIf: (f) => f.endsWith('.php') },
    { re: /\bpickle\.loads?\s*\(|\bjsonpickle\.decode\s*\(|\bdill\.loads?\s*\(/g, confidence: 'medium', message: 'pickle/jsonpickle sobre datos potencialmente externos' },
    { re: /\byaml\.load\s*\((?![^)]*Loader\s*=\s*(yaml\.)?(C?SafeLoader|BaseLoader))/g, confidence: 'medium', message: 'yaml.load sin SafeLoader' },
    { re: /\byaml\.unsafe_load\s*\(/g, confidence: 'high', message: 'yaml.unsafe_load' },
    { re: /require\(\s*['"]node-serialize['"]\)/g, confidence: 'high', message: 'node-serialize (RCE conocido)' },
  ]),

  'xml-external-entities': deny({ files: CODE }, [
    { re: /LIBXML_NOENT|libxml_disable_entity_loader\s*\(\s*false/g, confidence: 'high', message: 'Resolución de entidades XML habilitada' },
    { re: /LIBXML_DTDLOAD/g, confidence: 'medium', message: 'Carga de DTD externos' },
    { re: /resolve_entities\s*=\s*True/g, confidence: 'high', message: 'lxml con resolve_entities=True' },
    { re: /\bnoent\s*:\s*true/g, confidence: 'high', message: 'libxmljs con noent: true' },
  ]),

  'outbound-no-timeout': deny({ files: CODE_PY }, [
    { re: /\brequests\.(get|post|put|patch|delete|head|request)\s*\((?![^)\n]*timeout\s*=)[^)\n]*\)/g, confidence: 'low', message: 'requests sin timeout' },
  ]),

  // ------------------------------------------------------------------ angular

  'angular-bypass-sanitizer': deny({ files: CODE_JS }, [
    { re: /bypassSecurityTrust(Html|Script|Style|Url|ResourceUrl)\s*\(/g, confidence: 'medium', message: 'bypassSecurityTrust* desactiva la sanitización de Angular' },
  ]),

  'dom-html-sinks': deny({ files: CODE_JS, exclude: ['**/server.ts', '**/server/**'] }, [
    { re: /\.(innerHTML|outerHTML)\s*(\+?=)(?!=)/g, confidence: 'medium', message: 'Asignación directa a innerHTML/outerHTML' },
    { re: /\.insertAdjacentHTML\s*\(/g, confidence: 'medium', message: 'insertAdjacentHTML' },
    { re: /\bdocument\.write(ln)?\s*\(/g, confidence: 'medium', message: 'document.write' },
    { re: /setProperty\([^,]+,\s*['"](innerHTML|outerHTML)['"]/g, confidence: 'medium', message: 'Renderer2.setProperty innerHTML' },
  ]),

  'angular-innerhtml-binding': deny({ files: ['**/*.html', ...CODE_JS] }, [
    { re: /\[innerHTML\]\s*=/g, confidence: 'low', message: 'Binding [innerHTML]: confirma que el contenido no proviene de usuarios o que pasa por sanitización' },
  ]),

  'tokens-in-web-storage': deny({ files: CODE_JS }, [
    { re: /(localStorage|sessionStorage)\.setItem\(\s*[^,]*(token|jwt|session|auth|password|secret|refresh)/gi, confidence: 'medium', message: 'Token o dato sensible en Web Storage' },
  ]),

  'csp-declared': (ctx) => {
    const files = select(ctx, { files: ['**/*.html', 'firebase.json', '**/server.ts', '**/server.mjs', '**/server/**/*.ts', 'serverless.yml', '*.tf', ...PROXY, '_headers', 'vercel.json', 'netlify.toml', 'template.yaml', ...CODE_JS] });
    const re = /Content-Security-Policy|contentSecurityPolicy|content_security_policy|ContentSecurityPolicy|\bhelmet\s*\(/;
    for (const f of files) {
      const text = ctx.read(f) || '';
      const m = re.exec(text);
      if (m && !/contentSecurityPolicy\s*:\s*false/.test(text)) {
        return { status: 'pass', findings: [], scanned: files.length, evidence: [{ file: f, line: lineAt(text, m.index) }] };
      }
    }
    return { status: 'not-verified', findings: [], scanned: files.length, note: 'No se encontró CSP en el repositorio. Puede estar definida en el CDN o el hosting: verifícala con el modo live (LIVE-02).' };
  },

  'prod-source-maps': (ctx) => {
    const files = ctx.files.filter((f) => basename(f) === 'angular.json');
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      let cfg; try { cfg = JSON.parse(text); } catch { continue; }
      for (const [name, p] of Object.entries(cfg.projects || {})) {
        const build = p.architect?.build;
        const prod = build?.configurations?.production || {};
        const sm = prod.sourceMap ?? build?.options?.sourceMap;
        const on = sm === true || (sm && typeof sm === 'object' && (sm.scripts || sm.styles) && !sm.hidden);
        if (on) findings.push(finding(f, text, text.indexOf('"sourceMap"'), 'medium', `Proyecto ${name}: source maps en producción`));
      }
    }
    return verdict(findings, files.length);
  },

  'frontend-privileged-keys': (ctx) => {
    const roots = ctx.files.filter((f) => basename(f) === 'angular.json').map((f) => (f.includes('/') ? f.slice(0, f.lastIndexOf('/')) : '.'));
    const files = select(ctx, { files: [...CODE_JS, '**/*.html', '**/*.json'], exclude: ['**/server.ts', '**/server/**', '**/functions/**', '**/api/**'] })
      .filter((f) => roots.some((r) => inDir(r, f) && f.slice(r === '.' ? 0 : r.length + 1).startsWith('src/')));
    const patterns = [
      { re: /service_role|SUPABASE_SERVICE_ROLE/g, confidence: 'high', message: 'Llave service_role de Supabase en el frontend' },
      { re: /"private_key"\s*:\s*"-----BEGIN/g, confidence: 'high', message: 'Cuenta de servicio en el frontend' },
      { re: /\b(sk|rk)_live_|\bsk-ant-|\b(AKIA|ASIA)[0-9A-Z]{16}\b/g, confidence: 'high', message: 'Llave de servidor en el frontend' },
      { re: /(api[_-]?secret|client[_-]?secret|secret[_-]?key|private[_-]?key)\s*[:=]\s*['"][^'"\s]{8,}['"]/gi, confidence: 'medium', message: 'Secreto en código del navegador' },
    ];
    return verdict(scan(ctx, files, patterns), files.length);
  },

  // ------------------------------------------------------------------ node backend

  'express-helmet': (ctx) => {
    const files = select(ctx, { files: CODE_JS, contains: /\bexpress\s*\(\s*\)/ });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f);
      const comp = componentOf(ctx, f, ['package.json']);
      const pkg = ctx.read(comp === '.' ? 'package.json' : `${comp}/package.json`) || '';
      if (/helmet/.test(text) || /"helmet"/.test(pkg)) continue;
      const i = text.search(/\bexpress\s*\(\s*\)/);
      findings.push(finding(f, text, i, 'medium', 'Servidor Express sin helmet ni cabeceras de seguridad explícitas'));
    }
    return verdict(findings, files.length);
  },

  'jwt-insecure-verify': deny({ files: CODE }, [
    { re: /\bjwt\.decode\s*\(/g, confidence: 'low', message: 'jwt.decode no verifica la firma: confirma que se verifica después', onlyIf: (f) => !f.endsWith('.py') },
    { re: /\bjwt\.verify\s*\(\s*[^,()]+,\s*[^,()]+\s*\)/g, confidence: 'medium', message: 'jwt.verify sin opciones (sin lista de algoritmos, issuer ni audience)' },
    { re: /algorithms\s*[:=]\s*\[[^\]]*['"]none['"]/gi, confidence: 'high', message: 'Algoritmo "none" permitido' },
    { re: /ignoreExpiration\s*:\s*true/g, confidence: 'high', message: 'ignoreExpiration: true' },
    { re: /\bjwtVerify\s*\(\s*[^,()]+,\s*[^,()]+\s*\)/g, confidence: 'low', message: 'jose.jwtVerify sin opciones (issuer, audience, algorithms)' },
    { re: /verify_signature['"]?\s*:\s*False/g, confidence: 'high', message: 'PyJWT sin verificar la firma' },
  ]),

  // ------------------------------------------------------------------ serverless / IaC

  'iam-wildcard': deny({ files: [...IAC, '**/*policy*.json'] }, [
    { re: /\bAction['"]?\s*:\s*['"]\*['"]/g, confidence: 'high', message: 'Action: "*"' },
    { re: /^\s*-\s*['"]?[a-z0-9-]+:\*['"]?\s*$/gm, confidence: 'medium', message: 'Acción con comodín de servicio (p. ej. dynamodb:*)' },
    { re: /"[a-z0-9-]+:\*"/g, confidence: 'medium', message: 'Acción con comodín de servicio' },
    { re: /\bactions\s*[:=]\s*\[\s*['"](\*|[a-z0-9-]+:\*)['"]/g, confidence: 'medium', message: 'actions con comodín (CDK/Terraform)' },
    { re: /\bResource['"]?\s*:\s*['"]\*['"]/g, confidence: 'low', message: 'Resource: "*" (algunas acciones lo requieren; confirma)' },
  ]),

  'iac-plain-secrets': (ctx) => {
    const files = select(ctx, { files: [...IAC, ...COMPOSE] }).filter((f) => !EXAMPLE_FILE.test(f));
    const key = '([A-Z0-9_]*(SECRET|PASSWORD|PASSWD|TOKEN|API_?KEY|PRIVATE_KEY|ACCESS_KEY|ENCRYPTION_KEY)[A-Z0-9_]*)';
    const patterns = [
      // Environment variable names are upper case: case-sensitive on purpose (http_tokens is not a secret).
      { re: new RegExp(`^\\s*-?\\s*${key}\\s*[:=]\\s*['"]?([^\\s'"#]{6,})['"]?\\s*$`, 'gm'), group: 3 },
      { re: /^\s*(password|master_password|secret|token|api_key|access_key|secret_key)\s*=\s*"([^"]{6,})"/gmi, group: 2 },
    ];
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const p of patterns) {
        for (const m of text.matchAll(p.re)) {
          const v = m[p.group] ?? m[m.length - 1];
          if (looksPlaceholder(v) || /^\$|^\{\{|^\d+[smhd]?$|^(true|false|yes|no)$/i.test(v) || /\$\{|\bvar\.|\blocal\.|\bdata\.|ssm:|secretsmanager/i.test(v)) continue;
          findings.push(finding(f, text, m.index, 'medium', `Valor literal para ${m[1]}`));
        }
      }
    }
    return verdict(findings, files.length);
  },

  's3-public': deny({ files: IAC }, [
    { re: /AccessControl\s*:\s*['"]?Public(Read|ReadWrite)/g, confidence: 'high', message: 'Bucket con ACL pública' },
    { re: /\bacl\s*=\s*"public-read(-write)?"/g, confidence: 'high', message: 'Bucket con ACL pública (Terraform)' },
    { re: /(BlockPublicAcls|BlockPublicPolicy|IgnorePublicAcls|RestrictPublicBuckets)\s*:\s*false/g, confidence: 'medium', message: 'Block Public Access desactivado' },
    { re: /block_public_(acls|policy)\s*=\s*false|restrict_public_buckets\s*=\s*false|ignore_public_acls\s*=\s*false/g, confidence: 'medium', message: 'Block Public Access desactivado (Terraform)' },
    { re: /Principal['"]?\s*:\s*['"]\*['"]/g, confidence: 'low', message: 'Política con Principal "*" (confirma que el bucket solo sirve activos públicos)' },
  ]),

  'lambda-url-no-auth': deny({ files: IAC }, [
    { re: /^\s*url\s*:\s*true\s*$/gm, confidence: 'low', message: 'Function URL sin authorizer (AuthType NONE)', onlyIf: (f) => /serverless\.ya?ml$/.test(f) },
    { re: /AuthType\s*:\s*['"]?NONE/g, confidence: 'low', message: 'Function URL con AuthType NONE' },
    { re: /authorization_type\s*=\s*"NONE"/g, confidence: 'low', message: 'authorization_type = NONE' },
  ]),

  // ------------------------------------------------------------------ firebase / supabase

  'firebase-rules-open': deny({ files: ['*.rules', 'firestore.rules', 'storage.rules', 'database.rules.json'], skipTests: false }, [
    { re: /allow\s+[\w,\s]*\b(write|create|update|delete)\b[\w,\s]*:\s*if\s+true\s*;/g, confidence: 'high', message: 'Escritura abierta (if true)' },
    { re: /allow\s+(?![\w,\s]*\b(write|create|update|delete)\b)[\w,\s]*:\s*if\s+true\s*;/g, confidence: 'low', message: 'Lectura pública (if true): confirma que es contenido público por diseño' },
    { re: /allow\s+[a-z,\s]+;/g, confidence: 'high', message: 'allow sin condición' },
    { re: /request\.time\s*<\s*timestamp\.date\(/g, confidence: 'high', message: 'Reglas en modo de prueba (vencen por fecha)' },
    { re: /"\.(write)"\s*:\s*(true|"true")/g, confidence: 'high', message: 'RTDB con escritura pública' },
    { re: /"\.(read)"\s*:\s*(true|"true")/g, confidence: 'low', message: 'RTDB con lectura pública' },
  ]),

  'firebase-rules-auth-only': (ctx) => {
    const files = select(ctx, { files: ['*.rules', 'firestore.rules', 'storage.rules'], skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const m of text.matchAll(/allow\s+[\w,\s]*\b(write|create|update|delete)\b[\w,\s]*:\s*if\s+request\.auth\s*!=\s*null\s*;/g)) {
        const before = text.slice(0, m.index);
        const wildcard = /match\s+\/\{[^}]*=\*\*\}\s*\{[^{}]*$/.test(before);
        findings.push(finding(f, text, m.index, wildcard ? 'high' : 'medium', wildcard ? 'Cualquier usuario autenticado puede escribir en toda la base' : 'Escritura para cualquier usuario autenticado, sin rol ni dueño'));
      }
    }
    return verdict(findings, files.length);
  },

  'supabase-rls': (ctx) => {
    const files = select(ctx, { files: ['**/*.sql'], skipTests: false });
    const created = new Map();
    const enabled = new Set();
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const m of text.matchAll(/create\s+table\s+(?:if\s+not\s+exists\s+)?(?:"?public"?\.)?"?([a-z_][\w]*)"?\s*\(/gi)) {
        const pre = text.slice(Math.max(0, m.index - 1), m.index + m[0].length);
        if (/create\s+table\s+(?:if\s+not\s+exists\s+)?"?(?!public)\w+"?\./i.test(pre)) continue;
        const name = m[1].toLowerCase();
        if (!created.has(name)) created.set(name, finding(f, text, m.index, 'high', `Tabla public.${name} sin ENABLE ROW LEVEL SECURITY`));
      }
      for (const m of text.matchAll(/alter\s+table\s+(?:if\s+exists\s+)?(?:only\s+)?(?:"?public"?\.)?"?([a-z_][\w]*)"?\s+enable\s+row\s+level\s+security/gi)) enabled.add(m[1].toLowerCase());
    }
    const findings = [...created].filter(([n]) => !enabled.has(n)).map(([, fd]) => fd);
    return verdict(findings, created.size ? files.length : 0);
  },

  'supabase-permissive-policy': deny({ files: ['**/*.sql'], skipTests: false }, [
    { re: /create\s+policy[^;]*\bto\s+(anon|public)\b[^;]*\b(using|with\s+check)\s*\(\s*true\s*\)/gis, confidence: 'high', message: 'Política RLS abierta para anon/public' },
    { re: /create\s+policy[^;]*\b(using|with\s+check)\s*\(\s*true\s*\)/gis, confidence: 'low', message: 'Política RLS con USING/WITH CHECK (true): confirma el rol y la intención' },
  ], { maxPerFile: 5 }),

  // ------------------------------------------------------------------ php

  'php-reflected-output': deny({ files: CODE_PHP }, [
    { re: /\b(echo|print)\b[^;\n]*\$_(GET|POST|REQUEST|COOKIE)\b/g, confidence: 'high', message: 'Salida de entrada del usuario sin codificar', skipLine: (l) => /htmlspecialchars|htmlentities|\bs\(|format_string|json_encode|intval|\(int\)|urlencode|esc_/.test(l) },
    { re: /<\?=\s*\$_(GET|POST|REQUEST|COOKIE)/g, confidence: 'high', message: '<?= con entrada del usuario' },
    { re: /\b(echo|print)\b[^;\n]*\$_SERVER\[['"](PHP_SELF|REQUEST_URI|QUERY_STRING)['"]\]/g, confidence: 'medium', message: 'Salida de $_SERVER controlable por el usuario', skipLine: (l) => /htmlspecialchars|htmlentities/.test(l) },
  ]),

  'php-dynamic-include': deny({ files: CODE_PHP }, [
    { re: /\b(include|require)(_once)?\b\s*\(?[^;\n]*\$_(GET|POST|REQUEST|COOKIE)/g, confidence: 'high', message: 'include/require con entrada del usuario' },
    { re: /\b(include|require)(_once)?\b\s*\(?\s*\$(?!CFG\b|this\b)\w+\s*[.;)]/g, confidence: 'low', message: 'include/require con ruta variable: confirma que no depende del usuario' },
  ]),

  'php-upload-original-name': deny({ files: CODE_PHP }, [
    { re: /move_uploaded_file\s*\([^;\n]*\$_FILES\s*\[[^\]]+\]\s*\[\s*['"]name['"]\s*\]/g, confidence: 'high', message: 'move_uploaded_file con el nombre original' },
    { re: /\$_FILES\s*\[[^\]]+\]\s*\[\s*['"]name['"]\s*\][^;\n]*(\.\s*['"]?\/|\/\s*['"]?\s*\.)/g, confidence: 'medium', message: 'Nombre original del archivo usado en una ruta' },
  ]),

  // ------------------------------------------------------------------ python

  'flask-upload-limits': (ctx) => {
    const users = select(ctx, { files: CODE_PY, contains: /request\.files\b/ });
    const findings = [];
    for (const f of users) {
      const comp = componentOf(ctx, f);
      const limited = ctx.files.some((g) => g.endsWith('.py') && inDir(comp, g) && /MAX_CONTENT_LENGTH/.test(ctx.read(g) || ''));
      if (!limited) {
        const text = ctx.read(f);
        findings.push(finding(f, text, text.search(/request\.files\b/), 'medium', 'Recibe archivos sin MAX_CONTENT_LENGTH en el componente'));
      }
    }
    return verdict(findings, users.length);
  },

  'python-unsafe-filename': deny({ files: CODE_PY }, [
    { re: /(os\.path\.join|\.save\s*\(|open\s*\(|Path\s*\()[^\n]*\.filename\b/g, confidence: 'medium', message: 'Nombre de archivo del cliente usado en una ruta', skipLine: (l) => /secure_filename|uuid/.test(l) },
  ]),

  // ------------------------------------------------------------------ auth service

  'session-cookie-flags': deny({ files: CODE, contains: /cookie|session/i }, [
    { re: /\bhttpOnly\s*:\s*false\b/g, confidence: 'high', message: 'Cookie con httpOnly: false' },
    { re: /\bsecure\s*:\s*false\b/g, confidence: 'medium', message: 'Cookie con secure: false', skipLine: (l, f) => DEV_FILE.test(f) },
    { re: /SESSION_COOKIE_(SECURE|HTTPONLY)\s*=\s*False/g, confidence: 'high', message: 'Cookie de sesión de Flask/Django sin Secure/HttpOnly' },
    { re: /\bsameSite\s*:\s*['"]none['"]/gi, confidence: 'low', message: 'SameSite=None: confirma que es necesario' },
    { re: /res\.cookie\s*\((?![^)]*httpOnly\s*:\s*true)[^)]*\)/g, confidence: 'low', message: 'res.cookie sin httpOnly explícito' },
  ]),

  'ldap-filter-concat': deny({ files: CODE }, [
    { re: /\b(uid|cn|sAMAccountName|mail|userPrincipalName|memberOf)=\$\{(?!\s*(escape\w*|ldapEscape|filterEscape)\s*\()/g, confidence: 'high', message: 'Filtro LDAP con interpolación sin escapar' },
    { re: /\(\s*(uid|cn|sAMAccountName|mail|userPrincipalName)=['"]\s*\+/g, confidence: 'high', message: 'Filtro LDAP concatenado' },
    { re: /f["'][^"'\n]*\((uid|cn|sAMAccountName|mail|userPrincipalName)=\{/g, confidence: 'high', message: 'Filtro LDAP con f-string', skipLine: (l) => /escape_filter_chars/.test(l) },
  ]),

  'login-rate-limit': (ctx) => {
    const code = select(ctx, { files: [...CODE, 'package.json', 'requirements*.txt', ...PROXY] });
    const limiter = /express-rate-limit|rate-limiter-flexible|express-slow-down|@fastify\/rate-limit|flask[-_]limiter|slowapi|RateLimit|limit_req|throttl/i;
    for (const f of code) {
      const text = ctx.read(f) || '';
      const m = limiter.exec(text);
      if (m) return { status: 'pass', findings: [], scanned: code.length, evidence: [{ file: f, line: lineAt(text, m.index) }] };
    }
    for (const f of select(ctx, { files: CODE })) {
      const text = ctx.read(f) || '';
      const i = text.search(/['"`]\/(api\/)?(auth\/)?(login|signin|token|callback)['"`]/i);
      if (i >= 0) return verdict([finding(f, text, i, 'medium', 'Ruta de autenticación sin rate limiting detectable en el componente')], code.length);
    }
    return { status: 'not-verified', findings: [], scanned: code.length, note: 'No se encontró limitador ni ruta de login reconocible. Puede estar en el gateway o el proxy.' };
  },

  // ------------------------------------------------------------------ containers

  'compose-internal-ports': (ctx) => {
    const files = select(ctx, { files: COMPOSE, skipTests: false });
    const internal = /(redis|postgres|postgis|mysql|mariadb|mongo|elasticsearch|opensearch|ollama|rabbitmq|memcached|minio|chroma|qdrant|weaviate|milvus|clickhouse|cassandra|neo4j|couchdb|etcd|zookeeper|kafka)/i;
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const svc of composeServices(text)) {
        if (!internal.test(`${svc.image} ${svc.name}`)) continue;
        for (const p of svc.ports) {
          if (/^\s*['"]?(127\.0\.0\.1|localhost|\[::1\]):/.test(p.value) || p.hostIp && /127\.0\.0\.1|::1/.test(p.hostIp)) continue;
          if (!/:/.test(p.value) && !p.long) continue; // "6379" alone publishes an ephemeral host port; still flagged below
          const dev = DEV_FILE.test(f);
          findings.push({ file: f, line: p.line, snippet: `${svc.name}: ${p.value.trim()}`, confidence: dev ? 'low' : 'high', message: `${svc.name} (${svc.image || 'sin imagen'}) publica un puerto en todas las interfaces${dev ? ' (archivo de desarrollo)' : ''}` });
        }
      }
    }
    return verdict(findings, files.length);
  },

  'image-unpinned': (ctx) => {
    const findings = [];
    const composeFiles = select(ctx, { files: COMPOSE, skipTests: false });
    for (const f of composeFiles) {
      const text = ctx.read(f) || '';
      for (const m of text.matchAll(/^\s*image\s*:\s*['"]?([^\s'"#]+)['"]?/gm)) {
        const img = m[1];
        if (img.includes('${')) continue;
        if (/@sha256:/.test(img)) continue;
        const tag = img.includes(':') && !img.endsWith(':') ? img.slice(img.lastIndexOf(':') + 1) : '';
        if (!tag || tag.includes('/') || tag === 'latest') findings.push(finding(f, text, m.index, 'medium', `Imagen sin versión fija: ${img}`));
      }
    }
    const dockerfiles = select(ctx, { files: DOCKERFILES, skipTests: false });
    for (const f of dockerfiles) {
      const text = ctx.read(f) || '';
      const stages = new Set();
      for (const m of text.matchAll(/^\s*FROM\s+(?:--platform=\S+\s+)?(\S+)(?:\s+AS\s+(\S+))?/gim)) {
        const img = m[1];
        if (m[2]) stages.add(m[2].toLowerCase());
        if (img === 'scratch' || img.includes('$') || stages.has(img.toLowerCase()) || /@sha256:/.test(img)) continue;
        const tag = img.includes(':') ? img.slice(img.lastIndexOf(':') + 1) : '';
        if (!tag || tag === 'latest') findings.push(finding(f, text, m.index, 'medium', `FROM sin versión fija: ${img}`));
      }
    }
    return verdict(findings, composeFiles.length + dockerfiles.length);
  },

  'dockerfile-root': (ctx) => {
    const files = select(ctx, { files: DOCKERFILES, skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      const froms = [...text.matchAll(/^\s*FROM\s+/gim)];
      if (!froms.length) continue;
      const last = froms[froms.length - 1].index;
      const users = [...text.slice(last).matchAll(/^\s*USER\s+(\S+)/gim)].map((m) => m[1]);
      const final = users[users.length - 1];
      if (!final || /^(root|0)(:|$)/.test(final)) {
        findings.push(finding(f, text, last, 'medium', final ? 'La etapa final vuelve a USER root' : 'La etapa final no declara USER (corre como root)'));
      }
    }
    return verdict(findings, files.length);
  },

  'compose-privileged': deny({ files: COMPOSE, skipTests: false }, [
    { re: /^\s*privileged\s*:\s*true/gm, confidence: 'high', message: 'privileged: true' },
    { re: /^\s*network_mode\s*:\s*['"]?host/gm, confidence: 'medium', message: 'network_mode: host' },
    { re: /\/var\/run\/docker\.sock/g, confidence: 'high', message: 'Socket de Docker montado en un contenedor' },
    { re: /^\s*pid\s*:\s*['"]?host/gm, confidence: 'medium', message: 'pid: host' },
    { re: /cap_add\s*:\s*(\n\s*-\s*\S+)*\n\s*-\s*['"]?(SYS_ADMIN|ALL)\b/g, confidence: 'medium', message: 'cap_add con SYS_ADMIN o ALL' },
  ]),

  'remote-script-pipe': deny({ files: [...DOCKERFILES, ...WORKFLOWS, '**/*.sh'], skipTests: false }, [
    { re: /\b(curl|wget)\b[^|\n]*\|\s*(sudo\s+)?(ba|z|da)?sh\b/g, confidence: 'medium', message: 'Script remoto ejecutado sin verificar integridad' },
    { re: /^\s*ADD\s+https?:\/\//gim, confidence: 'medium', message: 'ADD desde URL remota sin checksum' },
  ]),

  'proxy-tls-config': deny({ files: [...PROXY, '**/*.conf'], skipTests: false }, [
    { re: /ssl_protocols[^;\n]*\bTLSv1(?:\.1)?(?![.\d])/g, confidence: 'high', message: 'nginx habilita TLS 1.0/1.1' },
    { re: /\bprotocols\s+tls1\.[01]\b/g, confidence: 'high', message: 'Caddy habilita TLS 1.0/1.1' },
    { re: /SSLProtocol\s+[^\n]*\+?TLSv1(?:\.1)?(?![.\d])(?![^\n]*-TLSv1)/g, confidence: 'medium', message: 'Apache habilita TLS 1.0/1.1' },
  ]),

  'proxy-hardening': (ctx) => {
    const files = select(ctx, { files: PROXY, skipTests: false });
    const findings = scan(ctx, files, [
      { re: /\bautoindex\s+on\b/g, confidence: 'medium', message: 'Listado de directorios activo' },
      { re: /\bserver_tokens\s+on\b/g, confidence: 'low', message: 'nginx expone su versión' },
    ]);
    for (const f of files) {
      const text = ctx.read(f) || '';
      const servesTls = /listen\s+[^;]*443|ssl_certificate\b/.test(text) || (basename(f) === 'Caddyfile' && /^[^\s#][^{]*\.[a-z]{2,}[^{]*\{/m.test(text));
      if (servesTls && !/Strict-Transport-Security/i.test(text)) findings.push({ file: f, line: 1, snippet: basename(f), confidence: 'low', message: 'Sirve HTTPS sin cabecera HSTS en esta configuración (puede definirse en la app)' });
    }
    return verdict(findings, files.length);
  },

  // ------------------------------------------------------------------ terraform

  'tf-open-ingress': (ctx) => {
    const files = select(ctx, { files: ['**/*.tf'], skipTests: false });
    const admin = [22, 3389, 3306, 5432, 1433, 1521, 6379, 27017, 9200, 11211, 5984, 2375, 2376];
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      const re = /\b(ingress\s*\{|resource\s+"aws_(security_group_rule|vpc_security_group_ingress_rule)"\s+"[^"]+"\s*\{)/g;
      for (const m of text.matchAll(re)) {
        const body = blockFrom(text, m.index + m[0].length - 1);
        if (/type\s*=\s*"egress"/.test(body)) continue;
        if (!/0\.0\.0\.0\/0|::\/0/.test(body)) continue;
        const from = Number(/from_port\s*=\s*(\d+)/.exec(body)?.[1] ?? -1);
        const to = Number(/to_port\s*=\s*(\d+)/.exec(body)?.[1] ?? from);
        const all = /protocol\s*=\s*"-1"/.test(body) || (from === 0 && to >= 65535);
        const hit = all ? 'todos' : admin.filter((p) => p >= from && p <= to).join(', ');
        if (hit) findings.push(finding(f, text, m.index, 'high', `Ingreso desde 0.0.0.0/0 a puertos sensibles (${hit})`));
      }
    }
    return verdict(findings, files.length);
  },

  'tf-db-public': deny({ files: ['**/*.tf', 'template.yaml', 'serverless.yml'], skipTests: false }, [
    { re: /publicly_accessible\s*=\s*true|PubliclyAccessible\s*:\s*true/g, confidence: 'high', message: 'Base de datos accesible públicamente' },
  ]),

  'tf-unencrypted-storage': (ctx) => {
    const files = select(ctx, { files: ['**/*.tf'], skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const b of hclBlocks(text, 'aws_db_instance|aws_rds_cluster')) {
        if (!/storage_encrypted\s*=\s*true/.test(b.body)) findings.push(finding(f, text, b.index, 'medium', `${b.type}.${b.name} sin storage_encrypted = true`));
      }
      for (const b of hclBlocks(text, 'aws_ebs_volume')) {
        if (!/encrypted\s*=\s*true/.test(b.body)) findings.push(finding(f, text, b.index, 'medium', `${b.type}.${b.name} sin encrypted = true`));
      }
      for (const b of hclBlocks(text, 'aws_instance|aws_launch_template')) {
        const rbd = /root_block_device\s*\{[^}]*\}/.exec(b.body)?.[0];
        if (rbd && !/encrypted\s*=\s*true/.test(rbd)) findings.push(finding(f, text, b.index, 'low', `${b.type}.${b.name}: root_block_device sin encrypted = true (puede estar cifrado por defecto de cuenta)`));
      }
    }
    return verdict(findings, files.length);
  },

  'tf-imdsv1': (ctx) => {
    const files = select(ctx, { files: ['**/*.tf'], skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      for (const b of hclBlocks(text, 'aws_instance|aws_launch_template')) {
        if (!/http_tokens\s*=\s*"required"/.test(b.body)) findings.push(finding(f, text, b.index, 'medium', `${b.type}.${b.name} permite IMDSv1 (falta metadata_options { http_tokens = "required" })`));
      }
    }
    return verdict(findings, files.length);
  },

  // ------------------------------------------------------------------ n8n

  'n8n-webhook-no-auth': (ctx) => {
    const files = select(ctx, { files: ['**/*.json'], contains: /"n8n-nodes-base\./ });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      let wf; try { wf = JSON.parse(text); } catch { continue; }
      for (const n of wf.nodes || []) {
        if (!/n8n-nodes-base\.(webhook|formTrigger)$/.test(n.type || '')) continue;
        const auth = n.parameters?.authentication;
        if (!auth || auth === 'none') {
          const i = text.indexOf(`"name": ${JSON.stringify(n.name)}`);
          findings.push(finding(f, text, Math.max(0, i), 'high', `Nodo "${n.name}" (${n.type.split('.').pop()}) sin autenticación`));
        }
      }
    }
    return verdict(findings, files.length);
  },

  'insecure-cookie-env': deny({ files: [...COMPOSE, ...DOCKERFILES, '**/*.env', '**/.env.*', '**/*.yml', '**/*.yaml'], skipTests: false }, [
    { re: /\b(N8N_SECURE_COOKIE|SESSION_COOKIE_SECURE|COOKIE_SECURE|SECURE_COOKIES?)['"]?\s*[:=]\s*['"]?(false|0)\b/gi, confidence: 'medium', message: 'Cookies seguras desactivadas por configuración', skipLine: (l, f) => DEV_FILE.test(f) || EXAMPLE_FILE.test(f) },
  ]),

  // ------------------------------------------------------------------ GitHub Actions

  'gha-script-injection': (ctx) => {
    const files = select(ctx, { files: WORKFLOWS, skipTests: false });
    const tainted = /\$\{\{\s*(github\.event\.(issue|pull_request|comment|review|review_comment|discussion|discussion_comment|head_commit|commits|workflow_run|pages)\b[\w.[\]*-]*\.(title|body|message|name|label|ref|email|description|head_branch)|github\.head_ref)\s*\}\}/;
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      const lines = text.split('\n');
      let runIndent = -1;
      lines.forEach((line, i) => {
        const indent = line.search(/\S/);
        const run = /^(\s*)(-\s+)?run:\s*(.*)$/.exec(line);
        if (run) {
          runIndent = /^[|>][-+]?\s*$/.test(run[3]) ? indent : -1;
          if (tainted.test(run[3])) findings.push({ file: f, line: i + 1, snippet: line.trim().slice(0, 160), confidence: 'high', message: 'run: interpola datos controlados por terceros' });
          return;
        }
        if (runIndent >= 0) {
          if (line.trim() && indent <= runIndent) runIndent = -1;
          else if (tainted.test(line)) findings.push({ file: f, line: i + 1, snippet: line.trim().slice(0, 160), confidence: 'high', message: 'run: interpola datos controlados por terceros (usa una variable env:)' });
        }
      });
    }
    return verdict(findings, files.length);
  },

  'gha-pull-request-target': (ctx) => {
    const files = select(ctx, { files: WORKFLOWS, skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      const i = text.search(/\bpull_request_target\b/);
      if (i < 0) continue;
      const checkoutHead = /ref:\s*\$\{\{\s*github\.event\.pull_request\.head\.(sha|ref)|ref:\s*refs\/pull\//.test(text);
      findings.push(finding(f, text, i, checkoutHead ? 'high' : 'low', checkoutHead ? 'pull_request_target con checkout del código del PR' : 'pull_request_target: confirma que no ejecuta código del PR'));
    }
    return verdict(findings, files.length);
  },

  'gha-permissions': (ctx) => {
    const files = select(ctx, { files: WORKFLOWS, skipTests: false });
    const findings = [];
    for (const f of files) {
      const text = ctx.read(f) || '';
      const wa = text.search(/^\s*permissions\s*:\s*write-all/m);
      if (wa >= 0) findings.push(finding(f, text, wa, 'high', 'permissions: write-all'));
      else if (!/^\s*permissions\s*:/m.test(text)) findings.push({ file: f, line: 1, snippet: basename(f), confidence: 'medium', message: 'Sin bloque permissions: (GITHUB_TOKEN con permisos por defecto)' });
    }
    return verdict(findings, files.length);
  },

  'gha-unpinned-actions': deny({ files: WORKFLOWS, skipTests: false }, [
    { re: /uses:\s*['"]?(?!\.\/|docker:\/\/|actions\/|github\/)([\w.-]+\/[\w./-]+)@(?![0-9a-f]{40}\b)[\w.-]+/g, confidence: 'medium', message: 'Acción de terceros fijada por etiqueta, no por SHA' },
  ], { maxPerFile: 20 }),

  'gha-static-cloud-keys': deny({ files: WORKFLOWS, skipTests: false }, [
    { re: /aws-access-key-id\s*:\s*\$\{\{\s*secrets\.|AWS_SECRET_ACCESS_KEY\s*:\s*\$\{\{\s*secrets\./g, confidence: 'medium', message: 'Llaves de AWS de larga duración (preferir OIDC: role-to-assume)' },
    { re: /credentials_json\s*:\s*\$\{\{\s*secrets\.|FIREBASE_TOKEN\s*:\s*\$\{\{\s*secrets\./g, confidence: 'medium', message: 'Credencial estática de Google/Firebase (preferir Workload Identity Federation)' },
  ]),
};

// ---------------------------------------------------------------- helpers

function blockFrom(text, open) {
  let depth = 0;
  for (let i = open; i < text.length; i++) {
    if (text[i] === '{') depth++;
    else if (text[i] === '}' && --depth === 0) return text.slice(open, i + 1);
  }
  return text.slice(open);
}

/**
 * Minimal docker-compose reader: enough to know each service's image and published
 * ports with their line numbers. Not a YAML parser; tolerant of both port syntaxes.
 */
export function composeServices(text) {
  const lines = text.split('\n');
  const services = [];
  let inServices = false, svcIndent = -1, cur = null, portsIndent = -1, longPort = null;
  lines.forEach((raw, i) => {
    const line = raw.replace(/\s+#.*$/, '');
    if (!line.trim() || line.trim().startsWith('#')) return;
    const indent = line.search(/\S/);
    if (/^services\s*:/.test(line)) { inServices = true; svcIndent = -1; return; }
    if (!inServices) return;
    if (indent === 0) { inServices = false; cur = null; return; }
    if (svcIndent === -1) svcIndent = indent;
    if (indent === svcIndent && /^\s*[\w.-]+\s*:\s*$/.test(line)) {
      cur = { name: line.trim().slice(0, -1).trim(), image: '', ports: [] };
      services.push(cur); portsIndent = -1; return;
    }
    if (!cur) return;
    const kv = /^\s*(image|ports)\s*:\s*(.*)$/.exec(line);
    if (kv && indent > svcIndent) {
      if (kv[1] === 'image') cur.image = kv[2].replace(/['"]/g, '').trim();
      else {
        portsIndent = indent;
        const inline = /^\[(.*)\]$/.exec(kv[2].trim());
        if (inline) inline[1].split(',').forEach((v) => cur.ports.push({ value: v.replace(/['"]/g, ''), line: i + 1 }));
      }
      return;
    }
    if (portsIndent >= 0) {
      if (indent <= portsIndent && !/^\s*-/.test(line)) { portsIndent = -1; return; }
      const item = /^\s*-\s*(.*)$/.exec(line);
      if (item && !/:\s/.test(item[1]) && !/^\w+\s*:/.test(item[1])) { cur.ports.push({ value: item[1].replace(/['"]/g, ''), line: i + 1 }); return; }
      const pub = /^\s*-?\s*(published|host_ip)\s*:\s*['"]?([^'"]+)['"]?/.exec(line);
      if (pub) {
        if (pub[1] === 'published') { longPort = { value: `${pub[2]}:?`, line: i + 1, long: true }; cur.ports.push(longPort); }
        else if (longPort) longPort.hostIp = pub[2];
      }
    }
  });
  return services;
}
