# 028: Optimizaciones de Build

**Severidad:** Warning  
**Categoría:** Tooling  
**Desde:** Angular 21+

## Regla

Configurar optimizaciones de build para producción que reduzcan el tamaño del bundle, mejoren tiempos de carga y optimicen el rendimiento en runtime.

## Rationale

- Bundle más pequeño = carga más rápida
- Mejor score en Core Web Vitals
- Reducción de costos de hosting y CDN
- Mejor experiencia de usuario

## Configuración Óptima de Producción

```json
// angular.json
{
  "build": {
    "configurations": {
      "production": {
        "budgets": [
          {
            "type": "initial",
            "maximumWarning": "500kB",
            "maximumError": "1MB"
          },
          {
            "type": "anyComponentStyle",
            "maximumWarning": "4kB",
            "maximumError": "8kB"
          }
        ],
        "outputHashing": "all",
        "optimization": {
          "scripts": true,
          "styles": {
            "minify": true,
            "inlineCritical": true
          },
          "fonts": {
            "inline": true
          }
        },
        "sourceMap": false,
        "namedChunks": false,
        "aot": true,
        "extractLicenses": true,
        "subresourceIntegrity": true
      }
    }
  }
}
```

## Budgets de Bundle

Define límites para detectar crecimiento inesperado:

```json
"budgets": [
  {
    "type": "initial",
    "maximumWarning": "500kB",
    "maximumError": "1MB"
  },
  {
    "type": "anyComponentStyle",
    "maximumWarning": "4kB",
    "maximumError": "8kB"
  },
  {
    "type": "anyScript",
    "maximumWarning": "100kB",
    "maximumError": "200kB"
  }
]
```

Tipos disponibles:
- `initial`: Bundle inicial (main + polyfills)
- `anyComponentStyle`: Estilos de cualquier componente
- `anyScript`: Cualquier chunk de JS
- `bundle`: Bundle específico por nombre
- `all`: Todo el build

## Análisis de Bundle

```bash
# Generar estadísticas
ng build --stats-json

# Analizar con webpack-bundle-analyzer
npx webpack-bundle-analyzer dist/my-app/stats.json
```

## Lazy Loading Efectivo

### Rutas
```typescript
// app.routes.ts
export const routes: Routes = [
  { path: '', component: HomeComponent },
  { 
    path: 'admin',
    loadComponent: () => import('./admin/admin.component')
      .then(m => m.AdminComponent)
  },
  {
    path: 'reports',
    loadChildren: () => import('./reports/reports.routes')
      .then(m => m.REPORTS_ROUTES)
  }
];
```

### Componentes en Templates
```html
@defer (on viewport) {
  <app-heavy-chart [data]="chartData()" />
} @placeholder {
  <div class="chart-skeleton"></div>
} @loading (minimum 200ms) {
  <app-spinner />
}
```

## Preloading Strategy

```typescript
// app.config.ts
import { PreloadAllModules } from '@angular/router';

export const appConfig: ApplicationConfig = {
  providers: [
    provideRouter(routes, 
      withPreloading(PreloadAllModules)
    )
  ]
};

// O estrategia selectiva
export const routes: Routes = [
  {
    path: 'dashboard',
    loadComponent: () => import('./dashboard.component'),
    data: { preload: true }
  },
  {
    path: 'settings',
    loadComponent: () => import('./settings.component'),
    data: { preload: false }  // Solo cargar si navega
  }
];
```

## Subresource Integrity (SRI)

Protege contra CDN comprometidos:

```json
"subresourceIntegrity": true
```

Genera:
```html
<script src="main.js" integrity="sha384-abc123..." crossorigin="anonymous">
```

## Optimización de Imágenes

### NgOptimizedImage
```typescript
import { NgOptimizedImage } from '@angular/common';

@Component({
  imports: [NgOptimizedImage],
  template: `
    <img ngSrc="/assets/hero.jpg" 
         width="800" 
         height="600" 
         priority />
  `
})
```

### Image Loader para CDN
```typescript
// app.config.ts
import { provideCloudinaryLoader } from '@angular/common';

export const appConfig: ApplicationConfig = {
  providers: [
    provideCloudinaryLoader('https://res.cloudinary.com/my-account')
  ]
};
```

## CSS Optimization

### Critical CSS Inlining
```json
"optimization": {
  "styles": {
    "inlineCritical": true
  }
}
```

### Eliminar CSS No Usado
```json
// angular.json
"styles": [
  {
    "input": "src/styles.scss",
    "bundleName": "styles"
  }
]
```

Usar PurgeCSS en proyectos con Tailwind:
```javascript
// tailwind.config.js
module.exports = {
  content: ['./src/**/*.{html,ts}'],
  // ...
}
```

## Server-Side Rendering (SSR)

```bash
ng add @angular/ssr
```

Beneficios:
- FCP más rápido
- Mejor SEO
- Funciona sin JS (progressive enhancement)

## Environment-based Configuration

```typescript
// environments/environment.ts
export const environment = {
  production: false,
  apiUrl: 'http://localhost:3000',
  enableDebug: true
};

// environments/environment.prod.ts
export const environment = {
  production: true,
  apiUrl: 'https://api.example.com',
  enableDebug: false
};
```

## CI/CD Build Optimization

```yaml
# GitHub Actions
- name: Build
  run: |
    ng build --configuration production
  env:
    NODE_OPTIONS: --max_old_space_size=4096

- name: Check bundle size
  run: |
    size=$(du -sb dist/my-app | cut -f1)
    if [ $size -gt 2000000 ]; then
      echo "Bundle too large: $size bytes"
      exit 1
    fi
```

## Resumen de Optimizaciones

| Optimización | Impacto | Configuración |
|--------------|---------|---------------|
| AOT | Alto | `aot: true` (default) |
| Minification | Alto | `optimization: true` |
| Tree-shaking | Alto | Automático con ES modules |
| Lazy loading | Alto | `loadComponent/loadChildren` |
| Budgets | Preventivo | `budgets: [...]` |
| SRI | Seguridad | `subresourceIntegrity: true` |
| Critical CSS | Medio | `inlineCritical: true` |
| Output hashing | Cache | `outputHashing: "all"` |

## Auto-fix

El agente debe:
1. Configurar budgets de bundle apropiados
2. Habilitar todas las optimizaciones de producción
3. Implementar lazy loading en rutas secundarias
4. Usar `NgOptimizedImage` para imágenes
5. Habilitar SRI para seguridad
6. Configurar preloading strategy apropiada
