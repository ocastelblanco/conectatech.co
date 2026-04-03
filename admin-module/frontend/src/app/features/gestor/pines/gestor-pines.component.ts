import { Component, ChangeDetectionStrategy, OnInit, inject, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DatePipe, SlicePipe } from '@angular/common';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { SelectModule } from 'primeng/select';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../../core/services/api.service';
import { GestorStateService } from '../../../core/services/gestor-state.service';

@Component({
  selector: 'cnt-gestor-pines',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, DatePipe, SlicePipe, ButtonModule, TableModule, DialogModule, SelectModule, TagModule, ToastModule, TooltipModule],
  providers: [MessageService],
  templateUrl: './gestor-pines.component.html',
})
export class GestorPinesComponent implements OnInit {
  private readonly api        = inject(ApiService);
  private readonly toast      = inject(MessageService);
  readonly gestorState        = inject(GestorStateService);

  readonly pines       = signal<any[]>([]);
  readonly grupos      = signal<any[]>([]);
  readonly loading     = signal(true);
  readonly saving      = signal(false);
  readonly descargando = signal(false);

  // Filtros
  readonly filtroStatus   = signal<string | null>(null);
  readonly filtroGrupoId  = signal<number | null>(null);
  readonly filtroCursoId  = signal<number | null>(null);

  // Selección para asignación masiva
  readonly seleccionados = signal<any[]>([]);
  readonly dialogAsignar = signal(false);

  // Formulario de asignación
  readonly asignarGrupoId  = signal<number | null>(null);
  readonly asignarCursoId  = signal<number | null>(null);
  readonly asignarRol      = signal<string>('student');

  readonly now = Date.now();

  readonly statusOpciones = [
    { label: 'Todos', value: null },
    { label: 'Disponible', value: 'available' },
    { label: 'Asignado',   value: 'assigned'  },
    { label: 'Activo',     value: 'active'    },
  ];

  readonly rolLabels: Record<string, string> = {
    student:        'Estudiante',
    teacher:        'Profesor',
    editingteacher: 'Profesor Editor',
  };

  // Siempre incluye Estudiante + los teacher_role únicos de los pines cargados
  readonly rolOpciones = computed(() => {
    const teacherRoles = [...new Set(
      this.pines()
        .map((p: any) => p.teacher_role as string)
        .filter((r): r is string => !!r && r !== 'student')
    )];
    return [
      { label: 'Estudiante', value: 'student' },
      ...teacherRoles.map(r => ({ label: this.rolLabels[r] ?? r, value: r })),
    ];
  });

  readonly cursos = computed(() => this.gestorState.org()?.courses ?? []);

  ngOnInit(): void {
    this.cargarGrupos();
    this.cargarPines();
  }

  private cargarGrupos(): void {
    this.api.getGestorGrupos().subscribe({
      next: (r: any) => this.grupos.set(r.data ?? []),
      error: () => {}
    });
  }

  cargarPines(): void {
    this.loading.set(true);
    this.seleccionados.set([]);
    this.api.getGestorPinesLista({
      status:    this.filtroStatus()   ?? undefined,
      group_id:  this.filtroGrupoId()  ?? undefined,
      course_id: this.filtroCursoId()  ?? undefined,
    }).subscribe({
      next: (r: any) => { this.pines.set(r.data ?? []); this.loading.set(false); },
      error: () => { this.loading.set(false); this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar los pines' }); }
    });
  }

  abrirAsignar(): void {
    if (this.seleccionados().length === 0) {
      this.toast.add({ severity: 'warn', summary: 'Aviso', detail: 'Selecciona al menos un pin' });
      return;
    }
    this.asignarGrupoId.set(null);
    this.asignarCursoId.set(null);
    this.asignarRol.set('student');
    this.dialogAsignar.set(true);
  }

  confirmarAsignar(): void {
    if (!this.asignarGrupoId() || !this.asignarCursoId()) {
      this.toast.add({ severity: 'warn', summary: 'Aviso', detail: 'Selecciona grupo y curso' });
      return;
    }
    this.saving.set(true);
    const pinIds = this.seleccionados().map((p: any) => p.id);
    this.api.asignarPinesGestor({
      pin_ids:   pinIds,
      group_id:  this.asignarGrupoId()!,
      course_id: this.asignarCursoId()!,
      role:      this.asignarRol(),
    }).subscribe({
      next: () => {
        this.saving.set(false);
        this.dialogAsignar.set(false);
        this.toast.add({ severity: 'success', summary: 'Asignados', detail: `${pinIds.length} pines asignados` });
        this.cargarPines();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo asignar' });
      }
    });
  }

  descargarCsv(): void {
    this.descargando.set(true);
    this.api.descargarPinesCsv({
      status:    this.filtroStatus()   ?? undefined,
      group_id:  this.filtroGrupoId()  ?? undefined,
      course_id: this.filtroCursoId()  ?? undefined,
    }).subscribe({
      next: (blob: Blob) => {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href     = url;
        a.download = `pines-${Date.now()}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        this.descargando.set(false);
      },
      error: () => {
        this.descargando.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo descargar el CSV' });
      }
    });
  }

  getStatusSeverity(status: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      available: 'info',
      assigned:  'warn',
      active:    'success',
    };
    return map[status] ?? 'secondary';
  }

  getStatusLabel(status: string): string {
    const map: Record<string, string> = { available: 'Disponible', assigned: 'Asignado', active: 'Activo' };
    return map[status] ?? status;
  }

  getRolLabel(role: string): string {
    const map: Record<string, string> = { student: 'Estudiante', teacher: 'Profesor', editingteacher: 'Prof. Editor' };
    return map[role] ?? role;
  }
}
