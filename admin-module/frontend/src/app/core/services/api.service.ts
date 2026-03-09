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

  procesarMarkdown(body: { shortname: string; content: string }): Observable<any> {
    return this.http.post(`${API_BASE}/markdown`, body);
  }

  getReporte(nombre: 'matriculas' | 'creacion' | 'poblamiento' | 'markdown'): Observable<any> {
    return this.http.get(`${API_BASE}/reportes/${nombre}`);
  }
}
