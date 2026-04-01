import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TreeTableModule } from 'primeng/treetable';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService, ConfirmationService, TreeNode } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-organizaciones',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService, ConfirmationService],
  imports: [
    DatePipe, FormsModule,
    ButtonModule, TreeTableModule, TableModule, DialogModule, InputTextModule,
    SelectModule, ToastModule, ConfirmDialogModule, TagModule, TooltipModule,
  ],
  templateUrl: './organizaciones.component.html',
})
export class OrganizacionesComponent implements OnInit {
  private readonly api     = inject(ApiService);
  private readonly toast   = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);

  readonly nodes               = signal<TreeNode[]>([]);
  readonly loading             = signal(true);
  readonly saving              = signal(false);
  readonly editandoOrg         = signal<any | null>(null);
  readonly verGestorPinesOrgId = signal<number | null>(null);
  readonly gestorPines         = signal<any[]>([]);
  readonly loadingGestorPines  = signal(false);
  readonly categorias          = signal<any[]>([]);

  ngOnInit(): void {
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => {
        const orgs: any[] = r.data ?? r.organizaciones ?? [];
        this.nodes.set(orgs.map(o => this.orgToNode(o)));
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las organizaciones' });
      }
    });

    this.api.getCategoriasOrganizaciones().subscribe({
      next: (r: any) => {
        const cats = r.categorias ?? [];
        this.categorias.set(cats.map((c: any) => ({ ...c, label: `${c.id} - ${c.name}` })));
      },
      error: () => {}
    });
  }

  // ── TreeTable ──────────────────────────────────────────────────────────────

  onNodeExpand(event: any): void {
    const node: TreeNode = event.node;
    if (node.data?.type !== 'org') return;

    const orgId: number = node.data.id;

    // Mostrar loading mientras se cargan los gestores
    this.setNodeChildren(orgId, [{ data: { type: 'loading' }, leaf: true }]);

    this.api.getGestores(orgId).subscribe({
      next: (r: any) => {
        const gestores: any[] = r.data ?? [];
        const children: TreeNode[] = gestores.map(g => ({
          data: { type: 'gestor', ...g, org_id: orgId },
          leaf: true,
        }));
        this.setNodeChildren(orgId, children);
      },
      error: () => {
        this.setNodeChildren(orgId, []);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los gestores' });
      }
    });
  }

  private setNodeChildren(orgId: number, children: TreeNode[]): void {
    this.nodes.update(nodes =>
      nodes.map(n => n.data?.id === orgId ? { ...n, children } : n)
    );
  }

  private orgToNode(org: any): TreeNode {
    return {
      data: { type: 'org', ...org },
      children: [],
      leaf: false,
    };
  }

  // ── Organizaciones ─────────────────────────────────────────────────────────

  abrirCrear(): void {
    this.editandoOrg.set({ name: '', moodle_category_id: null });
  }

  abrirEditar(org: any): void {
    this.editandoOrg.set({ ...org });
  }

  updateEditCatId(id: number): void {
    this.editandoOrg.update(o => ({ ...o, moodle_category_id: id }));
  }

  guardar(): void {
    const org = this.editandoOrg();
    if (!org) return;
    this.saving.set(true);
    const call = org.id
      ? this.api.actualizarOrganizacion(org.id, { name: org.name, moodle_category_id: org.moodle_category_id })
      : this.api.crearOrganizacion({ name: org.name, moodle_category_id: org.moodle_category_id });

    call.subscribe({
      next: () => {
        this.saving.set(false);
        this.editandoOrg.set(null);
        this.toast.add({ severity: 'success', summary: 'Guardado', detail: org.id ? 'Organización actualizada' : 'Organización creada' });
        this.recargarOrgs();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al guardar' });
      }
    });
  }

  eliminarOrg(org: any): void {
    this.confirm.confirm({
      message: `¿Eliminar "${org.name}"? Se eliminarán también todos sus gestores y pines. Esta acción no se puede deshacer.`,
      header: 'Confirmar eliminación',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.api.eliminarOrganizacion(org.id).subscribe({
          next: () => {
            this.toast.add({ severity: 'success', summary: 'Eliminada', detail: `"${org.name}" eliminada` });
            this.recargarOrgs();
          },
          error: (err: any) => {
            this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al eliminar' });
          }
        });
      }
    });
  }

  // ── Gestores ───────────────────────────────────────────────────────────────

  eliminarGestor(gestor: any): void {
    const nombre = `${gestor.firstname} ${gestor.lastname}`.trim() || gestor.username;
    this.confirm.confirm({
      message: `¿Eliminar al gestor "${nombre}"? Se eliminará su cuenta Moodle y se liberará su pin de acceso.`,
      header: 'Confirmar eliminación de gestor',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.api.eliminarGestor(gestor.id).subscribe({
          next: () => {
            this.toast.add({ severity: 'success', summary: 'Eliminado', detail: `Gestor "${nombre}" eliminado` });
            // Recargar gestores del nodo padre
            this.setNodeChildren(gestor.org_id, [{ data: { type: 'loading' }, leaf: true }]);
            this.api.getGestores(gestor.org_id).subscribe({
              next: (r: any) => {
                const gestores: any[] = r.data ?? [];
                const children = gestores.map(g => ({
                  data: { type: 'gestor', ...g, org_id: gestor.org_id },
                  leaf: true,
                }));
                this.setNodeChildren(gestor.org_id, children);
              },
              error: () => this.setNodeChildren(gestor.org_id, [])
            });
            // Actualizar el gestor_count en el nodo de la org
            this.nodes.update(nodes =>
              nodes.map(n => {
                if (n.data?.id !== gestor.org_id) return n;
                return { ...n, data: { ...n.data, gestor_count: Math.max(0, (n.data.gestor_count ?? 1) - 1) } };
              })
            );
          },
          error: (err: any) => {
            this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al eliminar gestor' });
          }
        });
      }
    });
  }

  // ── Invitaciones (pines de gestor) ─────────────────────────────────────────

  verGestorPines(org: any): void {
    this.verGestorPinesOrgId.set(org.id);
    this.cargarGestorPines(org.id);
  }

  cargarGestorPines(orgId: number): void {
    this.loadingGestorPines.set(true);
    this.api.getGestorPines(orgId).subscribe({
      next: (r: any) => {
        this.gestorPines.set(r.data ?? r.gestores ?? []);
        this.loadingGestorPines.set(false);
      },
      error: () => {
        this.loadingGestorPines.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar las invitaciones' });
      }
    });
  }

  crearGestorPin(orgId: number): void {
    this.api.crearGestorPin(orgId).subscribe({
      next: () => {
        this.toast.add({ severity: 'success', summary: 'Creado', detail: 'Nuevo pin de invitación generado' });
        this.cargarGestorPines(orgId);
      },
      error: (err: any) => {
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al crear pin' });
      }
    });
  }

  anularGestorPin(hash: string): void {
    const orgId = this.verGestorPinesOrgId();
    this.confirm.confirm({
      message: 'Anular este pin de invitación lo eliminará permanentemente.',
      header: 'Confirmar anulación',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Anular',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.api.anularGestorPin(hash).subscribe({
          next: () => {
            this.toast.add({ severity: 'success', summary: 'Anulado', detail: 'Pin anulado' });
            if (orgId) this.cargarGestorPines(orgId);
          },
          error: (err: any) => {
            this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al anular' });
          }
        });
      }
    });
  }

  copiarHash(hash: string): void {
    navigator.clipboard.writeText(hash).then(() => {
      this.toast.add({ severity: 'success', summary: 'Copiado', detail: 'Hash copiado al portapapeles' });
    }).catch(() => {});
  }

  nombreOrg(orgId: number | null): string {
    if (!orgId) return '';
    return this.nodes().find(n => n.data?.id === orgId)?.data?.name ?? '';
  }

  private recargarOrgs(): void {
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => {
        const orgs: any[] = r.data ?? r.organizaciones ?? [];
        this.nodes.set(orgs.map(o => this.orgToNode(o)));
      },
      error: () => {}
    });
  }
}
