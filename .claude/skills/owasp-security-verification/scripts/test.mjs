#!/usr/bin/env node
/**
 * owasp-ignore-file: este archivo contiene patrones y fixtures vulnerables a propósito.
 *
 * Regression tests. Run with: node scripts/test.mjs
 *
 * Every static rule has a vulnerable fixture that must fail and a safe fixture that must
 * pass. Fixtures are written to a temp dir at run time and secret-shaped strings are
 * assembled from parts, so the repository never contains anything a secret scanner
 * would (rightly) flag.
 */

import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { RULES, composeServices } from './rules/index.mjs';
import { buildContext, redact, runRules } from './run-rules.mjs';
import { validateCatalog } from './validate-catalog.mjs';
import { validateEvidence } from './findings.mjs';
import { selectChecks } from './select-checks.mjs';
import { detect } from './detect-stack.mjs';
import { catalog, parseArgs } from './lib/catalog.mjs';
import { globToRegExp } from './lib/files.mjs';
import { finalize, sarif } from './report.mjs';
import { PROBES, makeClient, runLive } from './live.mjs';
import { createServer } from 'node:http';

let passed = 0, failed = 0;
const temps = [];
async function test(name, fn) {
  try { await fn(); console.log(`  ok   ${name}`); passed++; }
  catch (e) { console.log(`  FAIL ${name}\n       ${e.message.split('\n').join('\n       ')}`); failed++; }
}
function group(name) { console.log(`\n${name}`); }

function project(files, { git = false } = {}) {
  const root = mkdtempSync(join(tmpdir(), 'osv-test-'));
  temps.push(root);
  if (git) mkdirSync(join(root, '.git'));
  for (const [p, content] of Object.entries(files)) {
    mkdirSync(dirname(join(root, p)), { recursive: true });
    writeFileSync(join(root, p), content);
  }
  return root;
}

// Secret-shaped values, assembled so no literal secret lives in this file.
const AWS_KEY = 'AKIA' + 'Q7XK2M9PLR4T8WZN';
const GH_TOKEN = 'ghp' + '_' + 'a1B2c3D4e5F6g7H8i9J0k1L2m3N4o5P6q7R8';
const TG_TOKEN = '7012345678' + ':AA' + 'Hk3lMnOpQrStUvWxYz0123456789abcde';
const PRIV = '-----BEGIN ' + 'RSA PRIVATE KEY-----';

/** rule -> { bad, good, expect? } ; expect defaults to 'fail' for bad and 'pass' for good. */
const FIXTURES = {
  'secrets-hardcoded': {
    bad: { 'src/config.js': `export const key = "${AWS_KEY}";\nconst gh = '${GH_TOKEN}';\n` },
    good: { 'src/config.js': 'export const key = process.env.AWS_ACCESS_KEY_ID;\nconst db = "postgres://user:pass@db:5432/app";\n', 'src/environments/environment.ts': `export const env = { apiKey: "AIza${'x'.repeat(35)}" };\n` },
  },
  'secret-files-tracked': {
    bad: { '.env': 'A=1\n', 'keys/server.pem': PRIV },
    good: { '.env.example': 'A=\n', '.env.prod.example': 'B=\n', 'keys/public.pem': 'x' },
  },
  'gitignore-secrets': {
    bad: { '.gitignore': 'node_modules/\n' }, badOpts: { git: true },
    good: { '.gitignore': '.env\n*.pem\n' }, goodOpts: { git: true },
  },
  'lockfile-present': {
    bad: { 'package.json': '{"dependencies":{"express":"^4"}}', 'requirements.txt': 'flask\n' },
    good: { 'package.json': '{"dependencies":{"express":"^4"}}', 'package-lock.json': '{}', 'requirements.txt': 'flask==3.0.0\n' },
  },
  'dynamic-code-exec': {
    bad: { 'a.js': 'const r = eval(userInput);\n' },
    good: { 'a.js': 'const m = /x/.exec(s);\nawait page.eval("1");\n// eval(x) en un comentario\n' },
  },
  'sql-concat': {
    bad: { 'a.js': 'db.query(`SELECT * FROM u WHERE id = ${req.params.id}`);\n', 'b.py': 'cur.execute(f"SELECT * FROM t WHERE id={uid}")\n', 'c.php': '<?php $DB->get_records_sql("SELECT * FROM {user} WHERE id = $id");\n' },
    good: { 'a.js': "db.query('SELECT * FROM u WHERE id = $1', [id]);\n", 'b.py': 'cur.execute("SELECT * FROM t WHERE id=%s", (uid,))\n', 'c.php': '<?php $DB->get_records_sql("SELECT * FROM {user} WHERE id = ?", [$id]);\n' },
  },
  'os-command-injection': {
    bad: { 'a.js': "const { exec } = require('child_process');\nexec(`convert ${file}`);\n", 'b.py': 'subprocess.run(cmd, shell=True)\n' },
    good: { 'a.js': "execFile('convert', [file]);\nconst m = re.exec(s);\n", 'b.py': 'subprocess.run(["convert", f])\n' },
  },
  'weak-hash': {
    bad: { 'a.js': "const h = createHash('md5').update(password).digest('hex');\n" },
    good: { 'a.js': "const h = createHash('sha256').update(data).digest('hex');\n" },
  },
  'insecure-random': {
    bad: { 'a.js': 'const token = Math.random().toString(36);\n' },
    good: { 'a.js': 'const x = Math.random() * width;\nconst token = crypto.randomUUID();\n' },
  },
  'tls-verify-disabled': {
    bad: { 'a.js': 'https.request({ rejectUnauthorized: false });\n', 'b.py': 'requests.get(u, verify=False, timeout=5)\n' },
    good: { 'a.js': 'https.request({ ca });\n', 'b.py': 'requests.get(u, timeout=5)\n' },
  },
  'debug-enabled': {
    bad: { 'app.py': 'app.run(host="0.0.0.0", debug=True)\n', 'bootstrap.php': "<?php ini_set('display_errors', '1');\n" },
    good: { 'app.py': 'app.run(host="0.0.0.0")\n', 'docker-compose.dev.yml': 'services:\n  a:\n    environment:\n      - FLASK_ENV=development\n' },
  },
  'cors-wildcard': {
    bad: { 'server.js': "app.use(cors());\n", 'api.php': "<?php header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);\n", 'serverless.yml': 'provider:\n  httpApi:\n    cors: true\n' },
    good: { 'server.js': "app.use(cors({ origin: ['https://app.example.com'] }));\n", 'api.php': '<?php Utils::cors();\n' },
  },
  'unsafe-deserialization': {
    bad: { 'a.py': 'data = pickle.loads(request.data)\ncfg = yaml.load(f)\n' },
    good: { 'a.py': 'cfg = yaml.safe_load(f)\ncfg2 = yaml.load(f, Loader=yaml.SafeLoader)\n' },
  },
  'xml-external-entities': {
    bad: { 'a.php': '<?php $d->loadXML($x, LIBXML_NOENT);\n' },
    good: { 'a.php': '<?php $d->loadXML($x);\n' },
  },
  'outbound-no-timeout': {
    bad: { 'a.py': 'r = requests.get(url)\n' }, expect: 'review',
    good: { 'a.py': 'r = requests.get(url, timeout=10)\n' },
  },
  'angular-bypass-sanitizer': {
    bad: { 'src/app/p.ts': 'return this.s.bypassSecurityTrustHtml(v);\n' },
    good: { 'src/app/p.ts': 'return this.s.sanitize(SecurityContext.HTML, v);\n' },
  },
  'dom-html-sinks': {
    bad: { 'src/app/c.ts': 'this.el.nativeElement.innerHTML = html;\n' },
    good: { 'src/app/c.ts': 'this.el.nativeElement.textContent = text;\nif (a.innerHTML === b) {}\n' },
  },
  'angular-innerhtml-binding': {
    bad: { 'src/app/c.html': '<div [innerHTML]="body"></div>\n' }, expect: 'review',
    good: { 'src/app/c.html': '<div>{{ body }}</div>\n' },
  },
  'tokens-in-web-storage': {
    bad: { 'src/app/auth.service.ts': "localStorage.setItem('access_token', t);\n" },
    good: { 'src/app/theme.service.ts': "localStorage.setItem('theme', 'dark');\n" },
  },
  'csp-declared': {
    bad: { 'src/index.html': '<html><head></head></html>\n' }, expect: 'not-verified',
    good: { 'src/index.html': '<meta http-equiv="Content-Security-Policy" content="default-src \'self\'">\n' },
  },
  'prod-source-maps': {
    bad: { 'angular.json': JSON.stringify({ projects: { app: { architect: { build: { configurations: { production: { sourceMap: true } } } } } } }) },
    good: { 'angular.json': JSON.stringify({ projects: { app: { architect: { build: { configurations: { production: { sourceMap: false } } } } } } }) },
  },
  'frontend-privileged-keys': {
    bad: { 'angular.json': '{}', 'src/environments/environment.ts': 'export const e = { supabaseKey: SUPABASE_SERVICE_ROLE };\n' },
    good: { 'angular.json': '{}', 'src/environments/environment.ts': "export const e = { supabaseAnonKey: 'public-anon' };\n", 'server/handler.ts': 'const k = SUPABASE_SERVICE_ROLE;\n' },
  },
  'express-helmet': {
    bad: { 'package.json': '{"dependencies":{"express":"4"}}', 'server.js': 'const app = express();\n' },
    good: { 'package.json': '{"dependencies":{"express":"4","helmet":"7"}}', 'server.js': 'const app = express();\napp.use(helmet());\n' },
  },
  'jwt-insecure-verify': {
    bad: { 'a.js': 'const p = jwt.verify(token, secret);\n' },
    good: { 'a.js': "const p = jwt.verify(token, key, { algorithms: ['RS256'], audience: 'api' });\n" },
  },
  'iam-wildcard': {
    bad: { 'serverless.yml': "provider:\n  iam:\n    role:\n      statements:\n        - Effect: Allow\n          Action: '*'\n" },
    good: { 'serverless.yml': "provider:\n  iam:\n    role:\n      statements:\n        - Effect: Allow\n          Action:\n            - dynamodb:GetItem\n" },
  },
  'iac-plain-secrets': {
    bad: { 'serverless.yml': 'provider:\n  environment:\n    BOLD_API_KEY: sk9f8a7d6s5a4\n' },
    good: { 'serverless.yml': 'provider:\n  environment:\n    BOLD_API_KEY: ${ssm:/agora/bold}\n    TOKEN_TTL: 3600\n', 'ec2.tf': 'metadata_options {\n  http_tokens = "required"\n}\n' },
  },
  's3-public': {
    bad: { 'main.tf': 'resource "aws_s3_bucket_acl" "a" {\n  acl = "public-read"\n}\n' },
    good: { 'main.tf': 'resource "aws_s3_bucket_acl" "a" {\n  acl = "private"\n}\n' },
  },
  'lambda-url-no-auth': {
    bad: { 'serverless.yml': 'functions:\n  ssr:\n    handler: h.h\n    url: true\n' }, expect: 'review',
    good: { 'serverless.yml': 'functions:\n  ssr:\n    handler: h.h\n    url:\n      authorizer: aws_iam\n' },
  },
  'firebase-rules-open': {
    bad: { 'firestore.rules': 'service cloud.firestore {\n  match /databases/{db}/documents {\n    match /{d=**} { allow read, write: if true; }\n  }\n}\n' },
    good: { 'firestore.rules': "service cloud.firestore {\n  match /databases/{db}/documents {\n    match /products/{p} { allow write: if request.auth.token.email == 'a@b.co'; }\n  }\n}\n" },
  },
  'firebase-rules-auth-only': {
    bad: { 'firestore.rules': 'match /orders/{o} { allow write: if request.auth != null; }\n' },
    good: { 'firestore.rules': 'match /orders/{o} { allow write: if request.auth.uid == resource.data.owner; }\n' },
  },
  'supabase-rls': {
    bad: { 'supabase/schema.sql': 'create table public.posts (id int);\n' },
    good: { 'supabase/schema.sql': 'create table public.posts (id int);\nalter table public.posts enable row level security;\ncreate table private.jobs (id int);\n' },
  },
  'supabase-permissive-policy': {
    bad: { 'supabase/p.sql': 'create policy "p" on posts for select to anon using (true);\n' },
    good: { 'supabase/p.sql': 'create policy "p" on posts for select to authenticated using (auth.uid() = owner);\n' },
  },
  'php-reflected-output': {
    bad: { 'a.php': '<?php echo $_GET["q"];\n' },
    good: { 'a.php': '<?php echo htmlspecialchars($_GET["q"], ENT_QUOTES);\n' },
  },
  'php-dynamic-include': {
    bad: { 'a.php': '<?php include($_GET["page"] . ".php");\n' },
    good: { 'a.php': "<?php require_once($CFG->dirroot . '/lib.php');\nrequire_once __DIR__ . '/x.php';\n" },
  },
  'php-upload-original-name': {
    bad: { 'a.php': "<?php move_uploaded_file($_FILES['f']['tmp_name'], 'up/' . $_FILES['f']['name']);\n" },
    good: { 'a.php': "<?php move_uploaded_file($_FILES['f']['tmp_name'], 'up/' . bin2hex(random_bytes(16)));\n" },
  },
  'flask-upload-limits': {
    bad: { 'requirements.txt': 'flask==3\n', 'app.py': "f = request.files['file']\n" },
    good: { 'requirements.txt': 'flask==3\n', 'app.py': "app.config['MAX_CONTENT_LENGTH'] = 50 * 1024 * 1024\nf = request.files['file']\n" },
  },
  'python-unsafe-filename': {
    bad: { 'app.py': 'f.save(os.path.join(UPLOADS, f.filename))\n' },
    good: { 'app.py': 'f.save(os.path.join(UPLOADS, secure_filename(f.filename)))\n' },
  },
  'session-cookie-flags': {
    bad: { 'app.ts': 'app.use(session({ cookie: { httpOnly: false } }));\n' },
    good: { 'app.ts': "app.use(session({ cookie: { httpOnly: true, secure: true, sameSite: 'lax' } }));\n" },
  },
  'ldap-filter-concat': {
    bad: { 'ldap.ts': 'const filter = `(&(objectClass=user)(sAMAccountName=${username}))`;\n' },
    good: { 'ldap.ts': 'const filter = new EqualityFilter({ attribute: "sAMAccountName", value: username });\n' },
  },
  'login-rate-limit': {
    bad: { 'routes.ts': "router.post('/auth/login', login);\n" },
    good: { 'routes.ts': "import rateLimit from 'express-rate-limit';\nrouter.post('/auth/login', rateLimit({ max: 5 }), login);\n" },
  },
  'compose-internal-ports': {
    bad: { 'docker-compose.prod.yml': 'services:\n  redis:\n    image: redis:7-alpine\n    ports:\n      - "6379:6379"\n' },
    good: { 'docker-compose.prod.yml': 'services:\n  redis:\n    image: redis:7-alpine\n    ports:\n      - "127.0.0.1:6379:6379"\n  web:\n    image: nginx:1.27\n    ports:\n      - "443:443"\n' },
  },
  'image-unpinned': {
    bad: { 'docker-compose.yml': 'services:\n  a:\n    image: ollama/ollama:latest\n', 'Dockerfile': 'FROM node\n' },
    good: { 'docker-compose.yml': 'services:\n  a:\n    image: ollama/ollama:0.12.3\n', 'Dockerfile': 'FROM node:24-alpine AS build\nFROM build\n' },
  },
  'dockerfile-root': {
    bad: { 'Dockerfile': 'FROM node:24-alpine\nCMD ["node","s.js"]\n' },
    good: { 'Dockerfile': 'FROM node:24-alpine\nUSER node\nCMD ["node","s.js"]\n' },
  },
  'compose-privileged': {
    bad: { 'docker-compose.yml': 'services:\n  a:\n    image: x:1\n    volumes:\n      - /var/run/docker.sock:/var/run/docker.sock\n' },
    good: { 'docker-compose.yml': 'services:\n  a:\n    image: x:1\n    read_only: true\n' },
  },
  'remote-script-pipe': {
    bad: { 'Dockerfile': 'FROM debian:12\nRUN curl -fsSL https://get.example.sh | bash\n' },
    good: { 'Dockerfile': 'FROM debian:12\nRUN curl -fsSLo i.sh https://get.example.sh && sha256sum -c i.sha && sh i.sh\n' },
  },
  'proxy-tls-config': {
    bad: { 'nginx.conf': 'ssl_protocols TLSv1 TLSv1.1 TLSv1.2;\n' },
    good: { 'nginx.conf': 'ssl_protocols TLSv1.2 TLSv1.3;\n' },
  },
  'proxy-hardening': {
    bad: { 'nginx.conf': 'server { listen 80; autoindex on; }\n' },
    good: { 'nginx.conf': 'server { listen 443 ssl; ssl_certificate c.pem; add_header Strict-Transport-Security "max-age=31536000"; }\n' },
  },
  'tf-open-ingress': {
    bad: { 'sg.tf': 'resource "aws_security_group" "s" {\n  ingress {\n    from_port = 22\n    to_port = 22\n    protocol = "tcp"\n    cidr_blocks = ["0.0.0.0/0"]\n  }\n}\n' },
    good: { 'sg.tf': 'resource "aws_security_group" "s" {\n  ingress {\n    from_port = 443\n    to_port = 443\n    protocol = "tcp"\n    cidr_blocks = ["0.0.0.0/0"]\n  }\n}\n' },
  },
  'tf-db-public': {
    bad: { 'rds.tf': 'resource "aws_db_instance" "d" {\n  publicly_accessible = true\n}\n' },
    good: { 'rds.tf': 'resource "aws_db_instance" "d" {\n  publicly_accessible = false\n}\n' },
  },
  'tf-unencrypted-storage': {
    bad: { 'rds.tf': 'resource "aws_db_instance" "d" {\n  engine = "mariadb"\n}\n' },
    good: { 'rds.tf': 'resource "aws_db_instance" "d" {\n  engine = "mariadb"\n  storage_encrypted = true\n}\n' },
  },
  'tf-imdsv1': {
    bad: { 'ec2.tf': 'resource "aws_instance" "w" {\n  ami = "x"\n}\n' },
    good: { 'ec2.tf': 'resource "aws_instance" "w" {\n  ami = "x"\n  metadata_options {\n    http_tokens = "required"\n  }\n}\n' },
  },
  'n8n-webhook-no-auth': {
    bad: { 'wf.json': JSON.stringify({ nodes: [{ name: 'Hook', type: 'n8n-nodes-base.webhook', parameters: { path: 'x' } }] }, null, 2) },
    good: { 'wf.json': JSON.stringify({ nodes: [{ name: 'Hook', type: 'n8n-nodes-base.webhook', parameters: { path: 'x', authentication: 'headerAuth' } }] }, null, 2) },
  },
  'insecure-cookie-env': {
    bad: { 'docker-compose.yml': 'services:\n  n8n:\n    environment:\n      - N8N_SECURE_COOKIE=false\n' },
    good: { 'docker-compose.yml': 'services:\n  n8n:\n    environment:\n      - N8N_SECURE_COOKIE=true\n' },
  },
  'gha-script-injection': {
    bad: { '.github/workflows/ci.yml': 'on: issues\njobs:\n  a:\n    runs-on: ubuntu-latest\n    steps:\n      - run: |\n          echo "${{ github.event.issue.title }}"\n' },
    good: { '.github/workflows/ci.yml': 'on: issues\njobs:\n  a:\n    runs-on: ubuntu-latest\n    steps:\n      - env:\n          TITLE: ${{ github.event.issue.title }}\n        run: echo "$TITLE"\n' },
  },
  'gha-pull-request-target': {
    bad: { '.github/workflows/ci.yml': 'on: pull_request_target\njobs:\n  a:\n    steps:\n      - uses: actions/checkout@v4\n        with:\n          ref: ${{ github.event.pull_request.head.sha }}\n' },
    good: { '.github/workflows/ci.yml': 'on: pull_request\njobs:\n  a:\n    steps:\n      - uses: actions/checkout@v4\n' },
  },
  'gha-permissions': {
    bad: { '.github/workflows/ci.yml': 'on: push\njobs:\n  a:\n    runs-on: ubuntu-latest\n' },
    good: { '.github/workflows/ci.yml': 'on: push\npermissions:\n  contents: read\njobs:\n  a:\n    runs-on: ubuntu-latest\n' },
  },
  'gha-unpinned-actions': {
    bad: { '.github/workflows/ci.yml': 'steps:\n  - uses: aws-actions/configure-aws-credentials@v4\n' },
    good: { '.github/workflows/ci.yml': `steps:\n  - uses: actions/checkout@v4\n  - uses: aws-actions/configure-aws-credentials@${'a'.repeat(40)}\n` },
  },
  'gha-static-cloud-keys': {
    bad: { '.github/workflows/d.yml': 'with:\n  aws-access-key-id: ${{ secrets.AWS_KEY }}\n' },
    good: { '.github/workflows/d.yml': 'with:\n  role-to-assume: arn:aws:iam::123:role/deploy\n' },
  },
};

// ---------------------------------------------------------------------------

group('Catálogo contra los estándares fijados');

await test('checks.json es válido contra ASVS 5.0.0, Top 10:2025 y cheat sheets', () => {
  const { errors } = validateCatalog();
  assert.deepEqual(errors, []);
});

await test('toda regla del catálogo tiene fixture vulnerable y seguro', () => {
  const used = new Set(catalog().checks.filter((c) => c.type === 'auto' && !c.rule.startsWith('tool:')).map((c) => c.rule));
  const missing = [...used].filter((r) => !FIXTURES[r]);
  assert.deepEqual(missing, [], `sin fixture: ${missing.join(', ')}`);
  const orphan = Object.keys(RULES).filter((r) => !used.has(r));
  assert.deepEqual(orphan, [], `reglas sin chequeo: ${orphan.join(', ')}`);
});

await test('el índice ASVS tiene los 345 requisitos del CSV oficial (70 L1, 183 L2, 92 L3)', () => {
  const reqs = Object.values(catalog().asvs.requirements);
  assert.equal(reqs.length, 345);
  assert.deepEqual([1, 2, 3].map((l) => reqs.filter((r) => r.level === l).length), [70, 183, 92]);
});

group('Reglas: fixture vulnerable falla, fixture seguro pasa');

for (const [rule, fx] of Object.entries(FIXTURES)) {
  await test(rule, async () => {
    const bad = await RULES[rule](buildContext(project(fx.bad, fx.badOpts)));
    assert.equal(bad.status, fx.expect || 'fail', `vulnerable: ${bad.status} ${JSON.stringify(bad.findings.map((f) => f.message))}`);
    const good = await RULES[rule](buildContext(project(fx.good, fx.goodOpts)));
    assert.equal(good.status, 'pass', `seguro: ${good.status} ${JSON.stringify(good.findings.map((f) => `${f.file}:${f.line} ${f.message}`))}`);
  });
}

group('Calibración contra el corpus (falsos positivos ya vistos)');

await test('un secreto con forma real dentro de un test queda para revisión, no como falla', async () => {
  const r = await RULES['secrets-hardcoded'](buildContext(project({ 'server/x.spec.ts': `const k = '${PRIV}\\nfake';\n` })));
  assert.equal(r.status, 'review');
});

await test('SQL de Moodle con fragmentos de get_in_or_equal queda para revisión', async () => {
  const r = await RULES['sql-concat'](buildContext(project({ 'lib.php': '<?php [$in, $p] = $DB->get_in_or_equal($ids);\n$DB->get_records_sql("SELECT * FROM {course} WHERE id $in", $p);\n' })));
  assert.equal(r.status, 'review');
});

group('Supresiones, excepciones y redacción');

await test('owasp-ignore en la línea anterior suprime el hallazgo y se cuenta', async () => {
  const ctx = buildContext(project({ 'a.js': '// owasp-ignore BASE-08: sandbox aislado\nconst r = eval(code);\n' }));
  const r = await RULES['dynamic-code-exec'](ctx);
  assert.equal(r.status, 'pass');
  assert.equal(ctx.suppressed, 1);
});

await test('una excepción aceptada vigente convierte la falla en pass y queda registrada', async () => {
  const root = project({ 'Dockerfile': 'FROM node\n' });
  const check = catalog().checks.find((c) => c.id === 'CTR-02');
  const profile = { accepted: [{ check: 'CTR-02', paths: ['Dockerfile'], reason: 'imagen interna', expires: '2999-01-01' }] };
  const res = await runRules(buildContext(root), [check], { profile });
  assert.equal(res['CTR-02'].status, 'pass');
  assert.equal(res['CTR-02'].findings[0].accepted.reason, 'imagen interna');
});

await test('una excepción vencida no silencia nada', async () => {
  const check = catalog().checks.find((c) => c.id === 'CTR-02');
  const profile = { accepted: [{ check: 'CTR-02', reason: 'x', expires: '2000-01-01' }] };
  const res = await runRules(buildContext(project({ 'Dockerfile': 'FROM node\n' })), [check], { profile });
  assert.equal(res['CTR-02'].status, 'fail');
});

await test('los secretos se redactan en el snippet (prefijo + hash), nunca completos', async () => {
  const check = catalog().checks.find((c) => c.id === 'BASE-01');
  const res = await runRules(buildContext(project({ 'a.js': `const k = "${AWS_KEY}";\nconst t = "${TG_TOKEN}";\n` })), [check], {});
  const text = JSON.stringify(res);
  assert.ok(!text.includes(AWS_KEY) && !text.includes(TG_TOKEN), 'el secreto aparece completo');
  assert.match(res['BASE-01'].findings[0].snippet, /AKIA…\[[0-9a-f]{8}\]/);
  assert.equal(redact('DATABASE_URL=postgresql://db'), 'DATABASE_URL=postgresql://db');
});

group('Evidencia del agente (compuerta anti-invención)');

await test('acepta archivo:línea existente y rechaza archivo inexistente, línea fuera de rango y rutas externas', () => {
  const root = project({ 'src/a.php': '<?php\nrequire_login();\n' });
  const ok = validateEvidence(root, ['src/a.php:2']);
  assert.equal(ok.errors.length, 0);
  assert.equal(ok.ok[0].snippet, 'require_login();');
  const bad = validateEvidence(root, ['src/b.php:1', 'src/a.php:3', '../x:1', '/etc/passwd:1', 'sin-linea']);
  assert.equal(bad.ok.length, 0);
  assert.equal(bad.errors.length, 5);
});

await test('URLs solo si están en el allowlist; AWS solo con formato de operación', () => {
  const r = validateEvidence('.', ['https://a.example.com/x', 'https://evil.example.com', 'aws:s3control:GetPublicAccessBlock', 'aws:s3:listar'], { liveUrls: ['https://a.example.com'] });
  assert.equal(r.ok.length, 2);
  assert.equal(r.errors.length, 2);
});

group('Detección y selección');

await test('detecta facetas por archivos, dependencias y contenido, y sube a L2 con señales', () => {
  const root = project({
    'angular.json': '{}',
    'package.json': '{"dependencies":{"@angular/core":"21","firebase":"11"}}',
    'functions/index.js': 'export const handler = async () => {};\n',
    'docker-compose.yml': 'services:\n  n8n:\n    image: n8nio/n8n:1.80.0\n',
    'PRD.md': 'Gestiona datos personales de compradores (Ley 1581).\n',
  });
  const d = detect(root);
  for (const f of ['baseline', 'angular-spa', 'firebase', 'node-backend', 'containers', 'automation-lowcode']) assert.ok(d.facets[f], `falta ${f}`);
  assert.ok(!d.facets['php-app']);
  assert.equal(d.level.suggested, 2);
  assert.ok(d.level.signals.pii.length);
});

await test('selección: L1 excluye L2 salvo always; when exige la señal; live solo con --live', () => {
  const base = { facets: ['baseline', 'angular-spa'], level: 1, signals: {} };
  const ids = selectChecks(base).map((c) => c.id);
  assert.ok(ids.includes('BASE-01') && ids.includes('NG-07'), 'always debe incluirse en L1');
  assert.ok(!ids.includes('NG-04'), 'NG-04 es L2');
  assert.ok(!ids.includes('BASE-29'), 'BASE-29 requiere señal payments');
  assert.ok(!ids.some((i) => i.startsWith('LIVE-')));
  const l2 = selectChecks({ ...base, level: 2, signals: { payments: true } }, { live: true }).map((c) => c.id);
  assert.ok(l2.includes('NG-04') && l2.includes('BASE-29') && l2.includes('LIVE-01'));
});

group('Utilidades');

await test('composeServices lee imagen y puertos en sintaxis corta, larga e inline', () => {
  const s = composeServices('services:\n  db:\n    image: postgres:16\n    ports:\n      - "5433:5432"\n  r:\n    image: redis:7\n    ports: ["6379:6379"]\n  o:\n    image: ollama/ollama:0.12\n    ports:\n      - target: 11434\n        published: 11434\n        host_ip: 127.0.0.1\nvolumes:\n  x:\n');
  assert.deepEqual(s.map((x) => x.name), ['db', 'r', 'o']);
  assert.equal(s[0].ports[0].value, '5433:5432');
  assert.equal(s[1].ports[0].value, '6379:6379');
  assert.equal(s[2].ports[0].hostIp, '127.0.0.1');
});

await test('globToRegExp: ** cruza directorios, * no', () => {
  assert.ok(globToRegExp('**/*.tf').test('infra/prod/main.tf'));
  assert.ok(globToRegExp('**/*.tf').test('main.tf'));
  assert.ok(!globToRegExp('src/*.ts').test('src/app/a.ts'));
  assert.ok(globToRegExp('n8n-workflows/**').test('n8n-workflows/01-ingesta.json'));
  assert.ok(globToRegExp('**/api/**').test('admin/api/handlers/x.php'));
});

await test('parseArgs acumula flags repetidos y respeta = dentro del valor', () => {
  const a = parseArgs(['--evidence', 'a:1', '--evidence', 'b:2', '--note=x=y', '--json']);
  assert.deepEqual(a.evidence, ['a:1', 'b:2']);
  assert.equal(a.note, 'x=y');
  assert.equal(a.json, true);
});

await test('reporte: un veredicto del agente reemplaza el estado y la evidencia; SARIF lleva ASVS y Top 10', () => {
  const doc = {
    checks: ['BASE-19', 'BASE-08'],
    results: { 'BASE-19': { status: 'pending', findings: [], source: 'agent' }, 'BASE-08': { status: 'pass', findings: [], source: 'rule' } },
    verdicts: { 'BASE-19': { status: 'fail', evidence: [{ file: 'api/x.php', line: 3, snippet: 'echo 1;' }], note: 'Sin verificación de rol' } },
  };
  const fin = finalize(doc, null);
  assert.equal(fin['BASE-19'].status, 'fail');
  assert.equal(fin['BASE-19'].findings[0].file, 'api/x.php');
  const s = sarif(doc, fin);
  assert.equal(s.runs[0].results[0].ruleId, 'BASE-19');
  assert.ok(s.runs[0].tool.driver.rules[0].properties.tags.includes('OWASP-A01:2025'));
  assert.ok(s.runs[0].tool.driver.rules[0].properties.tags.includes('v5.0.0-8.3.1'));
});

group('Modo live contra un servidor local (sin red externa)');

function serve(handler) {
  return new Promise((ok) => { const s = createServer(handler); s.listen(0, '127.0.0.1', () => ok(s)); });
}
const weak = await serve((req, res) => {
  if (req.url === '/.git/HEAD') { res.end('ref: refs/heads/main\n'); return; }
  if (req.headers.origin) res.setHeader('access-control-allow-origin', req.headers.origin);
  res.setHeader('content-type', 'text/html');
  res.setHeader('set-cookie', 'sid=abc; Path=/');
  res.setHeader('allow', 'GET, HEAD, TRACE');
  res.end('<html><script src="/main.js"></script></html>');
});
const strong = await serve((req, res) => {
  res.setHeader('content-type', 'text/html; charset=utf-8');
  res.setHeader('strict-transport-security', 'max-age=63072000; includeSubDomains');
  res.setHeader('content-security-policy', "default-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'");
  res.setHeader('x-content-type-options', 'nosniff');
  res.setHeader('referrer-policy', 'strict-origin-when-cross-origin');
  res.setHeader('set-cookie', 'sid=abc; Path=/; Secure; HttpOnly; SameSite=Lax');
  res.setHeader('allow', 'GET, HEAD, OPTIONS');
  res.end(req.url === '/' ? '<html></html>' : '<html>index</html>');
});
const weakUrl = `http://127.0.0.1:${weak.address().port}/`;
const strongUrl = `http://127.0.0.1:${strong.address().port}/`;
const liveChecks = catalog().checks.filter((c) => c.type === 'live' && !['aws', 'tls-legacy', 'http-redirect'].includes(c.probe));

await test('servidor débil: fallan HSTS, CSP, cabeceras, CORS reflejado, cookies, .git expuesto y TRACE', async () => {
  const r = await runLive(liveChecks, [weakUrl]);
  for (const id of ['LIVE-01', 'LIVE-02', 'LIVE-03', 'LIVE-04', 'LIVE-05', 'LIVE-08', 'LIVE-09']) assert.equal(r[id].status, 'fail', `${id}: ${r[id].status}`);
});

await test('servidor endurecido: todas las sondas pasan', async () => {
  const r = await runLive(liveChecks, [strongUrl]);
  for (const [id, x] of Object.entries(r)) assert.equal(x.status, 'pass', `${id}: ${x.status} ${x.findings.map((f) => f.message)}`);
});

await test('el cliente live rechaza hosts fuera del allowlist y métodos que no son de lectura', async () => {
  const req = makeClient([strongUrl]);
  await assert.rejects(req('http://example.com/'), /allowlist/);
  await assert.rejects(req(strongUrl, { method: 'POST' }), /no permitido/);
  await assert.rejects(req(strongUrl, { method: 'TRACE' }), /no permitido/);
});
await test('una URL que redirige se evalúa como redirección y el resultado lo advierte', async () => {
  const redir = await serve((req, res) => { res.writeHead(301, { location: 'https://otro.example/app/', 'content-type': 'text/plain' }); res.end('Moved'); });
  const u = `http://127.0.0.1:${redir.address().port}/`;
  const r = await runLive(liveChecks, [u]);
  assert.match(r['LIVE-01'].note, /responde 301 → https:\/\/otro\.example\/app\//);
  assert.ok(!r['LIVE-03'].findings.some((f) => /Referrer-Policy/.test(f.message)), 'Referrer-Policy no aplica a una redirección');
  assert.equal(r['LIVE-02'].status, 'not-applicable');
  redir.close();
});
weak.close(); strong.close();

for (const t of temps) rmSync(t, { recursive: true, force: true });
console.log(`\n${passed} ok, ${failed} fallidas`);
process.exit(failed ? 1 : 0);
