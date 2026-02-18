# 014: HTML Semántico

**Severidad:** Warning  
**Categoría:** Accesibilidad  
**Desde:** Angular 21+

## Regla

Usar elementos HTML semánticos que comuniquen el propósito del contenido. Evitar `<div>` y `<span>` cuando existe un elemento más apropiado.

## Rationale

- Screen readers dependen de la semántica HTML para navegar
- Mejora SEO al indicar estructura del contenido
- Reduce necesidad de ARIA
- Proporciona comportamientos accesibles por defecto

## Estructura de Documento

### Incorrecto

```html
<!-- ❌ Divs sin semántica -->
<div class="header">
  <div class="nav">...</div>
</div>
<div class="content">
  <div class="article">
    <div class="title">Título</div>
    <div class="text">Contenido...</div>
  </div>
  <div class="sidebar">...</div>
</div>
<div class="footer">...</div>
```

### Correcto

```html
<!-- ✅ Elementos semánticos -->
<header>
  <nav aria-label="Principal">...</nav>
</header>
<main>
  <article>
    <h1>Título</h1>
    <p>Contenido...</p>
  </article>
  <aside aria-label="Recursos relacionados">...</aside>
</main>
<footer>...</footer>
```

## Elementos Semánticos Clave

| Elemento | Uso |
|----------|-----|
| `<header>` | Encabezado de página o sección |
| `<nav>` | Navegación principal o secundaria |
| `<main>` | Contenido principal (único por página) |
| `<article>` | Contenido independiente/auto-contenido |
| `<section>` | Sección temática con encabezado |
| `<aside>` | Contenido tangencialmente relacionado |
| `<footer>` | Pie de página o sección |
| `<figure>` | Contenido ilustrativo con caption |
| `<time>` | Fechas y horas |
| `<address>` | Información de contacto |
| `<mark>` | Texto destacado/resaltado |

## Jerarquía de Encabezados

### Incorrecto

```html
<!-- ❌ Saltos en jerarquía -->
<h1>Título principal</h1>
<h3>Subtítulo</h3>  <!-- Saltó h2 -->
<h5>Otro subtítulo</h5>  <!-- Saltó h4 -->
```

### Correcto

```html
<!-- ✅ Jerarquía secuencial -->
<h1>Título principal</h1>
<h2>Sección 1</h2>
<h3>Subsección 1.1</h3>
<h3>Subsección 1.2</h3>
<h2>Sección 2</h2>
<h3>Subsección 2.1</h3>
```

### En Componentes Angular

```typescript
// Permitir nivel de heading configurable
@Component({
  selector: 'app-card',
  template: `
    <article class="card">
      @switch (headingLevel()) {
        @case (2) { <h2>{{ title() }}</h2> }
        @case (3) { <h3>{{ title() }}</h3> }
        @case (4) { <h4>{{ title() }}</h4> }
        @default { <h2>{{ title() }}</h2> }
      }
      <ng-content />
    </article>
  `
})
export class CardComponent {
  readonly title = input.required<string>();
  readonly headingLevel = input<2 | 3 | 4>(2);
}
```

## Listas

```html
<!-- ✅ Lista desordenada -->
<ul>
  <li>Item 1</li>
  <li>Item 2</li>
</ul>

<!-- ✅ Lista ordenada -->
<ol>
  <li>Paso 1</li>
  <li>Paso 2</li>
</ol>

<!-- ✅ Lista de definiciones -->
<dl>
  <dt>Término</dt>
  <dd>Definición del término</dd>
</dl>

<!-- ✅ Navegación como lista -->
<nav aria-label="Principal">
  <ul>
    <li><a routerLink="/home">Inicio</a></li>
    <li><a routerLink="/about">Nosotros</a></li>
  </ul>
</nav>
```

## Tablas

```html
<!-- ✅ Tabla semántica -->
<table>
  <caption>Ventas mensuales 2024</caption>
  <thead>
    <tr>
      <th scope="col">Mes</th>
      <th scope="col">Ventas</th>
      <th scope="col">Crecimiento</th>
    </tr>
  </thead>
  <tbody>
    @for (row of data(); track row.id) {
      <tr>
        <th scope="row">{{ row.month }}</th>
        <td>{{ row.sales | currency }}</td>
        <td>{{ row.growth }}%</td>
      </tr>
    }
  </tbody>
  <tfoot>
    <tr>
      <th scope="row">Total</th>
      <td>{{ total() | currency }}</td>
      <td>-</td>
    </tr>
  </tfoot>
</table>
```

## Formularios

```html
<!-- ✅ Formulario con fieldset -->
<form>
  <fieldset>
    <legend>Información personal</legend>
    
    <div class="field">
      <label for="name">Nombre completo</label>
      <input id="name" type="text" required />
    </div>
    
    <div class="field">
      <label for="email">Correo electrónico</label>
      <input id="email" type="email" required />
    </div>
  </fieldset>
  
  <fieldset>
    <legend>Preferencias de contacto</legend>
    
    <div class="field">
      <input id="contact-email" type="radio" name="contact" value="email" />
      <label for="contact-email">Email</label>
    </div>
    
    <div class="field">
      <input id="contact-phone" type="radio" name="contact" value="phone" />
      <label for="contact-phone">Teléfono</label>
    </div>
  </fieldset>
  
  <button type="submit">Enviar</button>
</form>
```

## Figuras e Imágenes

```html
<!-- ✅ Imagen con alt descriptivo -->
<img src="product.jpg" alt="Camiseta azul de algodón, talla M" />

<!-- ✅ Imagen decorativa -->
<img src="decoration.svg" alt="" aria-hidden="true" />

<!-- ✅ Figure con caption -->
<figure>
  <img src="chart.png" alt="Gráfico mostrando crecimiento de 20% en Q4" />
  <figcaption>Crecimiento trimestral de ventas 2024</figcaption>
</figure>
```

## Fechas y Tiempos

```html
<!-- ✅ Tiempo legible por máquinas -->
<time datetime="2024-12-25">25 de diciembre de 2024</time>

<p>
  Publicado <time datetime="2024-01-15T14:30:00-05:00">hace 2 días</time>
</p>

<p>
  Duración: <time datetime="PT2H30M">2 horas y 30 minutos</time>
</p>
```

## Auto-fix

El agente debe:
1. Reemplazar `<div class="header">` por `<header>`
2. Reemplazar `<div class="nav">` por `<nav>`
3. Asegurar un único `<main>` por página
4. Verificar jerarquía secuencial de headings
5. Agregar `scope` a celdas de encabezado de tabla
6. Usar `<fieldset>/<legend>` para grupos de campos relacionados
7. Agregar alt apropiado a imágenes
