import {
  Component, ChangeDetectionStrategy, signal, computed, inject, OnInit,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { firstValueFrom } from 'rxjs';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService, ConfirmationService } from 'primeng/api';
import { CdnApiService, AssetItem } from '../../core/services/cdn-api.service';
import { CrearVisorDialogComponent } from './crear-visor-dialog.component';

type Tab = 'pdf' | 'imagen';

@Component({
  selector: 'cnt-activos',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [MessageService, ConfirmationService],
  imports: [
    FormsModule,
    ButtonModule, TableModule, DialogModule, InputTextModule,
    ToastModule, ConfirmDialogModule, TooltipModule,
    CrearVisorDialogComponent,
  ],
  templateUrl: './activos.component.html',
})
export class ActivosComponent implements OnInit {
  private readonly cdn = inject(CdnApiService);
  private readonly messageService = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);

  // ─── Tab ──────────────────────────────────────────────────────────────────
  readonly activeTab = signal<Tab>('pdf');

  // ─── Asset lists ──────────────────────────────────────────────────────────
  readonly pdfs = signal<AssetItem[]>([]);
  readonly imagenes = signal<AssetItem[]>([]);
  readonly loading = signal(false);

  readonly activeItems = computed<AssetItem[]>(() =>
    this.activeTab() === 'pdf' ? this.pdfs() : this.imagenes()
  );

  // ─── Upload dialog ────────────────────────────────────────────────────────
  readonly uploadVisible = signal(false);
  readonly uploadTitle = signal('');
  readonly uploadFile = signal<File | null>(null);
  readonly uploading = signal(false);
  readonly uploadError = signal('');
  readonly dragOver = signal(false);

  // ─── Rename inline ────────────────────────────────────────────────────────
  readonly editingId = signal<string | null>(null);
  readonly editingTitle = signal('');

  // ─── Crear visor dialog ───────────────────────────────────────────────────
  readonly visorVisible = signal(false);
  readonly visorPdf = signal<AssetItem | null>(null);

  // ─── Post-upload PDF prompt ───────────────────────────────────────────────
  readonly postUploadPdf = signal<AssetItem | null>(null);
  readonly postUploadVisible = signal(false);

  // ─── Computed ─────────────────────────────────────────────────────────────
  readonly acceptTypes = computed(() =>
    this.activeTab() === 'pdf' ? '.pdf' : '.png,.jpg,.jpeg,.webp,.gif'
  );

  ngOnInit(): void {
    this.loadAll();
  }

  loadAll(): void {
    this.loading.set(true);
    Promise.all([
      firstValueFrom(this.cdn.listPdfs()).then(r => this.pdfs.set(r.items ?? [])),
      firstValueFrom(this.cdn.listImagenes()).then(r => this.imagenes.set(r.items ?? [])),
    ])
      .catch(() => {
        this.messageService.add({
          severity: 'error',
          summary: 'Error',
          detail: 'No se pudieron cargar los activos',
        });
      })
      .finally(() => this.loading.set(false));
  }

  // ─── Tab switch ───────────────────────────────────────────────────────────
  switchTab(tab: Tab): void {
    this.activeTab.set(tab);
    this.cancelEdit();
  }

  // ─── Upload ───────────────────────────────────────────────────────────────
  openUpload(): void {
    this.uploadTitle.set('');
    this.uploadFile.set(null);
    this.uploadError.set('');
    this.uploadVisible.set(true);
  }

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    this.uploadFile.set(file);
    if (file && !this.uploadTitle()) {
      this.uploadTitle.set(file.name.replace(/\.[^.]+$/, ''));
    }
  }

  onDragOver(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(true);
  }

  onFileDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
    const file = event.dataTransfer?.files?.[0] ?? null;
    if (!file) return;
    this.uploadFile.set(file);
    if (!this.uploadTitle()) {
      this.uploadTitle.set(file.name.replace(/\.[^.]+$/, ''));
    }
  }

  async upload(): Promise<void> {
    const file = this.uploadFile();
    const title = this.uploadTitle().trim();
    if (!file || !title) return;

    this.uploading.set(true);
    this.uploadError.set('');
    const tab = this.activeTab();

    try {
      const createRes = await firstValueFrom(
        tab === 'pdf'
          ? this.cdn.createPdf(title, file.name)
          : this.cdn.createImagen(title, file.name)
      );

      await firstValueFrom(this.cdn.uploadToS3(createRes.uploadUrl, file, createRes.contentType));

      const confirmRes = await firstValueFrom(
        tab === 'pdf'
          ? this.cdn.confirmPdf(createRes.item.id)
          : this.cdn.confirmImagen(createRes.item.id)
      );

      const confirmed = confirmRes.item;
      this.uploadVisible.set(false);

      if (tab === 'pdf') {
        this.pdfs.update(items => [...items, confirmed]);
        this.postUploadPdf.set(confirmed);
        this.postUploadVisible.set(true);
      } else {
        this.imagenes.update(items => [...items, confirmed]);
        this.copyUrl(confirmed.url);
        this.messageService.add({
          severity: 'success',
          summary: 'Imagen cargada',
          detail: 'URL copiada al portapapeles',
        });
      }
    } catch (e: any) {
      this.uploadError.set(e.error?.error || e.message || 'Error al cargar el archivo');
    } finally {
      this.uploading.set(false);
    }
  }

  // ─── Post-upload PDF actions ──────────────────────────────────────────────
  openVisorForPostUpload(): void {
    this.visorPdf.set(this.postUploadPdf());
    this.postUploadVisible.set(false);
    this.visorVisible.set(true);
  }

  dismissPostUpload(): void {
    const pdf = this.postUploadPdf();
    if (pdf) {
      this.copyUrl(pdf.url);
      this.messageService.add({ severity: 'info', summary: 'URL copiada', detail: pdf.url });
    }
    this.postUploadVisible.set(false);
  }

  // ─── Rename ───────────────────────────────────────────────────────────────
  startEdit(item: AssetItem): void {
    this.editingId.set(item.id);
    this.editingTitle.set(item.title);
  }

  cancelEdit(): void {
    this.editingId.set(null);
    this.editingTitle.set('');
  }

  async saveEdit(item: AssetItem): Promise<void> {
    const newTitle = this.editingTitle().trim();
    if (!newTitle || newTitle === item.title) { this.cancelEdit(); return; }

    try {
      const res = await firstValueFrom(
        this.activeTab() === 'pdf'
          ? this.cdn.renamePdf(item.id, newTitle)
          : this.cdn.renameImagen(item.id, newTitle)
      );
      const updated = res.item;
      if (this.activeTab() === 'pdf') {
        this.pdfs.update(items => items.map(i => i.id === updated.id ? updated : i));
      } else {
        this.imagenes.update(items => items.map(i => i.id === updated.id ? updated : i));
      }
      this.copyUrl(updated.url);
      this.messageService.add({ severity: 'success', summary: 'Renombrado', detail: 'URL copiada al portapapeles' });
    } catch (e: any) {
      this.messageService.add({ severity: 'error', summary: 'Error', detail: e.error?.error || 'Error al renombrar' });
    } finally {
      this.cancelEdit();
    }
  }

  // ─── Delete ───────────────────────────────────────────────────────────────
  deleteItem(item: AssetItem): void {
    this.confirm.confirm({
      message: `¿Eliminar "${item.title}"? Esta acción no se puede deshacer.`,
      header: 'Confirmar eliminación',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => this.doDelete(item),
    });
  }

  private async doDelete(item: AssetItem): Promise<void> {
    try {
      await firstValueFrom(
        this.activeTab() === 'pdf'
          ? this.cdn.deletePdf(item.id)
          : this.cdn.deleteImagen(item.id)
      );
      if (this.activeTab() === 'pdf') {
        this.pdfs.update(items => items.filter(i => i.id !== item.id));
      } else {
        this.imagenes.update(items => items.filter(i => i.id !== item.id));
      }
      this.messageService.add({ severity: 'success', summary: 'Eliminado', detail: `"${item.title}" eliminado` });
    } catch (e: any) {
      this.messageService.add({ severity: 'error', summary: 'Error', detail: e.error?.error || 'Error al eliminar' });
    }
  }

  // ─── Crear visor ──────────────────────────────────────────────────────────
  openVisor(item: AssetItem): void {
    this.visorPdf.set(item);
    this.visorVisible.set(true);
  }

  // ─── Copy URL ─────────────────────────────────────────────────────────────
  copyUrl(url: string): void {
    navigator.clipboard.writeText(url).catch(() => { });
  }

  onCopyUrl(item: AssetItem): void {
    this.copyUrl(item.url);
    this.messageService.add({ severity: 'info', summary: 'URL copiada', detail: item.url });
  }

  // ─── Helpers ──────────────────────────────────────────────────────────────
  formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString('es-CO', {
      day: '2-digit', month: '2-digit', year: 'numeric',
    });
  }
}
