# 019: Manejador Global de Errores

**Severidad:** Critical  
**Categoría:** Manejo de Errores  
**Desde:** Angular 21+

## Regla

Implementar un `ErrorHandler` global personalizado para capturar y procesar todos los errores no manejados de la aplicación.

## Rationale

- Punto centralizado para logging y reporting de errores
- Evita que errores no manejados rompan la aplicación silenciosamente
- Permite integración con servicios de monitoreo (Sentry, LogRocket, etc.)
- Mejora la experiencia de usuario con feedback apropiado

## Implementación

### ErrorHandler Personalizado

```typescript
// core/error/global-error.handler.ts
import { ErrorHandler, Injectable, inject, NgZone } from '@angular/core';
import { ErrorReportingService } from './error-reporting.service';
import { NotificationService } from '../notification/notification.service';

@Injectable()
export class GlobalErrorHandler implements ErrorHandler {
  private readonly errorReporting = inject(ErrorReportingService);
  private readonly notification = inject(NotificationService);
  private readonly zone = inject(NgZone);

  handleError(error: unknown): void {
    // Extraer error real si está wrapped
    const resolvedError = this.resolveError(error);
    
    // Log en consola (desarrollo)
    console.error('Error no manejado:', resolvedError);
    
    // Reportar a servicio externo
    this.errorReporting.report(resolvedError);
    
    // Notificar al usuario (ejecutar dentro de NgZone para UI)
    this.zone.run(() => {
      this.notification.showError(
        'Ha ocurrido un error inesperado. Por favor, intenta de nuevo.'
      );
    });
  }

  private resolveError(error: unknown): Error {
    // Desempaquetar errores de Zone.js o promesas
    if (error instanceof Error) {
      return error;
    }
    
    if (typeof error === 'string') {
      return new Error(error);
    }
    
    // Error con estructura { rejection: Error }
    if (error && typeof error === 'object' && 'rejection' in error) {
      return this.resolveError((error as { rejection: unknown }).rejection);
    }
    
    return new Error('Error desconocido');
  }
}
```

### Registro del ErrorHandler

```typescript
// app.config.ts
import { ApplicationConfig, ErrorHandler } from '@angular/core';
import { GlobalErrorHandler } from './core/error/global-error.handler';

export const appConfig: ApplicationConfig = {
  providers: [
    { provide: ErrorHandler, useClass: GlobalErrorHandler },
    // otros providers...
  ]
};
```

## Servicio de Reporting

```typescript
// core/error/error-reporting.service.ts
import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../../environments/environment';

interface ErrorReport {
  message: string;
  stack?: string;
  url: string;
  timestamp: string;
  userAgent: string;
  userId?: string;
}

@Injectable({ providedIn: 'root' })
export class ErrorReportingService {
  private readonly http = inject(HttpClient);

  report(error: Error): void {
    // No reportar en desarrollo
    if (!environment.production) {
      return;
    }

    const report: ErrorReport = {
      message: error.message,
      stack: error.stack,
      url: window.location.href,
      timestamp: new Date().toISOString(),
      userAgent: navigator.userAgent
    };

    // Enviar a backend o servicio externo
    this.http.post('/api/errors', report).subscribe({
      error: (e) => console.error('Error al reportar:', e)
    });
  }
}
```

## Integración con Sentry (Ejemplo)

```typescript
// core/error/sentry-error.handler.ts
import { ErrorHandler, Injectable } from '@angular/core';
import * as Sentry from '@sentry/angular';

@Injectable()
export class SentryErrorHandler implements ErrorHandler {
  handleError(error: unknown): void {
    console.error(error);
    Sentry.captureException(error);
  }
}

// app.config.ts
import { APP_INITIALIZER } from '@angular/core';
import { Router } from '@angular/router';
import * as Sentry from '@sentry/angular';

export const appConfig: ApplicationConfig = {
  providers: [
    { provide: ErrorHandler, useClass: SentryErrorHandler },
    {
      provide: Sentry.TraceService,
      deps: [Router]
    },
    {
      provide: APP_INITIALIZER,
      useFactory: () => () => {},
      deps: [Sentry.TraceService],
      multi: true
    }
  ]
};
```

## Errores en Zoneless

Con `provideZonelessChangeDetection()`, usar `NgZone.run()` para actualizaciones de UI:

```typescript
handleError(error: unknown): void {
  // UI updates deben estar en zone
  this.zone.run(() => {
    this.notification.showError('Error inesperado');
  });
}
```

## Debugging con provideStabilityDebugging

Para identificar tareas que impiden estabilización:

```typescript
// Solo en desarrollo
import { provideStabilityDebugging } from '@angular/core';

export const appConfig: ApplicationConfig = {
  providers: [
    provideStabilityDebugging()  // Log de tareas pendientes
  ]
};
```

## Tipos de Errores a Manejar

| Tipo | Fuente | Acción |
|------|--------|--------|
| Error de runtime | Código TypeScript | Log + notificación |
| Error HTTP | HttpClient | Interceptor + retry |
| Error de navegación | Router | Redirigir a error page |
| Chunk load error | Lazy loading | Reload de página |
| Promise rejection | Async/await | Capturar y reportar |

## Manejo de Chunk Load Errors

```typescript
handleError(error: unknown): void {
  const resolvedError = this.resolveError(error);
  
  // Detectar error de carga de chunk (lazy loading fallido)
  if (resolvedError.message?.includes('ChunkLoadError') ||
      resolvedError.message?.includes('Loading chunk')) {
    // Sugerir recarga de página
    this.notification.showError(
      'Nueva versión disponible. La página se recargará.',
      { action: () => window.location.reload() }
    );
    return;
  }
  
  // Manejo normal...
}
```

## Auto-fix

El agente debe:
1. Crear `GlobalErrorHandler` en `core/error/`
2. Registrar como provider de `ErrorHandler`
3. Implementar servicio de reporting
4. Agregar notificación de usuario amigable
5. Manejar casos especiales (chunk errors, zone issues)
