import {
  Component,
  ChangeDetectionStrategy,
  inject,
  signal,
  computed,
  OnInit,
  OnDestroy,
} from '@angular/core';
import { ActivatedRoute, Router } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { Subject, Subscription } from 'rxjs';
import { debounceTime } from 'rxjs/operators';
import { ButtonModule } from 'primeng/button';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService, ConfirmationService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-arbol-editor',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    FormsModule,
    ButtonModule,
    InputTextModule,
    SelectModule,
    CardModule,
    ToastModule,
    ConfirmDialogModule,
    TooltipModule,
  ],
  providers: [MessageService, ConfirmationService],
  templateUrl: './arbol-editor.component.html',
})
export class ArbolEditorComponent implements OnInit, OnDestroy {
  private readonly api = inject(ApiService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly toast = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);

  readonly arbol = signal<any>(null);
  readonly loading = signal(true);
  readonly saving = signal(false);
  readonly saveStatus = signal<'idle' | 'saving' | 'saved' | 'error'>('idle');
  readonly gradoActivo = signal<any>(null);
  readonly cursoActivo = signal<any>(null);
  readonly plantillas = signal<any[]>([]);
  readonly categoriasRaiz = signal<any[]>([]);

  readonly esNuevo = computed(() => this.route.snapshot.params['id'] === 'nuevo');

  readonly plantillasOpciones = computed(() =>
    this.plantillas().flatMap((p: any) =>
      (p.cursos ?? []).map((c: any) => ({ label: `${c.shortname} — ${c.fullname}`, value: c.shortname }))
    )
  );

  readonly categoriasOpciones = computed(() =>
    this.categoriasRaiz().map((c: any) => ({ label: c.name, value: c.id }))
  );

  private readonly saveSubject = new Subject<void>();
  private readonly subs = new Subscription();

  ngOnInit(): void {
    this.subs.add(
      this.saveSubject.pipe(debounceTime(800)).subscribe(() => this._doSave())
    );

    const id = this.route.snapshot.params['id'];

    // Load plantillas and categorias in parallel
    this.api.getArbolesPlantillas().subscribe({
      next: (r: any) => this.plantillas.set(r.plantillas ?? []),
      error: () => {},
    });
    this.api.getArbolesCategoriasRaiz().subscribe({
      next: (r: any) => this.categoriasRaiz.set(r.categorias ?? []),
      error: () => {},
    });

    if (id === 'nuevo') {
      this.arbol.set({
        nombre: '',
        shortname: '',
        periodo: '',
        institucion: '',
        categoria_raiz: '',
        grados: [],
      });
      this.loading.set(false);
    } else {
      this.api.getArbol(id).subscribe({
        next: (r: any) => {
          this.arbol.set(r.arbol);
          this.loading.set(false);
        },
        error: (err: any) => {
          this.loading.set(false);
          this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo cargar el árbol' });
        },
      });
    }
  }

  ngOnDestroy(): void {
    this.subs.unsubscribe();
  }

  onArbolChange(): void {
    if (this.esNuevo()) return;
    this.saveSubject.next();
  }

  guardarArbol(): void {
    const a = this.arbol();
    if (!a) return;

    if (this.esNuevo()) {
      this.saving.set(true);
      this.saveStatus.set('saving');
      this.api.crearArbol(a).subscribe({
        next: (r: any) => {
          this.saving.set(false);
          this.saveStatus.set('saved');
          this.toast.add({ severity: 'success', summary: 'Creado', detail: 'Árbol creado correctamente' });
          this.router.navigate(['/arboles', r.arbol.id]);
        },
        error: (err: any) => {
          this.saving.set(false);
          this.saveStatus.set('error');
          this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo crear el árbol' });
        },
      });
    } else {
      this.saveSubject.next();
    }
  }

  private _doSave(): void {
    const a = this.arbol();
    if (!a?.id) return;
    this.saving.set(true);
    this.saveStatus.set('saving');
    this.api.guardarArbol(a.id, a).subscribe({
      next: () => {
        this.saving.set(false);
        this.saveStatus.set('saved');
        setTimeout(() => {
          if (this.saveStatus() === 'saved') this.saveStatus.set('idle');
        }, 3000);
      },
      error: () => {
        this.saving.set(false);
        this.saveStatus.set('error');
      },
    });
  }

  addGrado(): void {
    const a = this.arbol();
    if (!a) return;
    const nuevo = { id: `g-${Date.now()}`, nombre: 'Nuevo grado', shortname: '', cursos: [] };
    this.arbol.set({ ...a, grados: [...(a.grados ?? []), nuevo] });
    this.onArbolChange();
  }

  removeGrado(grado: any): void {
    this.confirm.confirm({
      message: `¿Eliminar el grado "${grado.nombre}" y todos sus cursos?`,
      header: 'Confirmar',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        const a = this.arbol();
        if (!a) return;
        this.arbol.set({ ...a, grados: a.grados.filter((g: any) => g.id !== grado.id) });
        if (this.gradoActivo()?.id === grado.id) {
          this.gradoActivo.set(null);
          this.cursoActivo.set(null);
        }
        this.onArbolChange();
      },
    });
  }

  addCurso(grado: any): void {
    const a = this.arbol();
    if (!a) return;
    const nuevoCurso = {
      id: `c-${Date.now()}`,
      nombre: 'Nueva área',
      shortname: '',
      templatecourse: '',
      startdate: '',
      enddate: '',
      temas: [],
    };
    const gradosActualizados = a.grados.map((g: any) =>
      g.id === grado.id ? { ...g, cursos: [...(g.cursos ?? []), nuevoCurso] } : g
    );
    this.arbol.set({ ...a, grados: gradosActualizados });
    this.seleccionarCurso(
      gradosActualizados.find((g: any) => g.id === grado.id),
      nuevoCurso
    );
    this.onArbolChange();
  }

  removeCurso(grado: any, curso: any): void {
    const a = this.arbol();
    if (!a) return;
    const gradosActualizados = a.grados.map((g: any) =>
      g.id === grado.id
        ? { ...g, cursos: g.cursos.filter((c: any) => c.id !== curso.id) }
        : g
    );
    this.arbol.set({ ...a, grados: gradosActualizados });
    if (this.cursoActivo()?.id === curso.id) {
      this.cursoActivo.set(null);
      this.gradoActivo.set(null);
    }
    this.onArbolChange();
  }

  seleccionarCurso(grado: any, curso: any): void {
    this.gradoActivo.set(grado);
    this.cursoActivo.set(curso);
  }

  updateArbolField(field: string, value: any): void {
    this.arbol.update(a => ({ ...a, [field]: value }));
    this.onArbolChange();
  }

  updateGradoField(grado: any, field: string, value: any): void {
    const a = this.arbol();
    if (!a) return;
    const gradosActualizados = a.grados.map((g: any) =>
      g.id === grado.id ? { ...g, [field]: value } : g
    );
    this.arbol.set({ ...a, grados: gradosActualizados });
    if (this.gradoActivo()?.id === grado.id) {
      this.gradoActivo.update(g => ({ ...g, [field]: value }));
    }
    this.onArbolChange();
  }

  updateCursoField(field: string, value: any): void {
    const grado = this.gradoActivo();
    const curso = this.cursoActivo();
    if (!grado || !curso) return;
    const a = this.arbol();
    if (!a) return;
    const gradosActualizados = a.grados.map((g: any) =>
      g.id === grado.id
        ? {
            ...g,
            cursos: g.cursos.map((c: any) =>
              c.id === curso.id ? { ...c, [field]: value } : c
            ),
          }
        : g
    );
    this.arbol.set({ ...a, grados: gradosActualizados });
    this.cursoActivo.update(c => ({ ...c, [field]: value }));
    this.onArbolChange();
  }

  removeTema(tema: any): void {
    const curso = this.cursoActivo();
    const grado = this.gradoActivo();
    if (!curso || !grado) return;
    const a = this.arbol();
    if (!a) return;
    const gradosActualizados = a.grados.map((g: any) =>
      g.id === grado.id
        ? {
            ...g,
            cursos: g.cursos.map((c: any) =>
              c.id === curso.id
                ? { ...c, temas: c.temas.filter((t: any) => t !== tema) }
                : c
            ),
          }
        : g
    );
    this.arbol.set({ ...a, grados: gradosActualizados });
    this.cursoActivo.update(c => ({ ...c, temas: c.temas.filter((t: any) => t !== tema) }));
    this.onArbolChange();
  }

  volver(): void {
    this.router.navigate(['/arboles']);
  }
}
