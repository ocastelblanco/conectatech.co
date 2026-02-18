# 004: Estructura de Archivos y Proyecto

**Severidad:** Warning  
**Categoría:** Arquitectura  
**Desde:** Angular 21+

## Regla

Organizar el proyecto por features (funcionalidades), no por tipo de archivo. Agrupar archivos relacionados en el mismo directorio.

## Convenciones de Nombres

| Tipo | Patrón | Ejemplo |
|------|--------|---------|
| Componente | `kebab-case.component.ts` | `user-profile.component.ts` |
| Servicio | `kebab-case.service.ts` | `auth.service.ts` |
| Directiva | `kebab-case.directive.ts` | `highlight.directive.ts` |
| Pipe | `kebab-case.pipe.ts` | `currency-format.pipe.ts` |
| Guard | `kebab-case.guard.ts` | `auth.guard.ts` |
| Interceptor | `kebab-case.interceptor.ts` | `error.interceptor.ts` |
| Test | `kebab-case.spec.ts` | `user-profile.spec.ts` |

## Estructura por Features

```text
src/
├── app/
│   ├── core/                    # Singleton services, guards, interceptors
│   │   ├── auth/
│   │   │   ├── auth.service.ts
│   │   │   ├── auth.guard.ts
│   │   │   └── auth.interceptor.ts
│   │   └── error/
│   │       └── global-error.handler.ts
│   │
│   ├── shared/                  # Componentes/pipes/directivas reutilizables
│   │   ├── components/
│   │   │   ├── button/
│   │   │   │   ├── button.component.ts
│   │   │   │   ├── button.component.html
│   │   │   │   ├── button.component.scss
│   │   │   │   └── button.spec.ts
│   │   │   └── modal/
│   │   ├── pipes/
│   │   └── directives/
│   │
│   ├── features/                # Módulos de funcionalidad
│   │   ├── users/
│   │   │   ├── user-list/
│   │   │   │   ├── user-list.component.ts
│   │   │   │   ├── user-list.component.html
│   │   │   │   └── user-list.spec.ts
│   │   │   ├── user-detail/
│   │   │   ├── user.routes.ts
│   │   │   └── user.service.ts
│   │   │
│   │   └── orders/
│   │       ├── order-list/
│   │       ├── order-detail/
│   │       ├── order.routes.ts
│   │       └── order.service.ts
│   │
│   ├── app.component.ts
│   ├── app.config.ts
│   └── app.routes.ts
│
└── main.ts
```

## Incorrecto

```text
# ❌ Organización por tipo
src/
├── components/
│   ├── user-list.component.ts
│   ├── user-detail.component.ts
│   ├── order-list.component.ts
│   └── order-detail.component.ts
├── services/
│   ├── user.service.ts
│   └── order.service.ts
└── tests/
    └── *.spec.ts
```

## Archivos de Componente

Mantener archivos relacionados juntos:

```text
user-profile/
├── user-profile.component.ts      # Clase del componente
├── user-profile.component.html    # Template (opcional si es inline)
├── user-profile.component.scss    # Estilos
└── user-profile.spec.ts           # Tests
```

## Un Concepto por Archivo

```typescript
// ❌ Múltiples componentes en un archivo
// user-components.ts
@Component({...}) export class UserList {}
@Component({...}) export class UserDetail {}

// ✅ Un componente por archivo
// user-list.component.ts
@Component({...}) export class UserList {}

// user-detail.component.ts  
@Component({...}) export class UserDetail {}
```

## Barrel Exports (Opcional)

Para features con muchos exports, usar `index.ts`:

```typescript
// features/users/index.ts
export * from './user-list/user-list.component';
export * from './user-detail/user-detail.component';
export * from './user.service';
```

## Auto-fix

El agente debe:
1. Renombrar archivos que no sigan la convención kebab-case
2. Mover tests `.spec.ts` junto al código que prueban
3. Sugerir reorganización si detecta estructura por tipo
4. Crear estructura de directorios correcta al generar nuevos componentes
