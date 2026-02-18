# 005: ChangeDetection OnPush Obligatorio

**Severidad:** Critical  
**Categoría:** Rendimiento  
**Desde:** Angular 21+

## Regla

Todos los componentes deben usar `ChangeDetectionStrategy.OnPush` para optimizar el rendimiento de detección de cambios.

## Rationale

- OnPush reduce drásticamente los ciclos de change detection
- Solo verifica el componente cuando: sus inputs cambian (por referencia), un evento se dispara dentro del componente, o se llama `markForCheck()` explícitamente
- Con signals, OnPush funciona automáticamente ya que los signals notifican cambios
- Puede mejorar el rendimiento 10x en aplicaciones grandes

## Incorrecto

```typescript
// ❌ Default change detection (verifica en cada ciclo)
@Component({
  selector: 'app-user-list',
  standalone: true,
  template: `...`
})
export class UserListComponent {}
```

## Correcto

```typescript
// ✅ OnPush change detection
@Component({
  selector: 'app-user-list',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `...`
})
export class UserListComponent {}
```

## Consideraciones con OnPush

### 1. Inmutabilidad de Datos

OnPush compara por referencia. Mutar objetos no dispara actualización:

```typescript
// ❌ Mutación no detectada
this.users.push(newUser);

// ✅ Nueva referencia detectada
this.users = [...this.users, newUser];

// ✅ Con signals (recomendado)
this.users.update(current => [...current, newUser]);
```

### 2. Async Pipe con Observables

El async pipe llama `markForCheck()` automáticamente:

```typescript
// ✅ Async pipe maneja change detection
@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @for (user of users$ | async; track user.id) {
      <app-user-card [user]="user" />
    }
  `
})
export class UserListComponent {
  readonly users$ = inject(UserService).getUsers();
}
```

### 3. Signals (Preferido)

Los signals funcionan perfectamente con OnPush:

```typescript
@Component({
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @for (user of users(); track user.id) {
      <app-user-card [user]="user" />
    }
  `
})
export class UserListComponent {
  readonly users = signal<User[]>([]);
  
  addUser(user: User) {
    // Signal notifica cambio automáticamente
    this.users.update(current => [...current, user]);
  }
}
```

### 4. Casos que Requieren markForCheck()

En casos raros donde se actualiza estado fuera del flujo normal:

```typescript
private readonly cdr = inject(ChangeDetectorRef);

// Callback externo (ej: WebSocket, setTimeout sin Zone)
onExternalUpdate(data: Data) {
  this.data = data;
  this.cdr.markForCheck();
}
```

## Auto-fix

El agente debe:
1. Agregar `changeDetection: ChangeDetectionStrategy.OnPush` a todo componente
2. Agregar import de `ChangeDetectionStrategy` desde `@angular/core`
3. Verificar que no existan mutaciones directas de objetos/arrays
4. Sugerir conversión a signals si se detectan problemas de inmutabilidad
