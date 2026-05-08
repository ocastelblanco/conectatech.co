import { Component, ChangeDetectionStrategy, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { TabsModule } from 'primeng/tabs';
import { TableModule } from 'primeng/table';
import { ButtonModule } from 'primeng/button';
import { TagModule } from 'primeng/tag';
import { TooltipModule } from 'primeng/tooltip';
import { SkeletonModule } from 'primeng/skeleton';
import { DialogModule } from 'primeng/dialog';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService],
  imports: [
    RouterLink, TabsModule, TableModule, ButtonModule, TagModule,
    TooltipModule, SkeletonModule, DialogModule, ToastModule,
  ],
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  // ── Resumen (carga en init) ──────────────────────────────────────────────
  readonly resumen        = signal<any>(null);
  readonly loadingResumen = signal(true);

  // ── Instituciones (carga en init — Tab A) ────────────────────────────────
  readonly instituciones        = signal<any[]>([]);
  readonly loadingInstituciones = signal(true);

  // ── Progreso por institución (dialog) ────────────────────────────────────
  readonly progresoVisible = signal(false);
  readonly progresoInst    = signal<any | null>(null);
  readonly progresoCursos  = signal<any[]>([]);
  readonly loadingProgreso = signal(false);

  // ── Organizaciones (lazy — Tab B) ────────────────────────────────────────
  readonly organizaciones = signal<any[]>([]);
  readonly loadingOrgs    = signal(false);
  readonly orgsLoaded     = signal(false);

  // ── Cursos cross-cutting (lazy — Tab C) ──────────────────────────────────
  readonly cursos        = signal<any[]>([]);
  readonly loadingCursos = signal(false);
  readonly cursosLoaded  = signal(false);

  ngOnInit(): void {
    this.cargarResumen();
    this.cargarInstituciones();
  }

  onTabChange(value: string | number | undefined): void {
    if (value === 'organizaciones' && !this.orgsLoaded()) this.cargarOrganizaciones();
    if (value === 'cursos'         && !this.cursosLoaded()) this.cargarCursos();
  }

  // ── Carga de datos ───────────────────────────────────────────────────────

  private cargarResumen(): void {
    this.api.getDashboardResumen().subscribe({
      next:  (r: any) => { this.resumen.set(r.data ?? null); this.loadingResumen.set(false); },
      error: ()       => {
        this.loadingResumen.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el resumen' });
      },
    });
  }

  private cargarInstituciones(): void {
    this.api.getInstituciones().subscribe({
      next:  (r: any) => { this.instituciones.set(r.data ?? []); this.loadingInstituciones.set(false); },
      error: ()       => this.loadingInstituciones.set(false),
    });
  }

  private cargarOrganizaciones(): void {
    this.loadingOrgs.set(true);
    this.api.getDashboardOrganizaciones().subscribe({
      next: (r: any) => {
        this.organizaciones.set(r.data ?? []);
        this.loadingOrgs.set(false);
        this.orgsLoaded.set(true);
      },
      error: () => this.loadingOrgs.set(false),
    });
  }

  private cargarCursos(): void {
    this.loadingCursos.set(true);
    this.api.getDashboardCursos().subscribe({
      next: (r: any) => {
        this.cursos.set(r.data ?? []);
        this.loadingCursos.set(false);
        this.cursosLoaded.set(true);
      },
      error: () => this.loadingCursos.set(false),
    });
  }

  // ── Progreso ─────────────────────────────────────────────────────────────

  verProgreso(inst: any): void {
    this.progresoInst.set(inst);
    this.progresoCursos.set([]);
    this.loadingProgreso.set(true);
    this.progresoVisible.set(true);

    this.api.getProgresoInstitucion(inst.id).subscribe({
      next:  (r: any) => { this.progresoCursos.set(r.data?.cursos ?? []); this.loadingProgreso.set(false); },
      error: ()       => {
        this.loadingProgreso.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el progreso' });
      },
    });
  }

  // ── Helpers de presentación ───────────────────────────────────────────────

  getPctColor(pct: number): string {
    if (pct >= 70) return '#22c55e';
    if (pct >= 30) return '#f59e0b';
    return '#ef4444';
  }

  getPinsSeverity(pct: number): 'success' | 'warn' | 'danger' {
    if (pct >= 70) return 'success';
    if (pct >= 30) return 'warn';
    return 'danger';
  }

}
