import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';

const API_BASE = 'https://conectatech.co/admin-api';

export interface ApiResponse<T = unknown> {
  ok: boolean;
  error?: string;
  redirect?: string;
  data?: T;
}

@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);

  ping(): Observable<{ ok: boolean; message: string }> {
    return this.http.get<{ ok: boolean; message: string }>(`${API_BASE}/ping`);
  }

  getCursos(category?: string): Observable<any> {
    let params = new HttpParams();
    if (category) params = params.set('category', category);
    return this.http.get(`${API_BASE}/cursos`, { params });
  }

  crearCursos(body: { dry_run: boolean; cursos: any[] }): Observable<any> {
    return this.http.post(`${API_BASE}/cursos/crear`, body);
  }

  poblarCursos(body: { dry_run: boolean; courses: any[] }): Observable<any> {
    return this.http.post(`${API_BASE}/cursos/poblar`, body);
  }

  matricular(body: { dry_run: boolean; usuarios: any[] }): Observable<any> {
    return this.http.post(`${API_BASE}/matriculas`, body);
  }

  getCursosArbol(): Observable<any> {
    return this.http.get(`${API_BASE}/cursos/arbol`);
  }

  procesarMarkdown(body: { shortname: string; content: string }): Observable<any> {
    return this.http.post(`${API_BASE}/markdown`, body);
  }

  getReporte(nombre: 'matriculas' | 'creacion' | 'poblamiento' | 'markdown'): Observable<any> {
    return this.http.get(`${API_BASE}/reportes/${nombre}`);
  }

  // Árboles curriculares
  getArboles(): Observable<any> { return this.http.get(`${API_BASE}/arboles`); }
  crearArbol(body: any): Observable<any> { return this.http.post(`${API_BASE}/arboles`, body); }
  getArbol(id: string): Observable<any> { return this.http.get(`${API_BASE}/arboles/${id}`); }
  guardarArbol(id: string, arbol: any): Observable<any> { return this.http.put(`${API_BASE}/arboles/${id}`, arbol); }
  eliminarArbol(id: string): Observable<any> { return this.http.delete(`${API_BASE}/arboles/${id}`); }
  duplicarArbol(id: string, meta: any): Observable<any> { return this.http.post(`${API_BASE}/arboles/${id}/duplicar`, meta); }
  validarArbol(id: string): Observable<any> { return this.http.get(`${API_BASE}/arboles/${id}/validar`); }
  ejecutarArbol(id: string, body: any): Observable<any> { return this.http.post(`${API_BASE}/arboles/${id}/ejecutar`, body); }
  getArbolesPlantillas(): Observable<any> { return this.http.get(`${API_BASE}/arboles/plantillas`); }
  getArbolesRepositorios(): Observable<any> { return this.http.get(`${API_BASE}/arboles/repositorios`); }
  getArbolesCategoriasRaiz(): Observable<any> { return this.http.get(`${API_BASE}/arboles/categorias-raiz`); }
  getArbolesOpcionesCss(): Observable<any>   { return this.http.get(`${API_BASE}/arboles/opciones-css`); }
}
