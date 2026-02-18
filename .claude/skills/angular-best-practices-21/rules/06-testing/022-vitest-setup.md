# 019: Configuración de Vitest

**Severidad:** Critical  
**Categoría:** Testing  
**Desde:** Angular 21+

## Regla

Usar Vitest como framework de testing por defecto. Configurar correctamente providers globales, cobertura y entorno de ejecución.

## Setup Básico

Angular CLI incluye Vitest por defecto. Ejecutar tests:

```bash
ng test              # Watch mode
ng test --no-watch   # Single run (CI)
ng test --coverage   # Con cobertura
```

## Configuración en angular.json

```json
{
  "projects": {
    "my-app": {
      "architect": {
        "test": {
          "builder": "@angular/build:unit-test",
          "options": {
            "include": ["**/*.spec.ts"],
            "exclude": ["**/e2e/**"],
            "tsConfig": "tsconfig.spec.json",
            "providersFile": "src/test-providers.ts",
            "coverage": false
          }
        }
      }
    }
  }
}
```

## Providers Globales

Crear `src/test-providers.ts` para providers comunes:

```typescript
// src/test-providers.ts
import { Provider } from '@angular/core';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting } from '@angular/common/http/testing';
import { provideRouter } from '@angular/router';

const testProviders: Provider[] = [
  provideHttpClient(),
  provideHttpClientTesting(),
  provideRouter([])  // Router vacío para tests
];

export default testProviders;
```

## Estructura de Test

```typescript
// user.service.spec.ts
import { TestBed } from '@angular/core/testing';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideHttpClient } from '@angular/common/http';
import { UserService } from './user.service';

describe('UserService', () => {
  let service: UserService;
  let httpMock: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting()
      ]
    });
    
    service = TestBed.inject(UserService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();  // Verificar no hay peticiones pendientes
  });

  it('should fetch users', () => {
    const mockUsers = [{ id: 1, name: 'Test' }];
    
    service.getUsers().subscribe(users => {
      expect(users).toEqual(mockUsers);
    });

    const req = httpMock.expectOne('/api/users');
    expect(req.request.method).toBe('GET');
    req.flush(mockUsers);
  });
});
```

## Testing de Componentes con Signals

```typescript
// counter.component.spec.ts
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { CounterComponent } from './counter.component';

describe('CounterComponent', () => {
  let component: CounterComponent;
  let fixture: ComponentFixture<CounterComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [CounterComponent]  // Standalone component
    }).compileComponents();

    fixture = TestBed.createComponent(CounterComponent);
    component = fixture.componentInstance;
  });

  it('should increment counter', () => {
    // Arrange
    expect(component.count()).toBe(0);
    
    // Act
    component.increment();
    
    // Assert
    expect(component.count()).toBe(1);
  });

  it('should render count in template', () => {
    // Act
    component.count.set(5);
    fixture.detectChanges();
    
    // Assert
    const element = fixture.nativeElement.querySelector('.count');
    expect(element.textContent).toContain('5');
  });
});
```

## Testing con Signal Inputs

```typescript
// user-card.component.spec.ts
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ComponentRef } from '@angular/core';
import { UserCardComponent } from './user-card.component';

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

  it('should display user name', () => {
    // Setear signal input via componentRef
    componentRef.setInput('user', { id: 1, name: 'John' });
    fixture.detectChanges();
    
    expect(fixture.nativeElement.textContent).toContain('John');
  });
});
```

## Ejecutar en Browser (Opcional)

Para tests que requieren APIs del browser real:

```bash
# Instalar provider de browser
npm install -D @vitest/browser-playwright playwright

# Ejecutar en Chromium
ng test --browsers=chromium
```

## Configuración de Cobertura

```json
// angular.json
{
  "test": {
    "options": {
      "coverage": true
    }
  }
}
```

Ver reporte en `coverage/index.html`.

## Auto-fix

El agente debe:
1. Crear `test-providers.ts` si no existe
2. Configurar providers comunes (HttpClient, Router)
3. Usar `compileComponents()` para componentes con templates externos
4. Usar `componentRef.setInput()` para signal inputs
5. Verificar peticiones HTTP con `httpMock.verify()` en `afterEach`
