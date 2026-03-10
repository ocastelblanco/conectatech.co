import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TextareaModule } from 'primeng/textarea';
import { TreeModule } from 'primeng/tree';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { MessageService, SharedModule, TreeNode } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-contenido',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TextareaModule, TreeModule, SharedModule, TagModule, ToastModule],
  providers: [MessageService],
  templateUrl: './contenido.component.html',
})
export class ContenidoComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly repoTree     = signal<TreeNode[]>([]);
  readonly treeLoading  = signal(true);
  readonly selectedNode = signal<TreeNode | null>(null);
  readonly shortname    = signal('');
  readonly content      = signal('');
  readonly loading      = signal(false);
  readonly summary      = signal<any>(null);
  readonly errors       = signal<string[]>([]);

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

  onNodeSelect(event: { node: TreeNode }): void {
    if (!event.node.leaf) { this.selectedNode.set(null); return; }
    this.selectedNode.set(event.node);
    this.shortname.set(event.node.data.shortname);
  }

  onNodeUnselect(): void {
    this.selectedNode.set(null);
    this.shortname.set('');
  }

  loadFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      this.content.set(e.target?.result as string ?? '');
      this.toast.add({ severity: 'info', summary: 'Archivo cargado', detail: file.name });
    };
    reader.readAsText(file);
  }

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
          detail: `Creadas: ${r.summary?.sections_created ?? 0} - Actualizadas: ${r.summary?.sections_updated ?? 0}`
        });
      },
      error: (err) => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error desconocido' });
      }
    });
  }

  private buildTree(cats: any[]): TreeNode[] {
    return cats.map(cat => ({
      label:    cat.name,
      icon:     'pi pi-folder',
      expanded: true,
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
