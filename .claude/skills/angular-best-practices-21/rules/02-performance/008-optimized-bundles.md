# 007: Optimización de Bundles

**Severidad:** Critical  
**Categoría:** Rendimiento  
**Desde:** Angular 21+

## Regla

Optimizar el tamaño de bundles eliminando código no usado, evitando imports pesados y configurando correctamente el build de producción.

## Análisis de Bundle

Generar estadísticas para analizar:

```bash
ng build --stats-json
npx webpack-bundle-analyzer dist/*/stats.json
```

## Imports Específicos

### Incorrecto

```typescript
// ❌ Importa toda la librería
import * as _ from 'lodash';
import { Observable } from 'rxjs';

// ❌ CommonModule importa todo
imports: [CommonModule]
```

### Correcto

```typescript
// ✅ Import específico de función
import { debounce } from 'lodash-es';

// ✅ Import específico de operador
import { map, filter } from 'rxjs/operators';

// ✅ Pipes y directivas específicas
imports: [NgIf, NgFor, AsyncPipe, DatePipe]
```

## Eliminar Código No Usado

### Tree Shaking

Asegurar que el código sea tree-shakeable:

```typescript
// ❌ No tree-shakeable (side effects)
export class Utils {
  static formatDate(d: Date) { ... }
  static formatCurrency(n: number) { ... }
}

// ✅ Tree-shakeable (funciones individuales)
export function formatDate(d: Date) { ... }
export function formatCurrency(n: number) { ... }
```

### Servicios en Root

```typescript
// ✅ Tree-shakeable si no se inyecta
@Injectable({ providedIn: 'root' })
export class AnalyticsService { }
```

## Configuración de Build

### angular.json (producción)

```json
{
  "configurations": {
    "production": {
      "budgets": [
        {
          "type": "initial",
          "maximumWarning": "500kb",
          "maximumError": "1mb"
        },
        {
          "type": "anyComponentStyle",
          "maximumWarning": "4kb",
          "maximumError": "8kb"
        }
      ],
      "optimization": true,
      "outputHashing": "all",
      "sourceMap": false,
      "namedChunks": false
    }
  }
}
```

## Evitar Waterfalls de Imports

### Incorrecto

```typescript
// ❌ Waterfall: cada import espera al anterior
const moduleA = await import('./module-a');
const dataA = await moduleA.fetchData();

const moduleB = await import('./module-b');
const dataB = await moduleB.fetchData();
```

### Correcto

```typescript
// ✅ Paralelo: imports simultáneos
const [moduleA, moduleB] = await Promise.all([
  import('./module-a'),
  import('./module-b')
]);

const [dataA, dataB] = await Promise.all([
  moduleA.fetchData(),
  moduleB.fetchData()
]);
```

## Preferir class/style sobre ngClass/ngStyle

### Incorrecto

```html
<!-- ❌ Directivas con overhead adicional -->
<div [ngClass]="{ 'admin': isAdmin, 'active': isActive }">
  <span [ngStyle]="{ 'color': textColor, 'font-size': fontSize }"></span>
</div>
```

### Correcto

```html
<!-- ✅ Bindings nativos (más eficientes) -->
<div [class.admin]="isAdmin()" [class.active]="isActive()">
  <span [style.color]="textColor()" [style.font-size]="fontSize()"></span>
</div>

<!-- ✅ También válido con objetos -->
<div [class]="{ admin: isAdmin(), active: isActive() }">
  <span [style]="{ color: textColor(), 'font-size': fontSize() }"></span>
</div>
```

### Rationale

- `class` y `style` bindings son sintaxis nativa, más directa
- `NgClass` y `NgStyle` son directivas que agregan overhead de procesamiento
- Los bindings nativos tienen mejor rendimiento y alineación con HTML estándar
- No requieren importar las directivas

## Imágenes y Assets

```typescript
// ✅ Usar NgOptimizedImage
import { NgOptimizedImage } from '@angular/common';

@Component({
  imports: [NgOptimizedImage],
  template: `
    <img ngSrc="/assets/hero.jpg" 
         width="800" 
         height="400" 
         priority />
  `
})
```

## Checklist de Producción

- [ ] `ng build` sin errores ni warnings de budget
- [ ] Bundle inicial < 500kb (warning), < 1mb (error)
- [ ] No hay imports de librerías completas
- [ ] Lazy loading en todas las features
- [ ] NgOptimizedImage para imágenes
- [ ] Source maps deshabilitados en producción

## Auto-fix

El agente debe:
1. Reemplazar imports de librería completa por específicos
2. Reemplazar `CommonModule` por imports individuales
3. Configurar budgets en angular.json
4. Identificar código no usado con análisis estático
5. Sugerir `@defer` para componentes pesados
