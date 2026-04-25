# Flujos de asignación de pines

## Flujo básico (Planeta Lector)

1. El administrador de **ConectaTech** crea una **Organización**, a la que le asigna una categoría, dentro de la categoría raíz **COLEGIOS**.
2. El administrador genera un pin de _gestor_ asociado a la **Organización**.
3. Un funcionario de la **Organización** ingresará a la URL `https://admin.conectatech.co/activar` y con el _hash_ del pin de _gestor_ creará un usuario gestor.
4. El usuario gestor tendrá rol `ct_gestor` dentro de Moodle, de tal forma que podrá acceder a los cursos dentro de la categoría de la **Organización**, sin permisos de edición.
5. El gestor crea **Colegios** y, dentro de cada **Colegio**, **Grupos**. Estos datos se usarán, en el paso 8, para crear un _grupo_ dentro del curso en Moodle, que garantice que los recursos, actividades, listados de participantes y calificaciones solo podrán ser accedidos por los miembros de un mismo grupo. El nombre del grupo se compone del **Colegio** y el **Grupo**, con la forma `[colegio]-[grupo]`. Por lo tanto, los cursos creados a través del sistema de árboles curriculares deberán tener la configuración de **Grupos** de la siguiente forma:
   - **Modo de grupo:** Grupos separados.
   - **Forzar el modo de grupo:** Si.
6. El administrador de **ConectaTech** genera un paquete de pines, con una duración determinada (3, 6 o 12 meses), fijando el tipo de **Rol de profesor**, que puede ser `profesor editor` o `profesor` (sin permiso de edición), y lo asigna a una **Organización**.
7. El gestor podrá usar los pines asignados en el paquete, indicando el colegio, grupo, rol (`profesor` o `estudiante`) y curso; el curso solo podrá ser uno de los cursos dentro de la categoría asignada por el administrador de **ConectaTech** en el paso 1.
8. El usuario al que se le entregue un pin, ingresará a la URL `https://admin.conectatech.co/activar` con el _hash_ del pin, y podrá crear su usuario en la plataforma Moodle si aún no existe; quedará matriculado en el curso designado para el pin, con las siguientes características:
   - Con el rol (`profesor` o `estudiante`) determinado en el pin.
   - Dentro del grupo del curso determinado. Si el grupo no existe en el curso, se creará en este momento.
   - La matrícula tendrá la vigencia del pin, que inicia en este momento.
9. Los pines podrán ser activados desde una URL que contenga su _hash_, tipo `https://admin.conectatech.co/activar/[hash]`; esto permitirá la generación y distribución de pines en formato QR.

---

## Análisis de brechas — estado actual vs. flujo requerido

Comparación entre el flujo del cliente (sección anterior) y el estado actual de la aplicación en la rama `revison-gestion-pines`.

| Paso | Estado | Observación |
|------|--------|-------------|
| 1. Organización con categoría Moodle | ✅ Implementado | `organizaciones.component` + API completos |
| 2. Pin de gestor por organización | ✅ Implementado | `crearGestorPin` en panel admin |
| 3. Activación de gestor en `/activar` | ✅ Implementado | Flujo `pin-gestor` en `activar.component` |
| 4. Rol `ct_gestor` en Moodle (solo lectura) | ✅ Implementado | `crear-rol-gestor.php` + `activarGestor()` |
| 5a. Gestor crea **Colegios** como entidad propia | ❌ No existe | `ct_group` es plano: solo `name` y `organization_id`; no hay entidad Colegio |
| 5b. Gestor crea **Grupos** dentro de cada Colegio | ❌ Incompleto | Los grupos actuales no tienen jerarquía ni usan el formato `[colegio]-[grupo]` |
| 5c. Cursos con **Grupos separados** forzados | ❌ No implementado | Al activar un pin, no se configura `groupmode = SEPARATEGROUPS` ni `groupmodeforce = 1` en el curso |
| 6. Paquete con duración 3/6/12 meses | ✅ Implementado | `duration_days` + selector en el panel admin |
| 7. Asignación de pines con colegio + grupo + rol + curso | ⚠️ Incompleto | El gestor asigna por grupo (plano), rol y curso, pero no existe la selección jerárquica colegio → grupo |
| 8. Activación: usuario, matrícula, grupo Moodle, vigencia | ✅ Implementado | Crea usuario, matricula con rol y timeend correcto, crea grupo en Moodle si no existe |
| 9. URL `/activar/[hash]` para QR | ❌ No implementado | La ruta actual es solo `/activar` sin parámetro; el hash se escribe manualmente |

**Resumen:** 3 funcionalidades faltantes, 1 incompleta.

---

## Plan de trabajo

> Pendiente de aprobación — implementar solo cuando el usuario lo autorice.
>
> **Respuestas incorporadas:** (1) separador ` - ` con espacios → nombre Moodle: `IED Manuela Beltrán - Grado 8B`; (2) admin ve gestores por org en tabla con filas expandidas, Colegios/Grupos en reportes futuros; (3) grupos separados se configuran de forma reactiva al activar el pin.

---

### Tarea 1 — Jerarquía Colegio → Grupo (BD + Backend + Frontend gestor)

**Problema:** El flujo requiere que el gestor cree primero Colegios y luego Grupos dentro de cada Colegio. Actualmente `ct_group` es una entidad plana sin jerarquía ni entidad padre. El nombre del grupo Moodle debe componer las dos partes con el formato `{colegio} - {grupo}`.

#### 1.1 Esquema de BD

Nueva tabla `ct_colegio`:

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | INT AUTO | PK |
| `organization_id` | INT | FK → `ct_organization` |
| `name` | VARCHAR(255) | Nombre del colegio |
| `created_at` | INT | Unix timestamp |

Modificación en `ct_group`: añadir columna `colegio_id INT NULL` con FK a `ct_colegio`.

El nombre del grupo Moodle al activar un pin se calcula en `ActivacionService::activarPin()` como:
```
{colegio.name} - {grupo.name}
```
Ejemplo: `IED Manuela Beltrán - Grado 8B`

**Archivos de esquema:**
- `admin-module/backend/migrar-pines-v2.php` → `CREATE TABLE ct_colegio` + `ALTER TABLE ct_group ADD COLUMN colegio_id`
- `admin-module/backend/install-pines.php` → añadir `ct_colegio` al esquema de instalación limpia

#### 1.2 Backend PHP

**`GestorService.php`** — añadir/modificar:
- `listarColegios(array $ctGestor): array` — filtra por `organization_id`
- `crearColegio(array $ctGestor, string $name): array` — nombre único por organización
- `crearGrupo(array $ctGestor, int $colegioId, string $name): array` — requiere `colegio_id`; valida que el colegio pertenece a la organización
- `listarGrupos()` → incluir `colegio_id` y `colegio_name` en el response

**`ActivacionService::activarPin()`** → al crear el grupo en Moodle:
```php
$colegio = $DB->get_record('ct_colegio', ['id' => $ctGroup->colegio_id], '*', MUST_EXIST);
$moodleGroupName = $colegio->name . ' - ' . $ctGroup->name;
```

**`admin-module/api/handlers/gestor.php`** → añadir rutas:
- `GET  /admin-api/gestor/colegios`
- `POST /admin-api/gestor/colegios`
- `POST /admin-api/gestor/grupos` → acepta `colegio_id` en el body

#### 1.3 Frontend Angular — panel gestor

**Nuevo componente `gestor-colegios.component`** (reemplaza a `gestor-grupos.component`):
- Tabla principal: lista de Colegios con columnas Nombre y Acciones
- Fila expandida de cada colegio: sub-tabla de Grupos (Nombre, Acciones)
- Botón "Nuevo colegio" → dialog con campo nombre
- Dentro de cada colegio expandido: botón "Nuevo grupo" → dialog con campo nombre

**`gestor-pines.component` — dialog de asignación:**
- Nuevo selector "Colegio" (primero)
- Selector "Grupo" filtrado según colegio seleccionado (computed signal)
- Flujo de selección: Colegio → Grupo → Rol → Curso

**`api.service.ts`:**
- `getGestorColegios(): Observable`
- `crearGestorColegio(body: { name: string }): Observable`
- `crearGestorGrupo(body)` → añadir `colegio_id`

**`app.routes.ts`:** ruta `gestor/grupos` → `gestor/colegios`

**Nota:** Los grupos existentes sin `colegio_id` (tablas limpias en este caso) no generan problema. En un entorno con datos previos, `colegio_id` sería `null` y la activación fallaría; se documenta como dato inválido para migrar manualmente.

**Archivos a modificar / crear:**

| Archivo | Cambio |
|---------|--------|
| `backend/install-pines.php` | Añadir tabla `ct_colegio` |
| `backend/migrar-pines-v2.php` | `CREATE TABLE ct_colegio` + `ALTER TABLE ct_group ADD colegio_id` |
| `backend/lib/GestorService.php` | CRUD colegios, `crearGrupo` con `colegio_id`, `listarGrupos` actualizado |
| `backend/lib/ActivacionService.php` | Nombre Moodle = `{colegio} - {grupo}` |
| `api/handlers/gestor.php` | Rutas `/gestor/colegios` |
| `frontend/.../gestor/colegios/gestor-colegios.component.ts` | Nuevo componente |
| `frontend/.../gestor/colegios/gestor-colegios.component.html` | Template |
| `frontend/.../gestor/pines/gestor-pines.component.ts` | Selector colegio → grupo en cascada |
| `frontend/.../gestor/pines/gestor-pines.component.html` | Template |
| `frontend/.../core/services/api.service.ts` | Métodos colegios |
| `frontend/.../app.routes.ts` | Ruta `gestor/colegios` |

**Definición de done:**
- [ ] El gestor puede crear colegios en `/gestor/colegios` y grupos dentro de cada colegio
- [ ] En el dialog de asignación de pines, el selector de grupo es una cascada colegio → grupo
- [ ] Al activar un pin, el grupo Moodle se crea con el nombre `{colegio} - {grupo}`
- [ ] Build del frontend sin errores

---

### Tarea 2 — Rediseño de `/organizaciones`: tabla con filas expandidas

**Problema:** La vista actual de Organizaciones usa un `p-treetable` con orgs como nodos raíz y gestores como nodos hijos. El cliente requiere una tabla estándar con filas expandidas: al expandir una organización se muestra la sub-tabla de sus gestores.

La tabla principal se simplifica a: **Nombre, Categoría Moodle, Pines (total), Fecha de creación, Acciones**.

La sub-tabla de gestores muestra: **Nombre, Email, Usuario, Fecha de creación, Acciones**.

#### Cambios

**`api/handlers/organizaciones.php` → `GET /admin-api/organizaciones`:**
- Añadir campo `total_pins` en el response de cada org: suma de pines de todos sus paquetes (cualquier estado)
- Añadir campo `category_name`: nombre de la categoría Moodle (`course_categories.name`)

**`organizaciones.component.ts`:**
- Reemplazar `TreeTableModule` + `TreeNode[]` por `TableModule` + `any[]`
- La expansión de fila carga los gestores vía `getGestores(orgId)` al expandirse (igual que ahora, pero con row expansion en lugar de nodo hijo)
- Mantener toda la lógica de acciones: crear org, editar org, eliminar org, gestionar pines de gestor (crear, anular, copiar), eliminar gestor

**`organizaciones.component.html`:**
- `<p-table>` con `[expandedRowKeys]` y template `#expansion`
- Columnas principales: Nombre, Categoría, Pines, Fecha de creación, Acciones
- Template de expansión: `<p-table>` anidada con gestores

**Archivos a modificar:**

| Archivo | Cambio |
|---------|--------|
| `api/handlers/organizaciones.php` | Añadir `total_pins` y `category_name` al response |
| `frontend/.../organizaciones/organizaciones.component.ts` | TreeTable → Table con row expansion |
| `frontend/.../organizaciones/organizaciones.component.html` | Template completo |

**Definición de done:**
- [ ] `/organizaciones` muestra una tabla con columnas Nombre, Categoría, Pines, Fecha de creación, Acciones
- [ ] Al expandir una fila se muestra la sub-tabla de gestores de esa organización
- [ ] Las acciones de org y gestor funcionan igual que antes
- [ ] Build del frontend sin errores

---

### Tarea 3 — Grupos separados forzados en cursos Moodle

**Problema:** El flujo requiere que los cursos tengan "Modo de grupo: Grupos separados" y "Forzar el modo de grupo: Sí" para que los recursos, actividades y calificaciones sean accesibles solo dentro del grupo del estudiante. Actualmente esto no se configura en ningún punto del flujo.

#### Implementación (reactiva — al activar el pin)

En `ActivacionService::activarPin()`, justo antes de llamar a `enrol_user`, verificar y configurar el curso:

```php
$course = $DB->get_record('course', ['id' => $pin->moodle_course_id], '*', MUST_EXIST);
if ((int)$course->groupmode !== SEPARATEGROUPS || !(int)$course->groupmodeforce) {
    $DB->update_record('course', (object)[
        'id'             => $course->id,
        'groupmode'      => SEPARATEGROUPS,  // = 2
        'groupmodeforce' => 1,
    ]);
    rebuild_course_cache((int)$course->id);
}
```

Idempotente: solo actualiza si aún no está configurado. No toca cursos sin pines activados.

**Archivos a modificar:**

| Archivo | Cambio |
|---------|--------|
| `backend/lib/ActivacionService.php` | Configurar `groupmode + groupmodeforce` en `activarPin()` |

**Definición de done:**
- [ ] Al activar el primer pin en un curso, ese curso queda con `groupmode = 2` y `groupmodeforce = 1`
- [ ] Las activaciones posteriores en el mismo curso no generan errores (idempotente)
- [ ] Verificable en Moodle: Administración del curso → Editar ajustes → sección Grupos

---

### Tarea 4 — Ruta `/activar/:hash` para distribución por QR

**Problema:** La URL actual es `/activar` con entrada manual del hash. Para distribuir pines en formato QR, se necesita que `/activar/abc123ef...` pre-cargue el hash y resuelva el pin automáticamente, llevando al usuario directamente al formulario correcto.

#### Cambios

**`app.routes.ts`:**
```typescript
{ path: 'activar',       loadComponent: () => import('.../activar.component')... },
{ path: 'activar/:hash', loadComponent: () => import('.../activar.component')... },
```

**`activar.component.ts`:**
- Inyectar `ActivatedRoute`
- En `ngOnInit`: leer `route.snapshot.paramMap.get('hash')`; si existe, volcarlo en `hashInput` — sin llamar `resolverPin()` automáticamente

**`activar.component.html`:**
- El campo de entrada del hash se muestra siempre; si el hash viene de la URL queda pre-rellenado y el usuario debe hacer clic en **Verificar código** para resolver el pin

**Archivos a modificar:**

| Archivo | Cambio |
|---------|--------|
| `frontend/.../app.routes.ts` | Añadir ruta `activar/:hash` |
| `frontend/.../activar/activar.component.ts` | `ngOnInit`, leer param, auto-resolver |
| `frontend/.../activar/activar.component.html` | Condicional hash-por-URL vs. entrada manual |

**Definición de done:**
- [ ] `https://admin.conectatech.co/activar/abc123...` resuelve el pin automáticamente y muestra el formulario correcto (gestor o regular)
- [ ] `https://admin.conectatech.co/activar` sigue funcionando con entrada manual del hash
- [ ] Hash inválido por URL muestra el toast de error y presenta el campo de entrada manual

---

### Tarea 5 — Elemento de menú 'ConectaTech Admin' en Moodle

**Problema:** Los gestores y administradores acceden a Moodle directamente (vía `/activar` y luego `wwwroot/course/view.php`). No hay un acceso visible desde Moodle hacia el panel admin. Se requiere un ítem en el menú principal superior de Moodle con el texto "ConectaTech Admin" que abra `https://admin.conectatech.co` en nueva pestaña, visible solo para administradores del sitio y usuarios con rol `ct_gestor`.

#### Implementación — plugin local de Moodle

Se crea un plugin local mínimo en `admin-module/backend/moodle-plugins/local_conectatech/` que se despliega en `/var/www/html/moodle/local/conectatech/`.

**Estructura de archivos:**

```
local_conectatech/
├── version.php
├── lang/
│   └── es/
│       └── local_conectatech.php
└── lib.php
```

**`version.php`:**
```php
$plugin->component = 'local_conectatech';
$plugin->version   = 2026042200;
$plugin->requires  = 2024100700;  // Moodle 4.5+
$plugin->maturity  = MATURITY_STABLE;
```

**`lib.php`** — usar el callback `extend_navigation` para añadir el nodo al menú de navegación del usuario:

```php
function local_conectatech_extend_navigation(global_navigation $nav): void {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) return;

    $mostrar = is_siteadmin()
        || $DB->record_exists('ct_gestor', ['moodle_userid' => (int)$USER->id]);

    if (!$mostrar) return;

    $node = $nav->add(
        'ConectaTech Admin',
        new moodle_url('https://admin.conectatech.co'),
        navigation_node::TYPE_CUSTOM,
        'ConectaTech Admin',
        'conectatech_admin',
        new pix_icon('i/settings', '')
    );
    $node->showinflatnavigation = true;
}
```

> **Nota técnica:** `global_navigation` añade el nodo al menú de navegación lateral/superior dependiendo del tema. En Boost (Moodle 5.x), los nodos con `showinflatnavigation = true` aparecen en la barra de navegación principal. Si el resultado visual no es el esperado, el fallback es usar el hook `\core\hook\navigation\primary_extend` disponible en Moodle 4.3+ para inyectar el nodo directamente en la barra primaria — se evaluará en implementación.

**Deploy:**
1. Rsync `admin-module/backend/moodle-plugins/local_conectatech/` → `/var/www/html/moodle/local/conectatech/`
2. Ajustar permisos: `chown -R apache:apache /var/www/html/moodle/local/conectatech`
3. Ejecutar upgrade de Moodle: `sudo -u apache php /var/www/html/moodle/admin/cli/upgrade.php --non-interactive`
4. Purgar caché: `sudo -u apache php /var/www/html/moodle/admin/cli/purge_caches.php`

**Archivos a crear:**

| Archivo | Descripción |
|---------|-------------|
| `backend/moodle-plugins/local_conectatech/version.php` | Metadata del plugin |
| `backend/moodle-plugins/local_conectatech/lang/es/local_conectatech.php` | Strings en español |
| `backend/moodle-plugins/local_conectatech/lib.php` | Callback de navegación |

**Definición de done:**
- [ ] Plugin instalado en Moodle sin errores (`/admin/index.php` no reporta notificaciones de error)
- [ ] Iniciando sesión como administrador: aparece "ConectaTech Admin" en el menú de Moodle
- [ ] Iniciando sesión como usuario con rol `ct_gestor`: aparece "ConectaTech Admin"
- [ ] Iniciando sesión como estudiante: NO aparece "ConectaTech Admin"
- [ ] El clic abre `https://admin.conectatech.co` en una nueva pestaña
