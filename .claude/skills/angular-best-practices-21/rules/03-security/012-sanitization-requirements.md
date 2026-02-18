# 009: Requisitos de Sanitización

**Severidad:** Critical  
**Categoría:** Seguridad  
**Desde:** Angular 21+

## Regla

Confiar en la sanitización automática de Angular. Nunca usar `bypassSecurityTrust*` sin revisión de seguridad explícita. No construir templates dinámicamente con datos de usuario.

## Cómo Angular Protege

Angular sanitiza automáticamente valores en estos contextos:
- **HTML**: Al usar `[innerHTML]`
- **Style**: Al bindear a `[style]`
- **URL**: En propiedades como `[href]`
- **Resource URL**: En `[src]` de scripts/iframes

```typescript
// ✅ Angular sanitiza automáticamente
@Component({
  template: `
    <div [innerHTML]="userContent"></div>
    <a [href]="userUrl">Link</a>
  `
})
export class SafeComponent {
  // Aunque contenga <script>, será eliminado
  userContent = '<b>Bold</b><script>alert("xss")</script>';
  userUrl = 'javascript:alert("xss")'; // Será bloqueado
}
```

## Nunca Bypass Sin Revisión

### Incorrecto

```typescript
// ❌ PELIGROSO: bypass sin validación
@Component({...})
export class DangerComponent {
  private sanitizer = inject(DomSanitizer);
  
  trustedHtml = this.sanitizer.bypassSecurityTrustHtml(
    this.userInput // ¡Nunca confiar en input de usuario!
  );
}
```

### Correcto (Solo con Validación)

```typescript
// ✅ Solo para contenido controlado y validado
@Component({...})
export class VideoEmbedComponent {
  private sanitizer = inject(DomSanitizer);
  
  readonly videoId = input.required<string>();
  
  // Construir URL segura con ID validado
  protected readonly trustedUrl = computed(() => {
    const id = this.videoId();
    // Validar que es un ID de YouTube válido
    if (!/^[a-zA-Z0-9_-]{11}$/.test(id)) {
      throw new Error('Invalid video ID');
    }
    const url = `https://www.youtube.com/embed/${id}`;
    return this.sanitizer.bypassSecurityTrustResourceUrl(url);
  });
}
```

## No Construir Templates Dinámicamente

### Incorrecto

```typescript
// ❌ CRÍTICO: Template injection vulnerability
const userInput = getUserInput();
const template = `<div>${userInput}</div>`; // XSS!

// ❌ Concatenar template con datos de usuario
const dynamicTemplate = `
  <h1>${this.title}</h1>
  <p>${this.userDescription}</p>
`;
```

### Correcto

```typescript
// ✅ Usar binding seguro
@Component({
  template: `
    <h1>{{ title() }}</h1>
    <p>{{ userDescription() }}</p>
  `
})
export class SafeComponent {
  readonly title = input<string>();
  readonly userDescription = input<string>();
}
```

## Usar AOT en Producción

El compilador Ahead-of-Time previene template injection:

```bash
# ✅ Build de producción usa AOT por defecto
ng build --configuration production
```

Nunca usar JIT en producción:

```typescript
// ❌ JIT es vulnerable a template injection
import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';
```

## Métodos de DomSanitizer

| Método | Usar Solo Cuando |
|--------|------------------|
| `bypassSecurityTrustHtml` | HTML de fuente 100% confiable |
| `bypassSecurityTrustStyle` | Estilos de fuente confiable |
| `bypassSecurityTrustUrl` | URLs construidas internamente |
| `bypassSecurityTrustResourceUrl` | URLs de recursos (iframe, script) validados |

## Sanitización Manual

Para casos donde necesitas sanitizar sin bypass:

```typescript
import { DomSanitizer, SecurityContext } from '@angular/platform-browser';

private sanitizer = inject(DomSanitizer);

// Sanitizar explícitamente
cleanHtml = this.sanitizer.sanitize(SecurityContext.HTML, userInput);
cleanUrl = this.sanitizer.sanitize(SecurityContext.URL, userUrl);
```

## Auto-fix

El agente debe:
1. Nunca agregar `bypassSecurityTrust*` sin validación explícita
2. Alertar si detecta concatenación de templates con datos externos
3. Verificar que el build use AOT (default en Angular CLI)
4. Reemplazar construcción dinámica de templates por bindings seguros
