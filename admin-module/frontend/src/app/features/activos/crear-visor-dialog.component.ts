import {
  Component, ChangeDetectionStrategy, input, output,
  signal, computed, effect, inject,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { DialogModule } from 'primeng/dialog';
import { SelectModule } from 'primeng/select';
import { InputTextModule } from 'primeng/inputtext';
import { CheckboxModule } from 'primeng/checkbox';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';
import { AssetItem } from '../../core/services/cdn-api.service';

interface CursoRepo {
  id: number;
  shortname: string;
  fullname: string;
  secciones: { num: number; titulo: string }[];
}

@Component({
  selector: 'cnt-crear-visor-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, DialogModule, SelectModule, InputTextModule, CheckboxModule],
  template: `
    <p-dialog
      [visible]="visible()"
      (visibleChange)="onVisibleChange($event)"
      [modal]="true"
      [draggable]="false"
      [resizable]="false"
      header="Crear visor en Moodle"
      class="w-full max-w-lg"
    >
      <div class="flex flex-col gap-4 py-2">

        <!-- Título del recurso -->
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700">Título del recurso en Moodle</label>
          <input
            pInputText
            [ngModel]="pdfTitle()"
            (ngModelChange)="pdfTitle.set($event)"
            class="w-full text-sm"
            placeholder="Título que aparecerá en Moodle"
          />
        </div>

        <!-- Selector de curso -->
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700">Curso repositorio</label>
          @if (loadingCursos()) {
            <div class="text-sm text-gray-400 py-2">Cargando cursos...</div>
          } @else {
            <p-select
              [ngModel]="selectedCourse()"
              (ngModelChange)="onCourseChange($event)"
              [options]="cursos()"
              optionLabel="fullname"
              placeholder="Seleccionar curso..."
              class="w-full"
              [filter]="true"
              filterBy="fullname,shortname"
            />
          }
        </div>

        <!-- Selector de sección -->
        <div class="flex flex-col gap-1">
          <label class="text-sm font-medium text-gray-700">Sección</label>
          <p-select
            [ngModel]="selectedSeccion()"
            (ngModelChange)="selectedSeccion.set($event)"
            [options]="secciones()"
            optionLabel="titulo"
            placeholder="Seleccionar sección..."
            class="w-full"
            [disabled]="!selectedCourse()"
          />
        </div>

        <!-- Rango de páginas (opcional) -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center gap-2">
            <p-checkbox
              [ngModel]="usePageRange()"
              (ngModelChange)="usePageRange.set($event)"
              [binary]="true"
              inputId="pageRangeCheck"
            />
            <label for="pageRangeCheck" class="text-sm font-medium text-gray-700 cursor-pointer">
              Limitar rango de páginas
            </label>
          </div>
          @if (usePageRange()) {
            <div class="grid grid-cols-2 gap-3 pl-6">
              <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">Página inicio</label>
                <input
                  pInputText
                  type="number"
                  [ngModel]="pageStart()"
                  (ngModelChange)="pageStart.set($event ? +$event : null)"
                  class="w-full text-sm"
                  placeholder="1"
                  min="1"
                />
              </div>
              <div class="flex flex-col gap-1">
                <label class="text-xs text-gray-500">Página fin</label>
                <input
                  pInputText
                  type="number"
                  [ngModel]="pageEnd()"
                  (ngModelChange)="pageEnd.set($event ? +$event : null)"
                  class="w-full text-sm"
                  placeholder="10"
                  min="1"
                />
              </div>
            </div>
          }
        </div>

      </div>

      <ng-template #footer>
        <div class="flex justify-end gap-2">
          <p-button
            label="Cancelar"
            severity="secondary"
            [text]="true"
            (onClick)="onVisibleChange(false)"
          />
          <p-button
            label="Crear visor"
            icon="pi pi-play"
            [loading]="creating()"
            [disabled]="!canSubmit()"
            (onClick)="submit()"
          />
        </div>
      </ng-template>
    </p-dialog>
  `,
})
export class CrearVisorDialogComponent {
  readonly visible = input<boolean>(false);
  readonly pdf = input<AssetItem | null>(null);
  readonly closed = output<void>();
  readonly created = output<void>();

  private readonly api = inject(ApiService);
  private readonly messageService = inject(MessageService);

  readonly cursos = signal<CursoRepo[]>([]);
  readonly loadingCursos = signal(false);
  readonly selectedCourse = signal<CursoRepo | null>(null);
  readonly selectedSeccion = signal<{ num: number; titulo: string } | null>(null);
  readonly usePageRange = signal(false);
  readonly pageStart = signal<number | null>(null);
  readonly pageEnd = signal<number | null>(null);
  readonly pdfTitle = signal('');
  readonly creating = signal(false);

  readonly secciones = computed(() => this.selectedCourse()?.secciones ?? []);
  readonly canSubmit = computed(() =>
    !!this.pdfTitle().trim() && !!this.selectedCourse() && !!this.selectedSeccion() && !this.creating()
  );

  constructor() {
    effect(() => {
      if (this.visible()) {
        const p = this.pdf();
        if (p) this.pdfTitle.set(p.title);
        this.selectedCourse.set(null);
        this.selectedSeccion.set(null);
        this.usePageRange.set(false);
        this.pageStart.set(null);
        this.pageEnd.set(null);
        if (this.cursos().length === 0) this.loadCursos();
      }
    });
  }

  loadCursos(): void {
    this.loadingCursos.set(true);
    this.api.getActivosCursosRepositorio().subscribe({
      next: (res) => {
        this.cursos.set(res.cursos ?? []);
        this.loadingCursos.set(false);
      },
      error: () => this.loadingCursos.set(false),
    });
  }

  onCourseChange(course: CursoRepo | null): void {
    this.selectedCourse.set(course);
    this.selectedSeccion.set(null);
  }

  onVisibleChange(v: boolean): void {
    if (!v) this.closed.emit();
  }

  submit(): void {
    const pdf = this.pdf();
    const course = this.selectedCourse();
    const seccion = this.selectedSeccion();
    if (!pdf || !course || !seccion) return;

    this.creating.set(true);
    const body: any = {
      pdfId: pdf.id,
      pdfTitle: this.pdfTitle().trim(),
      courseId: course.id,
      seccionNum: seccion.num,
    };
    if (this.usePageRange() && this.pageStart() !== null) body.pageStart = this.pageStart();
    if (this.usePageRange() && this.pageEnd() !== null) body.pageEnd = this.pageEnd();

    this.api.crearVisor(body).subscribe({
      next: () => {
        this.creating.set(false);
        this.messageService.add({
          severity: 'success',
          summary: 'Visor creado',
          detail: 'Recurso añadido a Moodle exitosamente',
        });
        this.created.emit();
        this.closed.emit();
      },
      error: (e: any) => {
        this.creating.set(false);
        this.messageService.add({
          severity: 'error',
          summary: 'Error al crear visor',
          detail: e.error?.error || 'Error inesperado',
        });
      },
    });
  }
}
