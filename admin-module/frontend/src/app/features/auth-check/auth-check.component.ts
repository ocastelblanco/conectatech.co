import { Component, ChangeDetectionStrategy, OnInit, inject } from '@angular/core';
import { Router } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { ProgressSpinnerModule } from 'primeng/progressspinner';

@Component({
  selector: 'cnt-auth-check',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    ProgressSpinnerModule
  ],
  template: `
    <div class="fondo flex flex-col items-center justify-center min-h-screen">
      <img src="logo-vertical-blanco.svg" alt="ConectaTech" class="h-20 mb-8" />
      <p-progressSpinner strokeWidth="3" class="w-12 h-12" />
      <p class="text-white/60 mt-4 text-sm">Verificando sesion...</p>
    </div>
  `,
  styles: [`
    .fondo {
      background-color: var(--color-cnt-midnight);
    }
  `]
})
export class AuthCheckComponent implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  ngOnInit(): void {
    this.auth.checkAuth();
    const interval = setInterval(() => {
      const status = this.auth.isAuthenticated();
      if (status === true) {
        clearInterval(interval);
        this.router.navigate(['/dashboard']);
      } else if (status === false) {
        clearInterval(interval);
        this.auth.redirectToMoodleLogin();
      }
    }, 100);
  }
}
