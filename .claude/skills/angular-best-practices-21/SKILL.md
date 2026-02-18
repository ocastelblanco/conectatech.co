---
name: angular-best-practices
description: Mejores practicas para desarrollo Angular 21+. Usar al crear, revisar, refactorizar o auditar componentes, servicios, directivas o aplicaciones Angular. Incluye arquitectura, rendimiento, seguridad, accesibilidad, testing y tooling.
allowed-tools: Read, Grep, Glob, Bash(node *)
---

# Angular Best Practices

Aplica las mejores practicas de Angular 21.1+ al escribir o revisar codigo.

## Reglas principales

Cuando trabajes con codigo Angular, sigue estas reglas criticas:

### Arquitectura
- Usar **componentes standalone** (`standalone: true`) - nunca NgModules
- Usar **signals** (`input()`, `output()`, `signal()`, `computed()`) en lugar de decoradores `@Input`/`@Output`
- Usar **control flow moderno** (`@if`, `@for`, `@switch`) en lugar de `*ngIf`, `*ngFor`
- Usar **`inject()`** en lugar de constructor injection

### Rendimiento
- Usar **`ChangeDetectionStrategy.OnPush`** en todos los componentes
- Implementar **lazy loading** para rutas y componentes pesados
- Usar **imports especificos** (no `CommonModule` completo)

### Seguridad
- Nunca usar `bypassSecurityTrust*` sin validacion previa
- Implementar proteccion CSRF con `HttpClientXsrfModule`
- Sanitizar contenido dinamico con `DomSanitizer`

### Accesibilidad
- Incluir atributos ARIA en elementos interactivos
- Asegurar navegacion por teclado
- Usar HTML semantico

## Recursos adicionales

Para reglas detalladas, consultar:
- [Arquitectura](rules/01-architecture/) - Componentes, signals, estructura
- [Rendimiento](rules/02-performance/) - OnPush, lazy loading, bundles
- [Seguridad](rules/03-security/) - Sanitizacion, CSRF, CSP
- [Accesibilidad](rules/04-accessibility/) - ARIA, teclado, semantica
- [Errores](rules/05-error-handling/) - ErrorHandler, HTTP errors
- [Testing](rules/06-testing/) - Vitest, cobertura
- [Tooling](rules/07-tooling/) - ESLint, DevTools

Para plantillas de codigo, consultar [templates/](templates/).

## Comandos de auditoria

```bash
# Auditar proyecto completo
node scripts/audit-angular-project.js ./proyecto

# Verificar archivo especifico
node scripts/check-best-practices.js archivo.ts

# Generar reporte HTML
node scripts/generate-compliance-report.js ./src --html > reporte.html
```

## Ejemplo de componente correcto

```typescript
@Component({
  selector: 'app-example',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (isVisible()) {
      <p>{{ title() }}</p>
    }
  `
})
export class ExampleComponent {
  private readonly service = inject(ExampleService);

  readonly title = input.required<string>();
  readonly isVisible = input(true);
  readonly clicked = output<void>();
}
```
