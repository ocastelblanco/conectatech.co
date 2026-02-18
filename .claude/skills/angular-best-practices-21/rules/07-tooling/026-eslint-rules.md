# 026: Configuración de ESLint

**Severidad:** Warning  
**Categoría:** Tooling  
**Desde:** Angular 21+

## Regla

Configurar ESLint con reglas específicas para Angular que refuercen las mejores prácticas y detecten anti-patrones automáticamente.

## Rationale

- Detecta errores antes de ejecutar la aplicación
- Refuerza convenciones del equipo automáticamente
- Previene anti-patrones comunes de Angular
- Integración con CI/CD para calidad de código

## Instalación

```bash
ng add @angular-eslint/schematics
```

## Configuración Base

```javascript
// eslint.config.js (flat config - ESLint 9+)
import angular from '@angular-eslint/eslint-plugin';
import angularTemplate from '@angular-eslint/eslint-plugin-template';
import tsParser from '@typescript-eslint/parser';
import templateParser from '@angular-eslint/template-parser';

export default [
  {
    files: ['**/*.ts'],
    languageOptions: {
      parser: tsParser,
      parserOptions: {
        project: './tsconfig.json'
      }
    },
    plugins: {
      '@angular-eslint': angular
    },
    rules: {
      // Arquitectura
      '@angular-eslint/prefer-standalone': 'error',
      '@angular-eslint/prefer-on-push-component-change-detection': 'error',
      
      // Signals
      '@angular-eslint/prefer-signals': 'warn',
      
      // Componentes
      '@angular-eslint/component-class-suffix': 'error',
      '@angular-eslint/component-selector': ['error', {
        type: 'element',
        prefix: 'app',
        style: 'kebab-case'
      }],
      '@angular-eslint/directive-selector': ['error', {
        type: 'attribute',
        prefix: 'app',
        style: 'camelCase'
      }],
      
      // Lifecycle
      '@angular-eslint/no-empty-lifecycle-method': 'warn',
      '@angular-eslint/use-lifecycle-interface': 'error',
      
      // Inputs/Outputs
      '@angular-eslint/no-input-rename': 'error',
      '@angular-eslint/no-output-rename': 'error',
      '@angular-eslint/no-output-native': 'error',
      
      // Inyección
      '@angular-eslint/prefer-inject': 'warn',
      
      // Seguridad
      '@angular-eslint/no-host-metadata-property': 'error',
      
      // Buenas prácticas
      '@angular-eslint/no-pipe-impure': 'warn',
      '@angular-eslint/relative-url-prefix': 'error'
    }
  },
  {
    files: ['**/*.html'],
    languageOptions: {
      parser: templateParser
    },
    plugins: {
      '@angular-eslint/template': angularTemplate
    },
    rules: {
      // Accesibilidad
      '@angular-eslint/template/accessibility-alt-text': 'error',
      '@angular-eslint/template/accessibility-elements-content': 'error',
      '@angular-eslint/template/accessibility-label-for': 'error',
      '@angular-eslint/template/accessibility-valid-aria': 'error',
      '@angular-eslint/template/click-events-have-key-events': 'warn',
      '@angular-eslint/template/mouse-events-have-key-events': 'warn',
      '@angular-eslint/template/no-autofocus': 'warn',
      
      // Control flow moderno
      '@angular-eslint/template/prefer-control-flow': 'error',
      '@angular-eslint/template/prefer-self-closing-tags': 'warn',
      
      // Seguridad
      '@angular-eslint/template/no-inline-styles': 'warn',
      
      // Rendimiento
      '@angular-eslint/template/no-call-expression': 'warn',
      
      // Buenas prácticas
      '@angular-eslint/template/banana-in-box': 'error',
      '@angular-eslint/template/eqeqeq': ['error', { allowNullOrUndefined: true }],
      '@angular-eslint/template/no-negated-async': 'error'
    }
  }
];
```

## Reglas Críticas Explicadas

### prefer-standalone
```typescript
// ❌ Error
@Component({ ... })  // Sin standalone: true

// ✅ Correcto  
@Component({ standalone: true, ... })
```

### prefer-on-push-component-change-detection
```typescript
// ❌ Error
@Component({ ... })  // Usa Default

// ✅ Correcto
@Component({ changeDetection: ChangeDetectionStrategy.OnPush, ... })
```

### prefer-control-flow (templates)
```html
<!-- ❌ Error -->
<div *ngIf="condition">...</div>

<!-- ✅ Correcto -->
@if (condition) {
  <div>...</div>
}
```

### no-call-expression (templates)
```html
<!-- ❌ Warning - se ejecuta en cada change detection -->
<p>{{ calculateTotal() }}</p>

<!-- ✅ Correcto - usar computed signal -->
<p>{{ total() }}</p>
```

## Integración con Scripts

```json
// package.json
{
  "scripts": {
    "lint": "ng lint",
    "lint:fix": "ng lint --fix",
    "lint:ci": "ng lint --format json --output-file eslint-report.json"
  }
}
```

## Configuración de CI

```yaml
# .github/workflows/lint.yml
name: Lint
on: [push, pull_request]
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: npm ci
      - run: npm run lint:ci
      - name: Annotate Code
        uses: ataylorme/eslint-annotate-action@v2
        with:
          report-json: eslint-report.json
```

## Ignorar Archivos

```javascript
// eslint.config.js
export default [
  {
    ignores: [
      'dist/',
      'coverage/',
      'node_modules/',
      '**/*.spec.ts',  // Si prefieres no lint en tests
      'src/environments/'
    ]
  },
  // ... resto de config
];
```

## Integración con Editor

### VS Code
```json
// .vscode/settings.json
{
  "editor.codeActionsOnSave": {
    "source.fixAll.eslint": "explicit"
  },
  "eslint.validate": [
    "typescript",
    "html"
  ]
}
```

## Auto-fix

El agente debe:
1. Configurar ESLint con `@angular-eslint`
2. Habilitar reglas de standalone, OnPush, control flow
3. Habilitar reglas de accesibilidad en templates
4. Agregar scripts de lint a package.json
5. Configurar VS Code para fix automático
