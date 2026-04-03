import { Component, ChangeDetectionStrategy, inject } from '@angular/core';
import { RouterOutlet, RouterLink, RouterLinkActive } from '@angular/router';
import { ButtonModule } from 'primeng/button';
import { AuthService } from '../../core/services/auth.service';
import { GestorStateService } from '../../core/services/gestor-state.service';

@Component({
  selector: 'cnt-gestor-shell',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, ButtonModule],
  template: `
    <div class="flex h-screen overflow-hidden bg-gray-50">
      <!-- Sidebar gestor -->
      <aside class="flex flex-col w-64 min-h-screen" style="background-color: #1D2B36;">
        <div class="flex items-center px-2 py-2 border-b border-white/10">
          <img src="logo-horizontal-blanco.svg"
              alt="ConectaTech"
              class="sidebar-logo w-auto" />
        </div>
        <nav class="flex-1 px-3 py-4 space-y-1">
          @for (item of navItems; track item.route) {
            <a [routerLink]="item.route"
               routerLinkActive="active-link"
               class="nav-link flex items-center gap-3 px-4 py-3 rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition-all duration-200 no-underline font-medium text-sm">
              <i [class]="item.icon + ' text-base w-5 text-center'"></i>
              <span>{{ item.label }}</span>
            </a>
          }
        </nav>
        <div class="px-6 py-4 border-t border-white/10">
          <p class="text-white/50 text-xs mb-1">{{ gestorState.org()?.name ?? '' }}</p>
          <a href="https://conectatech.co" target="_blank"
             class="text-white/40 text-xs hover:text-white/70 transition-colors">
            Ir a Moodle
          </a>
        </div>
      </aside>

      <!-- Contenido principal -->
      <div class="flex flex-col flex-1 overflow-hidden">
        <header class="flex items-center justify-between px-6 py-4 bg-white border-b border-gray-200 shadow-sm">
          <h1 class="text-xl font-heading font-bold m-0" style="color: #1D2B36;">{{ pageTitle() }}</h1>
          <div class="flex items-center gap-3">
            <span class="text-sm text-gray-500">Portal Gestor</span>
            <p-button label="Cerrar sesión" icon="pi pi-sign-out"
                      severity="secondary" size="small" [text]="true"
                      (onClick)="logout()" />
          </div>
        </header>
        <main class="flex-1 overflow-y-auto p-6">
          <router-outlet />
        </main>
      </div>
    </div>
  `,
  styles: [`
    .active-link {
      background-color: rgba(255,255,255,0.15) !important;
      color: white !important;
    }
    .sidebar-logo {
      height: 4rem;
    }
  `]
})
export class GestorShellComponent {
  readonly gestorState = inject(GestorStateService);
  private readonly auth = inject(AuthService);

  readonly navItems = [
    { label: 'Dashboard', icon: 'pi pi-home', route: '/gestor/dashboard' },
    { label: 'Grupos', icon: 'pi pi-users', route: '/gestor/grupos' },
    { label: 'Pines', icon: 'pi pi-ticket', route: '/gestor/pines' },
  ];

  pageTitle(): string {
    return 'Portal Gestor';
  }

  logout(): void {
    this.auth.redirectToMoodleLogin();
  }
}
