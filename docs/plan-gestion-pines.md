# Plan de implementación: Sistema de Gestión de Pines

## Arquitectura general

```
BD Moodle (prefijo ct_)
  ct_organization, ct_gestor, ct_gestor_pin,
  ct_pin_package, ct_pin, ct_group

API PHP (admin-module/api/)
  Rutas admin:     /api/organizaciones, /api/pines, /api/paquetes
  Rutas gestor:    /api/gestor/*  (autenticado como gestor Moodle)
  Rutas públicas:  /api/activar/* (sin autenticación)

Frontend Angular (admin-module/frontend/)
  Vistas admin:    /organizaciones, /pines
  Vista gestor:    /gestor (con guardia de rol)
  Vista pública:   /activar (sin guardia)
```

---

## Modelo de datos

| Tabla | Columnas clave |
|-------|----------------|
| `ct_organization` | `id`, `name`, `moodle_category_id`, `created_at` |
| `ct_gestor` | `id`, `organization_id`, `moodle_userid`, `created_at` |
| `ct_gestor_pin` | `id`, `organization_id`, `hash`, `status` (pending/used), `created_by`, `created_at`, `used_at` |
| `ct_pin_package` | `id`, `organization_id`, `teacher_role` (editingteacher/teacher), `expires_at`, `created_by`, `created_at` |
| `ct_pin` | `id`, `package_id`, `hash`, `role` (editingteacher/teacher/student), `group_id`, `moodle_course_id`, `status` (available/assigned/active/expired), `activated_by`, `activated_at` |
| `ct_group` | `id`, `organization_id`, `name`, `moodle_group_id` (null hasta matricular) |

### Notas de diseño

- El `expires_at` viene del `ct_pin_package`; la matrícula en Moodle se crea con `timeend = expires_at`.
- Al vencer un pin (`expires_at < now`), queda disponible para reactivarse (conserva grupo/curso/rol asignados).
- El hash del gestor-pin es de un solo uso; los pines regulares son reutilizables tras vencimiento.
- El `teacher_role` del paquete determina el rol que se asigna en Moodle a los pines de tipo profesor; el gestor no puede modificarlo.

---

## Fases de implementación

### Fase 1 — Esquema de base de datos

**Archivo nuevo:** `admin-module/backend/install-pines.php` (script CLI, ejecución única)

- Crea las 6 tablas `ct_*` usando `$DB->execute()`.
- Idempotente: verifica si cada tabla ya existe antes de crearla.

---

### Fase 2 — Backend: API del administrador

**Archivos nuevos:**

- `backend/lib/OrganizacionService.php` — CRUD organizaciones + asignación de categoría Moodle.
- `backend/lib/PinesService.php` — creación de paquetes, generación de hashes, asignación a org.
- `api/handlers/organizaciones.php` — handlers de las rutas de organización.
- `api/handlers/pines.php` — handlers de paquetes y pines.

**Rutas nuevas en `api/index.php`:**

```
GET    /api/organizaciones                      → listar
POST   /api/organizaciones                      → crear
PUT    /api/organizaciones/{id}                 → renombrar / reasignar categoría
DELETE /api/organizaciones/{id}                 → eliminar

POST   /api/organizaciones/{id}/gestor-pin      → crear pin de gestor
DELETE /api/gestor-pines/{hash}                 → anular pin de gestor pendiente

POST   /api/paquetes                            → crear paquete + generar N pines
GET    /api/paquetes                            → listar (filtrables por organización)
POST   /api/paquetes/{id}/asignar               → asignar paquete a organización

GET    /api/reportes/pines                      → reporte de uso (por organización / paquete)
```

---

### Fase 3 — Extensión de autenticación

**Archivo modificado:** `api/auth.php`

- Rutas `/api/activar/*` → sin autenticación (bypass completo).
- Rutas `/api/gestor/*` → verifica sesión Moodle + existencia en `ct_gestor`; inyecta `$ctGestor` con datos de la organización del usuario.
- Resto de rutas → comportamiento actual (solo administrador Moodle).

**Archivo nuevo:** `backend/lib/GestorAuth.php`

- `lookupGestor(int $moodleUserId): ?array` — consulta `ct_gestor` + `ct_organization`.

---

### Fase 4 — Backend: API del gestor

**Archivo nuevo:** `backend/lib/GestorService.php`

- Grupos: CRUD en `ct_group` (sin tocar Moodle hasta la activación).
- Pines: listar pines disponibles de la organización, asignar grupo/curso/rol en lote.
- Descarga: generar CSV con hash, rol, vigencia, grupo y curso.

**Archivo nuevo:** `api/handlers/gestor.php`

```
GET  /api/gestor/organizacion         → datos de la org + cursos disponibles en la categoría
GET  /api/gestor/grupos               → listar grupos de la organización
POST /api/gestor/grupos               → crear grupo
GET  /api/gestor/pines                → listar pines (filtrables por estado, grupo, curso)
PUT  /api/gestor/pines/asignar        → asignar lote de pines (grupo, curso, rol)
GET  /api/gestor/pines/descargar      → descarga CSV
```

---

### Fase 5 — Backend: API pública de activación

**Archivo nuevo:** `backend/lib/ActivacionService.php`

Lógica central:

1. `resolvePin(string $hash)` — detecta si es pin-gestor o pin-regular; retorna tipo y datos sin ejecutar ningún cambio.
2. `activarGestor(string $hash, array $datosUsuario)`:
   - Crea usuario en Moodle (`create_user()`).
   - Asigna rol `teacher` a nivel de contexto de categoría (`role_assign()`), dando visibilidad sobre los cursos de la organización sin permisos de edición.
   - Inserta registro en `ct_gestor`.
   - Marca el `ct_gestor_pin` como `used`.
3. `activarPin(string $hash, int $moodleUserId)`:
   - Si `ct_group.moodle_group_id` es null, crea el grupo en Moodle (`groups_create_group()`) y guarda el id.
   - Matricula el usuario con `timeend = expires_at` usando `enrol_manual`.
   - Añade el usuario al grupo (`groups_add_member()`).
   - Marca el pin como `active`, guarda `activated_by` y `activated_at`.

**Archivo nuevo:** `api/handlers/activacion.php`

```
POST /api/activar/resolver    → recibe hash, devuelve tipo + info del pin (sin ejecutar cambios)
POST /api/activar/gestor      → crea cuenta de gestor y activa el pin-gestor
POST /api/activar/login       → verifica credenciales Moodle (para usuarios ya existentes)
POST /api/activar/pin         → activa pin para el usuario autenticado
```

---

### Fase 6 — Frontend: Vistas del administrador

**Componentes nuevos:**

- `features/organizaciones/` — listado + formulario de creación/edición; selector de categoría Moodle; sección de gestores con generación de pin-gestor.
- `features/pines/` — tabla de paquetes con estado (disponibles/asignados/activos/vencidos); wizard de creación (cantidad, fecha de expiración, tipo de rol profesor); acción de asignación a organización.
- `features/pines/reporte/` — tabla de uso con filtros por organización y paquete.

**Cambios en routing/navegación:**

- Añadir "Organizaciones" y "Pines" al menú lateral de la vista de administrador.

---

### Fase 7 — Frontend: Vista del gestor

**Guardia de rol nuevo:** `GestorGuard`

- Al iniciar sesión, consulta `/api/gestor/organizacion`.
- Si el usuario es gestor: redirige a `/gestor`.
- Si es administrador: redirige a la vista admin habitual.
- Si no tiene ningún rol: muestra 403.

**Componentes nuevos:**

- `features/gestor/dashboard/` — resumen de pines disponibles, asignados y activos.
- `features/gestor/grupos/` — CRUD de grupos de la organización.
- `features/gestor/pines/` — tabla con asignación masiva (selección múltiple → asignar grupo/curso/rol) y botón de descarga CSV.

---

### Fase 8 — Frontend: Página pública de activación

**Ruta:** `/activar` (sin `AuthGuard`)

Flujo del componente:

1. Campo para ingresar el hash del pin → llama `POST /api/activar/resolver`.
2. Si es **pin-gestor**: muestra formulario de registro (nombres, apellidos, email, usuario, contraseña).
3. Si es **pin-regular**: muestra información del pin (curso, grupo, rol, vigencia) y pregunta si el usuario ya tiene cuenta:
   - **Sí**: formulario de login → llama `/api/activar/login` → activa pin.
   - **No**: formulario de registro → crea cuenta Moodle → activa pin.
4. Pantalla de confirmación con instrucciones de acceso a `conectatech.co`.

---

## Orden de ejecución

```
F1 (DB) → F2 (Admin API) → F3 (Auth) → F4 (Gestor API) → F5 (Activación API)
                                                                     ↓
F6 (Frontend Admin) → F7 (Frontend Gestor) → F8 (Frontend Activación)
```

Las fases 1–5 son backend puro y se pueden probar con `curl` antes de tocar el frontend.
