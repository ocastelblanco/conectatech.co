# 002: Patrón de Signals para Estado y Comunicación

**Severidad:** Critical  
**Categoría:** Arquitectura  
**Desde:** Angular 21+

## Regla

Usar signals (`input()`, `output()`, `model()`, `signal()`, `computed()`) en lugar de decoradores `@Input()`, `@Output()` y propiedades mutables para estado y comunicación entre componentes.

## Rationale

- Signals proporcionan reactividad fine-grained sin Zone.js
- Mejor inferencia de tipos en TypeScript
- Eliminan necesidad de `ngOnChanges` para detectar cambios en inputs
- Habilitan optimizaciones de change detection
- Son inmutables por defecto, previniendo mutaciones accidentales

## Incorrecto

```typescript
// ❌ Decoradores legacy
@Component({...})
export class UserFormComponent implements OnChanges {
  @Input() userId: string;
  @Input() initialData: User | null = null;
  @Output() save = new EventEmitter<User>();
  
  ngOnChanges(changes: SimpleChanges) {
    if (changes['userId']) {
      this.loadUser(changes['userId'].currentValue);
    }
  }
}
```

## Correcto

```typescript
// ✅ Signals modernos
@Component({...})
export class UserFormComponent {
  readonly userId = input.required<string>();
  readonly initialData = input<User | null>(null);
  readonly save = output<User>();
  
  // Efecto reactivo en lugar de ngOnChanges
  private loadEffect = effect(() => {
    this.loadUser(this.userId());
  });
}
```

## Signal Forms (Angular 21.1+)

Para formularios, usar la directiva `[formField]`:

```typescript
// ✅ Signal Forms
@Component({
  imports: [FormField],
  template: `
    <input type="email" [formField]="loginForm.email" />
    @if (loginForm.email().touched() && loginForm.email().invalid()) {
      <span class="error">Email inválido</span>
    }
  `
})
export class LoginComponent {
  loginModel = signal<LoginData>({ email: '', password: '' });
  
  loginForm = form(this.loginModel, (f) => {
    required(f.email);
    email(f.email);
  });
}
```

## Computed Signals

Derivar estado con `computed()` en lugar de getters:

```typescript
// ❌ Getter (se recalcula en cada change detection)
get fullName(): string {
  return `${this.firstName} ${this.lastName}`;
}

// ✅ Computed signal (se cachea y recalcula solo cuando cambian dependencias)
readonly fullName = computed(() => `${this.firstName()} ${this.lastName()}`);
```

## Model Inputs (Two-way Binding)

Para componentes que modifican su input:

```typescript
// ✅ Model input para two-way binding
readonly value = model<string>('');

// Uso en template padre:
// <app-input [(value)]="parentValue" />
```

## Auto-fix

El agente debe:
1. Reemplazar `@Input()` por `input()` o `input.required()`
2. Reemplazar `@Output()` por `output()`
3. Convertir `ngOnChanges` a `effect()` cuando corresponda
4. Agregar `readonly` a todas las declaraciones de signals
5. Usar `computed()` para valores derivados

## Marcadores para `readonly`

Marcar como `readonly` todos los signals para prevenir reasignación:

```typescript
readonly userId = input.required<string>();       // ✅
readonly save = output<User>();                   // ✅
readonly isLoading = signal(false);               // ✅
readonly fullName = computed(() => ...);          // ✅
```
