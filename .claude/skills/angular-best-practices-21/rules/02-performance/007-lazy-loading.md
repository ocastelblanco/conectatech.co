# 006: Lazy Loading de Rutas y Componentes

**Severidad:** Critical  
**Categoría:** Rendimiento  
**Desde:** Angular 21+

## Regla

Implementar lazy loading para todas las rutas de features y componentes pesados. El bundle inicial debe contener solo lo esencial para el primer render.

## Rationale

- Reduce el tiempo de carga inicial (TTI - Time to Interactive)
- Mejora Core Web Vitals (LCP, FID)
- Carga código solo cuando el usuario lo necesita
- Permite code splitting efectivo

## Lazy Loading de Rutas

### Incorrecto

```typescript
// ❌ Importación estática - todo en el bundle inicial
import { UserListComponent } from './features/users/user-list.component';
import { OrderListComponent } from './features/orders/order-list.component';

export const routes: Routes = [
  { path: 'users', component: UserListComponent },
  { path: 'orders', component: OrderListComponent },
];
```

### Correcto

```typescript
// ✅ Lazy loading con loadComponent
export const routes: Routes = [
  { 
    path: 'users', 
    loadComponent: () => import('./features/users/user-list.component')
      .then(m => m.UserListComponent)
  },
  { 
    path: 'orders', 
    loadComponent: () => import('./features/orders/order-list.component')
      .then(m => m.OrderListComponent)
  },
];
```

### Lazy Loading de Rutas Hijas

```typescript
// ✅ Cargar archivo de rutas completo
export const routes: Routes = [
  {
    path: 'admin',
    loadChildren: () => import('./features/admin/admin.routes')
      .then(m => m.ADMIN_ROUTES)
  }
];

// features/admin/admin.routes.ts
export const ADMIN_ROUTES: Routes = [
  { 
    path: '', 
    loadComponent: () => import('./dashboard.component')
      .then(m => m.DashboardComponent)
  },
  { 
    path: 'settings', 
    loadComponent: () => import('./settings.component')
      .then(m => m.SettingsComponent)
  }
];
```

## @defer para Componentes en Templates

Angular 17+ permite lazy loading declarativo en templates:

```typescript
@Component({
  template: `
    <!-- Componente pesado cargado cuando es visible -->
    @defer (on viewport) {
      <app-heavy-chart [data]="chartData()" />
    } @placeholder {
      <div class="skeleton-chart"></div>
    } @loading (minimum 200ms) {
      <app-spinner />
    } @error {
      <p>Error cargando gráfico</p>
    }
  `
})
export class DashboardComponent {}
```

### Triggers de @defer

| Trigger | Descripción |
|---------|-------------|
| `on viewport` | Cuando entra en viewport |
| `on idle` | Cuando el browser está idle |
| `on interaction` | Al interactuar (click, focus) |
| `on hover` | Al pasar el mouse |
| `on timer(500ms)` | Después de un delay |
| `when condition` | Cuando condición es true |

### Prefetch con @defer

```html
<!-- Prefetch cuando está idle, renderiza cuando es visible -->
@defer (on viewport; prefetch on idle) {
  <app-recommendations />
}
```

## Preloading Strategies

Para mejorar UX, precargar rutas después de la carga inicial:

```typescript
// app.config.ts
import { PreloadAllModules } from '@angular/router';

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(
      routes,
      withPreloading(PreloadAllModules) // Precarga todas las rutas lazy
    )
  ]
};
```

### Preloading Selectivo

```typescript
// Preload solo rutas marcadas
export const routes: Routes = [
  {
    path: 'dashboard',
    loadComponent: () => import('./dashboard.component'),
    data: { preload: true }  // Marcar para preload
  }
];

// Custom preloading strategy
@Injectable({ providedIn: 'root' })
export class SelectivePreloadStrategy implements PreloadingStrategy {
  preload(route: Route, load: () => Observable<any>) {
    return route.data?.['preload'] ? load() : of(null);
  }
}
```

## Auto-fix

El agente debe:
1. Convertir imports estáticos de componentes de ruta a `loadComponent`
2. Crear archivos `.routes.ts` para features con múltiples rutas
3. Sugerir `@defer` para componentes pesados en templates
4. Configurar preloading strategy apropiada
