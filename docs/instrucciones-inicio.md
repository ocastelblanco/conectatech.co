# Instrucciones de inicio — Documentación especializada con IA

> Este documento explica cómo construir el sistema de documentación especializada
> (PRD, Tech Specs, MEMORY, TODO, seguridad OWASP, git flow) en dos escenarios:
> un proyecto nuevo desde cero y un proyecto ya existente.

---

## Tabla de contenidos

- [Parte 1: Proyecto nuevo (instrucciones para el humano)](#parte-1-proyecto-nuevo)
- [Parte 2: Proyecto existente (instrucciones para la IA)](#parte-2-proyecto-existente)
- [Apéndice A: Git flow obligatorio para agentes IA](#apéndice-a-git-flow-obligatorio-para-agentes-ia)
- [Apéndice B: Documentos resultantes y su propósito](#apéndice-b-documentos-resultantes-y-su-propósito)

---

## Parte 1: Proyecto nuevo

> **Audiencia:** El humano que inicia el proyecto desde cero.
> Sigue estos pasos en orden. Cada paso incluye el prompt exacto que debes escribirle a la IA.

---

### Paso 1 · Crear el `CLAUDE.md` inicial

Antes de hablar con la IA, crea manualmente el archivo `CLAUDE.md` en la raíz del repositorio. Este es el archivo de instrucciones permanentes que la IA leerá en cada sesión. Incluye al menos:

- Descripción del proyecto (qué hace, para quién, contexto de negocio).
- Stack tecnológico con versiones exactas.
- Estructura de carpetas del proyecto.
- Convenciones de código que el equipo ya tiene definidas.
- Idioma del proyecto (código, comentarios, documentación).

**Luego, escríbele esto a la IA:**

```
Lee el CLAUDE.md del proyecto. Identifica si hay información faltante que debería
estar documentada para que cualquier desarrollador (o agente IA) pueda entender
el proyecto sin contexto previo. Hazme las preguntas necesarias para completarlo.
```

---

### Paso 2 · Crear el PRD (Product Requirements Document)

El PRD captura los requisitos de negocio, los usuarios, los objetivos y el roadmap.
Escríbele esto a la IA:

```
Basándote en el CLAUDE.md y en lo que acabamos de discutir, crea un archivo PRD.md
en la raíz del proyecto. El documento debe tener:

1. Visión del producto (tabla con nombre, tipo, público, idiomas, URLs)
2. Contexto y problema que resuelve
3. Usuarios y audiencias (tabla con perfiles y necesidades)
4. Objetivos del producto (tabla con métrica de éxito y estado: implementado / pendiente)
5. Funcionalidades actuales — una por sección del sitio o módulo, con diagrama de flujo
   en texto para las más importantes
6. Roadmap de funcionalidades futuras (tabla con prioridad: Alta / Media / Baja)
7. Casos de uso principales (tabla: actor, acción, resultado esperado)
8. Requisitos no funcionales (performance, SEO, seguridad, accesibilidad, etc.)
9. Restricciones y decisiones de diseño (qué no puede cambiar y por qué)
10. Glosario de negocio (términos del dominio que un externo no conocería)

Audiencia: mixta (equipo técnico + stakeholders). Idioma: [TU IDIOMA].
Antes de escribir, hazme las preguntas que necesites para entender el negocio.
```

---

### Paso 3 · Crear las especificaciones técnicas (`tech-specs.md`)

Las Tech Specs capturan cómo está construido el sistema: arquitectura, patrones,
APIs, infraestructura, convenciones.

```
Con base en el CLAUDE.md y el PRD.md, crea un archivo tech-specs.md en la raíz
del proyecto. Incluye:

1. Visión general de la arquitectura (diagrama ASCII de todas las capas)
2. Stack tecnológico completo (tabla con versión, propósito y link a docs)
3. Estructura del repositorio comentada + tabla de path aliases
4. Frontend / cliente:
   - Patrones arquitectónicos (con regla de cuándo aplicar cada uno)
   - Rutas y navegación (tabla con guards, modo de carga, notas)
   - Modelos de datos principales (interfaces clave + diagrama de estructura)
   - Sistema de estilos / temas
   - SEO y SSR (si aplica)
5. Backend y APIs externas:
   - Tabla de todos los endpoints con método, llamado por, descripción, payload
   - Endpoints internos del servidor (si aplica)
   - Servicios externos (tabla: servicio, estado, uso actual, uso futuro)
6. Gestión de contenido (si aplica): diagrama del flujo de datos
7. Infraestructura y despliegue:
   - Diagrama de infraestructura
   - Multi-entorno (tabla: stage, URL, variables, comando)
   - Proceso de build y deploy paso a paso
8. Autenticación y seguridad
9. Gestión de secretos (tabla de variables con propósito y contexto)
10. Convenciones de código y flujo de trabajo (git, commits, tags)
11. Roadmap técnico (tabla: feature, archivos a crear, dependencias técnicas)

Nivel de detalle: referencia (suficiente para retomar el proyecto sin contexto previo).
```

---

### Paso 4 · Agregar reglas de seguridad OWASP al `CLAUDE.md`

Este paso hace que la IA genere código seguro por defecto en todas las sesiones futuras.

```
Analiza la arquitectura descrita en tech-specs.md e identifica las vulnerabilidades
OWASP Top 10 (2021) más relevantes para ESTA solución específica. Agrega una sección
## Seguridad (OWASP) al CLAUDE.md con reglas obligatorias y específicas para cada
vulnerabilidad identificada. Para cada categoría incluye:

- Qué está en riesgo en esta arquitectura concreta (no reglas genéricas)
- Regla de código exacta a seguir (con ejemplo si es necesario)
- Tabla de prohibiciones absolutas al final

Las reglas deben ser lo suficientemente específicas para que la IA las aplique
automáticamente al generar código, sin necesidad de recordárselo en cada sesión.
```

---

### Paso 5 · Agregar reglas de git flow al `CLAUDE.md`

Este paso evita que cualquier agente IA haga commits directos a ramas protegidas.
Copia y pega la sección del [Apéndice A](#apéndice-a-git-flow-obligatorio-para-agentes-ia)
directamente en tu `CLAUDE.md`, o escríbele esto a la IA:

```
Agrega una sección ## Git Flow para Agentes al CLAUDE.md con las siguientes reglas
obligatorias, adaptadas al nombre de las ramas del proyecto:

1. Nunca hacer commits directos a [RAMA_PRINCIPAL] ni a [RAMA_DESARROLLO].
2. Antes de cualquier cambio: crear una feature branch con prefijo feature/, fix/ o hotfix/.
3. Antes de commitear: ejecutar [COMANDO_BUILD] y verificar que pasa sin errores.
4. Al finalizar: crear un PR hacia [RAMA_DESTINO] usando `gh pr create`.
5. Ningún agente puede fusionar (merge) un PR. Solo humanos aprueban y fusionan.
6. Nunca usar --force push, --no-verify ni --no-gpg-sign.

Reemplaza los valores entre corchetes con los nombres reales del proyecto.
```

---

### Paso 6 · Crear el `MEMORY.md` (rehidratación de estado)

El MEMORY.md es el documento que la IA leerá primero en cada sesión para restaurar
el contexto sin necesidad de reexplicar el proyecto.

```
Con base en todo lo que hemos construido (CLAUDE.md, PRD.md, tech-specs.md), crea
un archivo MEMORY.md en la raíz del proyecto. Debe contener:

1. Estado actual del proyecto (tabla: versión, URLs, ramas, última sesión)
2. Funcionalidades completadas vs. pendientes (listas con checkboxes)
3. Registro de Decisiones de Arquitectura (ADR) — una por cada decisión importante:
   - Fecha, estado (implementado / en curso / pendiente)
   - Decisión tomada
   - Razón por la que se tomó
   - Consecuencias conocidas (problemas, limitaciones, gotchas)
4. Dependencias instaladas (tabla completa con versiones exactas)
5. Configuraciones vigentes (URLs, ARNs, IDs, buckets, etc.)
6. Patrones de código establecidos (con ejemplos)
7. Gotchas conocidos (tabla: situación → solución)
8. Documentos de referencia (tabla de todos los .md del proyecto)
9. Contexto de la sesión actual (qué se hizo hoy, próxima tarea sugerida)

Este documento debe actualizarse al cierre de cada sesión relevante.
```

---

### Paso 7 · Crear el `TODO.md` con motor JIT

El TODO.md tiene un motor de planificación just-in-time: siempre contiene
exactamente las dos siguientes tareas atómicas, calculadas comparando el PRD
con el estado real en MEMORY.md.

```
Compara el PRD.md (objetivo final del producto) con el MEMORY.md (estado real
del proyecto) y escribe un TODO.md en la raíz del proyecto con:

1. Explicación del motor JIT: cómo funciona, cómo actualizar el archivo.
2. Exactamente DOS tareas atómicas — las siguientes más prioritarias según:
   - Prioridad 1: Seguridad activa en producción (gaps de OWASP pendientes)
   - Prioridad 2: Features de Alta prioridad del roadmap
   - Prioridad 3: Features de Media prioridad
3. Para cada tarea:
   - Título con etiqueta [SEGURIDAD] o [FEATURE]
   - Origen (de qué PRD objetivo o ADR viene)
   - Archivo(s) exacto(s) a modificar o crear
   - Qué hacer (instrucciones precisas, con código si aplica)
   - Definición de done (checklist verificable)
4. Historial de tareas completadas (vacío al inicio)
5. Log del motor JIT (fecha, comparación, resultado)

No planifiques más de dos tareas. El punto es justamente no tener un backlog grande.
```

---

### Resumen del orden de creación

```
CLAUDE.md (manual)
    ↓
PRD.md          ← prompt paso 2
    ↓
tech-specs.md   ← prompt paso 3
    ↓
CLAUDE.md       ← agregar OWASP (paso 4) + git flow (paso 5)
    ↓
MEMORY.md       ← prompt paso 6
    ↓
TODO.md         ← prompt paso 7
```

---

## Parte 2: Proyecto existente

> **Audiencia:** Una IA que recibe la instrucción de documentar un proyecto
> que ya tiene código, historia de git y posiblemente documentación parcial.
> Seguir este protocolo en orden estricto.

---

### Fase 1 · Exploración en paralelo (lectura, sin modificar nada)

Lanzar agentes de exploración en paralelo para cubrir:

**Agente A — Documentación existente:**
- Leer todos los archivos `.md` en la raíz y en `docs/`.
- Leer `CLAUDE.md`, `README.md`, `AGENTS.md` si existen.
- Leer cualquier archivo de especificación (`*.json`, `*.yaml` de esquemas).

**Agente B — Arquitectura técnica:**
- Leer archivos de configuración: `package.json`, `angular.json` (o equivalente),
  `tsconfig.json`, `serverless.yml`, `docker-compose.yml`, `Dockerfile`, etc.
- Leer los archivos de configuración de la aplicación: `app.config.ts`,
  `app.routes.ts`, `main.ts`, `server.ts` o equivalentes.
- Listar la estructura completa de carpetas.

**Agente C — Código de negocio:**
- Leer los servicios principales (HTTP clients, servicios de datos, auth).
- Leer los modelos de datos / interfaces / tipos.
- Leer los componentes o vistas principales (los que representan rutas).
- Leer los guards o middleware de autenticación.

**Historia de git:**
- Ejecutar `git log --oneline -30` para entender la evolución del proyecto.
- Ejecutar `git tag -l` para ver versiones.

---

### Fase 2 · Preguntas al usuario

Antes de escribir cualquier documento, hacer al usuario las siguientes preguntas
(usar la herramienta `AskUserQuestion` si está disponible, o hacer las preguntas en texto):

1. **Audiencia:** ¿Para quién son los documentos? ¿Equipo técnico, stakeholders, inversores, o mixto?
2. **Alcance del PRD:** ¿Solo documentar lo que existe, o incluir también el roadmap futuro?
3. **Idioma:** ¿En qué idioma deben estar los documentos?
4. **Nivel de detalle técnico:** ¿Exhaustivo, referencia (medio) o resumen ejecutivo?
5. **Decisiones de arquitectura:** ¿Hay decisiones importantes que no son evidentes en el código
   y que debería documentar? (Por ejemplo: por qué se eligió X sobre Y, limitaciones conocidas,
   deuda técnica intencional.)

---

### Fase 3 · Construcción del PRD

Con la información de la exploración y las respuestas del usuario, construir `PRD.md`.

**Checklist antes de escribir:**
- [ ] ¿Entiendo el problema de negocio que resuelve el proyecto?
- [ ] ¿Identifiqué los dos o tres perfiles de usuario principales?
- [ ] ¿Tengo claro qué funcionalidades están implementadas vs. planeadas?
- [ ] ¿El usuario confirmó la prioridad del roadmap?

**Estructura obligatoria** (ver Parte 1, Paso 2 para el detalle de cada sección):
Visión → Contexto → Usuarios → Objetivos → Funcionalidades actuales →
Roadmap → Casos de uso → Requisitos no funcionales → Restricciones → Glosario

**Regla de escritura:** En el PRD nunca usar términos como `signal`, `Lambda`,
`S3`, `Observable`. Usar lenguaje de negocio: "se actualiza automáticamente",
"el sistema guarda", "el contenido aparece en el sitio".

---

### Fase 4 · Construcción de `tech-specs.md`

Con base en los artefactos de exploración, construir `tech-specs.md`.

**Checklist antes de escribir:**
- [ ] ¿Tengo el diagrama de arquitectura completo (todas las capas y servicios externos)?
- [ ] ¿Identifiqué todos los endpoints y sus callers?
- [ ] ¿Documenté todos los modelos de datos principales?
- [ ] ¿Sé cuáles secretos/variables de entorno existen y cómo se gestionan?
- [ ] ¿Identifiqué los patrones de código no evidentes (ej. Transfer State, Zoneless)?

**Estructura obligatoria** (ver Parte 1, Paso 3 para el detalle).

**Regla:** Las Tech Specs referencian el PRD por número de sección cuando explican
el "por qué" existe algo técnico (ej. "ver PRD §5.3 para el contexto de negocio").

---

### Fase 5 · Agregar seguridad OWASP al `CLAUDE.md`

Analizar la arquitectura identificada y agregar la sección `## Seguridad (OWASP)`
al `CLAUDE.md` existente (o crearlo si no existe). Ver Parte 1, Paso 4.

**Vulnerabilidades a evaluar siempre para cualquier arquitectura web:**

| Categoría | Preguntas de evaluación |
|---|---|
| A01 Acceso roto | ¿Hay endpoints que modifican datos sin verificar identidad server-side? ¿Los guards solo existen en el cliente? |
| A02 Criptografía | ¿Hay secretos que podrían llegar al cliente? ¿Hay credenciales hardcodeadas? |
| A03 Inyección/XSS | ¿Hay `innerHTML`, `eval()`, o queries construidas con input del usuario? |
| A05 Configuración | ¿Faltan headers HTTP de seguridad? ¿El CORS es `*`? ¿Hay buckets S3 públicos? |
| A07 Autenticación | ¿Se verifican tokens server-side? ¿Hay rate limiting en endpoints de auth? |
| A08 Integridad | ¿Se valida el esquema de datos antes de persistirlos? ¿Se ejecuta `npm audit`? |
| A10 SSRF | ¿Algún endpoint hace fetch a una URL enviada por el cliente? |

---

### Fase 6 · Agregar git flow al `CLAUDE.md`

Independientemente del proyecto, agregar siempre la sección del
[Apéndice A](#apéndice-a-git-flow-obligatorio-para-agentes-ia), adaptando los nombres
de ramas al proyecto específico.

---

### Fase 7 · Construcción del `MEMORY.md`

Sintetizar todo lo aprendido en las fases anteriores en un `MEMORY.md`.

**Fuentes para construir los ADRs:**
- Historia de git (`git log`): las decisiones suelen estar en los commits de arquitectura.
- Documentación existente: READMEs, notas, comentarios en código.
- Preguntas al usuario sobre decisiones no documentadas.
- Inferencia desde las dependencias y la configuración.

**Para cada ADR identificar:**
- ¿Cuándo se tomó la decisión? (inferir del git log si no está documentada)
- ¿Qué alternativas existían?
- ¿Cuál fue la razón principal?
- ¿Qué problemas conocidos tiene esta decisión?

---

### Fase 8 · Construcción del `TODO.md` con motor JIT

Aplicar el motor JIT (ver Parte 1, Paso 7):
comparar `PRD.md` vs `MEMORY.md` → escribir exactamente 2 tareas atómicas.

**Criterio de atomicidad:** una tarea es atómica si:
- Puede completarse en una sola sesión de trabajo.
- Modifica un máximo de 3 archivos.
- Tiene una definición de done verificable sin ambigüedad.
- No depende de que otra tarea en el TODO esté completa primero
  (si hay dependencia, la tarea dependiente va en posición 2, no en posición 1).

---

### Orden de entrega de los documentos

```
1. PRD.md              → negocio primero, valida entendimiento con el usuario
2. tech-specs.md       → técnico, referencia el PRD
3. CLAUDE.md           → agregar secciones OWASP + git flow
4. MEMORY.md           → síntesis de todo lo anterior + ADRs
5. TODO.md             → motor JIT: 2 tareas atómicas
```

Si el usuario pide los documentos en otro orden, aceptarlo pero advertir que
el PRD debe estar validado antes de escribir las Tech Specs.

---

## Apéndice A: Git flow obligatorio para agentes IA

> Copiar esta sección literalmente en el `CLAUDE.md` de cualquier proyecto,
> adaptando los valores entre corchetes.
>
> **Por qué no está en letiende.co:** Este proyecto usa la rama `2025` como
> rama de desarrollo con un flujo ya establecido. Agregar estas reglas a mitad
> del desarrollo activo podría interrumpir el flujo sin planificación previa.
> Se debe incorporar al inicio del siguiente ciclo de desarrollo.

---

### Sección para copiar en `CLAUDE.md`

```markdown
## Git Flow para Agentes IA

Las siguientes reglas son **obligatorias** para cualquier agente que opere en
este repositorio. No existe excepción, aunque el usuario lo solicite explícitamente.

### Ramas protegidas

Las ramas `[RAMA_PRINCIPAL]` y `[RAMA_DESARROLLO]` están protegidas.
**Ningún agente puede hacer commits directos a ellas.**

### Protocolo obligatorio antes de cualquier cambio de código

**Paso 1 — Verificar la rama actual:**
```bash
git branch --show-current
```
Si el resultado es `[RAMA_PRINCIPAL]` o `[RAMA_DESARROLLO]`, ejecutar el Paso 2.
Si ya hay una feature branch activa, continuar desde el Paso 3.

**Paso 2 — Crear feature branch:**
```bash
# Desde [RAMA_DESARROLLO] (nunca desde [RAMA_PRINCIPAL])
git checkout [RAMA_DESARROLLO]
git pull origin [RAMA_DESARROLLO]
git checkout -b [PREFIJO]/descripcion-corta-en-kebab-case
```

Prefijos válidos:
- `feature/` — nueva funcionalidad
- `fix/` — corrección de bug
- `hotfix/` — corrección urgente (desde [RAMA_PRINCIPAL])
- `docs/` — solo documentación
- `refactor/` — refactorización sin cambio funcional

**Paso 3 — Hacer los cambios y commitear:**
```bash
# Solo después de que el build pase sin errores
[COMANDO_BUILD]   # ej: npm run build, cargo build, etc.

# Si el build falla: NO commitear. Resolver los errores primero.
git add [archivos específicos]   # Nunca `git add .` o `git add -A`
git commit -m "tipo(alcance): descripción en [IDIOMA_DEL_PROYECTO]"
```

**Paso 4 — Crear el Pull Request al finalizar:**
```bash
git push -u origin HEAD
gh pr create \
  --base [RAMA_DESARROLLO] \
  --title "tipo(alcance): descripción breve" \
  --body "$(cat <<'EOF'
## Cambios realizados
- [bullet con cada cambio]

## Cómo probar
- [pasos verificables]

## Checklist
- [ ] Build pasa sin errores
- [ ] No hay secretos hardcodeados
- [ ] Seguí las convenciones de código del proyecto

🤖 Generado con Claude Code
EOF
)"
```

### Prohibiciones absolutas

| Acción prohibida | Por qué |
|---|---|
| `git push origin [RAMA_PRINCIPAL]` | Commit directo a producción |
| `git push --force` en cualquier rama | Destruye historial |
| `git merge` de cualquier PR | Solo humanos pueden aprobar y fusionar |
| `--no-verify` en commits o pushes | Omite hooks de seguridad |
| `git add .` o `git add -A` | Puede incluir secretos o archivos no deseados |
| Commitear `secrets.ts`, `.env`, `*.pem` | Exposición de credenciales |

### El agente NUNCA debe:
- Fusionar un PR (ni con `gh pr merge`, ni con `git merge`).
- Aprobar su propio PR.
- Cerrar un PR sin fusionar si el trabajo está completo — dejarlo abierto para revisión humana.
- Crear un PR hacia `[RAMA_PRINCIPAL]` directamente (siempre hacia `[RAMA_DESARROLLO]` primero,
  excepto hotfixes documentados).
```

---

### Valores a reemplazar

| Placeholder | Ejemplo Angular/Node | Ejemplo Python | Ejemplo otro |
|---|---|---|---|
| `[RAMA_PRINCIPAL]` | `main` | `main` | `master` |
| `[RAMA_DESARROLLO]` | `develop` o `2025` | `develop` | `dev` |
| `[PREFIJO]` | `feature`, `fix`, `docs` | igual | igual |
| `[COMANDO_BUILD]` | `npm run build` | `python -m pytest` | `make build` |
| `[IDIOMA_DEL_PROYECTO]` | `español colombiano` | `inglés` | el que aplique |

---

## Apéndice B: Documentos resultantes y su propósito

| Archivo | Ubicación | Quién lo lee | Cuándo actualizarlo |
|---|---|---|---|
| `CLAUDE.md` | Raíz | IA en cada sesión | Al agregar nuevas convenciones o tecnologías |
| `PRD.md` | Raíz | Humanos + IA | Al completar funcionalidades o redefinir el roadmap |
| `tech-specs.md` | Raíz | Desarrolladores + IA | Al cambiar arquitectura, agregar dependencias, modificar APIs |
| `MEMORY.md` | Raíz | IA al inicio de cada sesión | Al cerrar cada sesión de trabajo relevante |
| `TODO.md` | Raíz | IA al inicio de cada sesión | Al completar cualquiera de las dos tareas activas |
| `docs/instrucciones-inicio.md` | `docs/` | Humanos al iniciar proyectos nuevos | Al refinar el proceso |
