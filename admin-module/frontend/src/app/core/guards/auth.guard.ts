import { inject } from '@angular/core';
import { CanActivateFn, Router, RouterStateSnapshot } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = (_route, state: RouterStateSnapshot) => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  const isAuth = auth.isAuthenticated();

  if (isAuth === true) return true;
  if (isAuth === false) {
    auth.redirectToMoodleLogin();
    return false;
  }

  // Pasar la URL actual como returnUrl para que auth-check vuelva a ella
  return router.createUrlTree(['/auth-check'], {
    queryParams: { returnUrl: state.url },
  });
};
