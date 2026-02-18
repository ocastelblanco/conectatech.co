# 008: Angular sin ZoneJS (Zoneless)

**Severidad:** Warning  
**Categoría:** Rendimiento  
**Desde:** Angular 18+ (estable en 21+)

## Regla

Configurar aplicaciones nuevas sin ZoneJS usando `provideZonelessChangeDetection()`. Para aplicaciones existentes, migrar gradualmente eliminando dependencias de Zone.js.

## Rationale

- **Mejor rendimiento**: ZoneJS dispara change detection en cada evento async, aunque el estado no haya cambiado
- **Menor bundle size**: Eliminar zone.js reduce ~13KB del bundle
- **Mejor debugging**: Stack traces más limpios sin el patching de ZoneJS
- **Mejor compatibilidad**: Algunas APIs modernas (`async/await`) no funcionan bien con ZoneJS

## Habilitar Zoneless

### Nueva Aplicación

```typescript
// app.config.ts
import { ApplicationConfig, provideZonelessChangeDetection } from '@angular/core';

export const appConfig: ApplicationConfig = {
  providers: [
    provideZonelessChangeDetection(),
    // otros providers...
  ]
};
```

### Eliminar ZoneJS del Build

1. Remover de `angular.json`:

```json
{
  "build": {
    "options": {
      "polyfills": []  // Remover "zone.js"
    }
  },
  "test": {
    "options": {
      "polyfills": []  // Remover "zone.js/testing"
    }
  }
}
```

2. Desinstalar el paquete:

```bash
npm uninstall zone.js
```

## Requisitos de Compatibilidad

### 1. Usar OnPush en Todos los Componentes

Zoneless requiere que Angular sea notificado explícitamente de cambios:

```typescript
@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,  // Requerido
  // ...
})
export class MyComponent {}
```

### 2. Usar Signals para Estado Reactivo

Los signals notifican automáticamente a Angular:

```typescript
@Component({
  template: `<p>{{ count() }}</p>`
})
export class CounterComponent {
  readonly count = signal(0);
  
  increment() {
    this.count.update(c => c + 1);  // Angular detecta el cambio
  }
}
```

### 3. AsyncPipe o toSignal para Observables

```typescript
// ✅ AsyncPipe llama markForCheck automáticamente
@Component({
  template: `
    @for (user of users$ | async; track user.id) {
      <app-user [user]="user" />
    }
  `
})
export class UserListComponent {
  readonly users$ = inject(UserService).getUsers();
}

// ✅ O convertir a signal
@Component({
  template: `
    @for (user of users(); track user.id) {
      <app-user [user]="user" />
    }
  `
})
export class UserListComponent {
  private readonly userService = inject(UserService);
  readonly users = toSignal(this.userService.getUsers(), { initialValue: [] });
}
```

### 4. Evitar NgZone APIs

Remover uso de APIs que dependen de Zone:

```typescript
// ❌ No funcionan en zoneless
NgZone.onMicrotaskEmpty
NgZone.onStable
NgZone.onUnstable
NgZone.isStable

// ✅ Usar en su lugar
afterNextRender(() => { /* después del siguiente render */ });
afterEveryRender(() => { /* después de cada render */ });
```

### 5. markForCheck para Actualizaciones Manuales

Si actualizas estado fuera del flujo normal:

```typescript
private readonly cdr = inject(ChangeDetectorRef);

onExternalCallback(data: Data) {
  this.data = data;
  this.cdr.markForCheck();  // Notificar a Angular
}
```

## SSR con Zoneless

Para Server-Side Rendering, usar `PendingTasks` para tareas async:

```typescript
const pendingTasks = inject(PendingTasks);

// Método 1: run()
pendingTasks.run(async () => {
  const data = await fetchData();
  this.state.set(data);
});

// Método 2: add/remove manual
const cleanup = pendingTasks.add();
try {
  await doAsyncWork();
} finally {
  cleanup();
}
```

## Testing Zoneless

```typescript
TestBed.configureTestingModule({
  providers: [provideZonelessChangeDetection()]
});

const fixture = TestBed.createComponent(MyComponent);

// Preferir whenStable sobre detectChanges
await fixture.whenStable();
expect(fixture.nativeElement.textContent).toContain('expected');
```

## Debug de Compatibilidad

Detectar actualizaciones que no notifican a Angular:

```typescript
// Solo en desarrollo
provideCheckNoChangesConfig({
  exhaustive: true,
  interval: 1000  // Verificar cada segundo
})
```

## Checklist de Migración

- [ ] Todos los componentes usan `OnPush`
- [ ] Estado manejado con signals o markForCheck
- [ ] Observables con AsyncPipe o toSignal
- [ ] Sin uso de `NgZone.onStable/onMicrotaskEmpty`
- [ ] Tests actualizados con `await fixture.whenStable()`
- [ ] SSR usa `PendingTasks` para async

## Auto-fix

El agente debe:
1. Agregar `provideZonelessChangeDetection()` a app.config.ts
2. Verificar que todos los componentes tengan OnPush
3. Alertar sobre uso de APIs de NgZone incompatibles
4. Sugerir conversión a signals para estado reactivo
5. Remover zone.js de polyfills en angular.json
