# admin-module

Panel de administración de **ConectaTech.co**, la plataforma educativa B2B construida sobre Moodle 5.2. Permite al equipo de ConectaTech gestionar contenido curricular, instituciones, organizaciones, pines de acceso y matrículas de estudiantes.

## ¿Qué es?

Un panel de administración (Angular) respaldado por una API REST propia (PHP) que opera directamente sobre la base de datos de Moodle a través de su API interna. No reemplaza a Moodle: lo complementa con flujos de gestión que Moodle no ofrece de forma nativa (importación masiva de contenido desde Markdown, árboles curriculares, pines de activación, matrícula por CSV).

## Estructura

```
admin-module/
├── frontend/   → SPA Angular 21 (panel de administración)
├── api/        → API REST PHP (expuesta como /admin-api/* en conectatech.co)
├── backend/    → Scripts CLI PHP + librerías compartidas
└── docs/       → Documentación de arquitectura e infraestructura
```

### `frontend/`

SPA Angular 21 standalone con PrimeNG + Tailwind. Rutas principales (`src/app/features/`): `dashboard`, `matriculas`, `contenido`, `arboles` (árboles curriculares), `activos`, `instituciones`, `organizaciones`, `pines`, `reportes`, y un shell separado para `gestor` (usuarios de organizaciones indirectas).

### `api/`

API REST en PHP puro, sin framework. `index.php` enruta hacia `handlers/` (uno por dominio: `cursos`, `matriculas`, `pines`, `organizaciones`, etc.), que a su vez usan los servicios de `backend/lib/`. Se despliega en el servidor como `/admin-api/*` dentro del VirtualHost de `conectatech.co` (no en el de `admin.conectatech.co`), para que la cookie de sesión de Moodle viaje en el mismo origen.

### `backend/`

Scripts CLI (`procesar-markdown.php`, `crear-cursos.php`, `poblar-cursos.php`, `matricular.php`, etc.) y las librerías PHP que comparten con `api/` (`lib/`). Se ejecutan siempre como usuario `apache`:

```bash
sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php --file <archivo.md> --course <shortname>
```

Ver `docs/uso-cli.md` para el detalle de cada script.

### `docs/`

Documentación de arquitectura técnica, estructura de carpetas, árbol curricular e infraestructura del servidor.

## Desarrollo local — frontend

```bash
cd frontend
npm install
npm start        # ng serve
npm run build     # build de producción
npm test          # Karma/Jasmine
```

## Convenciones

Ver `CLAUDE.md` en la raíz del repositorio para las convenciones de código (PHP/Angular), reglas de seguridad OWASP y el flujo de Git obligatorio para cambios en este módulo.

## Despliegue

El despliegue al servidor EC2 es manual (rsync + ajuste de permisos `apache`). El proceso completo está documentado en `docs/infraestructura-servidor.md`.
