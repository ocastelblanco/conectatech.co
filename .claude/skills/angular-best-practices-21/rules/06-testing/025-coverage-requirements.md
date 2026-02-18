# 025: Requisitos de Cobertura

**Severidad:** Warning  
**Categoría:** Testing  
**Desde:** Angular 21+

## Regla

Mantener cobertura de código mínima del 80% en líneas, ramas y funciones. Configurar umbrales en CI/CD para prevenir regresiones.

## Rationale

- Cobertura alta indica tests comprehensivos
- Previene código no testeado en producción
- Facilita refactoring con confianza
- Identifica código muerto o inalcanzable

## Ejecutar Tests con Cobertura

```bash
# Generar reporte de cobertura
ng test --coverage

# Single run con cobertura (CI)
ng test --no-watch --coverage
```

El reporte se genera en `coverage/index.html`.

## Configuración de Umbrales

### angular.json

```json
{
  "test": {
    "options": {
      "coverage": true
    },
    "configurations": {
      "ci": {
        "watch": false,
        "browsers": ["ChromeHeadless"]
      }
    }
  }
}
```

### Vitest Config (si usas configuración custom)

```typescript
// vitest.config.ts
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html', 'lcov'],
      reportsDirectory: './coverage',
      thresholds: {
        lines: 80,
        branches: 80,
        functions: 80,
        statements: 80
      },
      exclude: [
        'node_modules/',
        'src/test.ts',
        'src/**/*.spec.ts',
        'src/**/*.mock.ts',
        'src/environments/',
        'src/main.ts'
      ]
    }
  }
});
```

## Métricas de Cobertura

| Métrica | Descripción | Umbral Recomendado |
|---------|-------------|-------------------|
| Lines | Líneas de código ejecutadas | ≥ 80% |
| Branches | Ramas condicionales (if/else) | ≥ 80% |
| Functions | Funciones/métodos llamados | ≥ 80% |
| Statements | Declaraciones ejecutadas | ≥ 80% |

## CI/CD Integration

### GitHub Actions

```yaml
# .github/workflows/test.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '20'
          cache: 'npm'
      
      - name: Install dependencies
        run: npm ci
      
      - name: Run tests with coverage
        run: npm test -- --no-watch --coverage
      
      - name: Check coverage thresholds
        run: |
          # Vitest falla automáticamente si no cumple umbrales
          # O usar herramienta como nyc check-coverage
          
      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage/lcov.info
          fail_ci_if_error: true
```

### GitLab CI

```yaml
# .gitlab-ci.yml
test:
  stage: test
  script:
    - npm ci
    - npm test -- --no-watch --coverage
  coverage: '/All files[^|]*\|[^|]*\s+([\d\.]+)/'
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage/cobertura-coverage.xml
```

## Excluir Archivos de Cobertura

Algunos archivos no necesitan cobertura:

```typescript
// vitest.config.ts o jest.config.ts
exclude: [
  // Archivos de configuración
  'src/main.ts',
  'src/polyfills.ts',
  'src/environments/**',
  
  // Archivos de test
  'src/**/*.spec.ts',
  'src/**/*.mock.ts',
  'src/test-setup.ts',
  
  // Módulos legacy o third-party
  'src/legacy/**',
  
  // Archivos generados
  'src/**/*.generated.ts'
]
```

## Qué Testear vs Qué No Testear

### Priorizar Testing

```typescript
// ✅ Lógica de negocio en servicios
// ✅ Transformaciones de datos
// ✅ Validaciones
// ✅ Comportamiento de componentes críticos
// ✅ Guards y resolvers
// ✅ Interceptors
// ✅ Pipes personalizados
```

### Menor Prioridad

```typescript
// ⚠️ Componentes puramente presentacionales (poco lógica)
// ⚠️ Wrappers de librerías externas
// ⚠️ Código generado
// ⚠️ Configuración estática
```

## Istanbul Ignore

Para excluir código específico:

```typescript
/* istanbul ignore next */
function legacyFunction() {
  // Código legacy que no se puede testear fácilmente
}

/* istanbul ignore if */
if (process.env.DEBUG) {
  console.log('Debug mode');
}

// Vitest/c8 también soporta
/* v8 ignore next */
/* c8 ignore next */
```

## Reporte de Cobertura en PR

Ejemplo de comentario automático en PRs:

```markdown
## Coverage Report

| File | Lines | Branches | Functions |
|------|-------|----------|-----------|
| src/app/services/user.service.ts | 95% | 90% | 100% |
| src/app/components/login/login.component.ts | 85% | 80% | 88% |

**Overall: 87% lines, 82% branches, 91% functions**

✅ All thresholds met
```

## Comandos Útiles

```bash
# Ver cobertura en terminal
ng test --no-watch --coverage

# Abrir reporte HTML
open coverage/index.html  # macOS
xdg-open coverage/index.html  # Linux
start coverage/index.html  # Windows

# Cobertura de archivo específico
ng test --include=**/user.service.spec.ts --coverage
```

## Anti-patrones de Cobertura

```typescript
// ❌ Tests que solo llaman métodos sin verificar
it('should call method', () => {
  service.doSomething();  // Sin expect
});

// ❌ Cobertura artificial
it('should cover all lines', () => {
  const result = service.process(null);  // Input inválido solo para cubrir rama
});

// ❌ Ignorar demasiado código
/* istanbul ignore next */
export class EntireClass { }  // No debería ignorarse toda una clase
```

## Auto-fix

El agente debe:
1. Configurar `--coverage` en scripts de test
2. Establecer umbrales mínimos de 80%
3. Excluir archivos apropiados (config, mocks, environments)
4. Integrar reporte en CI/CD
5. No usar `istanbul ignore` excepto casos justificados
