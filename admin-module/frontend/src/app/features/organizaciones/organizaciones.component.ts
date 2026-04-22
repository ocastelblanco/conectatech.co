import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
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
import { MessageService, ConfirmationService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-organizaciones',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService, ConfirmationService],
  imports: [
    DatePipe, FormsModule,
    ButtonModule, TableModule, DialogModule, InputTextModule,
    SelectModule, ToastModule, ConfirmDialogModule, TagModule, TooltipModule,
  ],
  templateUrl: './organizaciones.component.html',
})
export class OrganizacionesComponent implements OnInit {
  private readonly api     = inject(ApiService);
  private readonly toast   = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);

  readonly orgs              = signal<any[]>([]);
  readonly loading           = signal(true);
  readonly saving            = signal(false);
  readonly editandoOrg       = signal<any | null>(null);
  readonly verGestorPinesOrgId = signal<number | null>(null);
  readonly gestorPines       = signal<any[]>([]);
  readonly loadingGestorPines = signal(false);
  readonly categorias        = signal<any[]>([]);

  // Row expansion: gestores loaded lazily per org
  readonly gestoresMap  = signal<Record<number, any[]>>({});
  readonly loadingIds   = signal<number[]>([]);
  expandedRows: Record<string, boolean> = {};

  ngOnInit(): void {
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => {
        this.orgs.set(r.data ?? r.organizaciones ?? []);
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
      error: () => { }
    });
  }

  // ── Row expansion ──────────────────────────────────────────────────────────

  onRowExpand(event: any): void {
    const org = event.data;
    if (this.gestoresMap()[org.id] !== undefined) return;

    this.loadingIds.update(ids => [...ids, org.id]);

    this.api.getGestores(org.id).subscribe({
      next: (r: any) => {
        this.gestoresMap.update(map => ({ ...map, [org.id]: r.data ?? [] }));
        this.loadingIds.update(ids => ids.filter(id => id !== org.id));
      },
      error: () => {
        this.gestoresMap.update(map => ({ ...map, [org.id]: [] }));
        this.loadingIds.update(ids => ids.filter(id => id !== org.id));
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los gestores' });
      }
    });
  }

  isLoadingGestores(orgId: number): boolean {
    return this.loadingIds().includes(orgId);
  }

  getGestoresOrg(orgId: number): any[] {
    return this.gestoresMap()[orgId] ?? [];
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
            this.gestoresMap.update(map => ({
              ...map,
              [gestor.org_id]: (map[gestor.org_id] ?? []).filter((g: any) => g.id !== gestor.id)
            }));
            this.orgs.update(orgs =>
              orgs.map(o => o.id === gestor.org_id
                ? { ...o, gestor_count: Math.max(0, (o.gestor_count ?? 1) - 1) }
                : o
              )
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
    }).catch(() => { });
  }

  nombreOrg(orgId: number | null): string {
    if (!orgId) return '';
    return this.orgs().find(o => o.id === orgId)?.name ?? '';
  }

  private recargarOrgs(): void {
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => {
        this.orgs.set(r.data ?? r.organizaciones ?? []);
        this.gestoresMap.set({});
        this.expandedRows = {};
      },
      error: () => { }
    });
  }
}
