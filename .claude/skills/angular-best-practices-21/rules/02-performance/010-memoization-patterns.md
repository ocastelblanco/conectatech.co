# 010: Patrones de Memoización

**Severidad:** Warning  
**Categoría:** Rendimiento  
**Desde:** Angular 21+

## Regla

Usar `computed()` para valores derivados y evitar recálculos innecesarios. No parsear ni transformar datos en templates o getters.

## Rationale

- `computed()` cachea valores y solo recalcula cuando cambian sus dependencias
- Los getters se ejecutan en cada ciclo de change detection
- Expresiones en templates se evalúan múltiples veces por render

## Incorrecto

```typescript
// ❌ Getter: se recalcula en cada change detection
@Component({
  template: `<p>{{ fullName }}</p>`
})
export class UserComponent {
  firstName = 'John';
  lastName = 'Doe';
  
  get fullName(): string {
    console.log('Calculating fullName'); // Se ejecuta muchas veces
    return `${this.firstName} ${this.lastName}`;
  }
}

// ❌ Transformación en template
@Component({
  template: `
    <p>{{ items.filter(i => i.active).length }} activos</p>
    <ul>
      @for (item of items.filter(i => i.active); track item.id) {
        <li>{{ item.name }}</li>
      }
    </ul>
  `
})
export class ListComponent {
  items: Item[] = [];
}
```

## Correcto

```typescript
// ✅ Computed: cachea y recalcula solo cuando cambian dependencias
@Component({
  template: `<p>{{ fullName() }}</p>`
})
export class UserComponent {
  readonly firstName = signal('John');
  readonly lastName = signal('Doe');
  
  readonly fullName = computed(() => {
    console.log('Calculating fullName'); // Solo cuando cambian first/lastName
    return `${this.firstName()} ${this.lastName()}`;
  });
}

// ✅ Valores derivados con computed
@Component({
  template: `
    <p>{{ activeCount() }} activos</p>
    <ul>
      @for (item of activeItems(); track item.id) {
        <li>{{ item.name }}</li>
      }
    </ul>
  `
})
export class ListComponent {
  readonly items = signal<Item[]>([]);
  
  readonly activeItems = computed(() => 
    this.items().filter(i => i.active)
  );
  
  readonly activeCount = computed(() => 
    this.activeItems().length
  );
}
```

## Parseo de Datos

### Incorrecto

```typescript
// ❌ Parsear en cada render
@Component({
  template: `<p>{{ parseConfig().theme }}</p>`
})
export class SettingsComponent {
  parseConfig() {
    return JSON.parse(localStorage.getItem('config') || '{}');
  }
}
```

### Correcto

```typescript
// ✅ Parsear una vez y cachear
@Component({
  template: `<p>{{ config().theme }}</p>`
})
export class SettingsComponent {
  readonly config = signal(this.loadConfig());
  
  private loadConfig(): Config {
    return JSON.parse(localStorage.getItem('config') || '{}');
  }
  
  reloadConfig() {
    this.config.set(this.loadConfig());
  }
}
```

## Transformaciones Costosas

```typescript
// ✅ Memoizar transformaciones costosas
@Component({...})
export class ReportComponent {
  readonly rawData = signal<DataPoint[]>([]);
  
  // Transformación costosa memoizada
  readonly processedData = computed(() => {
    return this.rawData()
      .map(this.transform)
      .sort(this.sortFn)
      .slice(0, 100);
  });
  
  // Agregaciones memoizadas
  readonly stats = computed(() => ({
    total: this.processedData().length,
    sum: this.processedData().reduce((a, b) => a + b.value, 0),
    avg: this.processedData().reduce((a, b) => a + b.value, 0) / this.processedData().length
  }));
}
```

## Evitar Objetos Nuevos en Templates

```typescript
// ❌ Crea objeto nuevo en cada render
@Component({
  template: `<app-chart [options]="{ color: 'blue', size: 100 }" />`
})

// ✅ Objeto definido como propiedad
@Component({
  template: `<app-chart [options]="chartOptions" />`
})
export class DashboardComponent {
  readonly chartOptions = { color: 'blue', size: 100 };
}

// ✅ O como computed si depende de estado
readonly chartOptions = computed(() => ({
  color: this.theme(),
  size: this.containerSize()
}));
```

## TrackBy en @for

Siempre especificar `track` para optimizar re-renders:

```html
<!-- ✅ Track permite reusar elementos DOM -->
@for (item of items(); track item.id) {
  <app-item [data]="item" />
}
```

## Auto-fix

El agente debe:
1. Convertir getters de valores derivados a `computed()`
2. Extraer expresiones complejas de templates a propiedades computed
3. Mover parseo de datos a signals con carga única
4. Verificar que `@for` tenga `track` apropiado
5. Extraer objetos literales de templates a propiedades
