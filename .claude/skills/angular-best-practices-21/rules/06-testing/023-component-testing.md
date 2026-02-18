# 023: Testing de Componentes

**Severidad:** Warning  
**Categoría:** Testing  
**Desde:** Angular 21+

## Regla

Testear componentes por comportamiento, no por implementación. Usar el DOM como interfaz de verificación y simular interacciones reales del usuario.

## Rationale

- Tests por comportamiento son más resistentes a refactoring
- Reflejan cómo los usuarios interactúan con la aplicación
- Detectan regresiones reales, no cambios de implementación
- Facilitan el desarrollo guiado por tests (TDD)

## Estructura de Test de Componente

```typescript
// counter.component.spec.ts
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CounterComponent } from './counter.component';

describe('CounterComponent', () => {
  let fixture: ComponentFixture<CounterComponent>;
  let component: CounterComponent;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CounterComponent]  // Standalone component
    }).compileComponents();

    fixture = TestBed.createComponent(CounterComponent);
    component = fixture.componentInstance;
  });

  describe('inicialización', () => {
    it('debe mostrar contador en 0 por defecto', () => {
      fixture.detectChanges();
      
      const countElement = fixture.nativeElement.querySelector('[data-testid="count"]');
      expect(countElement.textContent).toContain('0');
    });
  });

  describe('incrementar', () => {
    it('debe incrementar el contador al hacer click en botón +', () => {
      fixture.detectChanges();
      
      const incrementBtn = fixture.nativeElement.querySelector('[data-testid="increment"]');
      incrementBtn.click();
      fixture.detectChanges();
      
      const countElement = fixture.nativeElement.querySelector('[data-testid="count"]');
      expect(countElement.textContent).toContain('1');
    });
  });
});
```

## Testing de Signal Inputs

```typescript
// user-card.component.spec.ts
import { ComponentRef } from '@angular/core';

describe('UserCardComponent', () => {
  let fixture: ComponentFixture<UserCardComponent>;
  let componentRef: ComponentRef<UserCardComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [UserCardComponent]
    }).compileComponents();

    fixture = TestBed.createComponent(UserCardComponent);
    componentRef = fixture.componentRef;
  });

  it('debe mostrar el nombre del usuario', () => {
    // Setear input via componentRef
    componentRef.setInput('user', { id: '1', name: 'María García' });
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('María García');
  });

  it('debe mostrar badge de admin si isAdmin es true', () => {
    componentRef.setInput('user', { id: '1', name: 'Admin' });
    componentRef.setInput('isAdmin', true);
    fixture.detectChanges();

    const badge = fixture.nativeElement.querySelector('[data-testid="admin-badge"]');
    expect(badge).toBeTruthy();
  });
});
```

## Testing de Outputs

```typescript
// login-form.component.spec.ts
describe('LoginFormComponent', () => {
  it('debe emitir credentials al enviar formulario válido', () => {
    const fixture = TestBed.createComponent(LoginFormComponent);
    const component = fixture.componentInstance;
    fixture.detectChanges();

    // Spy en el output
    const submitSpy = vi.fn();
    component.submitted.subscribe(submitSpy);

    // Llenar formulario
    const emailInput = fixture.nativeElement.querySelector('[data-testid="email"]');
    const passwordInput = fixture.nativeElement.querySelector('[data-testid="password"]');
    
    emailInput.value = 'test@example.com';
    emailInput.dispatchEvent(new Event('input'));
    
    passwordInput.value = 'password123';
    passwordInput.dispatchEvent(new Event('input'));
    
    fixture.detectChanges();

    // Enviar
    const submitBtn = fixture.nativeElement.querySelector('[data-testid="submit"]');
    submitBtn.click();

    expect(submitSpy).toHaveBeenCalledWith({
      email: 'test@example.com',
      password: 'password123'
    });
  });
});
```

## Testing con Dependencias Mockeadas

```typescript
// user-profile.component.spec.ts
describe('UserProfileComponent', () => {
  let userServiceSpy: { getUser: ReturnType<typeof vi.fn> };

  beforeEach(async () => {
    userServiceSpy = {
      getUser: vi.fn()
    };

    await TestBed.configureTestingModule({
      imports: [UserProfileComponent],
      providers: [
        { provide: UserService, useValue: userServiceSpy }
      ]
    }).compileComponents();
  });

  it('debe mostrar datos del usuario cuando carga exitosamente', async () => {
    userServiceSpy.getUser.mockReturnValue(of({ id: '1', name: 'Test User' }));

    const fixture = TestBed.createComponent(UserProfileComponent);
    fixture.componentRef.setInput('userId', '1');
    fixture.detectChanges();
    
    await fixture.whenStable();
    fixture.detectChanges();

    expect(fixture.nativeElement.textContent).toContain('Test User');
  });

  it('debe mostrar error cuando falla la carga', async () => {
    userServiceSpy.getUser.mockReturnValue(
      throwError(() => new Error('Network error'))
    );

    const fixture = TestBed.createComponent(UserProfileComponent);
    fixture.componentRef.setInput('userId', '1');
    fixture.detectChanges();
    
    await fixture.whenStable();
    fixture.detectChanges();

    const errorElement = fixture.nativeElement.querySelector('[data-testid="error"]');
    expect(errorElement).toBeTruthy();
  });
});
```

## Testing de Router

```typescript
// navigation.component.spec.ts
import { provideRouter } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';

describe('NavigationComponent', () => {
  it('debe navegar a /about al hacer click en link', async () => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([
          { path: '', component: HomeComponent },
          { path: 'about', component: AboutComponent }
        ])
      ]
    });

    const harness = await RouterTestingHarness.create();
    await harness.navigateByUrl('/');

    const aboutLink = harness.routeNativeElement!.querySelector('a[href="/about"]');
    aboutLink?.click();

    expect(harness.routeNativeElement?.textContent).toContain('About Page');
  });
});
```

## data-testid para Selectores

```html
<!-- Preferir data-testid sobre clases o estructura DOM -->
<button data-testid="submit-button" (click)="onSubmit()">
  Enviar
</button>

<div data-testid="user-name">{{ user().name }}</div>

<ul data-testid="user-list">
  @for (user of users(); track user.id) {
    <li data-testid="user-item">{{ user.name }}</li>
  }
</ul>
```

## Async Testing con whenStable

```typescript
it('debe cargar datos asíncronos', async () => {
  const fixture = TestBed.createComponent(AsyncComponent);
  fixture.detectChanges();
  
  // Esperar a que se estabilice (signals, observables, etc.)
  await fixture.whenStable();
  fixture.detectChanges();
  
  expect(fixture.nativeElement.querySelector('[data-testid="data"]')).toBeTruthy();
});
```

## Anti-patrones

```typescript
// ❌ Testear implementación, no comportamiento
expect(component.users()).toEqual([...]);  // Acceso directo a signal

// ❌ Selectores frágiles
fixture.nativeElement.querySelector('.btn.btn-primary.mt-3');

// ❌ No esperar async
component.loadData();
expect(component.data()).toBeTruthy();  // Puede fallar intermitentemente
```

## Auto-fix

El agente debe:
1. Usar `componentRef.setInput()` para signal inputs
2. Agregar `data-testid` a elementos testeados
3. Usar `await fixture.whenStable()` para operaciones async
4. Mockear servicios con `vi.fn()`
5. Testear comportamiento visible, no estado interno
