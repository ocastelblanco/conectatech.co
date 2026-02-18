# 005: Control Flow Moderno (@if, @for, @switch)

**Severidad:** Critical  
**Categoría:** Arquitectura  
**Desde:** Angular 17+

## Regla

Usar la sintaxis de control flow integrada (`@if`, `@for`, `@switch`, `@defer`) en lugar de las directivas estructurales legacy (`*ngIf`, `*ngFor`, `*ngSwitch`).

## Rationale

- Sintaxis nativa del compilador, no requiere imports
- Mejor rendimiento (optimizaciones a nivel de compilador)
- Mejor type-checking y autocompletado en IDEs
- Soporte para `@empty` en bucles y `@defer` para lazy loading
- Menos boilerplate y código más legible

## Incorrecto

```html
<!-- ❌ Directivas estructurales legacy -->
<div *ngIf="isLoading; else content">
  <app-spinner></app-spinner>
</div>
<ng-template #content>
  <p>Contenido cargado</p>
</ng-template>

<ul>
  <li *ngFor="let item of items; trackBy: trackById">
    {{ item.name }}
  </li>
</ul>

<div [ngSwitch]="status">
  <p *ngSwitchCase="'pending'">Pendiente</p>
  <p *ngSwitchCase="'completed'">Completado</p>
  <p *ngSwitchDefault>Desconocido</p>
</div>
```

## Correcto

```html
<!-- ✅ Bloques de control flow modernos -->
@if (isLoading()) {
  <app-spinner />
} @else {
  <p>Contenido cargado</p>
}

<ul>
  @for (item of items(); track item.id) {
    <li>{{ item.name }}</li>
  } @empty {
    <li>No hay items</li>
  }
</ul>

@switch (status()) {
  @case ('pending') {
    <p>Pendiente</p>
  }
  @case ('completed') {
    <p>Completado</p>
  }
  @default {
    <p>Desconocido</p>
  }
}
```

## @if con Variables Locales

```html
<!-- ✅ Variable local con @if -->
@if (user(); as currentUser) {
  <p>Bienvenido, {{ currentUser.name }}</p>
}

<!-- ✅ Múltiples condiciones -->
@if (isAdmin()) {
  <app-admin-panel />
} @else if (isEditor()) {
  <app-editor-panel />
} @else {
  <app-user-panel />
}
```

## @for con track Obligatorio

El `track` es **obligatorio** y debe ser una expresión que identifique únicamente cada item:

```html
<!-- ✅ Track por propiedad única -->
@for (user of users(); track user.id) {
  <app-user-card [user]="user" />
}

<!-- ✅ Track por índice (solo si items no tienen ID) -->
@for (item of items(); track $index) {
  <span>{{ item }}</span>
}

<!-- Variables contextuales disponibles -->
@for (item of items(); track item.id; let idx = $index, let isFirst = $first) {
  <div [class.first]="isFirst">
    {{ idx }}: {{ item.name }}
  </div>
}
```

### Variables de @for

| Variable | Tipo | Descripción |
|----------|------|-------------|
| `$index` | number | Índice actual |
| `$first` | boolean | Es el primer item |
| `$last` | boolean | Es el último item |
| `$even` | boolean | Índice par |
| `$odd` | boolean | Índice impar |
| `$count` | number | Total de items |

## @switch con Múltiples Cases (Angular 21.1+)

```html
<!-- ✅ Múltiples cases con mismo resultado -->
@switch (status()) {
  @case ('pending')
  @case ('processing') {
    <app-loading />
  }
  @case ('completed') {
    <app-success />
  }
  @default {
    <app-unknown />
  }
}
```

## Migración Automática

Angular CLI incluye un schematic para migrar:

```bash
ng generate @angular/core:control-flow
```

## Auto-fix

El agente debe:
1. Reemplazar `*ngIf` por `@if`
2. Reemplazar `*ngFor` por `@for` con `track` apropiado
3. Reemplazar `*ngSwitch/*ngSwitchCase` por `@switch/@case`
4. Eliminar imports de `NgIf`, `NgFor`, `NgSwitch` del componente
5. Convertir `ng-template` con `#else` a bloques `@else`
6. Agregar `@empty` cuando sea útil para UX
