# 010: Protección CSRF/XSRF

**Severidad:** Critical  
**Categoría:** Seguridad  
**Desde:** Angular 21+

## Regla

Configurar protección CSRF para todas las peticiones HTTP mutantes (POST, PUT, DELETE, PATCH). El servidor debe enviar un token en cookie y Angular lo reenvía en header.

## Cómo Funciona CSRF

1. Servidor envía cookie `XSRF-TOKEN` con token único por sesión
2. Angular HttpClient lee la cookie automáticamente
3. Angular incluye el token en header `X-XSRF-TOKEN` en peticiones mutantes
4. Servidor valida que header coincida con cookie

## Configuración Básica

HttpClient incluye protección CSRF por defecto:

```typescript
// app.config.ts
export const appConfig: ApplicationConfig = {
  providers: [
    provideHttpClient()  // CSRF habilitado por defecto
  ]
};
```

## Configuración Personalizada

Si el servidor usa nombres diferentes:

```typescript
// app.config.ts
import { withXsrfConfiguration } from '@angular/common/http';

export const appConfig: ApplicationConfig = {
  providers: [
    provideHttpClient(
      withXsrfConfiguration({
        cookieName: 'MY-XSRF-TOKEN',      // Nombre de cookie del servidor
        headerName: 'X-My-Xsrf-Header'    // Header que espera el servidor
      })
    )
  ]
};
```

## Requisitos del Servidor

El servidor debe:

```
1. Generar token único por sesión
2. Enviar token en cookie:
   Set-Cookie: XSRF-TOKEN=abc123; Path=/; SameSite=Strict

3. Validar en cada petición mutante:
   - Leer cookie XSRF-TOKEN
   - Leer header X-XSRF-TOKEN
   - Comparar valores
   - Rechazar si no coinciden (403)
```

## Peticiones que Angular Protege

| Método | Protegido |
|--------|-----------|
| GET | ❌ No (solo lectura) |
| HEAD | ❌ No (solo lectura) |
| POST | ✅ Sí |
| PUT | ✅ Sí |
| DELETE | ✅ Sí |
| PATCH | ✅ Sí |

## Deshabilitar CSRF (No Recomendado)

Solo para APIs públicas sin autenticación:

```typescript
// ⚠️ SOLO para APIs públicas sin estado de sesión
import { withNoXsrfProtection } from '@angular/common/http';

provideHttpClient(
  withNoXsrfProtection()
)
```

## Cross-Origin Requests

CSRF solo aplica a peticiones same-origin. Para APIs cross-origin:

```typescript
// CSRF no aplica a dominios diferentes por política de same-origin
// La API debe tener su propia protección (CORS + auth tokens)
```

## Verificación

Comprobar que la protección funciona:

```typescript
// En DevTools > Network, verificar petición POST:
// 
// Request Headers:
//   X-XSRF-TOKEN: abc123...
//
// Cookies:
//   XSRF-TOKEN: abc123...
```

## Errores Comunes

### Cookie No Legible

```typescript
// ❌ El servidor envía cookie HttpOnly
Set-Cookie: XSRF-TOKEN=abc; HttpOnly  // Angular no puede leerla

// ✅ Cookie debe ser accesible por JavaScript
Set-Cookie: XSRF-TOKEN=abc; Path=/; SameSite=Strict
```

### Path Incorrecto

```typescript
// ❌ Cookie con path restrictivo
Set-Cookie: XSRF-TOKEN=abc; Path=/api

// ✅ Cookie accesible en toda la app
Set-Cookie: XSRF-TOKEN=abc; Path=/
```

## Auto-fix

El agente debe:
1. Verificar que `provideHttpClient()` está configurado
2. No deshabilitar XSRF sin justificación documentada
3. Alertar si detecta APIs mutantes sin protección CSRF
4. Documentar nombres de cookie/header si son personalizados
