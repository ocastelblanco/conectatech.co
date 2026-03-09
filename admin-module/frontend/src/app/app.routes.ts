import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';

export const routes: Routes = [
  {
    path: '',
    loadComponent: () => import('./layout/shell/shell.component').then(m => m.ShellComponent),
    canActivate: [authGuard],
    children: [
      { path: '', redirectTo: 'dashboard', pathMatch: 'full' },
      {
        path: 'dashboard',
        loadComponent: () => import('./features/dashboard/dashboard.component').then(m => m.DashboardComponent)
      },
      {
        path: 'cursos',
        loadComponent: () => import('./features/cursos/cursos.component').then(m => m.CursosComponent)
      },
      {
        path: 'matriculas',
        loadComponent: () => import('./features/matriculas/matriculas.component').then(m => m.MatriculasComponent)
      },
      {
        path: 'markdown',
        loadComponent: () => import('./features/markdown/markdown.component').then(m => m.MarkdownComponent)
      },
      {
        path: 'reportes',
        loadComponent: () => import('./features/reportes/reportes.component').then(m => m.ReportesComponent)
      },
    ]
  },
  {
    path: 'auth-check',
    loadComponent: () => import('./features/auth-check/auth-check.component').then(m => m.AuthCheckComponent)
  },
  { path: '**', redirectTo: '' }
];
