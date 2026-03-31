import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { ApiService } from '../services/api.service';
import { GestorStateService } from '../services/gestor-state.service';
import { catchError, map, of } from 'rxjs';

export const gestorGuard: CanActivateFn = () => {
  const api    = inject(ApiService);
  const state  = inject(GestorStateService);
  const router = inject(Router);

  // Si ya tenemos los datos cacheados, permitir acceso directamente
  if (state.org() !== null) return true;

  return api.getGestorOrganizacion().pipe(
    map((r: any) => {
      state.setOrg(r);
      return true;
    }),
    catchError(() => of(router.createUrlTree(['/auth-check'])))
  );
};
