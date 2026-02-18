# 003: Diseño de Componentes

**Severidad:** Warning  
**Categoría:** Arquitectura  
**Desde:** Angular 21+

## Regla

Los componentes deben seguir principios de responsabilidad única, separación de presentación y lógica, y usar patrones de inyección de dependencias modernos.

## Principios

### 1. Usar `inject()` para Dependencias

```typescript
// ❌ Inyección por constructor
constructor(
  private userService: UserService,
  private router: Router,
  private route: ActivatedRoute
) {}

// ✅ Función inject()
private readonly userService = inject(UserService);
private readonly router = inject(Router);
private readonly route = inject(ActivatedRoute);
```

### 2. Usar `protected` para Miembros de Template

```typescript
@Component({
  template: `<p>{{ fullName() }}</p>`
})
export class UserProfile {
  readonly firstName = input<string>();
  readonly lastName = input<string>();
  
  // ✅ protected: usado en template pero no es API pública
  protected readonly fullName = computed(
    () => `${this.firstName()} ${this.lastName()}`
  );
}
```

### 3. Agrupar Propiedades Angular Primero

```typescript
@Component({...})
export class OrderComponent {
  // 1. Inyecciones
  private readonly orderService = inject(OrderService);
  
  // 2. Inputs
  readonly orderId = input.required<string>();
  readonly showDetails = input(false);
  
  // 3. Outputs
  readonly orderUpdated = output<Order>();
  
  // 4. Queries
  readonly itemsList = viewChild<ElementRef>('itemsList');
  
  // 5. Signals internos
  readonly isLoading = signal(false);
  
  // 6. Computed
  protected readonly total = computed(() => ...);
  
  // 7. Métodos
  submitOrder() { ... }
}
```

### 4. Mantener Componentes Enfocados en Presentación

```typescript
// ❌ Lógica de negocio en componente
@Component({...})
export class InvoiceComponent {
  calculateTax(amount: number): number {
    return amount * 0.19; // Lógica de negocio
  }
}

// ✅ Lógica delegada a servicio
@Component({...})
export class InvoiceComponent {
  private readonly taxService = inject(TaxService);
  
  protected readonly tax = computed(
    () => this.taxService.calculate(this.amount())
  );
}
```

### 5. Evitar Lógica Compleja en Templates

```typescript
// ❌ Expresión compleja en template
template: `
  <span>{{ items.filter(i => i.active).reduce((a,b) => a + b.price, 0) }}</span>
`

// ✅ Usar computed
readonly activeTotal = computed(() => 
  this.items()
    .filter(i => i.active)
    .reduce((a, b) => a + b.price, 0)
);

template: `<span>{{ activeTotal() }}</span>`
```

### 6. Nombrar Event Handlers por Acción

```typescript
// ❌ Nombre genérico
<button (click)="handleClick()">Guardar</button>

// ✅ Nombre descriptivo
<button (click)="saveUserData()">Guardar</button>
```

### 7. Lifecycle Hooks Simples

```typescript
// ❌ Lógica extensa en ngOnInit
ngOnInit() {
  this.logger.setMode('info');
  this.logger.monitorErrors();
  // ... más código
}

// ✅ Delegar a métodos con nombres descriptivos
ngOnInit() {
  this.initializeLogging();
  this.loadInitialData();
}

private initializeLogging() { ... }
private loadInitialData() { ... }
```

## Auto-fix

El agente debe:
1. Convertir inyección por constructor a `inject()`
2. Agregar `protected` a miembros usados solo en template
3. Reordenar propiedades según el orden recomendado
4. Extraer expresiones complejas de templates a `computed()`
5. Renombrar handlers genéricos a nombres descriptivos
