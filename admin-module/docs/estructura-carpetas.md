El árbol de archivos de la carpeta `conectatech.co` donde se aloja todo el proyecto, y desde donde se monta el repositorio en Github, es el siguiente:

```bash
.
├── .claude
│   ├── settings.local.json
│   └── skills
│       ├── angular-best-practices-21
│       │   ├── package.json
│       │   ├── rules
│       │   │   ├── 01-architecture
│       │   │   │   ├── 001-standalone-components.md
│       │   │   │   ├── 002-signals-pattern.md
│       │   │   │   ├── 003-component-design.md
│       │   │   │   ├── 004-file-structure.md
│       │   │   │   └── 005-modern-control-flow.md
│       │   │   ├── 02-performance
│       │   │   │   ├── 006-change-detection-onpush.md
│       │   │   │   ├── 007-lazy-loading.md
│       │   │   │   ├── 008-optimized-bundles.md
│       │   │   │   ├── 009-zoneless-angular.md
│       │   │   │   └── 010-memoization-patterns.md
│       │   │   ├── 03-security
│       │   │   │   ├── 012-sanitization-requirements.md
│       │   │   │   ├── 013-csrf-protection.md
│       │   │   │   └── 014-content-security-policy.md
│       │   │   ├── 04-accessibility
│       │   │   │   ├── 015-aria-requirements.md
│       │   │   │   ├── 016-keyboard-navigation.md
│       │   │   │   ├── 017-semantic-html.md
│       │   │   │   └── 018-focus-management.md
│       │   │   ├── 05-error-handling
│       │   │   │   ├── 019-global-error-handler.md
│       │   │   │   ├── 020-http-error-patterns.md
│       │   │   │   └── 021-reactive-error-flows.md
│       │   │   ├── 06-testing
│       │   │   │   ├── 022-vitest-setup.md
│       │   │   │   ├── 023-component-testing.md
│       │   │   │   ├── 024-service-testing.md
│       │   │   │   └── 025-coverage-requirements.md
│       │   │   └── 07-tooling
│       │   │       ├── 026-eslint-rules.md
│       │   │       ├── 027-angular-devtools.md
│       │   │       └── 028-build-optimizations.md
│       │   ├── scripts
│       │   │   ├── audit-angular-project.js
│       │   │   ├── check-best-practices.js
│       │   │   └── generate-compliance-report.js
│       │   ├── SKILL.md
│       │   └── templates
│       │       ├── component.template.ts
│       │       ├── guard.template.ts
│       │       └── service.template.ts
│       ├── configure-ssl
│       │   └── SKILL.md
│       ├── frontend-design
│       │   ├── LICENSE.txt
│       │   └── SKILL.md
│       ├── install-moodle
│       │   └── SKILL.md
│       ├── moodle-deployment
│       │   └── SKILL.md
│       ├── optimize-system
│       │   └── SKILL.md
│       ├── provision-infrastructure
│       │   └── SKILL.md
│       ├── readme
│       │   └── SKILL.md
│       ├── setup-backups
│       │   └── SKILL.md
│       ├── setup-monitoring
│       │   └── SKILL.md
│       ├── setup-moodle-server
│       │   └── SKILL.md
│       └── troubleshoot-moodle
│           └── SKILL.md
├── admin-module
│   └── docs
│       ├── arquitectura-addendum.md
│       ├── arquitectura-tecnica-definitiva.md
│       ├── Manual Técnico Moodle Fase 1.md
│       ├── propuesta-creacion_externa_contenido.md
│       ├── respuestas-a-validacion-propuesta-automatizacion.md
│       ├── respuestas-adicionales.md
│       └── validacion-propuesta-automatizacion.md
├── CLAUDE.md
├── config
│   └── variables.sh.example
├── CREDENCIALES.md
├── docs
│   ├── 01-architecture-overview.md
│   ├── 02-prerequisites.md
│   ├── 03-ec2-configuration.md
│   ├── 04-moodle-installation.md
│   ├── 05-optimization.md
│   ├── 06-ssl-configuration.md
│   ├── 07-backups.md
│   ├── 08-monitoring.md
│   ├── 09-maintenance.md
│   └── cuenta-aws.md
├── favicon
│   ├── apple-touch-icon.png
│   ├── favicon-96x96.png
│   ├── favicon.ico
│   ├── favicon.svg
│   ├── site.webmanifest
│   ├── web-app-manifest-192x192.png
│   └── web-app-manifest-512x512.png
├── README.md
├── scripts
│   ├── 01-provision-infrastructure.sh
│   ├── 02-setup-server.sh
│   ├── 03-install-moodle.sh
│   ├── 04-configure-ssl.sh
│   ├── 05-optimize-system.sh
│   ├── 06-setup-backups.sh
│   └── 07-setup-monitoring.sh
├── snippets
│   ├── conectatech-end-snippet.html
│   ├── conectatech-post.scss
│   └── conectatech-pre.scss
├── templates
└── terraform
    ├── .terraform.lock.hcl
    ├── ec2.tf
    ├── main.tf
    ├── outputs.tf
    ├── rds.tf
    ├── terraform.tfstate
    ├── terraform.tfstate.backup
    ├── terraform.tfvars
    ├── terraform.tfvars.example
    ├── tfplan
    └── variables.tf
```

Quiero montar la interfaz de procesamiento de contenidos y usuarios en la carpeta `admin-module` donde, como podrás ver, ya está la carpeta de documentos `docs`.

En dicha carpeta `admin-module` crearemos el backend (estos primeros scripts), el futuro frontend y, si fuera necesario ahora o en un futuro, los plugins que desarrollemos para Moodle.

La estructura, entonces, podría ser:

```bash
.
├── docs
│   ├── arquitectura-addendum.md
│   ├── arquitectura-tecnica-definitiva.md
│   ├── Manual Técnico Moodle Fase 1.md
│   ├── propuesta-creacion_externa_contenido.md
│   ├── respuestas-a-validacion-propuesta-automatizacion.md
│   ├── respuestas-adicionales.md
│   └── validacion-propuesta-automatizacion.md
├── frontend
├── backend
├── plugin-01
├── plugin-02
└── ...
```

Mi objetivo, además, es ubicar la aplicación en la carpeta `/var/www/html/admin/` (aún no creada) del servidor y que esté vinculada al dominio `https://admin.conectatech.co/`; Moodle está en la carpeta `/var/www/html/moodle/` y se carga desde `https://conectatech.co/`.

Incluye esto, si es necesario, en el prompt inicial (o en un documento de instrucciones aparte) para Claude Code.

