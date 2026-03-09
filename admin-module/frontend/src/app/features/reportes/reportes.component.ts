import { Component, ChangeDetectionStrategy, inject, signal } from '@angular/core';
import { DatePipe, JsonPipe } from '@angular/common';
import { ButtonModule } from 'primeng/button';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

type ReporteName = 'matriculas' | 'creacion' | 'poblamiento' | 'markdown';

@Component({
  selector: 'cnt-reportes',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, JsonPipe, ButtonModule, TagModule, ToastModule],
  providers: [MessageService],
  templateUrl: './reportes.component.html',
})
export class ReportesComponent {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly activeTab = signal<ReporteName>('matriculas');
  readonly reports   = signal<Record<ReporteName, any>>({
    matriculas:  null,
    creacion:    null,
    poblamiento: null,
    markdown:    null,
  });
  readonly loading = signal<ReporteName | null>(null);

  readonly tabs: { key: ReporteName; label: string; icon: string }[] = [
    { key: 'matriculas',  label: 'Matriculas',      icon: 'pi pi-users' },
    { key: 'creacion',    label: 'Creacion',         icon: 'pi pi-book' },
    { key: 'poblamiento', label: 'Poblamiento',      icon: 'pi pi-copy' },
    { key: 'markdown',    label: 'Markdown',         icon: 'pi pi-file-edit' },
  ];

  selectTab(key: ReporteName): void {
    this.activeTab.set(key);
    if (!this.reports()[key]) this.cargar(key);
  }

  cargar(key: ReporteName): void {
    this.loading.set(key);
    this.api.getReporte(key).subscribe({
      next: (r: any) => {
        this.reports.update(prev => ({ ...prev, [key]: r.report }));
        this.loading.set(null);
      },
      error: (err) => {
        this.loading.set(null);
        this.toast.add({
          severity: 'info',
          summary: 'Sin datos',
          detail: err.error?.error ?? 'El reporte no existe aun'
        });
      }
    });
  }

  getKeys(obj: any): string[] {
    return obj ? Object.keys(obj) : [];
  }
}
