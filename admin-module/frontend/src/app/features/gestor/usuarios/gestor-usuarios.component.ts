import { Component, ChangeDetectionStrategy, OnInit, inject, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
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
    ButtonModule, TableModule, InputTextModule, SelectModule, DialogModule,
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
  readonly saving       = signal(false);
  readonly searchInput  = signal('');

  // Filtros
  readonly cursoSeleccionado   = signal<number | null>(null);
  readonly grupoSeleccionado   = signal<number | null>(null);
  readonly colegioSeleccionado = signal<number | null>(null);

  // Diálogo reset contraseña
  readonly usuarioReset = signal<any | null>(null);
  newPassword     = '';
  confirmPassword = '';

  // Diálogo editar perfil
  readonly usuarioEditar = signal<any | null>(null);
  editFirstname = '';
  editLastname  = '';
  editEmail     = '';

  // Computed: opciones de filtro extraídas del listado
  readonly cursosFiltro = computed(() => {
    const map = new Map<number, string>();
    for (const u of this.usuarios())
      for (const c of u.cursos ?? []) map.set(c.id, c.name);
    return [...map.entries()].map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.name.localeCompare(b.name));
  });

  readonly gruposFiltro = computed(() => {
    const map = new Map<number, string>();
    for (const u of this.usuarios())
      for (const g of u.grupos ?? []) map.set(g.id, g.name);
    return [...map.entries()].map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.name.localeCompare(b.name));
  });

  readonly colegiosFiltro = computed(() => {
    const map = new Map<number, string>();
    for (const u of this.usuarios())
      for (const c of u.colegios ?? []) map.set(c.id, c.name);
    return [...map.entries()].map(([id, name]) => ({ id, name }))
      .sort((a, b) => a.name.localeCompare(b.name));
  });

  readonly usuariosFiltrados = computed(() => {
    let list = this.usuarios();
    const curso   = this.cursoSeleccionado();
    const grupo   = this.grupoSeleccionado();
    const colegio = this.colegioSeleccionado();
    if (curso)   list = list.filter(u => u.cursos?.some((c: any)   => c.id === curso));
    if (grupo)   list = list.filter(u => u.grupos?.some((g: any)   => g.id === grupo));
    if (colegio) list = list.filter(u => u.colegios?.some((c: any) => c.id === colegio));
    return list;
  });

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

  // ── Reset contraseña ────────────────────────────────────────────────────────

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

  // ── Editar perfil ───────────────────────────────────────────────────────────

  abrirEditar(usuario: any): void {
    this.usuarioEditar.set(usuario);
    this.editFirstname = usuario.firstname;
    this.editLastname  = usuario.lastname;
    this.editEmail     = usuario.email;
  }

  confirmarEditar(): void {
    if (!this.editFirstname.trim() || !this.editLastname.trim()) {
      this.toast.add({ severity: 'warn', summary: 'Campos requeridos', detail: 'Nombre y apellido son obligatorios' });
      return;
    }
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(this.editEmail)) {
      this.toast.add({ severity: 'warn', summary: 'Email inválido', detail: 'Ingresa un email válido' });
      return;
    }
    const u = this.usuarioEditar();
    if (!u) return;
    this.saving.set(true);
    this.api.editarPerfilGestor(u.id, {
      firstname: this.editFirstname.trim(),
      lastname:  this.editLastname.trim(),
      email:     this.editEmail.trim(),
    }).subscribe({
      next: () => {
        this.saving.set(false);
        this.usuarios.update(list => list.map(x => x.id === u.id
          ? { ...x, firstname: this.editFirstname.trim(), lastname: this.editLastname.trim(), email: this.editEmail.trim() }
          : x
        ));
        this.usuarioEditar.set(null);
        this.toast.add({ severity: 'success', summary: 'Perfil actualizado', detail: `${this.editFirstname} ${this.editLastname}` });
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo actualizar el perfil' });
      }
    });
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  formatLastLogin(ts: number): string {
    if (!ts) return 'Nunca';
    return new Date(ts * 1000).toLocaleDateString('es-CO', {
      day: '2-digit', month: '2-digit', year: '2-digit',
      hour: '2-digit', minute: '2-digit',
    });
  }
}
