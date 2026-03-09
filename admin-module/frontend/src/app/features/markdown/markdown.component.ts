import { Component, ChangeDetectionStrategy, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TextareaModule } from 'primeng/textarea';
import { SelectModule } from 'primeng/select';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

const REPO_COURSES = [
  'repo-cc-cn-4-5','repo-cc-cn-6-7','repo-cc-cn-8-9','repo-cc-fi-10-11','repo-cc-qu-10-11',
  'repo-cc-ma-4-5','repo-cc-ma-6-7','repo-cc-ma-8-9','repo-cc-ma-10-11',
  'repo-cc-le-4-5','repo-cc-le-6-7','repo-cc-le-8-9','repo-cc-le-10-11',
  'repo-cc-cs-4-5','repo-cc-cs-6-7','repo-cc-cs-8-9','repo-cc-cs-10-11',
  'repo-uc-cn-4-5','repo-uc-cn-6-7','repo-uc-cn-8-9','repo-uc-fi-10-11','repo-uc-qu-10-11',
  'repo-uc-ma-4-5','repo-uc-ma-6-7','repo-uc-ma-8-9','repo-uc-ma-10-11',
  'repo-uc-le-4-5','repo-uc-le-6-7','repo-uc-le-8-9','repo-uc-le-10-11',
  'repo-uc-cs-4-5','repo-uc-cs-6-7','repo-uc-cs-8-9','repo-uc-cs-10-11',
];

@Component({
  selector: 'cnt-markdown',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TextareaModule, SelectModule, TagModule, ToastModule],
  providers: [MessageService],
  templateUrl: './markdown.component.html',
})
export class MarkdownComponent {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly courses   = REPO_COURSES.map(v => ({ label: v, value: v }));
  readonly shortname = signal('');
  readonly content   = signal('');
  readonly loading   = signal(false);
  readonly summary   = signal<any>(null);
  readonly errors    = signal<string[]>([]);

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
}
