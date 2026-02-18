# 027: Angular DevTools

**Severidad:** Suggestion  
**Categoría:** Tooling  
**Desde:** Angular 21+

## Regla

Utilizar Angular DevTools y herramientas de diagnóstico integradas para depurar, perfilar rendimiento e inspeccionar la estructura de la aplicación.

## Rationale

- Visualiza árbol de componentes y sus propiedades
- Identifica problemas de rendimiento con profiler
- Inspecciona signals y su estado actual
- Detecta ciclos de change detection innecesarios

## Angular DevTools (Extensión de Chrome)

### Instalación

1. Instalar desde [Chrome Web Store](https://chrome.google.com/webstore/detail/angular-devtools/ienfalfjdbdpebioblfackkekamfmbnh)
2. Abrir DevTools (F12) → Panel "Angular"

### Funcionalidades

#### Component Explorer
- Árbol jerárquico de componentes
- Propiedades: inputs, outputs, signals
- Estado interno del componente
- Edición en tiempo real de propiedades

#### Profiler
```
1. Click en "Start recording"
2. Interactuar con la aplicación
3. Click en "Stop recording"
4. Analizar ciclos de change detection
```

Métricas importantes:
- Tiempo total de change detection
- Componentes más lentos
- Frecuencia de detección

## Herramientas de Debugging Integradas

### ng.getComponent()

```typescript
// En consola del navegador
const element = document.querySelector('app-user-card');
const component = ng.getComponent(element);
console.log(component);

// Acceder a signals
component.user();  // Valor actual del signal
```

### ng.applyChanges()

```typescript
// Forzar change detection en un componente
const element = document.querySelector('app-counter');
const component = ng.getComponent(element);
component.count.set(100);
ng.applyChanges(component);
```

### Profiling de Change Detection

```typescript
// Habilitar profiling detallado (solo desarrollo)
// main.ts
import { enableProdMode, ɵsetProfiler } from '@angular/core';

if (!environment.production) {
  ɵsetProfiler((event) => {
    console.log('CD Event:', event);
  });
}
```

## provideStabilityDebugging

Identifica tareas que impiden que la app se estabilice (útil para SSR):

```typescript
// app.config.ts (solo desarrollo)
import { provideStabilityDebugging, isDevMode } from '@angular/core';

export const appConfig: ApplicationConfig = {
  providers: [
    ...(isDevMode() ? [provideStabilityDebugging()] : [])
  ]
};
```

Output en consola:
```
Angular stability prevented by:
- Pending HTTP request: GET /api/users
- Active timer: setTimeout (1000ms)
```

## Source Maps para Debugging

Asegurar source maps en desarrollo:

```json
// angular.json
{
  "build": {
    "configurations": {
      "development": {
        "sourceMap": true,
        "optimization": false
      },
      "production": {
        "sourceMap": false,
        "optimization": true
      }
    }
  }
}
```

## Performance Profiling con Chrome

### Performance Tab
1. Abrir DevTools → Performance
2. Click "Record"
3. Interactuar con la app
4. Analizar:
   - Scripting time
   - Rendering time
   - Painting time

### Memory Tab
1. Abrir DevTools → Memory
2. Take heap snapshot
3. Buscar memory leaks comparando snapshots

## Lighthouse Audits

```bash
# CLI
npm install -g lighthouse
lighthouse http://localhost:4200 --view

# O usar DevTools → Lighthouse tab
```

Métricas clave:
- First Contentful Paint (FCP)
- Largest Contentful Paint (LCP)
- Total Blocking Time (TBT)
- Cumulative Layout Shift (CLS)

## Bundle Analyzer

```bash
# Generar stats
ng build --stats-json

# Analizar
npx webpack-bundle-analyzer dist/my-app/stats.json
```

Identificar:
- Dependencias más pesadas
- Código duplicado
- Oportunidades de lazy loading

## Debugging de Signals

```typescript
// Inspeccionar signal en consola
const element = document.querySelector('app-counter');
const component = ng.getComponent(element);

// Ver valor actual
console.log(component.count());

// Ver computed
console.log(component.doubleCount());

// Modificar y observar
component.count.set(50);
ng.applyChanges(component);
```

## VS Code Extensions Recomendadas

```json
// .vscode/extensions.json
{
  "recommendations": [
    "angular.ng-template",           // Angular Language Service
    "dbaeumer.vscode-eslint",        // ESLint
    "esbenp.prettier-vscode",        // Prettier
    "christian-kohler.path-intellisense",
    "ms-vscode.vscode-typescript-next"
  ]
}
```

## Configuración de Angular Language Service

```json
// tsconfig.json
{
  "angularCompilerOptions": {
    "strictTemplates": true,
    "strictInjectionParameters": true,
    "strictInputAccessModifiers": true
  }
}
```

Beneficios:
- Autocompletado en templates
- Type checking en bindings
- Errores en tiempo real
- Go to definition

## Auto-fix

El agente debe:
1. Recomendar instalación de Angular DevTools
2. Habilitar `provideStabilityDebugging()` en desarrollo
3. Configurar source maps apropiadamente
4. Agregar extensiones recomendadas para VS Code
5. Habilitar `strictTemplates` en tsconfig
