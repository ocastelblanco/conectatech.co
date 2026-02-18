# 015: Gestión del Foco

**Severidad:** Warning  
**Categoría:** Accesibilidad  
**Desde:** Angular 21+

## Regla

Gestionar el foco correctamente durante navegación SPA, apertura de modales, y actualizaciones dinámicas del DOM para mantener una experiencia coherente con teclado y screen readers.

## Rationale

- En SPAs, el navegador no mueve el foco automáticamente en cambios de ruta
- Usuarios de teclado pueden perder su posición en la página
- Screen readers necesitan anuncios cuando el contenido cambia dinámicamente

## Foco en Navegación de Rutas

### Problema

En Angular, al navegar entre rutas, el foco permanece donde estaba, desorientando a usuarios de teclado.

### Solución con Router Events

```typescript
// app.component.ts
@Component({
  template: `
    <a class="skip-link" href="#main-content">Saltar al contenido</a>
    <app-nav />
    <main id="main-content" #mainContent tabindex="-1">
      <router-outlet />
    </main>
  `
})
export class AppComponent {
  private readonly router = inject(Router);
  readonly mainContent = viewChild<ElementRef>('mainContent');
  
  constructor() {
    // Mover foco a main después de cada navegación
    this.router.events.pipe(
      filter(event => event instanceof NavigationEnd)
    ).subscribe(() => {
      this.mainContent()?.nativeElement.focus();
    });
  }
}
```

### Solución con Title Service

```typescript
// page.component.ts
@Component({...})
export class PageComponent implements OnInit {
  private readonly title = inject(Title);
  private readonly focusService = inject(FocusService);
  
  ngOnInit() {
    this.title.setTitle('Página de Productos - Mi App');
    this.focusService.focusMainContent();
  }
}

// focus.service.ts
@Injectable({ providedIn: 'root' })
export class FocusService {
  focusMainContent() {
    setTimeout(() => {
      const main = document.querySelector('main');
      main?.focus();
    });
  }
}
```

## Foco en Modales y Diálogos

### Con Angular CDK

```typescript
import { DialogRef, DIALOG_DATA } from '@angular/cdk/dialog';

@Component({
  template: `
    <div class="modal" 
         role="dialog" 
         aria-labelledby="modal-title"
         aria-modal="true"
         cdkTrapFocus
         cdkTrapFocusAutoCapture>
      
      <h2 id="modal-title">{{ data.title }}</h2>
      
      <div class="modal-content">
        <ng-content />
      </div>
      
      <div class="modal-actions">
        <button (click)="cancel()">Cancelar</button>
        <button (click)="confirm()" cdkFocusInitial>Confirmar</button>
      </div>
    </div>
  `
})
export class ModalComponent {
  readonly dialogRef = inject(DialogRef);
  readonly data = inject(DIALOG_DATA);
  
  cancel() {
    this.dialogRef.close(false);
  }
  
  confirm() {
    this.dialogRef.close(true);
  }
}
```

### Restaurar Foco al Cerrar

```typescript
@Component({...})
export class ParentComponent {
  private readonly dialog = inject(Dialog);
  readonly openButton = viewChild<ElementRef>('openBtn');
  
  openModal() {
    const dialogRef = this.dialog.open(ModalComponent, {
      data: { title: 'Confirmar acción' }
    });
    
    dialogRef.closed.subscribe(() => {
      // Restaurar foco al elemento que abrió el modal
      this.openButton()?.nativeElement.focus();
    });
  }
}
```

## Foco en Contenido Dinámico

### Notificaciones y Alertas

```typescript
@Component({
  template: `
    <div #alertRegion 
         role="alert" 
         aria-live="assertive"
         tabindex="-1">
      @if (error()) {
        <p>{{ error() }}</p>
      }
    </div>
  `
})
export class FormComponent {
  readonly error = signal<string | null>(null);
  readonly alertRegion = viewChild<ElementRef>('alertRegion');
  
  onSubmitError(message: string) {
    this.error.set(message);
    // Mover foco a la región de error
    this.alertRegion()?.nativeElement.focus();
  }
}
```

### Listas con Eliminación

```typescript
@Component({
  template: `
    <ul>
      @for (item of items(); track item.id; let i = $index) {
        <li #itemElement>
          {{ item.name }}
          <button (click)="deleteItem(i)">Eliminar</button>
        </li>
      }
    </ul>
  `
})
export class ListComponent {
  readonly items = signal<Item[]>([]);
  readonly itemElements = viewChildren<ElementRef>('itemElement');
  
  deleteItem(index: number) {
    this.items.update(items => items.filter((_, i) => i !== index));
    
    // Mover foco al siguiente item o al anterior si era el último
    setTimeout(() => {
      const elements = this.itemElements();
      const focusIndex = Math.min(index, elements.length - 1);
      
      if (focusIndex >= 0) {
        elements[focusIndex].nativeElement.querySelector('button')?.focus();
      }
    });
  }
}
```

## Focus Visible

```scss
// Estilos globales para indicador de foco
:focus-visible {
  outline: 3px solid var(--color-focus, #005fcc);
  outline-offset: 2px;
}

// Para inputs/buttons con diseño personalizado
.custom-input:focus-visible,
.custom-button:focus-visible {
  outline: 3px solid var(--color-focus);
  outline-offset: 2px;
  box-shadow: 0 0 0 4px rgba(0, 95, 204, 0.2);
}
```

## FocusMonitor del CDK

```typescript
import { FocusMonitor, FocusOrigin } from '@angular/cdk/a11y';

@Component({...})
export class ButtonComponent implements OnDestroy {
  private readonly focusMonitor = inject(FocusMonitor);
  private readonly elementRef = inject(ElementRef);
  
  readonly isFocused = signal(false);
  readonly focusOrigin = signal<FocusOrigin | null>(null);
  
  constructor() {
    this.focusMonitor.monitor(this.elementRef).subscribe(origin => {
      this.focusOrigin.set(origin);
      this.isFocused.set(!!origin);
    });
  }
  
  ngOnDestroy() {
    this.focusMonitor.stopMonitoring(this.elementRef);
  }
}
```

## Orden de Foco en Layouts Complejos

```html
<!-- Usar CSS order sin afectar DOM order -->
<div class="layout">
  <!-- DOM order = focus order -->
  <nav class="sidebar">...</nav>
  <main class="content">...</main>
  <aside class="panel">...</aside>
</div>

<style>
  .layout {
    display: flex;
  }
  /* Visual order diferente, pero tab order sigue DOM */
  .sidebar { order: 2; }
  .content { order: 1; }
  .panel { order: 3; }
</style>
```

## Auto-fix

El agente debe:
1. Implementar gestión de foco en cambios de ruta
2. Agregar `cdkTrapFocus` a modales y diálogos
3. Restaurar foco al elemento trigger al cerrar modales
4. Mover foco a mensajes de error en formularios
5. Mantener foco lógico al eliminar/agregar elementos de listas
6. Nunca usar CSS que oculte indicadores de foco sin alternativa
