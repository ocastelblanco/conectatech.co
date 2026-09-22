# Third-Party Notices · Avisos de terceros

Everything in this repository is **Copyright © 2026 Ideas Maestras Inc. — All rights reserved** (see [`LICENSE`](LICENSE)), **except** the components listed below, which keep their original licenses.

Todo el contenido de este repositorio es **Copyright © 2026 Ideas Maestras Inc. — Todos los derechos reservados** (ver [`LICENSE`](LICENSE)), **excepto** los componentes listados abajo, que conservan sus licencias originales.

## Components in this repository · Componentes en este repositorio

| Path · Ruta | Component · Componente | License · Licencia | Notes · Notas |
| :--- | :--- | :--- | :--- |
| [`admin-module/backend/moodle-plugins/usagereports/`](admin-module/backend/moodle-plugins/usagereports/) | `local_usagereports` Moodle plugin | [GNU GPL v3 or later](https://www.gnu.org/licenses/gpl-3.0.html) | Written by ConectaTech. Moodle requires plugins to be GPL v3+. Every file carries the GPL header. · Escrito por ConectaTech. Moodle exige que sus plugins sean GPL v3+. Cada archivo lleva la cabecera GPL. |
| [`.claude/skills/frontend-design/`](.claude/skills/frontend-design/) | `frontend-design` agent skill (Anthropic) | [Apache 2.0](.claude/skills/frontend-design/LICENSE.txt) | Full license text included. · Texto completo de la licencia incluido. |
| [`.claude/skills/slim-readme/`](.claude/skills/slim-readme/) | README template from [NASA-AMMOS SLIM](https://github.com/NASA-AMMOS/slim) | [Apache 2.0](https://www.apache.org/licenses/LICENSE-2.0) | |
| [`.claude/skills/angular-best-practices-21/`](.claude/skills/angular-best-practices-21/) | `angular-best-practices` agent skill | MIT | As declared in its `package.json`. · Según su `package.json`. |

## Runtime dependencies · Dependencias de ejecución

These are not stored in this repository. They're installed or run separately, under their own licenses.

Estas no se almacenan en el repositorio. Se instalan o ejecutan aparte, bajo sus propias licencias.

| Component · Componente | License · Licencia |
| :--- | :--- |
| [Moodle](https://moodle.org) (LMS) | GNU GPL v3 or later |
| [Angular](https://angular.dev), [RxJS](https://rxjs.dev), [PrimeNG](https://primeng.org), [PrimeIcons](https://primeng.org/icons), [Tailwind CSS](https://tailwindcss.com) | MIT |
| [ngx-extended-pdf-viewer](https://github.com/stephanrauh/ngx-extended-pdf-viewer) and [PDF.js](https://mozilla.github.io/pdf.js/) (PDF viewer · visor PDF) | Apache 2.0 |
| [AWS SDK for JavaScript](https://github.com/aws/aws-sdk-js-v3) | Apache 2.0 |
| [Terraform AWS provider](https://github.com/hashicorp/terraform-provider-aws) | MPL 2.0 |
| Other npm packages · Otros paquetes npm | See each package's `LICENSE` in `node_modules/` · Ver el `LICENSE` de cada paquete en `node_modules/` |

To list the licenses of every installed npm dependency · Para listar las licencias de todas las dependencias npm instaladas:

```bash
npx license-checker --summary
```
