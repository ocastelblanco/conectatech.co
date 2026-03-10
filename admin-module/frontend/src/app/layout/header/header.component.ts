import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { Router, NavigationEnd } from '@angular/router';
import { filter, map } from 'rxjs/operators';
import { toSignal } from '@angular/core/rxjs-interop';
import { ButtonModule } from 'primeng/button';
import { AuthService } from '../../core/services/auth.service';

const routeTitles: Record<string, string> = {
  dashboard:  'Dashboard',
  cursos:     'Gestion de Cursos',
  matriculas: 'Matriculacion de Usuarios',
  contenido:  'Crear Contenido',
  reportes:   'Reportes',
};

@Component({
  selector: 'cnt-header',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ButtonModule],
  template: `
    <header class="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200 shadow-sm">
      <div>
        <h1 class="text-xl font-heading font-bold" style="color: #1D2B36; margin: 0;">
          {{ pageTitle() }}
        </h1>
      </div>
      <div class="flex items-center gap-3">
        <span class="text-sm text-gray-500">Panel de Administracion</span>
        <p-button
          label="Cerrar sesion"
          icon="pi pi-sign-out"
          severity="secondary"
          size="small"
          [text]="true"
          (onClick)="logout()"
        />
      </div>
    </header>
  `
})
export class HeaderComponent {
  private readonly router = inject(Router);
  private readonly auth   = inject(AuthService);

  readonly pageTitle = toSignal(
    this.router.events.pipe(
      filter(e => e instanceof NavigationEnd),
      map(() => {
        const seg = this.router.url.split('/').filter(Boolean)[0] ?? 'dashboard';
        return routeTitles[seg] ?? 'Panel Admin';
      })
    ),
    { initialValue: 'Dashboard' }
  );

  logout(): void {
    this.auth.redirectToMoodleLogin();
  }
}
