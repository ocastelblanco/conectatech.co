import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TextareaModule } from 'primeng/textarea';
import { TreeModule } from 'primeng/tree';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { ProgressSpinnerModule } from 'primeng/progressspinner';
import { MessageService, SharedModule, TreeNode } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

/** Representa un bloque de sección H1 dividido en H2-subsecciones. */
interface RawSection {
  /** Línea H1 + cualquier contenido antes del primer H2 */
  raw: string;
  /** Cada bloque H2 con todo su contenido hasta el siguiente H2 */
  subsections: { raw: string }[];
}

@Component({
  selector: 'cnt-contenido',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    FormsModule, ButtonModule, TextareaModule, TreeModule,
    SharedModule, TagModule, ToastModule, ProgressSpinnerModule,
  ],
  providers: [MessageService],
  templateUrl: './contenido.component.html',
})
export class ContenidoComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  // ── Árbol de cursos repositorio ──────────────────────────────────────────
  readonly repoTree     = signal<TreeNode[]>([]);
  readonly treeLoading  = signal(true);
  readonly selectedNode = signal<TreeNode | null>(null);
  readonly shortname    = signal('');

  // ── Carga de archivo ────────────────────────────────────────────────────
  readonly fileName    = signal('');
  readonly isDragOver  = signal(false);

  // ── Contenido y preview ─────────────────────────────────────────────────
  /** Secciones crudas del Markdown (para reconstrucción tras reordenamiento) */
  readonly rawSections    = signal<RawSection[]>([]);
  /** Contenido Markdown actual (orden original o reordenado por el árbol) */
  readonly content        = signal('');
  /** Árbol de estructura obtenido del endpoint /preview */
  readonly previewTree    = signal<TreeNode[]>([]);
  readonly previewLoading = signal(false);

  // ── Procesamiento ────────────────────────────────────────────────────────
  readonly loading = signal(false);
  readonly summary = signal<any>(null);
  readonly errors  = signal<string[]>([]);

  ngOnInit(): void {
    this.api.getCursosArbol().subscribe({
      next: (r: any) => {
        const arbol: any[]  = r.arbol ?? [];
        const reposCat      = arbol.find((c: any) => c.name === 'REPOSITORIOS');
        this.repoTree.set(reposCat ? this.buildTree(reposCat.hijos ?? []) : []);
        this.treeLoading.set(false);
      },
      error: () => this.treeLoading.set(false),
    });
  }

  // ── Selección de curso ───────────────────────────────────────────────────

  onNodeSelect(event: { node: TreeNode }): void {
    if (!event.node.leaf) { this.selectedNode.set(null); return; }
    this.selectedNode.set(event.node);
    this.shortname.set(event.node.data.shortname);
  }

  onNodeUnselect(): void {
    this.selectedNode.set(null);
    this.shortname.set('');
  }

  // ── Carga de archivo ─────────────────────────────────────────────────────

  /** Input <file> change event */
  loadFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (file) this.readFile(file);
    // Reset input para permitir volver a cargar el mismo archivo
    input.value = '';
  }

  /** Drag & drop sobre el dropzone */
  onDrop(event: DragEvent): void {
    event.preventDefault();
    this.isDragOver.set(false);
    const file = event.dataTransfer?.files?.[0];
    if (file) this.readFile(file);
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.isDragOver.set(true);
  }

  onDragLeave(): void {
    this.isDragOver.set(false);
  }

  // ── Árbol de estructura (drag & drop de nodos) ───────────────────────────

  /** Llamado por PrimeNG cuando el usuario termina un drag & drop en el árbol */
  onNodeDrop(_event: any): void {
    // PrimeNG ya mutó el array interno del árbol; forzar referencia nueva
    // para que el signal detecte el cambio y rebuilt del contenido ocurra.
    const currentTree  = this.previewTree();
    const newContent   = this.buildContentFromTree(currentTree, this.rawSections());
    this.content.set(newContent);
    this.previewTree.update(t => [...t]);
  }

  /** Retorna la clase de ícono PrimeIcons según el tipo de nodo */
  getNodeIcon(node: any): string {
    switch (node.type) {
      case 'seccion':                   return 'pi pi-folder-open text-amber-500 text-base';
      case 'subseccion-regular':        return 'pi pi-folder text-blue-500 text-base';
      case 'subseccion-evaluacion':     return 'pi pi-question-circle text-purple-600 text-base';
      case 'subseccion-presaberes':     return 'pi pi-lightbulb text-teal-500 text-base';
      case 'referente-biblico-seccion':
      case 'h2-texto-directo':          return 'pi pi-book text-gray-400 text-base';
      case 'label':                     return 'pi pi-file-edit text-gray-500 text-base';
      case 'quiz':                      return 'pi pi-question-circle text-purple-400 text-base';
      default:                          return 'pi pi-file text-gray-400 text-base';
    }
  }

  // ── Procesamiento en Moodle ──────────────────────────────────────────────

  procesar(): void {
    if (!this.shortname() || !this.content()) return;
    this.loading.set(true);
    this.api.procesarMarkdown({ shortname: this.shortname(), content: this.content() }).subscribe({
      next: (r: any) => {
        this.summary.set(r.summary);
        this.errors.set(r.errors ?? []);
        this.loading.set(false);
        this.toast.add({
          severity: r.ok ? 'success' : 'warn',
          summary: 'Procesamiento completo',
          detail: `Creadas: ${r.summary?.sections_created ?? 0} · Actualizadas: ${r.summary?.sections_updated ?? 0}`,
        });
      },
      error: (err) => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error desconocido' });
      },
    });
  }

  // ── Métodos privados ─────────────────────────────────────────────────────

  private readFile(file: File): void {
    const reader = new FileReader();
    reader.onload = (e) => {
      const text = e.target?.result as string ?? '';
      this.fileName.set(file.name);
      this.content.set(text);
      this.rawSections.set(this.splitMarkdown(text));
      this.summary.set(null);
      this.errors.set([]);
      this.toast.add({ severity: 'info', summary: 'Archivo cargado', detail: file.name });
      this.loadPreview(text);
    };
    reader.readAsText(file);
  }

  /** Llama al endpoint /preview y actualiza el árbol de estructura */
  private loadPreview(text: string): void {
    this.previewLoading.set(true);
    this.previewTree.set([]);
    this.api.previewMarkdown(text).subscribe({
      next: (r) => {
        this.previewTree.set(r.tree ?? []);
        this.previewLoading.set(false);
      },
      error: () => {
        // El preview es informativo — si falla, el flujo principal sigue funcionando
        this.previewLoading.set(false);
        this.toast.add({
          severity: 'warn',
          summary: 'Vista previa no disponible',
          detail: 'No se pudo analizar la estructura del Markdown.',
        });
      },
    });
  }

  /**
   * Divide el texto Markdown en secciones H1, cada una con sus H2 subsecciones.
   * Usa índices de caracteres para garantizar que el join sea idéntico al original.
   */
  private splitMarkdown(text: string): RawSection[] {
    const result: RawSection[] = [];

    // Encontrar posiciones de cada línea que empieza con "# " (H1, no H2+)
    const h1Regex = /^# (?!#)/gm;
    const h1Positions: number[] = [];
    let m: RegExpExecArray | null;
    while ((m = h1Regex.exec(text)) !== null) {
      h1Positions.push(m.index);
    }

    for (let i = 0; i < h1Positions.length; i++) {
      const start       = h1Positions[i];
      const end         = i + 1 < h1Positions.length ? h1Positions[i + 1] : text.length;
      const sectionText = text.slice(start, end);

      // Encontrar posiciones H2 dentro de la sección
      const h2Regex = /^## (?!#)/gm;
      const h2Positions: number[] = [];
      let h2m: RegExpExecArray | null;
      while ((h2m = h2Regex.exec(sectionText)) !== null) {
        h2Positions.push(h2m.index);
      }

      if (h2Positions.length === 0) {
        result.push({ raw: sectionText, subsections: [] });
        continue;
      }

      const intro = sectionText.slice(0, h2Positions[0]);
      const subsections: { raw: string }[] = [];

      for (let j = 0; j < h2Positions.length; j++) {
        const h2Start = h2Positions[j];
        const h2End   = j + 1 < h2Positions.length ? h2Positions[j + 1] : sectionText.length;
        subsections.push({ raw: sectionText.slice(h2Start, h2End) });
      }

      result.push({ raw: intro, subsections });
    }

    return result;
  }

  /**
   * Reconstruye el string Markdown respetando el orden actual del árbol de preview.
   * Los nodos H3 (leaf) son informativos y no afectan el orden de reconstrucción.
   */
  private buildContentFromTree(tree: TreeNode[], sections: RawSection[]): string {
    if (!sections.length) return this.content();

    return tree.map(sNode => {
      const sIdx = sNode.data?.sectionIdx;
      if (sIdx === undefined) return '';
      const section = sections[sIdx];
      if (!section) return '';

      // Solo los hijos con subsectionIdx son H2 (los H3 leaf no tienen subsectionIdx)
      const h2Children = (sNode.children ?? []).filter(
        n => n.data?.subsectionIdx !== undefined,
      );

      if (!h2Children.length) {
        return section.raw + section.subsections.map(ss => ss.raw).join('');
      }

      return section.raw + h2Children.map(ssNode => {
        const ssIdx = ssNode.data!.subsectionIdx as number;
        return section.subsections[ssIdx]?.raw ?? '';
      }).join('');
    }).join('');
  }

  private buildTree(cats: any[]): TreeNode[] {
    return cats.map(cat => ({
      label:      cat.name,
      icon:       'pi pi-folder',
      expanded:   true,
      selectable: false,
      children: [
        ...this.buildTree(cat.hijos ?? []),
        ...(cat.cursos ?? []).map((c: any): TreeNode => ({
          label:      c.fullname,
          icon:       'pi pi-book',
          data:       { shortname: c.shortname },
          leaf:       true,
          selectable: true,
        })),
      ],
    }));
  }
}
