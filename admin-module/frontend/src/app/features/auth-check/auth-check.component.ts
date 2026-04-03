import { Component, ChangeDetectionStrategy, OnInit, inject } from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ApiService } from '../../core/services/api.service';
import { GestorStateService } from '../../core/services/gestor-state.service';
import { ProgressSpinnerModule } from 'primeng/progressspinner';

@Component({
  selector: 'cnt-auth-check',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ProgressSpinnerModule],
  template: `
    <div class="fondo flex flex-col items-center justify-center min-h-screen">
      <img src="logo-vertical-blanco.svg" alt="ConectaTech" class="h-20 mb-8" />
      <p-progressSpinner strokeWidth="3" class="w-12 h-12" />
      <p class="text-white/60 mt-4 text-sm">Verificando sesión...</p>
    </div>
  `,
  styles: [`.fondo { background-color: var(--color-cnt-midnight); }`]
})
export class AuthCheckComponent implements OnInit {
  private readonly auth        = inject(AuthService);
  private readonly api         = inject(ApiService);
  private readonly gestorState = inject(GestorStateService);
  private readonly router      = inject(Router);
  private readonly route       = inject(ActivatedRoute);

  ngOnInit(): void {
    this.auth.checkAuth();

    const interval = setInterval(() => {
      const status = this.auth.isAuthenticated();

      if (status === true) {
        clearInterval(interval);
        // Detectar rol: ¿es gestor o admin?
        this.api.getGestorOrganizacion().subscribe({
          next: (r: any) => {
            this.gestorState.setOrg(r.data ?? r);
            this.router.navigate(['/gestor/dashboard']);
          },
          error: () => {
            // 403 = admin → volver a la URL que estaba antes del reload,
            // o al dashboard si no hay returnUrl (acceso directo a /auth-check)
            const returnUrl = this.route.snapshot.queryParamMap.get('returnUrl') ?? '/dashboard';
            this.router.navigateByUrl(returnUrl);
          }
        });
      } else if (status === false) {
        clearInterval(interval);
        this.auth.redirectToMoodleLogin();
      }
    }, 100);
  }
}
