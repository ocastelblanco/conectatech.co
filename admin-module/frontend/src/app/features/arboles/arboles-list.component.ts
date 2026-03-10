import { Component, ChangeDetectionStrategy, inject, signal, OnInit } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { DatePipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { ToastModule } from 'primeng/toast';
import { ConfirmDialogModule } from 'primeng/confirmdialog';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { SharedModule } from 'primeng/api';
import { MessageService, ConfirmationService } from 'primeng/api';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-arboles-list',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    RouterLink,
    DatePipe,
    FormsModule,
    ButtonModule,
    TableModule,
    ToastModule,
    ConfirmDialogModule,
    DialogModule,
    InputTextModule,
    SharedModule,
  ],
  providers: [MessageService, ConfirmationService],
  templateUrl: './arboles-list.component.html',
})
export class ArbolesListComponent implements OnInit {
  private readonly api = inject(ApiService);
  private readonly toast = inject(MessageService);
  private readonly confirm = inject(ConfirmationService);
  private readonly router = inject(Router);

  readonly arboles = signal<any[]>([]);
  readonly loading = signal(true);
  readonly duplicandoId = signal<string | null>(null);
  readonly dupMeta = signal({ nombre: '', shortname: '', periodo: '', institucion: '' });

  ngOnInit(): void {
    this.cargarArboles();
  }

  private cargarArboles(): void {
    this.loading.set(true);
    this.api.getArboles().subscribe({
      next: (r: any) => {
        this.arboles.set(r.arboles ?? []);
        this.loading.set(false);
      },
      error: (err: any) => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo cargar los árboles' });
      },
    });
  }

  abrirDuplicar(arbol: any): void {
    this.dupMeta.set({
      nombre: arbol.nombre + ' (copia)',
      shortname: arbol.shortname + '-COPIA',
      periodo: arbol.periodo,
      institucion: arbol.institucion,
    });
    this.duplicandoId.set(arbol.id);
  }

  confirmarDuplicar(): void {
    const id = this.duplicandoId();
    if (!id) return;
    this.api.duplicarArbol(id, this.dupMeta()).subscribe({
      next: () => {
        this.duplicandoId.set(null);
        this.toast.add({ severity: 'success', summary: 'Duplicado', detail: 'Árbol duplicado correctamente' });
        this.cargarArboles();
      },
      error: (err: any) => {
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo duplicar' });
      },
    });
  }

  eliminar(arbol: any): void {
    this.confirm.confirm({
      message: `¿Eliminar el árbol "${arbol.nombre}"? Esta acción no se puede deshacer.`,
      header: 'Confirmar eliminación',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Eliminar',
      rejectLabel: 'Cancelar',
      acceptButtonStyleClass: 'p-button-danger',
      accept: () => {
        this.api.eliminarArbol(arbol.id).subscribe({
          next: () => {
            this.toast.add({ severity: 'success', summary: 'Eliminado', detail: 'Árbol eliminado' });
            this.cargarArboles();
          },
          error: (err: any) => {
            this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo eliminar' });
          },
        });
      },
    });
  }

  editar(arbol: any): void {
    this.router.navigate(['/arboles', arbol.id]);
  }

  getDupMeta() { return this.dupMeta(); }

  updateDupNombre(val: string): void { this.dupMeta.update(m => ({ ...m, nombre: val })); }
  updateDupShortname(val: string): void { this.dupMeta.update(m => ({ ...m, shortname: val })); }
  updateDupPeriodo(val: string): void { this.dupMeta.update(m => ({ ...m, periodo: val })); }
  updateDupInstitucion(val: string): void { this.dupMeta.update(m => ({ ...m, institucion: val })); }
}
