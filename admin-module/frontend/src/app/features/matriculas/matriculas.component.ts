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
  selector: 'cnt-matriculas',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TableModule, TagModule, ToastModule, ToggleButtonModule],
  providers: [MessageService],
  templateUrl: './matriculas.component.html',
})
export class MatriculasComponent {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly rows    = signal<any[]>([]);
  readonly results = signal<any[]>([]);
  readonly loading = signal(false);
  readonly dryRun  = signal(true);

  parseFile(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file  = input.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = (e) => {
      const text   = e.target?.result as string;
      const parsed = this.parseCSV(text);
      this.rows.set(parsed);
      this.results.set([]);
      this.toast.add({ severity: 'info', summary: 'Archivo cargado', detail: `${parsed.length} usuarios` });
    };
    reader.readAsText(file);
  }

  private parseCSV(text: string): any[] {
    const lines  = text.trim().split('\n');
    const header = lines[0].split(',').map(h => h.trim().toLowerCase());
    return lines.slice(1).filter(l => l.trim()).map(line => {
      const vals = line.split(',').map(v => v.trim());
      const row: Record<string, unknown>  = Object.fromEntries(header.map((h, i) => [h, vals[i] ?? '']));
      if (row['grado']) row['grado'] = parseInt(row['grado'] as string, 10) || 0;
      return row;
    });
  }

  ejecutar(): void {
    if (this.rows().length === 0) return;
    this.loading.set(true);
    this.api.matricular({ dry_run: this.dryRun(), usuarios: this.rows() }).subscribe({
      next: (r: any) => {
        this.results.set(r.results ?? []);
        this.loading.set(false);
        const s = r.summary;
        this.toast.add({
          severity: r.ok ? 'success' : 'warn',
          summary: this.dryRun() ? 'Simulacion completa' : 'Matriculacion completa',
          detail: `Creados: ${s.created} - Actualizados: ${s.updated} - Errores: ${s.errors}`
        });
      },
      error: (err) => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'Error desconocido' });
      }
    });
  }

  downloadCsvTemplate(): void {
    const csv  = 'username,password,firstname,lastname,email,institution,rol,grado,idnumber,grupo\n';
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'matriculas-modelo.csv'; a.click();
    URL.revokeObjectURL(url);
  }

  getSeverity(action: string): 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast' {
    const map: Record<string, 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'> = {
      created: 'success', updated: 'info', 'dry-run:create': 'secondary', 'dry-run:update': 'secondary', error: 'danger'
    };
    return map[action] ?? 'secondary';
  }
}
