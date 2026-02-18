# 021: Flujos de Error Reactivos

**Severidad:** Warning  
**Categoría:** Manejo de Errores  
**Desde:** Angular 21+

## Regla

Manejar errores en streams reactivos (Observables y Signals) sin romper el flujo de datos. Usar operadores de recuperación y estados de error explícitos.

## Rationale

- Un error no capturado termina el Observable
- Los usuarios necesitan feedback sobre errores sin perder funcionalidad
- El estado de error debe ser parte del modelo de datos
- Permite retry y recuperación sin recargar la página

## Patrón: Estado con Error Explícito

### Con Signals

```typescript
// Estado que incluye loading, data y error
interface AsyncState<T> {
  loading: boolean;
  data: T | null;
  error: string | null;
}

@Component({...})
export class UserListComponent {
  private readonly userService = inject(UserService);
  
  readonly state = signal<AsyncState<User[]>>({
    loading: true,
    data: null,
    error: null
  });

  constructor() {
    this.loadUsers();
  }

  loadUsers() {
    this.state.set({ loading: true, data: null, error: null });
    
    this.userService.getUsers().subscribe({
      next: (users) => {
        this.state.set({ loading: false, data: users, error: null });
      },
      error: (err) => {
        this.state.set({ 
          loading: false, 
          data: null, 
          error: err.message 
        });
      }
    });
  }

  // Computed para acceso fácil
  readonly isLoading = computed(() => this.state().loading);
  readonly users = computed(() => this.state().data ?? []);
  readonly error = computed(() => this.state().error);
}
```

### Template con Estados

```html
@if (isLoading()) {
  <app-skeleton-list />
} @else if (error(); as errorMsg) {
  <app-error-state 
    [message]="errorMsg" 
    (retry)="loadUsers()" />
} @else {
  @for (user of users(); track user.id) {
    <app-user-card [user]="user" />
  } @empty {
    <app-empty-state message="No hay usuarios" />
  }
}
```

## Patrón: catchError con Valor por Defecto

```typescript
// No romper el stream, devolver valor por defecto
readonly users$ = this.userService.getUsers().pipe(
  catchError((error) => {
    console.error('Error cargando usuarios:', error);
    return of([]); // Array vacío como fallback
  })
);
```

## Patrón: catchError con Re-throw Transformado

```typescript
// Transformar error pero mantenerlo como error
readonly user$ = this.route.params.pipe(
  switchMap(params => this.userService.getUser(params['id'])),
  catchError((error: ApiError) => {
    if (error.status === 404) {
      return throwError(() => new Error('Usuario no encontrado'));
    }
    return throwError(() => error);
  })
);
```

## Patrón: toSignal con Manejo de Errores

```typescript
import { toSignal } from '@angular/core/rxjs-interop';

@Component({...})
export class DashboardComponent {
  private readonly dataService = inject(DataService);
  
  // toSignal con valor inicial (no lanza error, devuelve initial)
  readonly stats = toSignal(
    this.dataService.getStats().pipe(
      catchError(() => of(null))
    ),
    { initialValue: null }
  );
  
  // Computed para detectar error (null = error o sin datos)
  readonly hasError = computed(() => this.stats() === null);
}
```

## Patrón: Retry con Estado

```typescript
@Component({...})
export class DataComponent {
  private readonly dataService = inject(DataService);
  private readonly retryTrigger = new Subject<void>();
  
  readonly data$ = this.retryTrigger.pipe(
    startWith(undefined),
    switchMap(() => this.dataService.getData().pipe(
      catchError((error) => {
        // Log pero no terminar el stream externo
        console.error(error);
        return EMPTY; // No emitir nada, esperar retry
      })
    ))
  );

  retry() {
    this.retryTrigger.next();
  }
}
```

## Patrón: Múltiples Fuentes con Errores Independientes

```typescript
@Component({...})
export class DashboardComponent {
  private readonly userService = inject(UserService);
  private readonly orderService = inject(OrderService);
  
  // Cada stream maneja su error independientemente
  readonly user$ = this.userService.getCurrentUser().pipe(
    catchError(() => of(null))
  );
  
  readonly orders$ = this.orderService.getRecentOrders().pipe(
    catchError(() => of([]))
  );
  
  // Combinar sin que un error afecte al otro
  readonly vm$ = combineLatest({
    user: this.user$,
    orders: this.orders$
  });
}
```

## Patrón: Error Boundary con Componente

```typescript
// error-boundary.component.ts
@Component({
  selector: 'app-error-boundary',
  template: `
    @if (error()) {
      <div class="error-container">
        <h3>Algo salió mal</h3>
        <p>{{ error() }}</p>
        <button (click)="retry.emit()">Reintentar</button>
      </div>
    } @else {
      <ng-content />
    }
  `
})
export class ErrorBoundaryComponent {
  readonly error = input<string | null>(null);
  readonly retry = output<void>();
}

// Uso
@Component({
  template: `
    <app-error-boundary [error]="usersError()" (retry)="loadUsers()">
      @for (user of users(); track user.id) {
        <app-user-card [user]="user" />
      }
    </app-error-boundary>
  `
})
export class UserListComponent {
  readonly users = signal<User[]>([]);
  readonly usersError = signal<string | null>(null);
  
  loadUsers() {
    this.usersError.set(null);
    this.userService.getUsers().subscribe({
      next: (users) => this.users.set(users),
      error: (err) => this.usersError.set(err.message)
    });
  }
}
```

## Operadores Útiles para Errores

| Operador | Uso |
|----------|-----|
| `catchError` | Capturar y transformar/recuperar |
| `retry` | Reintentar N veces |
| `retryWhen` | Retry con lógica personalizada |
| `throwError` | Crear Observable que emite error |
| `EMPTY` | Observable que completa sin emitir |
| `of` | Emitir valor por defecto |
| `finalize` | Ejecutar siempre (éxito o error) |

## Anti-patrones

```typescript
// ❌ No capturar error - rompe el stream
this.data$ = this.service.getData();

// ❌ Capturar pero no hacer nada útil
catchError(() => EMPTY)  // Silencia el error sin feedback

// ❌ Subscribe sin error handler
this.service.getData().subscribe(data => this.data = data);
// Si hay error, se propaga como excepción no manejada
```

## Auto-fix

El agente debe:
1. Siempre incluir manejo de error en `subscribe()` o usar `catchError`
2. Modelar estado con `loading`, `data`, `error`
3. Proporcionar feedback de error en UI
4. Permitir retry sin recargar página
5. No silenciar errores sin logging
