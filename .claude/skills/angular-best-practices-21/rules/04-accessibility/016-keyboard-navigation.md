# 013: Navegación por Teclado

**Severidad:** Critical  
**Categoría:** Accesibilidad  
**Desde:** Angular 21+

## Regla

Todos los elementos interactivos deben ser accesibles y operables mediante teclado. El orden de tabulación debe ser lógico y el foco debe ser visible.

## Rationale

- Usuarios con discapacidades motoras dependen del teclado
- Screen readers navegan con teclado
- Power users prefieren atajos de teclado
- Requerido para cumplimiento WCAG 2.1 nivel A

## Elementos Interactivos Nativos

Estos elementos son tabulables por defecto:

- `<button>`
- `<a href="...">`
- `<input>`, `<select>`, `<textarea>`
- `<details>/<summary>`
- Elementos con `contenteditable`

## Incorrecto

```html
<!-- ❌ No tabulable, no tiene rol -->
<div (click)="doAction()">Acción</div>

<!-- ❌ No responde a teclado -->
<span class="link" (click)="navigate()">Ir a página</span>

<!-- ❌ tabindex positivo (rompe orden natural) -->
<input tabindex="5" />
<input tabindex="1" />
<input tabindex="3" />
```

## Correcto

```html
<!-- ✅ Botón nativo -->
<button (click)="doAction()">Acción</button>

<!-- ✅ Link nativo -->
<a routerLink="/pagina">Ir a página</a>

<!-- ✅ Si necesitas div, hacerlo accesible -->
<div role="button"
     tabindex="0"
     (click)="doAction()"
     (keydown.enter)="doAction()"
     (keydown.space)="doAction(); $event.preventDefault()">
  Acción
</div>

<!-- ✅ Orden natural con tabindex="0" -->
<input />
<input />
<input />
```

## Valores de tabindex

| Valor | Comportamiento |
|-------|----------------|
| `tabindex="-1"` | No tabulable, pero puede recibir foco programáticamente |
| `tabindex="0"` | Tabulable en orden del DOM |
| `tabindex="1+"` | ⚠️ Evitar - rompe orden natural |

## Manejo de Eventos de Teclado

### Teclas Estándar

```typescript
@Component({
  template: `
    <div role="button"
         tabindex="0"
         (keydown)="onKeydown($event)"
         (click)="activate()">
      Botón personalizado
    </div>
  `
})
export class CustomButtonComponent {
  onKeydown(event: KeyboardEvent) {
    // Enter y Space activan botones
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();  // Prevenir scroll con Space
      this.activate();
    }
  }
  
  activate() {
    // lógica de activación
  }
}
```

### Navegación en Listas

```typescript
@Component({
  template: `
    <ul role="listbox" 
        (keydown)="onListKeydown($event)"
        tabindex="0">
      @for (item of items(); track item.id; let i = $index) {
        <li role="option"
            [attr.aria-selected]="i === activeIndex()"
            [class.active]="i === activeIndex()">
          {{ item.name }}
        </li>
      }
    </ul>
  `
})
export class ListboxComponent {
  readonly activeIndex = signal(0);
  readonly items = input.required<Item[]>();
  
  onListKeydown(event: KeyboardEvent) {
    const itemCount = this.items().length;
    
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault();
        this.activeIndex.update(i => (i + 1) % itemCount);
        break;
      case 'ArrowUp':
        event.preventDefault();
        this.activeIndex.update(i => (i - 1 + itemCount) % itemCount);
        break;
      case 'Home':
        event.preventDefault();
        this.activeIndex.set(0);
        break;
      case 'End':
        event.preventDefault();
        this.activeIndex.set(itemCount - 1);
        break;
      case 'Enter':
        this.selectItem(this.activeIndex());
        break;
    }
  }
}
```

## Indicador de Foco Visible

```scss
// ✅ Nunca remover outline sin alternativa
button:focus {
  // ❌ outline: none;
  
  // ✅ Estilo de foco personalizado
  outline: 2px solid var(--color-focus);
  outline-offset: 2px;
}

// ✅ Usar :focus-visible para mouse vs teclado
button:focus-visible {
  outline: 2px solid var(--color-focus);
  outline-offset: 2px;
}

button:focus:not(:focus-visible) {
  outline: none;  // Sin outline para clicks de mouse
}
```

## Skip Links

Permitir saltar navegación repetitiva:

```html
<!-- Al inicio del body -->
<a href="#main-content" class="skip-link">
  Saltar al contenido principal
</a>

<nav><!-- navegación extensa --></nav>

<main id="main-content" tabindex="-1">
  <!-- contenido principal -->
</main>
```

```scss
.skip-link {
  position: absolute;
  top: -40px;
  left: 0;
  padding: 8px;
  background: white;
  z-index: 100;
  
  &:focus {
    top: 0;
  }
}
```

## Focus Trapping en Modales

Usar `@angular/cdk` para trap de foco:

```typescript
import { A11yModule, FocusTrap, FocusTrapFactory } from '@angular/cdk/a11y';

@Component({
  imports: [A11yModule],
  template: `
    @if (isOpen()) {
      <div class="modal" cdkTrapFocus cdkTrapFocusAutoCapture>
        <h2>Título del modal</h2>
        <!-- contenido -->
        <button (click)="close()">Cerrar</button>
      </div>
    }
  `
})
export class ModalComponent {}
```

## Gestión de Foco Programática

```typescript
import { FocusMonitor } from '@angular/cdk/a11y';

@Component({...})
export class FormComponent {
  private readonly focusMonitor = inject(FocusMonitor);
  readonly firstInput = viewChild<ElementRef>('firstInput');
  
  ngAfterViewInit() {
    // Enfocar primer campo al abrir
    this.focusMonitor.focusVia(
      this.firstInput()!.nativeElement, 
      'program'
    );
  }
  
  onError() {
    // Mover foco al primer campo con error
    const firstError = document.querySelector('[aria-invalid="true"]');
    (firstError as HTMLElement)?.focus();
  }
}
```

## Auto-fix

El agente debe:
1. Agregar `tabindex="0"` a elementos interactivos personalizados
2. Implementar handlers de teclado (Enter, Space) para botones custom
3. Nunca usar `tabindex` positivo
4. Mantener outline de foco o proveer alternativa visible
5. Implementar skip links en layouts con navegación extensa
6. Usar `cdkTrapFocus` en modales y diálogos
