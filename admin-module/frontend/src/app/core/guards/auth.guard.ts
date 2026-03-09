import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from '../services/auth.service';

export const authGuard: CanActivateFn = () => {
  const auth   = inject(AuthService);
  const router = inject(Router);

  const isAuth = auth.isAuthenticated();

  if (isAuth === true) return true;
  if (isAuth === false) {
    auth.redirectToMoodleLogin();
    return false;
  }

  return router.createUrlTree(['/auth-check']);
};
