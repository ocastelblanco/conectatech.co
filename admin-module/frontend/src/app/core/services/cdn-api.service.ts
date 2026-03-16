import { Injectable, inject } from '@angular/core';
import { HttpBackend, HttpClient, HttpHeaders } from '@angular/common/http';
// HttpClient se instancia manualmente con HttpBackend para bypassar interceptores
import { Observable } from 'rxjs';

const CDN_API_BASE = 'https://api.conectatech.co';

export interface AssetItem {
  id: string;
  title: string;
  filename: string;
  originalFilename: string;
  s3key: string;
  url: string;
  status: 'pending' | 'active';
  createdAt: string;
  updatedAt: string;
}

@Injectable({ providedIn: 'root' })
export class CdnApiService {
  // Bypasa todos los interceptores (incluido el que añade withCredentials: true)
  private readonly http = new HttpClient(inject(HttpBackend));

  private get<T>(path: string): Observable<T> {
    return this.http.get<T>(`${CDN_API_BASE}${path}`, { withCredentials: false });
  }
  private post<T>(path: string, body: unknown): Observable<T> {
    return this.http.post<T>(`${CDN_API_BASE}${path}`, body, { withCredentials: false });
  }
  private patch<T>(path: string, body: unknown): Observable<T> {
    return this.http.patch<T>(`${CDN_API_BASE}${path}`, body, { withCredentials: false });
  }
  private delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(`${CDN_API_BASE}${path}`, { withCredentials: false });
  }

  // ─── PDFs ──────────────────────────────────────────────────────────────────
  listPdfs(): Observable<{ items: AssetItem[] }> {
    return this.get('/pdfs');
  }
  createPdf(title: string, filename: string): Observable<{ item: AssetItem; uploadUrl: string; contentType: string }> {
    return this.post('/pdfs', { title, filename });
  }
  confirmPdf(id: string): Observable<{ item: AssetItem }> {
    return this.post(`/pdfs/${id}/confirm`, {});
  }
  renamePdf(id: string, title: string): Observable<{ item: AssetItem }> {
    return this.patch(`/pdfs/${id}`, { title });
  }
  deletePdf(id: string): Observable<{ deleted: boolean }> {
    return this.delete(`/pdfs/${id}`);
  }

  // ─── Imágenes ──────────────────────────────────────────────────────────────
  listImagenes(): Observable<{ items: AssetItem[] }> {
    return this.get('/imagenes');
  }
  createImagen(title: string, filename: string): Observable<{ item: AssetItem; uploadUrl: string; contentType: string }> {
    return this.post('/imagenes', { title, filename });
  }
  confirmImagen(id: string): Observable<{ item: AssetItem }> {
    return this.post(`/imagenes/${id}/confirm`, {});
  }
  renameImagen(id: string, title: string): Observable<{ item: AssetItem }> {
    return this.patch(`/imagenes/${id}`, { title });
  }
  deleteImagen(id: string): Observable<{ deleted: boolean }> {
    return this.delete(`/imagenes/${id}`);
  }

  // ─── S3 direct upload ─────────────────────────────────────────────────────
  uploadToS3(uploadUrl: string, file: File, contentType: string): Observable<void> {
    return this.http.put<void>(uploadUrl, file, {
      headers: new HttpHeaders({ 'Content-Type': contentType }),
      withCredentials: false,
    });
  }
}
