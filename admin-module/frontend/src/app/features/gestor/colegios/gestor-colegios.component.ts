import { Component, ChangeDetectionStrategy, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ButtonModule } from 'primeng/button';
import { TableModule } from 'primeng/table';
import { DialogModule } from 'primeng/dialog';
import { InputTextModule } from 'primeng/inputtext';
import { ToastModule } from 'primeng/toast';
import { TooltipModule } from 'primeng/tooltip';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';
import { ApiService } from '../../../core/services/api.service';

interface Grupo {
  id: number;
  name: string;
  colegio_id: number;
  moodle_group_id: number | null;
  teachers_active: number;
  teachers_assigned: number;
  students_active: number;
  students_assigned: number;
}

interface Colegio {
  id: number;
  name: string;
  created_at: number;
  grupos: Grupo[];
}

@Component({
  selector: 'cnt-gestor-colegios',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [FormsModule, ButtonModule, TableModule, DialogModule, InputTextModule, ToastModule, TooltipModule, TagModule],
  providers: [MessageService],
  templateUrl: './gestor-colegios.component.html',
})
export class GestorColegiosComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);

  readonly colegios     = signal<Colegio[]>([]);
  readonly loading      = signal(true);
  readonly saving       = signal(false);

  readonly dialogColegio = signal(false);
  readonly nuevoColegio  = signal('');

  readonly dialogGrupo   = signal(false);
  readonly nuevoGrupo    = signal('');
  readonly colegioActivo = signal<Colegio | null>(null);

  readonly expandedRows = signal<Record<string, boolean>>({});

  onRowExpand(event: any): void {
    this.expandedRows.update(r => ({ ...r, [event.data.id]: true }));
  }

  onRowCollapse(event: any): void {
    this.expandedRows.update(r => {
      const next = { ...r };
      delete next[event.data.id];
      return next;
    });
  }

  ngOnInit(): void { this.cargar(); }

  private cargar(): void {
    this.loading.set(true);
    this.api.getGestorColegios().subscribe({
      next: (r: any) => { this.colegios.set(r.data ?? []); this.loading.set(false); },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar los colegios' });
      }
    });
  }

  abrirCrearColegio(): void {
    this.nuevoColegio.set('');
    this.dialogColegio.set(true);
  }

  guardarColegio(): void {
    const name = this.nuevoColegio().trim();
    if (!name) return;
    this.saving.set(true);
    this.api.crearGestorColegio({ name }).subscribe({
      next: () => {
        this.saving.set(false);
        this.dialogColegio.set(false);
        this.toast.add({ severity: 'success', summary: 'Colegio creado', detail: name });
        this.cargar();
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo crear el colegio' });
      }
    });
  }

  abrirCrearGrupo(colegio: Colegio): void {
    this.colegioActivo.set(colegio);
    this.nuevoGrupo.set('');
    this.dialogGrupo.set(true);
  }

  guardarGrupo(): void {
    const name    = this.nuevoGrupo().trim();
    const colegio = this.colegioActivo();
    if (!name || !colegio) return;
    this.saving.set(true);
    this.api.crearGestorGrupo({ colegio_id: colegio.id, name }).subscribe({
      next: (r: any) => {
        this.saving.set(false);
        this.dialogGrupo.set(false);
        this.toast.add({ severity: 'success', summary: 'Grupo creado', detail: name });
        const nuevoGrupo: Grupo = r.data ?? { id: 0, name, colegio_id: colegio.id, moodle_group_id: null };
        this.colegios.update(list =>
          list.map(c => c.id === colegio.id ? { ...c, grupos: [...c.grupos, nuevoGrupo] } : c)
        );
      },
      error: (err: any) => {
        this.saving.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: err.error?.error ?? 'No se pudo crear el grupo' });
      }
    });
  }
}
