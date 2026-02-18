# 012: Requisitos ARIA

**Severidad:** Critical  
**Categoría:** Accesibilidad  
**Desde:** Angular 21+

## Regla

Implementar atributos ARIA correctamente para garantizar que la aplicación sea usable con tecnologías de asistencia. Preferir HTML semántico sobre ARIA cuando sea posible.

## Principio Fundamental

> **Primera regla de ARIA**: Si puedes usar un elemento HTML nativo con la semántica y comportamiento requerido, úsalo en lugar de agregar ARIA.

## Roles ARIA Esenciales

### Incorrecto

```html
<!-- ❌ Div sin semántica -->
<div (click)="submit()">Enviar</div>

<!-- ❌ Span como botón sin rol -->
<span (click)="openMenu()">Menú</span>

<!-- ❌ Lista sin semántica -->
<div>
  <div>Item 1</div>
  <div>Item 2</div>
</div>
```

### Correcto

```html
<!-- ✅ Elemento nativo -->
<button (click)="submit()">Enviar</button>

<!-- ✅ Si necesitas div, agregar rol y teclado -->
<div role="button" 
     tabindex="0" 
     (click)="openMenu()" 
     (keydown.enter)="openMenu()"
     (keydown.space)="openMenu()">
  Menú
</div>

<!-- ✅ Lista semántica -->
<ul>
  <li>Item 1</li>
  <li>Item 2</li>
</ul>
```

## Etiquetas Accesibles

### Formularios

```html
<!-- ✅ Label asociado -->
<label for="email">Correo electrónico</label>
<input id="email" type="email" [formField]="form.email" />

<!-- ✅ aria-label para inputs sin label visible -->
<input type="search" 
       aria-label="Buscar productos" 
       placeholder="Buscar..." />

<!-- ✅ aria-labelledby para labels complejos -->
<h2 id="shipping-title">Dirección de envío</h2>
<form aria-labelledby="shipping-title">
  <!-- campos -->
</form>

<!-- ✅ aria-describedby para instrucciones -->
<input id="password" 
       type="password" 
       aria-describedby="password-hint" />
<p id="password-hint">Mínimo 8 caracteres, una mayúscula y un número</p>
```

### Botones e Iconos

```html
<!-- ✅ Botón con solo icono -->
<button aria-label="Cerrar diálogo" (click)="close()">
  <svg aria-hidden="true"><!-- icono X --></svg>
</button>

<!-- ✅ Botón con icono y texto -->
<button>
  <svg aria-hidden="true"><!-- icono --></svg>
  <span>Guardar</span>
</button>

<!-- ✅ Link con icono externo -->
<a href="https://external.com" target="_blank">
  Documentación
  <svg aria-hidden="true"><!-- icono external --></svg>
  <span class="sr-only">(abre en nueva ventana)</span>
</a>
```

## Estados y Propiedades

```html
<!-- ✅ Estado expandido/colapsado -->
<button [attr.aria-expanded]="isMenuOpen()" 
        aria-controls="menu-content"
        (click)="toggleMenu()">
  Menú
</button>
<div id="menu-content" [hidden]="!isMenuOpen()">
  <!-- contenido del menú -->
</div>

<!-- ✅ Estado de carga -->
<button [attr.aria-busy]="isLoading()" 
        [disabled]="isLoading()">
  @if (isLoading()) {
    <span aria-hidden="true">Cargando...</span>
  } @else {
    Enviar
  }
</button>

<!-- ✅ Elemento seleccionado -->
<ul role="listbox" aria-label="Opciones">
  @for (option of options(); track option.id) {
    <li role="option" 
        [attr.aria-selected]="option.id === selectedId()">
      {{ option.label }}
    </li>
  }
</ul>

<!-- ✅ Campo inválido -->
<input [attr.aria-invalid]="form.email.invalid() && form.email.touched()"
       aria-describedby="email-error" />
@if (form.email.invalid() && form.email.touched()) {
  <p id="email-error" role="alert">
    Por favor ingresa un email válido
  </p>
}
```

## Regiones y Landmarks

```html
<!-- ✅ Estructura de página con landmarks -->
<header role="banner">
  <nav aria-label="Principal"><!-- navegación --></nav>
</header>

<main role="main">
  <h1>Título de página</h1>
  <!-- contenido principal -->
</main>

<aside role="complementary" aria-label="Recursos relacionados">
  <!-- contenido secundario -->
</aside>

<footer role="contentinfo">
  <!-- pie de página -->
</footer>
```

## Live Regions para Actualizaciones Dinámicas

```html
<!-- ✅ Anuncios importantes (interrumpe) -->
<div role="alert" aria-live="assertive">
  @if (errorMessage()) {
    {{ errorMessage() }}
  }
</div>

<!-- ✅ Actualizaciones no críticas (espera) -->
<div aria-live="polite" aria-atomic="true">
  @if (successMessage()) {
    {{ successMessage() }}
  }
</div>

<!-- ✅ Estado de progreso -->
<div role="status" aria-live="polite">
  {{ itemsLoaded() }} de {{ totalItems() }} cargados
</div>
```

## Angular CDK A11y

Usar `@angular/cdk/a11y` para funcionalidades avanzadas:

```typescript
import { LiveAnnouncer } from '@angular/cdk/a11y';

private readonly announcer = inject(LiveAnnouncer);

onSave() {
  // Anunciar a screen readers
  this.announcer.announce('Cambios guardados correctamente', 'polite');
}
```

## Clase para Contenido Solo Screen Reader

```scss
// styles.scss
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}
```

## Auto-fix

El agente debe:
1. Agregar `aria-label` a botones con solo iconos
2. Asociar labels con inputs usando `for`/`id`
3. Agregar `aria-hidden="true"` a iconos decorativos
4. Implementar `aria-expanded` en controles expandibles
5. Usar elementos nativos antes de roles ARIA

## Referencia

- Complementar con skill `web-design-guidelines` para auditoría completa
- WCAG 2.1 Guidelines: https://www.w3.org/WAI/WCAG21/quickref/
