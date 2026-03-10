import { Component, ChangeDetectionStrategy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { ToggleButtonModule } from 'primeng/togglebutton';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-cursos',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TableModule, TagModule, ToastModule, ToggleButtonModule],
  providers: [MessageService],
  templateUrl: './cursos.component.html',
})
export class CursosComponent {
  private readonly api     = inject(ApiService);
  private readonly toast   = inject(MessageService);

  readonly rows     = signal<any[]>([]);
  readonly results  = signal<any[]>([]);
  readonly loading  = signal(false);
  readonly dryRun   = signal(true);

  parseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const text    = e.target?.result as string;
        const parsed  = this.parseCSV(text);
        this.rows.set(parsed);
        this.results.set([]);
        this.toast.add({ severity: 'info', summary: 'Archivo cargado', detail: `${parsed.length} filas` });
      } catch {
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo leer el archivo' });
      }
    };
    reader.readAsText(file);
  }

  private parseCSV(text: string): any[] {
    const lines  = text.trim().split('\n');
    const header = lines[0].split(',').map(h => h.trim().toLowerCase());
    return lines.slice(1)
      .filter(l => l.trim())
      .map(line => {
        const vals = line.split(',').map(v => v.trim());
        return Object.fromEntries(header.map((h, i) => [h, vals[i] ?? '']));
      });
  }

  ejecutar(): void {
    if (this.rows().length === 0) return;
    this.loading.set(true);
    this.api.crearCursos({ dry_run: this.dryRun(), cursos: this.rows() }).subscribe({
      next: (r: any) => {
        this.results.set(r.results ?? []);
        this.loading.set(false);
        const s = r.summary;
        this.toast.add({
          severity: r.ok ? 'success' : 'warn',
          summary: this.dryRun() ? 'Simulacion completa' : 'Operacion completa',
          detail: `Creados: ${s.created} - Omitidos: ${s.skipped} - Errores: ${s.errors}`
        });
      },
      error: (err) => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error desconocido' });
      }
    });
  }

  downloadCsvTemplate(): void {
    const csv  = 'shortname,fullname,category,templatecourse\n';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'cursos-modelo.csv'; a.click();
    URL.revokeObjectURL(url);
  }

  getSeverity(action: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      created:  'success',
      skipped:  'info',
      'dry-run':'secondary',
      error:    'danger',
    };
    return map[action] ?? 'secondary';
  }
}
