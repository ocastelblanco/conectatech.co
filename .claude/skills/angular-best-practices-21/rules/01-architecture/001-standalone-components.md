# 001: Componentes Standalone Obligatorios

**Severidad:** Critical  
**Categoría:** Arquitectura  
**Desde:** Angular 19+

## Regla

Todos los componentes, directivas y pipes deben ser standalone. No usar NgModules para declarar componentes.

## Rationale

- Los componentes standalone simplifican la arquitectura eliminando la capa de NgModules
- Mejoran el tree-shaking y reducen el bundle size
- Facilitan el lazy loading granular a nivel de componente
- Son el estándar oficial de Angular desde v19

## Incorrecto

```typescript
// ❌ Componente declarado en NgModule
@Component({
  selector: 'app-user-card',
  templateUrl: './user-card.component.html'
})
export class UserCardComponent {}

@NgModule({
  declarations: [UserCardComponent],
  imports: [CommonModule],
  exports: [UserCardComponent]
})
export class UserCardModule {}
```

## Correcto

```typescript
// ✅ Componente standalone
@Component({
  selector: 'app-user-card',
  standalone: true,
  imports: [NgIf, NgFor, DatePipe],
  templateUrl: './user-card.component.html'
})
export class UserCardComponent {}
```

## Notas para Migración

Si existe código legacy con NgModules:

```bash
ng generate @angular/core:standalone
```

El schematic convierte automáticamente componentes existentes a standalone.

## Auto-fix

El agente debe:
1. Agregar `standalone: true` al decorator
2. Mover imports del NgModule al array `imports` del componente
3. Eliminar el NgModule si solo declaraba este componente
4. Importar pipes y directivas específicas (no CommonModule completo)

## Excepciones

- Librerías que deben mantener compatibilidad con versiones anteriores de Angular
- Proyectos en proceso de migración gradual (documentar plan de migración)
