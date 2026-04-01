import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { gestorGuard } from './core/guards/gestor.guard';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./layout/shell/shell.component').then(m => m.ShellComponent),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: '/auth-check', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent)
      },
      {
        path: 'matriculas',
        loadComponent: () => import('./features/matriculas/matriculas.component').then(m => m.MatriculasComponent)
      },
      {
        path: 'contenido',
        loadComponent: () => import('./features/contenido/contenido.component').then(m => m.ContenidoComponent)
      },
      {
        path: 'arboles',
        loadComponent: () => import('./features/arboles/arboles-list.component').then(m => m.ArbolesListComponent)
      },
      {
        path: 'arboles/:id',
        loadComponent: () => import('./features/arboles/arbol-editor.component').then(m => m.ArbolEditorComponent)
      },
      {
        path: 'activos',
        loadComponent: () => import('./features/activos/activos.component').then(m => m.ActivosComponent)
      },
      {
        path: 'organizaciones',
        loadComponent: () => import('./features/organizaciones/organizaciones.component').then(m => m.OrganizacionesComponent)
      },
      {
        path: 'pines',
        loadComponent: () => import('./features/pines/pines.component').then(m => m.PinesComponent)
      },
      {
        path: 'pines/reporte',
        loadComponent: () => import('./features/pines/reporte/pines-reporte.component').then(m => m.PinesReporteComponent)
      },
    ]
  },
  {
    path: 'auth-check',
    loadComponent: () => import('./features/auth-check/auth-check.component').then(m => m.AuthCheckComponent)
  },
  {
    path: 'gestor',
    loadComponent: () => import('./layout/gestor-shell/gestor-shell.component').then(m => m.GestorShellComponent),
    canActivate: [gestorGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () => import('./features/gestor/dashboard/gestor-dashboard.component').then(m => m.GestorDashboardComponent)
      },
      {
        path: 'grupos',
        loadComponent: () => import('./features/gestor/grupos/gestor-grupos.component').then(m => m.GestorGruposComponent)
      },
      {
        path: 'pines',
        loadComponent: () => import('./features/gestor/pines/gestor-pines.component').then(m => m.GestorPinesComponent)
      },
    ]
  },
  {
    path: 'activar',
    loadComponent: () => import('./features/activar/activar.component').then(m => m.ActivarComponent)
  },
  { path: '**', redirectTo: '' }
];
