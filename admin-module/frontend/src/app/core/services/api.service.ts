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

  previewMarkdown(content: string): Observable<{ ok: boolean; tree: any[] }> {
    return this.http.post<{ ok: boolean; tree: any[] }>(`${API_BASE}/markdown/preview`, { content });
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

  // ── Organizaciones ──────────────────────────────────────────────────────────
  getOrganizaciones(): Observable<any> {
    return this.http.get(`${API_BASE}/organizaciones`);
  }
  crearOrganizacion(body: { name: string; moodle_category_id: number }): Observable<any> {
    return this.http.post(`${API_BASE}/organizaciones`, body);
  }
  actualizarOrganizacion(id: number, body: { name?: string; moodle_category_id?: number }): Observable<any> {
    return this.http.put(`${API_BASE}/organizaciones/${id}`, body);
  }
  eliminarOrganizacion(id: number): Observable<any> {
    return this.http.delete(`${API_BASE}/organizaciones/${id}`);
  }
  getGestorPines(orgId: number): Observable<any> {
    return this.http.get(`${API_BASE}/organizaciones/${orgId}/gestor-pines`);
  }
  getGestores(orgId: number): Observable<any> {
    return this.http.get(`${API_BASE}/organizaciones/${orgId}/gestores`);
  }
  eliminarGestor(gestorId: number): Observable<any> {
    return this.http.delete(`${API_BASE}/gestores/${gestorId}`);
  }
  crearGestorPin(orgId: number): Observable<any> {
    return this.http.post(`${API_BASE}/organizaciones/${orgId}/gestor-pines`, {});
  }
  anularGestorPin(hash: string): Observable<any> {
    return this.http.delete(`${API_BASE}/gestor-pines/${hash}`);
  }

  // ── Paquetes y pines ─────────────────────────────────────────────────────────
  getPaquetes(orgId?: number): Observable<any> {
    let params = new HttpParams();
    if (orgId) params = params.set('org_id', orgId);
    return this.http.get(`${API_BASE}/paquetes`, { params });
  }
  crearPaquete(body: { organization_id: number; teacher_role: string; duration_days: number; cantidad: number }): Observable<any> {
    return this.http.post(`${API_BASE}/paquetes`, body);
  }
  asignarPaquete(id: number, body: { organization_id: number }): Observable<any> {
    return this.http.post(`${API_BASE}/paquetes/${id}/asignar`, body);
  }
  getReportePines(orgId?: number | null, packageId?: number | null): Observable<any> {
    let params = new HttpParams();
    if (orgId)     params = params.set('org_id', orgId);
    if (packageId) params = params.set('package_id', packageId);
    return this.http.get(`${API_BASE}/pines/reporte`, { params });
  }
  getMoodleCategorias(): Observable<any> {
    return this.http.get(`${API_BASE}/arboles/categorias-raiz`);
  }
  getCategoriasOrganizaciones(): Observable<any> {
    return this.http.get(`${API_BASE}/organizaciones/categorias`);
  }

  // Activos — integración Moodle
  getActivosCursosRepositorio(): Observable<{ ok: boolean; cursos: any[] }> {
    return this.http.get<any>(`${API_BASE}/activos/cursos-repositorio`);
  }

  crearVisor(body: {
    pdfId: string;
    pdfTitle: string;
    courseId: number;
    seccionNum: number;
    pageStart?: number;
    pageEnd?: number;
  }): Observable<{ ok: boolean; cmId: number | null }> {
    return this.http.post<any>(`${API_BASE}/activos/crear-visor`, body);
  }

  // ── Vista gestor ─────────────────────────────────────────────────────────────
  getGestorOrganizacion(): Observable<any> {
    return this.http.get(`${API_BASE}/gestor/organizacion`);
  }
  getGestorColegios(): Observable<any> {
    return this.http.get(`${API_BASE}/gestor/colegios`);
  }
  crearGestorColegio(body: { name: string }): Observable<any> {
    return this.http.post(`${API_BASE}/gestor/colegios`, body);
  }
  getGestorGrupos(): Observable<any> {
    return this.http.get(`${API_BASE}/gestor/grupos`);
  }
  crearGestorGrupo(body: { colegio_id?: number; name: string }): Observable<any> {
    return this.http.post(`${API_BASE}/gestor/grupos`, body);
  }
  getGestorUsuarios(search?: string): Observable<any> {
    let p = new HttpParams();
    if (search) p = p.set('search', search);
    return this.http.get(`${API_BASE}/gestor/usuarios`, { params: p });
  }
  resetearPasswordGestor(userId: number, password: string): Observable<any> {
    return this.http.post(`${API_BASE}/gestor/usuarios/${userId}/reset-password`, { password });
  }
  editarPerfilGestor(userId: number, body: { firstname: string; lastname: string; email: string }): Observable<any> {
    return this.http.put(`${API_BASE}/gestor/usuarios/${userId}`, body);
  }

  getGestorPinesLista(params?: { status?: string; group_id?: number; course_id?: number }): Observable<any> {
    let p = new HttpParams();
    if (params?.status)    p = p.set('status',    params.status);
    if (params?.group_id)  p = p.set('group_id',  params.group_id);
    if (params?.course_id) p = p.set('course_id', params.course_id);
    return this.http.get(`${API_BASE}/gestor/pines`, { params: p });
  }
  asignarPinesGestor(body: { pin_ids: number[]; group_id: number; course_id: number; role: string }): Observable<any> {
    return this.http.put(`${API_BASE}/gestor/pines/asignar`, body);
  }
  descargarPinesCsv(params?: { status?: string; group_id?: number; course_id?: number }): Observable<Blob> {
    let p = new HttpParams();
    if (params?.status)    p = p.set('status',    params.status);
    if (params?.group_id)  p = p.set('group_id',  params.group_id);
    if (params?.course_id) p = p.set('course_id', params.course_id);
    return this.http.get(`${API_BASE}/gestor/pines/descargar`, { params: p, responseType: 'blob' });
  }

  // ── Activación pública ────────────────────────────────────────────────────────
  resolverPin(hash: string): Observable<any> {
    return this.http.post(`${API_BASE}/activar/resolver`, { hash });
  }
  activarGestor(body: { hash: string; firstname: string; lastname: string; email: string; username: string; password: string }): Observable<any> {
    return this.http.post(`${API_BASE}/activar/gestor`, body);
  }
  registrarUsuario(body: { firstname: string; lastname: string; email: string; username: string; password: string }): Observable<any> {
    return this.http.post(`${API_BASE}/activar/registro`, body);
  }
  activarLogin(body: { username: string; password: string }): Observable<any> {
    return this.http.post(`${API_BASE}/activar/login`, body);
  }
  activarPin(body: { hash: string; user_id: number }): Observable<any> {
    return this.http.post(`${API_BASE}/activar/pin`, body);
  }
}
