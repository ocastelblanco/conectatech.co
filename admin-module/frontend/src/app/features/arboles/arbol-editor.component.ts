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
import { TreeModule } from 'primeng/tree';
import { SharedModule } from 'primeng/api';
import { SelectModule } from 'primeng/select';
import { CardModule } from 'primeng/card';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService, ConfirmationService, TreeNode } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';
import { CreatableSelectComponent } from '../../shared/components/creatable-select/creatable-select.component';

@Component({
  selector: 'cnt-arbol-editor',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    FormsModule,
    ButtonModule,
    InputTextModule,
    TreeModule,
    SharedModule,
    SelectModule,
    CardModule,
    ToastModule,
    ConfirmDialogModule,
    DialogModule,
    TooltipModule,
    CreatableSelectComponent,
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
  readonly repositorios = signal<any[]>([]);
  readonly dragOverIndex = signal(-1);
  readonly opcionesCss = signal<{ proyectos: string[]; areas: string[] }>({ proyectos: [], areas: [] });

  readonly esNuevo = computed(() => this.route.snapshot.params['id'] === 'nuevo');

  readonly plantillasTree = computed<TreeNode[]>(() =>
    this.plantillas().map((p: any) => ({
      label: p.nombre,
      selectable: false,
      expanded: true,
      children: (p.cursos ?? []).map((c: any) => ({
        label: c.fullname,
        selectable: true,
        leaf: true,
        data: { shortname: c.shortname },
      })),
    }))
  );

  readonly categoriasOpciones = computed(() =>
    this.categoriasRaiz().map((c: any) => ({ label: c.name, value: c.id }))
  );

  readonly repositoriosTree = computed<TreeNode[]>(() =>
    this.repositorios().map((repo: any) => ({
      label: repo.nombre,
      selectable: false,
      expanded: true,
      children: (repo.areas ?? []).map((area: any) => ({
        label: area.nombre,
        selectable: false,
        expanded: true,
        children: (area.cursos ?? []).map((curso: any) => ({
          label: curso.fullname,
          selectable: false,
          expanded: true,
          children: (curso.secciones ?? []).map((sec: any) => ({
            label: sec.titulo,
            selectable: false,
            leaf: true,
            data: { repo_shortname: curso.shortname, section_num: sec.num, titulo: sec.titulo },
          })),
        })),
      })),
    }))
  );

  readonly validating = signal(false);
  readonly executing = signal(false);
  readonly conflictos = signal<any[]>([]);
  readonly ejResultado = signal<any>(null);
  readonly showValidar = signal(false);
  readonly showResults = signal(false);

  readonly resumenConflictos = computed(() => {
    const c = this.conflictos();
    return {
      nuevos: c.filter(x => x.estado === 'nuevo').length,
      recrear: c.filter(x => x.estado === 'existe_sin_estudiantes').length,
      actualizar: c.filter(x => x.estado === 'existe_con_estudiantes').length,
    };
  });

  private _draggedSection: any = null;
  private _draggedTemaIndex = -1;

  private readonly saveSubject = new Subject<void>();
  private readonly subs = new Subscription();

  ngOnInit(): void {
    this.subs.add(
      this.saveSubject.pipe(debounceTime(800)).subscribe(() => this._doSave())
    );

    const id = this.route.snapshot.params['id'];

    // Load plantillas, categorias and repositorios in parallel
    this.api.getArbolesPlantillas().subscribe({
      next: (r: any) => this.plantillas.set(r.plantillas ?? []),
      error: () => { },
    });
    this.api.getArbolesCategoriasRaiz().subscribe({
      next: (r: any) => this.categoriasRaiz.set(r.categorias ?? []),
      error: () => { },
    });
    this.api.getArbolesOpcionesCss().subscribe({
      next: (r: any) => this.opcionesCss.set({ proyectos: r.proyectos ?? [], areas: r.areas ?? [] }),
      error: () => { },
    });
    this.api.getArbolesRepositorios().subscribe({
      next: (r: any) => this.repositorios.set(r.repositorios ?? []),
      error: () => { },
    });

    if (id === 'nuevo') {
      this.arbol.set({
        nombre: '',
        shortname: '',
        shortname_inst: '',
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
      nombre: 'Nuevo curso',
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
    this.arbol.update(a => {
      const updated = { ...a, [field]: value };
      if (['nombre', 'shortname_inst', 'periodo'].includes(field)) {
        updated.shortname = this.computeArbolShortname(updated);
      }
      return updated;
    });
    this.onArbolChange();
  }

  updateCategoriaRaiz(categoryId: any): void {
    this.arbol.update(a => {
      const updated = { ...a, categoria_raiz: categoryId };
      updated.shortname = this.computeArbolShortname(updated);
      return updated;
    });
    this.onArbolChange();
  }

  private computeArbolShortname(arbol: any): string {
    const cat = this.categoriasRaiz().find((c: any) => c.id === arbol.categoria_raiz);
    const catPrefix = cat ? cat.name.substring(0, 3).toUpperCase() : '';
    const parts = [catPrefix, arbol.shortname_inst, arbol.periodo, this.slugify(arbol.nombre)]
      .filter(Boolean);
    return parts.join('-');
  }

  private slugify(text: string): string {
    return (text ?? '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/\s+/g, '_')
      .replace(/[^a-z0-9_]/g, '');
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

  // ── Ejecución ──────────────────────────────────────────────────────────────

  iniciarIngesta(): void {
    const id = this.arbol()?.id;
    if (!id) return;
    this.validating.set(true);
    this.api.validarArbol(id).subscribe({
      next: (r: any) => {
        this.conflictos.set(r.conflictos ?? []);
        this.validating.set(false);
        this.showValidar.set(true);
      },
      error: (err: any) => {
        this.validating.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo validar' });
      },
    });
  }

  confirmarIngesta(): void {
    const id = this.arbol()?.id;
    if (!id) return;
    this.showValidar.set(false);
    this.executing.set(true);
    this.api.ejecutarArbol(id, { dry_run: false }).subscribe({
      next: (r: any) => {
        this.executing.set(false);
        this.ejResultado.set(r);
        this.showResults.set(true);
      },
      error: (err: any) => {
        this.executing.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error en la ejecución' });
      },
    });
  }

  onPlantillaSelect(event: { node: TreeNode }): void {
    if (!event.node.leaf) return;
    this.updateCursoField('templatecourse', event.node.data?.shortname ?? '');
  }

  // ── Drag & drop from repositorios to temas ────────────────────────────────

  onRepoDragStart(event: DragEvent, nodeData: any): void {
    this._draggedSection = nodeData;
    this._draggedTemaIndex = -1;
    event.dataTransfer!.effectAllowed = 'copy';
  }

  onTemasDragStart(event: DragEvent, index: number): void {
    this._draggedTemaIndex = index;
    this._draggedSection = null;
    event.dataTransfer!.effectAllowed = 'move';
  }

  onTemasDragOver(event: DragEvent, index: number): void {
    event.preventDefault();
    this.dragOverIndex.set(index);
    event.dataTransfer!.dropEffect = this._draggedSection ? 'copy' : 'move';
  }

  onTemasDropAtIndex(event: DragEvent, targetIndex: number): void {
    event.preventDefault();
    event.stopPropagation();
    this.dragOverIndex.set(-1);

    if (this._draggedSection) {
      const curso = this.cursoActivo();
      if (!curso) return;
      const temas = [...(curso.temas ?? [])];
      const already = temas.some(
        t => t.repo_shortname === this._draggedSection.repo_shortname &&
          t.section_num === this._draggedSection.section_num
      );
      if (!already) {
        const idx = targetIndex < 0 ? temas.length : targetIndex;
        temas.splice(idx, 0, { ...this._draggedSection });
        this.updateCursoField('temas', temas);
      }
      this._draggedSection = null;
    } else if (this._draggedTemaIndex >= 0) {
      const curso = this.cursoActivo();
      if (!curso) return;
      const temas = [...(curso.temas ?? [])];
      const [moved] = temas.splice(this._draggedTemaIndex, 1);
      const dest = targetIndex > this._draggedTemaIndex ? targetIndex - 1 : targetIndex;
      temas.splice(dest < 0 ? temas.length : dest, 0, moved);
      this.updateCursoField('temas', temas);
      this._draggedTemaIndex = -1;
    }
  }

  volver(): void {
    this.router.navigate(['/arboles']);
  }
}
