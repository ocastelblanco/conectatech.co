import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { SelectModule } from 'primeng/select';
import { ToastModule } from 'primeng/toast';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../../core/services/api.service';

@Component({
  selector: 'cnt-pines-reporte',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService],
  imports: [
    DatePipe, FormsModule,
    ButtonModule, TableModule, SelectModule,
    ToastModule, TagModule, TooltipModule,
  ],
  templateUrl: './pines-reporte.component.html',
})
export class PinesReporteComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly rows        = signal<any[]>([]);
  readonly orgs        = signal<any[]>([]);
  readonly paquetes    = signal<any[]>([]);
  readonly loading     = signal(false);
  readonly filtroOrgId = signal<number | null>(null);
  readonly filtroPkgId = signal<number | null>(null);

  ngOnInit(): void {
    this.api.getOrganizaciones().subscribe({
      next: (r: any) => this.orgs.set(r.data ?? r.organizaciones ?? []),
      error: () => {}
    });
    this.cargar();
  }

  cargar(): void {
    this.loading.set(true);
    this.api.getReportePines(this.filtroOrgId(), this.filtroPkgId()).subscribe({
      next: (r: any) => {
        this.rows.set(r.data ?? r.pines ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el reporte' });
      }
    });
  }

  onOrgChange(orgId: number | null): void {
    this.filtroOrgId.set(orgId);
    this.filtroPkgId.set(null);
    if (orgId) {
      this.api.getPaquetes(orgId).subscribe({
        next: (r: any) => this.paquetes.set(r.data ?? r.paquetes ?? []),
        error: () => this.paquetes.set([])
      });
    } else {
      this.paquetes.set([]);
    }
    this.cargar();
  }

  onPkgChange(pkgId: number | null): void {
    this.filtroPkgId.set(pkgId);
    this.cargar();
  }

  getEstadoSeverity(estado: string): 'secondary' | 'warn' | 'success' | 'danger' | 'info' {
    switch (estado) {
      case 'available': return 'secondary';
      case 'assigned':  return 'warn';
      case 'active':    return 'success';
      default:          return 'info';
    }
  }

  getEstadoLabel(estado: string): string {
    switch (estado) {
      case 'available': return 'Disponible';
      case 'assigned':  return 'Asignado';
      case 'active':    return 'Activo';
      default:          return estado;
    }
  }
}
