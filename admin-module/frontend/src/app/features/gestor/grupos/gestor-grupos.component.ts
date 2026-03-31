import { Component, ChangeDetectionStrategy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { TagModule } from 'primeng/tag';
import { ToastModule } from 'primeng/toast';
import { TooltipModule } from 'primeng/tooltip';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../../core/services/api.service';

@Component({
  selector: 'cnt-gestor-grupos',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TableModule, DialogModule, InputTextModule, TagModule, ToastModule, TooltipModule],
  providers: [MessageService],
  templateUrl: './gestor-grupos.component.html',
})
export class GestorGruposComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly grupos        = signal<any[]>([]);
  readonly loading       = signal(true);
  readonly saving        = signal(false);
  readonly dialogVisible = signal(false);
  readonly nuevoNombre   = signal('');

  ngOnInit(): void { this.cargar(); }

  private cargar(): void {
    this.loading.set(true);
    this.api.getGestorGrupos().subscribe({
      next: (r: any) => { this.grupos.set(r.grupos ?? r ?? []); this.loading.set(false); },
      error: () => { this.loading.set(false); this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar los grupos' }); }
    });
  }

  abrirCrear(): void { this.nuevoNombre.set(''); this.dialogVisible.set(true); }

  guardar(): void {
    const name = this.nuevoNombre().trim();
    if (!name) return;
    this.saving.set(true);
    this.api.crearGestorGrupo({ name }).subscribe({
      next: () => {
        this.saving.set(false);
        this.dialogVisible.set(false);
        this.toast.add({ severity: 'success', summary: 'Grupo creado', detail: name });
        this.cargar();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo crear el grupo' });
      }
    });
  }
}
