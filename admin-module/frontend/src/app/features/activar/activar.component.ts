import { Component, ChangeDetectionStrategy, signal, inject } from '@angular/core';
import { FormsModule, ReactiveFormsModule, FormBuilder, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { PasswordModule } from 'primeng/password';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

type Paso = 'inicio' | 'pin-gestor' | 'pin-regular' | 'login' | 'registro-nuevo' | 'confirmacion';

function cedulaValidator(control: AbstractControl): ValidationErrors | null {
  const val = (control.value ?? '') as string;
  if (!val) return null; // required se encarga del vacío
  if (!/^\d+$/.test(val)) return { cedula: 'soloNumeros' };
  const n = Number(val);
  if (n < 10_000 || n > 2_000_000_000) return { cedula: 'rangoInvalido' };
  return null;
}

@Component({
  selector: 'cnt-activar',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ReactiveFormsModule, ButtonModule, InputTextModule, PasswordModule, TagModule, ToastModule],
  providers: [MessageService],
  templateUrl: './activar.component.html',
})
export class ActivarComponent {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);
  private readonly fb    = inject(FormBuilder);

  // Estado
  readonly paso       = signal<Paso>('inicio');
  readonly cargando   = signal(false);
  readonly hashInput  = signal('');
  readonly pinData    = signal<any>(null);
  readonly userId     = signal<number | null>(null);
  readonly resultado  = signal<any>(null);
  readonly year       = new Date().getFullYear();

  // Formularios reactivos
  readonly loginForm = this.fb.group({
    username: ['', [Validators.required, cedulaValidator]],
    password: ['', Validators.required],
  });

  readonly gestorForm = this.fb.group({
    firstname: ['', Validators.required],
    lastname:  ['', Validators.required],
    email:     ['', [Validators.required, Validators.email]],
    username:  ['', [Validators.required, cedulaValidator]],
    password:  ['', [Validators.required, Validators.minLength(8)]],
  });

  readonly registroForm = this.fb.group({
    firstname: ['', Validators.required],
    lastname:  ['', Validators.required],
    email:     ['', [Validators.required, Validators.email]],
    username:  ['', [Validators.required, cedulaValidator]],
    password:  ['', [Validators.required, Validators.minLength(8)]],
  });

  // ── Paso 1: resolver pin ────────────────────────────────────────────────────

  resolverPin(): void {
    const hash = this.hashInput().trim();
    if (!hash) return;
    this.cargando.set(true);
    this.api.resolverPin(hash).subscribe({
      next: (r: any) => {
        this.cargando.set(false);
        this.pinData.set(r.pin);
        this.paso.set(r.type === 'gestor' ? 'pin-gestor' : 'pin-regular');
      },
      error: (err: any) => {
        this.cargando.set(false);
        this.toast.add({ severity: 'error', summary: 'Pin inválido', detail: err.error?.error ?? 'No se pudo resolver el pin' });
      }
    });
  }

  // ── Paso 2a: activar gestor ─────────────────────────────────────────────────

  activarGestor(): void {
    if (this.gestorForm.invalid) { this.gestorForm.markAllAsTouched(); return; }
    this.cargando.set(true);
    const v = this.gestorForm.value;
    this.api.activarGestor({
      hash:      this.hashInput().trim(),
      firstname: v.firstname!,
      lastname:  v.lastname!,
      email:     v.email!,
      username:  v.username!,
      password:  v.password!,
    }).subscribe({
      next: () => {
        this.cargando.set(false);
        this.resultado.set({ tipo: 'gestor', username: v.username });
        this.paso.set('confirmacion');
      },
      error: (err: any) => {
        this.cargando.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo activar' });
      }
    });
  }

  // ── Paso 2b: crear cuenta nueva + activar pin ───────────────────────────────

  registrarYActivar(): void {
    if (this.registroForm.invalid) { this.registroForm.markAllAsTouched(); return; }
    this.cargando.set(true);
    const v = this.registroForm.value;
    this.api.registrarUsuario({
      firstname: v.firstname!,
      lastname:  v.lastname!,
      email:     v.email!,
      username:  v.username!,
      password:  v.password!,
    }).subscribe({
      next: (r: any) => {
        this.api.activarPin({ hash: this.hashInput().trim(), user_id: r.user_id }).subscribe({
          next: (res: any) => {
            this.cargando.set(false);
            this.resultado.set({ tipo: 'pin', ...res });
            this.paso.set('confirmacion');
          },
          error: (err: any) => {
            this.cargando.set(false);
            this.toast.add({ severity: 'error', summary: 'Error al activar', detail: err.error?.error ?? 'No se pudo activar el pin' });
          }
        });
      },
      error: (err: any) => {
        this.cargando.set(false);
        this.toast.add({ severity: 'error', summary: 'Error al crear cuenta', detail: err.error?.error ?? 'No se pudo crear la cuenta' });
      }
    });
  }

  // ── Paso 2c: login + activar pin ────────────────────────────────────────────

  loginYActivar(): void {
    if (this.loginForm.invalid) { this.loginForm.markAllAsTouched(); return; }
    this.cargando.set(true);
    const v = this.loginForm.value;
    this.api.activarLogin({ username: v.username!, password: v.password! }).subscribe({
      next: (r: any) => {
        this.userId.set(r.user_id);
        this.api.activarPin({ hash: this.hashInput().trim(), user_id: r.user_id }).subscribe({
          next: (res: any) => {
            this.cargando.set(false);
            this.resultado.set({ tipo: 'pin', ...res });
            this.paso.set('confirmacion');
          },
          error: (err: any) => {
            this.cargando.set(false);
            this.toast.add({ severity: 'error', summary: 'Error al activar', detail: err.error?.error ?? 'No se pudo activar el pin' });
          }
        });
      },
      error: (err: any) => {
        this.cargando.set(false);
        this.toast.add({ severity: 'error', summary: 'Credenciales incorrectas', detail: err.error?.error ?? 'Usuario o contraseña incorrectos' });
      }
    });
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  reiniciar(): void {
    this.paso.set('inicio');
    this.hashInput.set('');
    this.pinData.set(null);
    this.userId.set(null);
    this.resultado.set(null);
    this.loginForm.reset();
    this.gestorForm.reset();
    this.registroForm.reset();
  }

  irAMoodle(): void {
    window.location.href = this.resultado()?.course_url ?? 'https://conectatech.co';
  }

  getRolLabel(role: string): string {
    const map: Record<string, string> = { student: 'Estudiante', teacher: 'Profesor', editingteacher: 'Profesor Editor' };
    return map[role] ?? role;
  }

  formatDate(ts: number): string {
    return new Date(ts * 1000).toLocaleDateString('es-CO', { day: '2-digit', month: 'long', year: 'numeric' });
  }

  fieldInvalid(form: any, field: string): boolean {
    const c = form.get(field);
    return !!(c?.invalid && c?.touched);
  }

  cedulaError(form: any, field: string): string | null {
    const c = form.get(field);
    if (!c?.touched) return null;
    if (c.hasError('required')) return 'El documento de identidad es obligatorio';
    if (c.hasError('cedula')) {
      return c.errors?.['cedula'] === 'soloNumeros'
        ? 'Solo se permiten números, sin comas ni puntos'
        : 'Debe ser un número entre 10.000 y 2.000.000.000';
    }
    return null;
  }
}
