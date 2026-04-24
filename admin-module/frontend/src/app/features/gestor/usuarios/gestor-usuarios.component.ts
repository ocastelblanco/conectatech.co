import { Component, ChangeDetectionStrategy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { InputTextModule } from 'primeng/inputtext';
import { DialogModule } from 'primeng/dialog';
import { PasswordModule } from 'primeng/password';
import { ToastModule } from 'primeng/toast';
import { TooltipModule } from 'primeng/tooltip';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../../core/services/api.service';

@Component({
  selector: 'cnt-gestor-usuarios',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    FormsModule,
    ButtonModule, TableModule, InputTextModule, DialogModule,
    PasswordModule, ToastModule, TooltipModule, TagModule,
  ],
  providers: [MessageService],
  templateUrl: './gestor-usuarios.component.html',
})
export class GestorUsuariosComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly usuarios     = signal<any[]>([]);
  readonly loading      = signal(false);
  readonly searchInput  = signal('');
  readonly saving       = signal(false);
  readonly usuarioReset = signal<any | null>(null);

  newPassword     = '';
  confirmPassword = '';

  private searchTimer: ReturnType<typeof setTimeout> | null = null;

  ngOnInit(): void { this.cargar(); }

  private cargar(search?: string): void {
    this.loading.set(true);
    this.api.getGestorUsuarios(search).subscribe({
      next: (r: any) => {
        this.usuarios.set(r.data ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los usuarios' });
      }
    });
  }

  onSearch(value: string): void {
    this.searchInput.set(value);
    if (this.searchTimer) clearTimeout(this.searchTimer);
    this.searchTimer = setTimeout(() => {
      const q = value.trim();
      this.cargar(q.length >= 3 ? q : undefined);
    }, 400);
  }

  abrirReset(usuario: any): void {
    this.usuarioReset.set(usuario);
    this.newPassword = '';
    this.confirmPassword = '';
  }

  confirmarReset(): void {
    if (this.newPassword.length < 8) {
      this.toast.add({ severity: 'warn', summary: 'Contraseña inválida', detail: 'Mínimo 8 caracteres' });
      return;
    }
    if (this.newPassword !== this.confirmPassword) {
      this.toast.add({ severity: 'warn', summary: 'No coinciden', detail: 'Los dos campos deben ser iguales' });
      return;
    }
    const u = this.usuarioReset();
    if (!u) return;
    this.saving.set(true);
    this.api.resetearPasswordGestor(u.id, this.newPassword).subscribe({
      next: () => {
        this.saving.set(false);
        this.usuarioReset.set(null);
        this.toast.add({ severity: 'success', summary: 'Contraseña restablecida', detail: `${u.firstname} ${u.lastname}` });
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo restablecer la contraseña' });
      }
    });
  }

  formatLastLogin(ts: number): string {
    if (!ts) return 'Nunca';
    return new Date(ts * 1000).toLocaleDateString('es-CO', {
      day: '2-digit', month: '2-digit', year: '2-digit',
      hour: '2-digit', minute: '2-digit',
    });
  }
}
