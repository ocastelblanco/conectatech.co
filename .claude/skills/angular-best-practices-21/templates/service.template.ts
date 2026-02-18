/**
 * Template de Servicio Angular 21+
 * 
 * Uso: Copiar y adaptar para nuevos servicios
 * Reemplazar: {{ServiceName}}, {{Entity}}, {{endpoint}}
 */
import { Injectable, inject, signal, computed } from '@angular/core';
import { HttpClient, HttpErrorResponse, HttpParams } from '@angular/common/http';
import { Observable, throwError, of } from 'rxjs';
import { catchError, map, tap, shareReplay } from 'rxjs/operators';
import { environment } from '../../environments/environment';

/**
 * Servicio para gestionar {{Entity}}
 * 
 * Características:
 * - Inyectable en root (tree-shakeable)
 * - Manejo centralizado de errores
 * - Cache opcional con shareReplay
 * - Estado con signals para UI reactiva
 */
@Injectable({ providedIn: 'root' })
export class {{ServiceName}}Service {
  // ============================================
  // DEPENDENCIAS
  // ============================================
  private readonly http = inject(HttpClient);
  
  // ============================================
  // CONFIGURACIÓN
  // ============================================
  private readonly baseUrl = `${environment.apiUrl}/{{endpoint}}`;

  // ============================================
  // ESTADO REACTIVO (opcional - para UI)
  // ============================================
  /** Estado de carga para operaciones async */
  readonly isLoading = signal(false);
  
  /** Último error ocurrido */
  readonly error = signal<string | null>(null);
  
  /** Cache de entidades */
  private readonly entitiesCache = signal<{{Entity}}[]>([]);
  
  /** Entidades expuestas (readonly) */
  readonly entities = this.entitiesCache.asReadonly();
  
  /** Conteo derivado */
  readonly count = computed(() => this.entitiesCache().length);

  // ============================================
  // OPERACIONES CRUD
  // ============================================
  
  /**
   * Obtiene todas las entidades
   * @param params Parámetros opcionales de filtrado
   */
  getAll(params?: {{Entity}}FilterParams): Observable<{{Entity}}[]> {
    this.isLoading.set(true);
    this.error.set(null);
    
    let httpParams = new HttpParams();
    if (params?.search) {
      httpParams = httpParams.set('search', params.search);
    }
    if (params?.page) {
      httpParams = httpParams.set('page', params.page.toString());
    }

    return this.http.get<{{Entity}}[]>(this.baseUrl, { params: httpParams }).pipe(
      tap(entities => {
        this.entitiesCache.set(entities);
        this.isLoading.set(false);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Obtiene una entidad por ID
   */
  getById(id: string): Observable<{{Entity}}> {
    this.isLoading.set(true);
    this.error.set(null);

    return this.http.get<{{Entity}}>(`${this.baseUrl}/${id}`).pipe(
      tap(() => this.isLoading.set(false)),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Crea una nueva entidad
   */
  create(entity: Create{{Entity}}Dto): Observable<{{Entity}}> {
    this.isLoading.set(true);
    this.error.set(null);

    return this.http.post<{{Entity}}>(this.baseUrl, entity).pipe(
      tap(created => {
        // Actualizar cache local
        this.entitiesCache.update(current => [...current, created]);
        this.isLoading.set(false);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Actualiza una entidad existente
   */
  update(id: string, entity: Update{{Entity}}Dto): Observable<{{Entity}}> {
    this.isLoading.set(true);
    this.error.set(null);

    return this.http.put<{{Entity}}>(`${this.baseUrl}/${id}`, entity).pipe(
      tap(updated => {
        // Actualizar cache local
        this.entitiesCache.update(current => 
          current.map(e => e.id === id ? updated : e)
        );
        this.isLoading.set(false);
      }),
      catchError(error => this.handleError(error))
    );
  }

  /**
   * Elimina una entidad
   */
  delete(id: string): Observable<void> {
    this.isLoading.set(true);
    this.error.set(null);

    return this.http.delete<void>(`${this.baseUrl}/${id}`).pipe(
      tap(() => {
        // Remover de cache local
        this.entitiesCache.update(current => 
          current.filter(e => e.id !== id)
        );
        this.isLoading.set(false);
      }),
      catchError(error => this.handleError(error))
    );
  }

  // ============================================
  // MÉTODOS CON CACHE
  // ============================================
  
  private cachedEntities$: Observable<{{Entity}}[]> | null = null;

  /**
   * Obtiene entidades con cache (útil para datos que cambian poco)
   */
  getAllCached(): Observable<{{Entity}}[]> {
    if (!this.cachedEntities$) {
      this.cachedEntities$ = this.http.get<{{Entity}}[]>(this.baseUrl).pipe(
        shareReplay({ bufferSize: 1, refCount: true })
      );
    }
    return this.cachedEntities$;
  }

  /**
   * Invalida el cache (llamar después de mutaciones)
   */
  invalidateCache(): void {
    this.cachedEntities$ = null;
  }

  // ============================================
  // MANEJO DE ERRORES
  // ============================================
  
  private handleError(error: HttpErrorResponse): Observable<never> {
    this.isLoading.set(false);
    
    let errorMessage: string;

    if (error.error instanceof ErrorEvent) {
      // Error del cliente
      errorMessage = `Error: ${error.error.message}`;
    } else {
      // Error del servidor
      switch (error.status) {
        case 400:
          errorMessage = 'Datos inválidos';
          break;
        case 401:
          errorMessage = 'No autorizado';
          break;
        case 403:
          errorMessage = 'Acceso denegado';
          break;
        case 404:
          errorMessage = 'Recurso no encontrado';
          break;
        case 500:
          errorMessage = 'Error interno del servidor';
          break;
        default:
          errorMessage = `Error ${error.status}: ${error.message}`;
      }
    }

    this.error.set(errorMessage);
    console.error('{{ServiceName}}Service Error:', error);
    
    return throwError(() => new Error(errorMessage));
  }

  /**
   * Limpia el estado de error
   */
  clearError(): void {
    this.error.set(null);
  }
}

// ============================================
// TIPOS E INTERFACES
// ============================================

export interface {{Entity}} {
  id: string;
  // Definir propiedades de la entidad
  createdAt: Date;
  updatedAt: Date;
}

export interface Create{{Entity}}Dto {
  // Propiedades para crear (sin id, timestamps)
}

export interface Update{{Entity}}Dto {
  // Propiedades para actualizar (parcial)
}

export interface {{Entity}}FilterParams {
  search?: string;
  page?: number;
  pageSize?: number;
  sortBy?: string;
  sortOrder?: 'asc' | 'desc';
}
