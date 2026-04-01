import { Component, ChangeDetectionStrategy, OnInit, inject, signal, computed } from '@angular/core';
import { ApiService } from '../../../core/services/api.service';
import { GestorStateService } from '../../../core/services/gestor-state.service';
import { TagModule } from 'primeng/tag';
import { MessageService } from 'primeng/api';
import { ToastModule } from 'primeng/toast';

@Component({
  selector: 'cnt-gestor-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [TagModule, ToastModule],
  providers: [MessageService],
  templateUrl: './gestor-dashboard.component.html',
})
export class GestorDashboardComponent implements OnInit {
  private readonly api   = inject(ApiService);
  private readonly toast = inject(MessageService);
  readonly gestorState   = inject(GestorStateService);

  readonly loading = signal(true);
  readonly pines   = signal<any[]>([]);

  readonly counts = computed(() => {
    const all = this.pines();
    return {
      available: all.filter(p => p.status === 'available').length,
      assigned:  all.filter(p => p.status === 'assigned').length,
      active:    all.filter(p => p.status === 'active').length,
    };
  });

  ngOnInit(): void {
    this.api.getGestorPinesLista().subscribe({
      next: (r: any) => {
        this.pines.set(r.data ?? []);
        this.loading.set(false);
      },
      error: () => {
        this.loading.set(false);
        this.toast.add({ severity: 'error', summary: 'Error', detail: 'No se pudo cargar el resumen' });
      }
    });
  }
}
