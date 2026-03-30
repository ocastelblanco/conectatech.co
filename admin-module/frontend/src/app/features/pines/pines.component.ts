import { Component, ChangeDetectionStrategy, inject, signal, computed, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { SelectModule } from 'primeng/select';
import { DatePickerModule } from 'primeng/datepicker';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService, ConfirmationService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-pines',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService, ConfirmationService],
  imports: [
    DatePipe, FormsModule,
    ButtonModule, TableModule, DialogModule, InputTextModule,
    SelectModule, DatePickerModule, ToastModule, ConfirmDialogModule,
    TagModule, TooltipModule,
  ],
  templateUrl: './pines.component.html',
})
export class PinesComponent implements OnInit {
  private readonly api     = inject(ApiService);
  private readonly toast   = inject(MessageService);

  readonly paquetes       = signal<any[]>([]);
  readonly orgs           = signal<any[]>([]);
  readonly loading        = signal(true);
  readonly saving         = signal(false);
  readonly filtroOrgId    = signal<number | null>(null);
  readonly creandoPaquete = signal(false);
  readonly nuevoPaquete   = signal<{
    organization_id: number;
    teacher_role: string;
    expires_at: Date | null;
    cantidad: number;
  }>({ organization_id: 0, teacher_role: 'teacher', expires_at: null, cantidad: 10 });
  readonly asignandoPaquete = signal<any | null>(null);
  readonly asignarOrgId     = signal<number | null>(null);

  readonly tomorrow = new Date(Date.now() + 86400 * 1000);

  readonly rolesProfesor = [
    { label: 'Profesor editor', value: 'editingteacher' },
    { label: 'Profesor', value: 'teacher' },
  ];

  readonly paquetesFiltrados = computed(() => this.paquetes());

  ngOnInit(): void {
    this.cargarPaquetes();
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => this.orgs.set(r.data ?? r.organizaciones ?? []),
      error: () => {}
    });
  }

  cargarPaquetes(): void {
    this.loading.set(true);
    this.api.getPaquetes(this.filtroOrgId() ?? undefined).subscribe({
      next: (r: any) => {
        this.paquetes.set(r.data ?? r.paquetes ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudieron cargar los paquetes' });
      }
    });
  }

  aplicarFiltro(orgId: number | null): void {
    this.filtroOrgId.set(orgId);
    this.cargarPaquetes();
  }

  crearPaquete(): void {
    const p = this.nuevoPaquete();
    if (!p.organization_id || !p.expires_at) return;
    this.saving.set(true);
    const body = {
      organization_id: p.organization_id,
      teacher_role: p.teacher_role,
      expires_at: Math.floor(p.expires_at.getTime() / 1000),
      cantidad: p.cantidad,
    };
    this.api.crearPaquete(body).subscribe({
      next: () => {
        this.saving.set(false);
        this.creandoPaquete.set(false);
        this.nuevoPaquete.set({ organization_id: 0, teacher_role: 'teacher', expires_at: null, cantidad: 10 });
        this.toast.add({ severity: 'success', summary: 'Creado', detail: 'Paquete creado exitosamente' });
        this.cargarPaquetes();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al crear paquete' });
      }
    });
  }

  abrirAsignar(paquete: any): void {
    this.asignandoPaquete.set(paquete);
    this.asignarOrgId.set(paquete.organization_id);
  }

  confirmarAsignar(): void {
    const paquete = this.asignandoPaquete();
    const orgId   = this.asignarOrgId();
    if (!paquete || !orgId) return;
    this.saving.set(true);
    this.api.asignarPaquete(paquete.id, { organization_id: orgId }).subscribe({
      next: () => {
        this.saving.set(false);
        this.asignandoPaquete.set(null);
        this.asignarOrgId.set(null);
        this.toast.add({ severity: 'success', summary: 'Reasignado', detail: 'Paquete reasignado exitosamente' });
        this.cargarPaquetes();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error al reasignar' });
      }
    });
  }

  updateNuevoPaqueteOrgId(id: number): void {
    this.nuevoPaquete.update(p => ({ ...p, organization_id: id }));
  }

  updateNuevoPaqueteRol(rol: string): void {
    this.nuevoPaquete.update(p => ({ ...p, teacher_role: rol }));
  }

  updateNuevoPaqueteFecha(fecha: Date | null): void {
    this.nuevoPaquete.update(p => ({ ...p, expires_at: fecha }));
  }

  updateNuevoPaqueteCantidad(cantidad: number): void {
    this.nuevoPaquete.update(p => ({ ...p, cantidad }));
  }

  getPinExpirClass(ts: number): string {
    const now = Date.now() / 1000;
    if (ts < now)                     return 'text-red-600 font-semibold';
    if (ts < now + 7 * 86400)         return 'text-orange-500 font-semibold';
    return 'text-gray-600';
  }

  getRolSeverity(rol: string): 'danger' | 'warn' | 'info' {
    if (rol === 'editingteacher') return 'danger';
    return 'warn';
  }

  getRolLabel(rol: string): string {
    if (rol === 'editingteacher') return 'Profesor editor';
    if (rol === 'teacher')        return 'Profesor';
    return rol;
  }

  nombreOrg(orgId: number): string {
    return this.orgs().find(o => o.id === orgId)?.name ?? String(orgId);
  }
}
