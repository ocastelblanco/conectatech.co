import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Router } from '@angular/router';
import { catchError, of, tap } from 'rxjs';

const API_BASE = 'https://conectatech.co/admin-api';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http   = inject(HttpClient);
  private readonly router = inject(Router);

  readonly isAuthenticated = signal<boolean | null>(null);

  checkAuth(): void {
    this.http.get(`${API_BASE}/cursos`, { params: { category: '__check__' } }).pipe(
      tap(() => this.isAuthenticated.set(true)),
      catchError((err: any) => {
        // 403 → sesión Moodle válida pero sin rol de administrador (puede ser gestor).
        // 401 → sin sesión activa → redirigir al login.
        this.isAuthenticated.set(err.status === 403 ? true : false);
        return of(null);
      })
    ).subscribe();
  }

  redirectToMoodleLogin(): void {
    window.location.href = 'https://conectatech.co/login';
  }
}
