# 011: Content Security Policy (CSP)

**Severidad:** Warning  
**Categoría:** Seguridad  
**Desde:** Angular 21+

## Regla

Configurar Content Security Policy para prevenir ataques XSS. Usar nonces para estilos inline de Angular y habilitar Trusted Types.

## Rationale

- CSP es una capa de defensa en profundidad contra XSS
- Bloquea ejecución de scripts no autorizados
- Trusted Types previenen manipulación insegura del DOM
- Requerido para cumplimiento de seguridad en muchas organizaciones

## Configuración Mínima Requerida

```
Content-Security-Policy: 
  default-src 'self'; 
  style-src 'self' 'nonce-{{RANDOM_NONCE}}'; 
  script-src 'self' 'nonce-{{RANDOM_NONCE}}';
```

## Configurar Nonce en Angular

### Opción 1: autoCsp (Recomendada)

```json
// angular.json
{
  "build": {
    "options": {
      "autoCsp": true
    }
  }
}
```

### Opción 2: Atributo ngCspNonce

```html
<!-- index.html (servidor genera el nonce) -->
<app-root ngCspNonce="{{SERVER_GENERATED_NONCE}}"></app-root>
```

### Opción 3: Provider CSP_NONCE

```typescript
// main.ts
import { bootstrapApplication, CSP_NONCE } from '@angular/core';

bootstrapApplication(AppComponent, {
  providers: [
    {
      provide: CSP_NONCE,
      useValue: globalThis.myRandomNonceValue
    }
  ]
});
```

## Trusted Types

Habilitar Trusted Types para protección adicional:

```
Content-Security-Policy: 
  trusted-types angular angular#bundler; 
  require-trusted-types-for 'script';
```

### Políticas de Trusted Types

| Política | Uso |
|----------|-----|
| `angular` | Core de Angular (siempre requerida) |
| `angular#bundler` | Lazy loading de chunks |
| `angular#unsafe-bypass` | Si usas `bypassSecurityTrust*` |
| `angular#unsafe-jit` | Si usas compilador JIT |

### Ejemplo para Lazy Loading

```
Content-Security-Policy: 
  trusted-types angular angular#bundler; 
  require-trusted-types-for 'script';
```

## Configuración del Servidor

### Express.js

```typescript
import helmet from 'helmet';
import crypto from 'crypto';

app.use((req, res, next) => {
  res.locals.nonce = crypto.randomBytes(16).toString('base64');
  next();
});

app.use(
  helmet.contentSecurityPolicy({
    directives: {
      defaultSrc: ["'self'"],
      styleSrc: ["'self'", (req, res) => `'nonce-${res.locals.nonce}'`],
      scriptSrc: ["'self'", (req, res) => `'nonce-${res.locals.nonce}'`],
      trustedTypes: ['angular', 'angular#bundler'],
      requireTrustedTypesFor: ["'script'"]
    }
  })
);
```

### Nginx

```nginx
add_header Content-Security-Policy "
  default-src 'self';
  style-src 'self' 'nonce-$request_id';
  script-src 'self' 'nonce-$request_id';
  trusted-types angular angular#bundler;
  require-trusted-types-for 'script';
" always;
```

## Desarrollo Local

Para desarrollo, configurar CSP en angular.json:

```json
{
  "serve": {
    "options": {
      "headers": {
        "Content-Security-Policy": "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'"
      }
    }
  }
}
```

> **Nota**: `'unsafe-inline'` solo para desarrollo. En producción usar nonces.

## Directivas CSP Comunes

| Directiva | Propósito |
|-----------|-----------|
| `default-src` | Fallback para todas las fuentes |
| `script-src` | Fuentes de JavaScript |
| `style-src` | Fuentes de CSS |
| `img-src` | Fuentes de imágenes |
| `font-src` | Fuentes de tipografías |
| `connect-src` | URLs para fetch/XHR/WebSocket |
| `frame-src` | Fuentes de iframes |

## Ejemplo Completo de Producción

```
Content-Security-Policy:
  default-src 'self';
  script-src 'self' 'nonce-{{NONCE}}';
  style-src 'self' 'nonce-{{NONCE}}';
  img-src 'self' data: https://cdn.example.com;
  font-src 'self' https://fonts.gstatic.com;
  connect-src 'self' https://api.example.com;
  frame-ancestors 'none';
  base-uri 'self';
  form-action 'self';
  trusted-types angular angular#bundler;
  require-trusted-types-for 'script';
```

## Auto-fix

El agente debe:
1. Sugerir configuración de `autoCsp: true` en angular.json
2. Generar headers CSP para el servidor
3. Alertar si detecta `'unsafe-inline'` en producción
4. Verificar que scripts de terceros estén en allowlist
5. Documentar políticas Trusted Types necesarias según el uso
