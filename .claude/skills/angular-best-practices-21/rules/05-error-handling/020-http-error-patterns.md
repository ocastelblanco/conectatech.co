# 020: Patrones de Errores HTTP

**Severidad:** Warning  
**Categoría:** Manejo de Errores  
**Desde:** Angular 21+

## Regla

Implementar un interceptor HTTP centralizado para manejo consistente de errores, retry automático y transformación de respuestas de error.

## Rationale

- Manejo consistente de errores en toda la aplicación
- Reducción de código duplicado en servicios
- Retry automático para errores transitorios
- Transformación de errores del servidor a formato útil para UI

## Interceptor de Errores HTTP

```typescript
// core/http/http-error.interceptor.ts
import { HttpInterceptorFn, HttpErrorResponse } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, retry, timer, throwError } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { NotificationService } from '../notification/notification.service';

export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  const router = inject(Router);
  const auth = inject(AuthService);
  const notification = inject(NotificationService);

  return next(req).pipe(
    // Retry automático para errores de red (no para errores de cliente)
    retry({
      count: 2,
      delay: (error, retryCount) => {
        // Solo reintentar errores de red o 5xx
        if (error instanceof HttpErrorResponse) {
          if (error.status === 0 || error.status >= 500) {
            return timer(1000 * retryCount); // Backoff exponencial
          }
        }
        return throwError(() => error);
      }
    }),
    
    catchError((error: HttpErrorResponse) => {
      const apiError = transformError(error);
      
      switch (error.status) {
        case 0:
          notification.showError('Sin conexión a internet');
          break;
          
        case 401:
          auth.logout();
          router.navigate(['/login']);
          break;
          
        case 403:
          notification.showError('No tienes permiso para esta acción');
          break;
          
        case 404:
          // No mostrar notificación, dejar que el componente maneje
          break;
          
        case 422:
          // Errores de validación - el componente los maneja
          break;
          
        case 429:
          notification.showError('Demasiadas solicitudes. Intenta más tarde.');
          break;
          
        case 500:
        case 502:
        case 503:
          notification.showError('Error del servidor. Intenta más tarde.');
          break;
          
        default:
          notification.showError(apiError.message);
      }
      
      return throwError(() => apiError);
    })
  );
};

function transformError(error: HttpErrorResponse): ApiError {
  // Transformar respuesta del servidor a formato consistente
  const body = error.error;
  
  return {
    status: error.status,
    message: body?.message || getDefaultMessage(error.status),
    code: body?.code || `HTTP_${error.status}`,
    errors: body?.errors || [],
    timestamp: new Date().toISOString()
  };
}

function getDefaultMessage(status: number): string {
  const messages: Record<number, string> = {
    0: 'No se pudo conectar con el servidor',
    400: 'Solicitud inválida',
    401: 'Sesión expirada',
    403: 'Acceso denegado',
    404: 'Recurso no encontrado',
    422: 'Datos inválidos',
    429: 'Demasiadas solicitudes',
    500: 'Error interno del servidor',
    502: 'Servidor no disponible',
    503: 'Servicio temporalmente no disponible'
  };
  return messages[status] || 'Error desconocido';
}

// Tipo para errores de API normalizados
export interface ApiError {
  status: number;
  message: string;
  code: string;
  errors: FieldError[];
  timestamp: string;
}

export interface FieldError {
  field: string;
  message: string;
}
```

## Registro del Interceptor

```typescript
// app.config.ts
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { httpErrorInterceptor } from './core/http/http-error.interceptor';

export const appConfig: ApplicationConfig = {
  providers: [
    provideHttpClient(
      withInterceptors([httpErrorInterceptor])
    )
  ]
};
```

## Manejo en Servicios

```typescript
// user.service.ts
@Injectable({ providedIn: 'root' })
export class UserService {
  private readonly http = inject(HttpClient);

  getUser(id: string): Observable<User> {
    return this.http.get<User>(`/api/users/${id}`);
    // El interceptor maneja errores automáticamente
  }

  updateUser(id: string, data: UpdateUserDto): Observable<User> {
    return this.http.put<User>(`/api/users/${id}`, data);
    // Errores 422 se propagan al componente para mostrar validación
  }
}
```

## Manejo en Componentes

```typescript
@Component({...})
export class UserFormComponent {
  private readonly userService = inject(UserService);
  
  readonly isLoading = signal(false);
  readonly fieldErrors = signal<FieldError[]>([]);

  onSubmit() {
    this.isLoading.set(true);
    this.fieldErrors.set([]);

    this.userService.updateUser(this.userId(), this.formData()).subscribe({
      next: (user) => {
        this.isLoading.set(false);
        // Éxito...
      },
      error: (error: ApiError) => {
        this.isLoading.set(false);
        
        // Mostrar errores de campo del servidor
        if (error.status === 422) {
          this.fieldErrors.set(error.errors);
        }
        // Otros errores ya fueron notificados por el interceptor
      }
    });
  }
  
  getFieldError(field: string): string | null {
    return this.fieldErrors().find(e => e.field === field)?.message ?? null;
  }
}
```

## Template con Errores de Campo

```html
<form (ngSubmit)="onSubmit()">
  <div class="field">
    <label for="email">Email</label>
    <input id="email" type="email" [(ngModel)]="email" />
    @if (getFieldError('email'); as error) {
      <span class="field-error" role="alert">{{ error }}</span>
    }
  </div>

  <button type="submit" [disabled]="isLoading()">
    @if (isLoading()) {
      Guardando...
    } @else {
      Guardar
    }
  </button>
</form>
```

## Retry con Configuración

Para casos que necesitan retry personalizado:

```typescript
// Con operador retry de RxJS
import { retry, timer } from 'rxjs';

this.http.get('/api/data').pipe(
  retry({
    count: 3,
    delay: (error, retryCount) => timer(Math.pow(2, retryCount) * 1000)
  })
);
```

## Skip del Interceptor

Para peticiones que no deben usar el interceptor:

```typescript
// Agregar header personalizado
const headers = new HttpHeaders().set('X-Skip-Error-Handler', 'true');
this.http.get('/api/health', { headers });

// En el interceptor
export const httpErrorInterceptor: HttpInterceptorFn = (req, next) => {
  if (req.headers.has('X-Skip-Error-Handler')) {
    return next(req);
  }
  // Manejo normal...
};
```

## Auto-fix

El agente debe:
1. Crear interceptor funcional con `HttpInterceptorFn`
2. Registrar con `withInterceptors()`
3. Implementar retry para errores transitorios
4. Transformar errores a formato consistente
5. Manejar códigos de estado comunes
6. Permitir skip del interceptor cuando sea necesario
