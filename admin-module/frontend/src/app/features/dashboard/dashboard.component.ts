import { Component, ChangeDetectionStrategy, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { CardModule } from 'primeng/card';
import { ButtonModule } from 'primeng/button';
import { ApiService } from '../../core/services/api.service';

interface QuickAction {
  label:       string;
  description: string;
  icon:        string;
  route:       string;
  color:       string;
}

@Component({
  selector: 'cnt-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, CardModule, ButtonModule],
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent implements OnInit {
  private readonly api = inject(ApiService);

  readonly totalCursos  = signal<number | null>(null);
  readonly apiStatus    = signal<'ok' | 'error' | 'checking'>('checking');

  readonly quickActions: QuickAction[] = [
    {
      label:       'Crear Cursos',
      description: 'Crea cursos finales desde un archivo Excel',
      icon:        'pi pi-book',
      route:       '/cursos',
      color:       '#4A90E2',
    },
    {
      label:       'Matricular Usuarios',
      description: 'Importa y matricula estudiantes y docentes',
      icon:        'pi pi-users',
      route:       '/matriculas',
      color:       '#52A467',
    },
    {
      label:       'Procesar Markdown',
      description: 'Convierte archivos .md en contenido de Moodle',
      icon:        'pi pi-file-edit',
      route:       '/markdown',
      color:       '#FF7F50',
    },
    {
      label:       'Ver Reportes',
      description: 'Consulta el resultado de las ultimas operaciones',
      icon:        'pi pi-chart-bar',
      route:       '/reportes',
      color:       '#1D2B36',
    },
  ];

  ngOnInit(): void {
    this.api.ping().subscribe({
      next:  () => this.apiStatus.set('ok'),
      error: () => this.apiStatus.set('error'),
    });

    this.api.getCursos().subscribe({
      next:  (r: any) => this.totalCursos.set(r.total ?? 0),
      error: () => this.totalCursos.set(null),
    });
  }
}
