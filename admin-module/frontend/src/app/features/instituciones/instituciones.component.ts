import { Component, ChangeDetectionStrategy, inject, OnInit, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { ProgressBarModule } from 'primeng/progressbar';
import { MessageService, ConfirmationService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-instituciones',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService, ConfirmationService],
  imports: [
    FormsModule,
    ButtonModule, TableModule, DialogModule, InputTextModule,
    SelectModule, ToastModule, ConfirmDialogModule, TagModule, TooltipModule,
    ProgressBarModule,
  ],
  templateUrl: './instituciones.component.html',
})
export class InstitucionesComponent implements OnInit {
  private readonly api     = inject(ApiService);
  private readonly toast   = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);

  readonly instituciones  = signal<any[]>([]);
  readonly categorias     = signal<any[]>([]);
  readonly loading        = signal(true);
  readonly saving         = signal(false);
  readonly editando       = signal<any | null>(null);

  // Diálogo de progreso
  readonly dialogVisible    = signal(false);

  // Diálogo de progreso
  readonly progresoVisible  = signal(false);
  readonly progresoInst     = signal<any | null>(null);
  readonly progresoCursos   = signal<any[]>([]);
  readonly loadingProgreso  = signal(false);

  // Campos del formulario
  formName  = '';
  formCatId = 0;

  readonly totalEstudiantes = computed(() =>
    this.instituciones().reduce((s, i) => s + (i.estudiantes ?? 0), 0)
  );

  ngOnInit(): void {
    this.cargar();
    this.api.getCategoriasInstituciones().subscribe({
      next: (r: any) => this.categorias.set(r.categorias ?? []),
    });
  }

  private cargar(): void {
    this.loading.set(true);
    this.api.getInstituciones().subscribe({
      next: (r: any) => {
        this.instituciones.set(r.data ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las instituciones' });
      },
    });
  }

  abrirCrear(): void {
    this.editando.set(null);
    this.formName  = '';
    this.formCatId = 0;
    this.dialogVisible.set(true);
  }

  abrirEditar(inst: any): void {
    this.editando.set(inst);
    this.formName  = inst.name;
    this.formCatId = inst.moodle_category_id;
    this.dialogVisible.set(true);
  }

  cerrarDialog(): void {
    this.dialogVisible.set(false);
    this.editando.set(null);
    this.formName  = '';
    this.formCatId = 0;
  }

  guardar(): void {
    if (!this.formName.trim() || !this.formCatId) return;

    this.saving.set(true);
    const body = {
      name: this.formName.trim(),
      moodle_category_id: this.formCatId,
    };

    const op = this.editando()
      ? this.api.actualizarInstitucion(this.editando().id, body)
      : this.api.crearInstitucion(body);

    const wasEditing = !!this.editando();
    op.subscribe({
      next: () => {
        this.saving.set(false);
        this.cerrarDialog();
        this.cargar();
        this.api.getCategoriasInstituciones().subscribe({
          next: (r: any) => this.categorias.set(r.categorias ?? []),
        });
        this.toast.add({
          severity: 'success',
          summary: 'Guardado',
          detail: wasEditing ? 'Institución actualizada' : 'Institución creada',
        });
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({
          severity: 'error',
          summary: 'Error',
          detail: err?.error?.error ?? 'No se pudo guardar la institución',
        });
      },
    });
  }

  eliminar(inst: any): void {
    // setTimeout requerido: ConfirmDialog de PrimeNG accede a confirmation.payload
    // antes de que OnPush complete el ciclo de CD → TypeError en la promesa interna.
    setTimeout(() => {
      this.confirm.confirm({
        message: `¿Eliminar la institución "${inst.name}"? Esta acción no se puede deshacer.`,
        header: 'Confirmar eliminación',
        icon: 'pi pi-exclamation-triangle',
        acceptLabel: 'Eliminar',
        rejectLabel: 'Cancelar',
        acceptButtonStyleClass: 'p-button-danger',
        accept: () => {
          this.api.eliminarInstitucion(inst.id).subscribe({
            next: () => {
              this.cargar();
              this.api.getCategoriasInstituciones().subscribe({
                next: (r: any) => this.categorias.set(r.categorias ?? []),
              });
              this.toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Institución eliminada' });
            },
            error: () => this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo eliminar la institución' }),
          });
        },
      });
    });
  }

  verProgreso(inst: any): void {
    this.progresoInst.set(inst);
    this.progresoVisible.set(true);
    this.progresoCursos.set([]);
    this.loadingProgreso.set(true);

    this.api.getProgresoInstitucion(inst.id).subscribe({
      next: (r: any) => {
        this.progresoCursos.set(r.data?.cursos ?? []);
        this.loadingProgreso.set(false);
      },
      error: () => {
        this.loadingProgreso.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el progreso' });
      },
    });
  }

  getPctColor(pct: number): string {
    if (pct >= 70) return '#22c55e';
    if (pct >= 30) return '#f59e0b';
    return '#ef4444';
  }
}
