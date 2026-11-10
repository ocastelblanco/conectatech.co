---
name: project-docs-bootstrap
description: Bootstrap complete AI-optimized project documentation. Use when asked to "documenta este proyecto", "crea el PRD", "inicializa la documentación", "empieza la documentación", "instrucciones de inicio", or when starting any new or existing project that needs structured docs. Creates CLAUDE.md, PRD.md, tech-specs.md, MEMORY.md, and TODO.md with JIT engine (exactly 2 atomic tasks at all times).
allowed-tools: Read, Glob, Bash(git log --oneline -30), Bash(git tag -l), Write
---

# Project Docs Bootstrap

Crea un sistema de documentación especializada para que cualquier IA (o desarrollador) pueda retomar el proyecto sin contexto previo. Produce cinco documentos en orden fijo.

**Orden de entrega obligatorio:**
```
CLAUDE.md → PRD.md → tech-specs.md → CLAUDE.md (OWASP + git flow) → MEMORY.md → TODO.md
```

> Guía narrativa equivalente, pensada para que un humano copie los prompts manualmente sin invocar al agente: [references/instrucciones-inicio.md](references/instrucciones-inicio.md).

---

## Detección de ruta

**Proyecto nuevo** → Sigue [Parte 1](#parte-1-proyecto-nuevo): guía al humano con prompts exactos para cada paso.

**Proyecto existente** (hay código, historial git, o documentación parcial) → Sigue [Parte 2](#parte-2-proyecto-existente): exploración autónoma primero, preguntas después.

---

## Parte 1: Proyecto nuevo

Entregar estos prompts al humano en orden. Esperar su respuesta antes de continuar.

### Paso 1 — CLAUDE.md inicial (humano lo crea manualmente)

Indicar al humano que cree `CLAUDE.md` con: descripción del proyecto, stack con versiones exactas, estructura de carpetas, convenciones de código, idioma del proyecto. Luego sugerirle este prompt para la IA:

> "Lee el CLAUDE.md del proyecto. Identifica si hay información faltante y hazme las preguntas necesarias para completarlo."

### Paso 2 — PRD.md

```
Basándote en el CLAUDE.md, crea PRD.md con:
1. Visión del producto (tabla: nombre, tipo, público, idiomas, URLs)
2. Contexto y problema que resuelve
3. Usuarios y audiencias (tabla: perfil, necesidades)
4. Objetivos (tabla: métrica de éxito, estado: implementado/pendiente)
5. Funcionalidades actuales — con diagrama de flujo en texto para las más importantes
6. Roadmap (tabla: feature, prioridad Alta/Media/Baja)
7. Casos de uso (tabla: actor, acción, resultado esperado)
8. Requisitos no funcionales (performance, SEO, seguridad, accesibilidad)
9. Restricciones y decisiones de diseño
10. Glosario de negocio
Idioma: [IDIOMA]. Hazme las preguntas que necesites antes de escribir.
```

### Paso 3 — tech-specs.md

```
Con base en CLAUDE.md y PRD.md, crea tech-specs.md con:
1. Diagrama ASCII de arquitectura completo
2. Stack tecnológico (tabla: versión, propósito, link a docs)
3. Estructura del repositorio + path aliases
4. Frontend: patrones, rutas (tabla con guards), modelos de datos, estilos, SEO/SSR
5. Backend/APIs: tabla de endpoints (método, caller, descripción, payload)
6. Servicios externos (tabla: servicio, estado, uso actual/futuro)
7. Infraestructura: diagrama, multi-entorno (tabla: stage, URL, variables, comando)
8. Autenticación y seguridad
9. Gestión de secretos (tabla: variable, propósito, contexto)
10. Convenciones de código y git flow
11. Roadmap técnico (tabla: feature, archivos a crear, dependencias)
Nivel: referencia (suficiente para retomar sin contexto previo).
```

### Paso 4 — Seguridad OWASP en CLAUDE.md

```
Analiza tech-specs.md e identifica las vulnerabilidades OWASP Top 10:2025 relevantes
para ESTA arquitectura. Agrega ## Seguridad (OWASP) al CLAUDE.md con:
- Qué está en riesgo en esta arquitectura concreta
- Regla de código exacta (con ejemplo si es necesario)
- Tabla de prohibiciones absolutas
```

Evaluar siempre: A01 (acceso roto, incluye SSRF), A02 (configuración), A03 (cadena de suministro), A04 (criptografía), A05 (inyección/XSS), A07 (autenticación), A08 (integridad), A10 (manejo de condiciones excepcionales).

Las reglas que se escriben aquí las verifica después la skill [`owasp-security-verification`](../owasp-security-verification/), contra el código y con evidencia.

### Paso 5 — Git flow en CLAUDE.md

Agregar la sección `## Git Flow para Agentes IA` del template en [references/git-flow-template.md](references/git-flow-template.md), adaptando `[RAMA_PRINCIPAL]`, `[RAMA_DESARROLLO]`, `[COMANDO_BUILD]` e `[IDIOMA_DEL_PROYECTO]`.

### Paso 6 — MEMORY.md

```
Con base en CLAUDE.md, PRD.md y tech-specs.md, crea MEMORY.md con:
1. Estado actual (tabla: versión, URLs, ramas, última sesión)
2. Funcionalidades completadas vs. pendientes (checkboxes)
3. ADRs — para cada decisión importante: fecha, estado, decisión, razón, consecuencias
4. Dependencias instaladas (tabla con versiones exactas)
5. Configuraciones vigentes (URLs, ARNs, IDs, buckets)
6. Patrones de código establecidos (con ejemplos)
7. Gotchas conocidos (tabla: situación → solución)
8. Documentos de referencia (tabla de todos los .md)
9. Contexto de la sesión actual (qué se hizo hoy, próxima tarea sugerida)
Actualizar al cierre de cada sesión relevante.
```

### Paso 7 — TODO.md con motor JIT

Ver [Motor JIT](#motor-jit) — siempre exactamente 2 tareas atómicas.

---

## Parte 2: Proyecto existente

### Fase 1 — Exploración en paralelo (solo lectura)

Lanzar simultáneamente:

**Agente A — Documentación:** todos los `.md` en raíz y `docs/`. Leer `CLAUDE.md`, `README.md`, `AGENTS.md`.

**Agente B — Arquitectura:** `package.json`, `angular.json`, `tsconfig.json`, `serverless.yml`, `docker-compose.yml`, `Dockerfile`, archivos de configuración de la app (`app.config.ts`, `app.routes.ts`, `main.ts`, `server.ts`). Listar estructura completa de carpetas.

**Agente C — Código de negocio:** servicios principales, modelos de datos/interfaces, componentes de rutas, guards/middleware de auth.

**Historia:** `git log --oneline -30` y `git tag -l`.

### Fase 2 — Preguntas al usuario (antes de escribir nada)

1. **Audiencia:** ¿Equipo técnico, stakeholders, inversores, o mixto?
2. **Alcance PRD:** ¿Solo lo que existe o incluir roadmap futuro?
3. **Idioma:** ¿En qué idioma deben estar los documentos?
4. **Detalle técnico:** ¿Exhaustivo, referencia (medio) o resumen ejecutivo?
5. **Decisiones no evidentes:** ¿Hay ADRs no documentados que deba capturar?

### Fase 3 — PRD.md

Aplicar checklist antes de escribir:
- [ ] ¿Entiendo el problema de negocio?
- [ ] ¿Identifiqué los 2-3 perfiles de usuario principales?
- [ ] ¿Tengo claro qué funcionalidades están implementadas vs. planeadas?
- [ ] ¿El usuario confirmó la prioridad del roadmap?

**Regla crítica:** En el PRD NO usar `signal`, `Lambda`, `S3`, `Observable`. Usar lenguaje de negocio: "se actualiza automáticamente", "el sistema guarda", "el contenido aparece en el sitio".

### Fases 4-8

Seguir el mismo contenido que Pasos 3-7 de la Parte 1, en este orden:
`tech-specs.md` → `CLAUDE.md (OWASP)` → `CLAUDE.md (git flow)` → `MEMORY.md` → `TODO.md`

**Regla:** tech-specs referencia el PRD por número de sección cuando explica el "por qué" de algo técnico (ej. "ver PRD §5.3").

**Fuentes para ADRs:** `git log` (decisiones en commits de arquitectura), documentación existente, preguntas al usuario, inferencia desde dependencias.

---

## Motor JIT

El `TODO.md` tiene siempre **exactamente 2 tareas atómicas**. No más.

**Prioridad de selección:**
1. Seguridad activa en producción (gaps OWASP pendientes)
2. Features de Alta prioridad del roadmap
3. Features de Media prioridad

**Criterio de atomicidad** — una tarea es atómica si:
- Completable en una sola sesión de trabajo
- Modifica máximo 3 archivos
- Tiene definición de done verificable sin ambigüedad
- No depende de que la otra tarea del TODO esté completa primero

**Estructura de cada tarea en TODO.md:**
```markdown
## Tarea N — [SEGURIDAD] o [FEATURE]: Título

**Origen:** Qué PRD objetivo o ADR la origina
**Archivos:** Lista exacta de archivos a modificar o crear
**Qué hacer:** Instrucciones precisas (con código si aplica)
**Definition of done:**
- [ ] Criterio verificable 1
- [ ] Criterio verificable 2
```

**Actualizar TODO.md** al completar cualquier tarea: eliminarla, moverla al historial, y calcular la siguiente más prioritaria comparando PRD.md vs MEMORY.md.

---

## Tabla de documentos resultantes

| Archivo | Quién lo lee | Cuándo actualizarlo |
|---------|-------------|---------------------|
| `CLAUDE.md` | IA en cada sesión | Al agregar convenciones o tecnologías |
| `PRD.md` | Humanos + IA | Al completar funcionalidades o redefinir roadmap |
| `tech-specs.md` | Desarrolladores + IA | Al cambiar arquitectura, agregar dependencias, modificar APIs |
| `MEMORY.md` | IA al inicio de sesión | Al cerrar cada sesión de trabajo relevante |
| `TODO.md` | IA al inicio de sesión | Al completar cualquiera de las dos tareas activas |
