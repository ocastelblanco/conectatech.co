/**
 * Guard Template - Angular 21+
 * 
 * Template para guards funcionales modernos.
 * Angular 21+ prefiere guards funcionales sobre guards basados en clases.
 * 
 * Uso:
 *   1. Copiar este archivo a tu proyecto
 *   2. Renombrar según la funcionalidad (ej: auth.guard.ts, role.guard.ts)
 *   3. Adaptar la lógica de verificación
 *   4. Registrar en las rutas
 * 
 * Tipos de guards:
 *   - CanActivateFn: Controla acceso a rutas
 *   - CanActivateChildFn: Controla acceso a rutas hijas
 *   - CanDeactivateFn: Controla salida de rutas (cambios sin guardar)
 *   - CanMatchFn: Controla si una ruta debe considerarse en el matching
 *   - ResolveFn: Precarga datos antes de activar ruta
 */

import { inject } from '@angular/core';
import { 
  CanActivateFn, 
  CanDeactivateFn, 
  CanMatchFn,
  Router, 
  ActivatedRouteSnapshot, 
  RouterStateSnapshot,
  UrlTree
} from '@angular/router';
import { Observable, map, take } from 'rxjs';

// =============================================================================
// SERVICIOS (importar los reales de tu proyecto)
// =============================================================================

// import { AuthService } from '../services/auth.service';
// import { PermissionService } from '../services/permission.service';

// Placeholder para el ejemplo
interface AuthService {
  isAuthenticated$: Observable<boolean>;
  currentUser$: Observable<{ roles: string[] } | null>;
  logout(): void;
}

// =============================================================================
// GUARD: Autenticación básica
// =============================================================================

/**
 * Guard que verifica si el usuario está autenticado.
 * Redirige a /login si no está autenticado.
 */
export const authGuard: CanActivateFn = (
  route: ActivatedRouteSnapshot,
  state: RouterStateSnapshot
): Observable<boolean | UrlTree> => {
  const authService = inject(AuthService);
  const router = inject(Router);
  
  return authService.isAuthenticated$.pipe(
    take(1),
    map(isAuthenticated => {
      if (isAuthenticated) {
        return true;
      }
      
      // Guardar URL intentada para redirect después de login
      return router.createUrlTree(['/login'], {
        queryParams: { returnUrl: state.url }
      });
    })
  );
};

// =============================================================================
// GUARD: Verificación de roles
// =============================================================================

/**
 * Factory para crear guard de roles.
 * Uso: canActivate: [roleGuard('admin', 'editor')]
 */
export function roleGuard(...allowedRoles: string[]): CanActivateFn {
  return (route, state) => {
    const authService = inject(AuthService);
    const router = inject(Router);
    
    return authService.currentUser$.pipe(
      take(1),
      map(user => {
        if (!user) {
          return router.createUrlTree(['/login']);
        }
        
        const hasRole = allowedRoles.some(role => user.roles.includes(role));
        
        if (hasRole) {
          return true;
        }
        
        // Redirigir a página de acceso denegado
        return router.createUrlTree(['/forbidden']);
      })
    );
  };
}

// =============================================================================
// GUARD: Verificación de permisos desde ruta
// =============================================================================

/**
 * Guard que lee permisos requeridos desde data de la ruta.
 * 
 * Uso en rutas:
 *   {
 *     path: 'admin',
 *     component: AdminComponent,
 *     canActivate: [permissionGuard],
 *     data: { permissions: ['users.read', 'users.write'] }
 *   }
 */
export const permissionGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  
  const requiredPermissions = route.data['permissions'] as string[] || [];
  
  if (requiredPermissions.length === 0) {
    return true; // Sin permisos requeridos
  }
  
  return authService.currentUser$.pipe(
    take(1),
    map(user => {
      if (!user) {
        return router.createUrlTree(['/login']);
      }
      
      // Verificar permisos (adaptar según tu lógica)
      // const hasPermissions = requiredPermissions.every(p => 
      //   permissionService.hasPermission(user, p)
      // );
      
      // Placeholder - implementar verificación real
      const hasPermissions = true;
      
      return hasPermissions ? true : router.createUrlTree(['/forbidden']);
    })
  );
};

// =============================================================================
// GUARD: Cambios sin guardar (CanDeactivate)
// =============================================================================

/**
 * Interface que deben implementar componentes con cambios sin guardar.
 */
export interface HasUnsavedChanges {
  hasUnsavedChanges(): boolean;
}

/**
 * Guard que previene navegación si hay cambios sin guardar.
 * El componente debe implementar HasUnsavedChanges.
 */
export const unsavedChangesGuard: CanDeactivateFn<HasUnsavedChanges> = (
  component: HasUnsavedChanges,
  currentRoute: ActivatedRouteSnapshot,
  currentState: RouterStateSnapshot,
  nextState: RouterStateSnapshot
): boolean | Observable<boolean> => {
  
  if (!component.hasUnsavedChanges()) {
    return true;
  }
  
  // Mostrar confirmación nativa del navegador
  return confirm(
    '¿Estás seguro de que deseas salir? Los cambios sin guardar se perderán.'
  );
  
  // Alternativa: usar un servicio de diálogo personalizado
  // const dialog = inject(DialogService);
  // return dialog.confirm({
  //   title: 'Cambios sin guardar',
  //   message: '¿Deseas salir sin guardar?',
  //   confirmText: 'Salir',
  //   cancelText: 'Quedarse'
  // });
};

// =============================================================================
// GUARD: CanMatch para feature flags
// =============================================================================

/**
 * Guard que oculta rutas según feature flags.
 * La ruta no aparece en el matching si el feature está deshabilitado.
 */
export function featureFlagGuard(featureName: string): CanMatchFn {
  return (route, segments) => {
    // const featureService = inject(FeatureFlagService);
    // return featureService.isEnabled(featureName);
    
    // Placeholder - implementar con tu servicio de feature flags
    const enabledFeatures = ['dashboard', 'reports'];
    return enabledFeatures.includes(featureName);
  };
}

// =============================================================================
// EJEMPLO DE USO EN RUTAS
// =============================================================================

/*
import { Routes } from '@angular/router';
import { 
  authGuard, 
  roleGuard, 
  permissionGuard, 
  unsavedChangesGuard,
  featureFlagGuard 
} from './guards/auth.guard';

export const routes: Routes = [
  // Ruta pública
  { path: 'login', component: LoginComponent },
  
  // Ruta protegida por autenticación
  {
    path: 'dashboard',
    component: DashboardComponent,
    canActivate: [authGuard]
  },
  
  // Ruta protegida por rol
  {
    path: 'admin',
    component: AdminComponent,
    canActivate: [authGuard, roleGuard('admin')]
  },
  
  // Ruta con permisos específicos
  {
    path: 'users',
    component: UsersComponent,
    canActivate: [authGuard, permissionGuard],
    data: { permissions: ['users.read'] }
  },
  
  // Ruta con prevención de salida
  {
    path: 'editor',
    component: EditorComponent,
    canActivate: [authGuard],
    canDeactivate: [unsavedChangesGuard]
  },
  
  // Ruta condicional por feature flag
  {
    path: 'beta-feature',
    component: BetaComponent,
    canMatch: [featureFlagGuard('beta-feature')]
  },
  
  // Rutas hijas protegidas
  {
    path: 'settings',
    canActivate: [authGuard],
    canActivateChild: [authGuard],
    children: [
      { path: 'profile', component: ProfileComponent },
      { path: 'security', component: SecurityComponent }
    ]
  }
];
*/

// =============================================================================
// NOTAS DE MIGRACIÓN DESDE GUARDS DE CLASE
// =============================================================================

/*
ANTES (Guard de clase - deprecated):

@Injectable({ providedIn: 'root' })
export class AuthGuard implements CanActivate {
  constructor(
    private authService: AuthService,
    private router: Router
  ) {}
  
  canActivate(route: ActivatedRouteSnapshot, state: RouterStateSnapshot) {
    // lógica
  }
}

DESPUÉS (Guard funcional - recomendado):

export const authGuard: CanActivateFn = (route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  // lógica
};

Beneficios:
- Menos boilerplate
- Más fácil de testear
- Tree-shakeable
- Composable con factories
*/
