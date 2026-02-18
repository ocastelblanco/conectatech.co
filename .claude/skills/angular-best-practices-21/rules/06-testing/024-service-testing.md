# 024: Testing de Servicios

**Severidad:** Warning  
**Categoría:** Testing  
**Desde:** Angular 21+

## Regla

Testear servicios de forma aislada, mockeando dependencias HTTP y otros servicios. Verificar lógica de negocio, transformaciones de datos y manejo de errores.

## Rationale

- Servicios contienen lógica de negocio crítica
- Tests aislados son rápidos y determinísticos
- Verifican contratos de API (inputs/outputs)
- Facilitan refactoring con confianza

## Testing de Servicio con HttpClient

```typescript
// user.service.spec.ts
import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
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
    // Verificar que no hay peticiones pendientes
    httpMock.verify();
  });

  describe('getUsers', () => {
    it('debe retornar lista de usuarios', () => {
      const mockUsers = [
        { id: '1', name: 'User 1' },
        { id: '2', name: 'User 2' }
      ];

      service.getUsers().subscribe(users => {
        expect(users).toEqual(mockUsers);
        expect(users.length).toBe(2);
      });

      const req = httpMock.expectOne('/api/users');
      expect(req.request.method).toBe('GET');
      req.flush(mockUsers);
    });

    it('debe incluir parámetros de búsqueda', () => {
      service.getUsers({ search: 'test', page: 2 }).subscribe();

      const req = httpMock.expectOne(
        req => req.url === '/api/users' && 
               req.params.get('search') === 'test' &&
               req.params.get('page') === '2'
      );
      req.flush([]);
    });

    it('debe manejar error del servidor', () => {
      service.getUsers().subscribe({
        next: () => fail('Debería haber fallado'),
        error: (error) => {
          expect(error.message).toContain('Error');
        }
      });

      const req = httpMock.expectOne('/api/users');
      req.flush('Error del servidor', { 
        status: 500, 
        statusText: 'Internal Server Error' 
      });
    });
  });

  describe('createUser', () => {
    it('debe enviar POST con datos del usuario', () => {
      const newUser = { name: 'New User', email: 'new@example.com' };
      const createdUser = { id: '3', ...newUser };

      service.createUser(newUser).subscribe(user => {
        expect(user).toEqual(createdUser);
      });

      const req = httpMock.expectOne('/api/users');
      expect(req.request.method).toBe('POST');
      expect(req.request.body).toEqual(newUser);
      req.flush(createdUser);
    });
  });

  describe('updateUser', () => {
    it('debe enviar PUT a URL correcta', () => {
      const updateData = { name: 'Updated Name' };

      service.updateUser('123', updateData).subscribe();

      const req = httpMock.expectOne('/api/users/123');
      expect(req.request.method).toBe('PUT');
      expect(req.request.body).toEqual(updateData);
      req.flush({ id: '123', ...updateData });
    });
  });

  describe('deleteUser', () => {
    it('debe enviar DELETE a URL correcta', () => {
      service.deleteUser('123').subscribe();

      const req = httpMock.expectOne('/api/users/123');
      expect(req.request.method).toBe('DELETE');
      req.flush(null);
    });
  });
});
```

## Testing de Servicio con Estado (Signals)

```typescript
// cart.service.spec.ts
describe('CartService', () => {
  let service: CartService;

  beforeEach(() => {
    TestBed.configureTestingModule({});
    service = TestBed.inject(CartService);
  });

  describe('estado inicial', () => {
    it('debe iniciar con carrito vacío', () => {
      expect(service.items()).toEqual([]);
      expect(service.total()).toBe(0);
      expect(service.itemCount()).toBe(0);
    });
  });

  describe('addItem', () => {
    it('debe agregar item al carrito', () => {
      const item = { id: '1', name: 'Product', price: 100 };
      
      service.addItem(item);
      
      expect(service.items()).toContainEqual({ ...item, quantity: 1 });
      expect(service.itemCount()).toBe(1);
    });

    it('debe incrementar cantidad si item ya existe', () => {
      const item = { id: '1', name: 'Product', price: 100 };
      
      service.addItem(item);
      service.addItem(item);
      
      expect(service.items()[0].quantity).toBe(2);
      expect(service.itemCount()).toBe(2);
    });
  });

  describe('total', () => {
    it('debe calcular total correctamente', () => {
      service.addItem({ id: '1', name: 'A', price: 100 });
      service.addItem({ id: '2', name: 'B', price: 50 });
      service.addItem({ id: '1', name: 'A', price: 100 }); // +1 cantidad
      
      // 100*2 + 50*1 = 250
      expect(service.total()).toBe(250);
    });
  });

  describe('removeItem', () => {
    it('debe remover item del carrito', () => {
      service.addItem({ id: '1', name: 'A', price: 100 });
      service.addItem({ id: '2', name: 'B', price: 50 });
      
      service.removeItem('1');
      
      expect(service.items().length).toBe(1);
      expect(service.items()[0].id).toBe('2');
    });
  });

  describe('clearCart', () => {
    it('debe vaciar el carrito', () => {
      service.addItem({ id: '1', name: 'A', price: 100 });
      service.addItem({ id: '2', name: 'B', price: 50 });
      
      service.clearCart();
      
      expect(service.items()).toEqual([]);
      expect(service.total()).toBe(0);
    });
  });
});
```

## Testing de Servicio con Dependencias

```typescript
// order.service.spec.ts
describe('OrderService', () => {
  let service: OrderService;
  let cartServiceSpy: { items: ReturnType<typeof vi.fn>, clearCart: ReturnType<typeof vi.fn> };
  let httpMock: HttpTestingController;

  beforeEach(() => {
    cartServiceSpy = {
      items: vi.fn().mockReturnValue([
        { id: '1', name: 'Product', price: 100, quantity: 2 }
      ]),
      clearCart: vi.fn()
    };

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: CartService, useValue: cartServiceSpy }
      ]
    });

    service = TestBed.inject(OrderService);
    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    httpMock.verify();
  });

  it('debe crear orden con items del carrito', () => {
    service.placeOrder({ address: '123 Main St' }).subscribe(order => {
      expect(order.id).toBeDefined();
    });

    const req = httpMock.expectOne('/api/orders');
    expect(req.request.body.items).toEqual(cartServiceSpy.items());
    expect(req.request.body.address).toBe('123 Main St');
    
    req.flush({ id: 'order-123', status: 'created' });
  });

  it('debe limpiar carrito después de orden exitosa', () => {
    service.placeOrder({ address: '123 Main St' }).subscribe();

    const req = httpMock.expectOne('/api/orders');
    req.flush({ id: 'order-123' });

    expect(cartServiceSpy.clearCart).toHaveBeenCalled();
  });
});
```

## Testing de Observables con Marble Testing

```typescript
// search.service.spec.ts
import { TestScheduler } from 'rxjs/testing';

describe('SearchService', () => {
  let scheduler: TestScheduler;

  beforeEach(() => {
    scheduler = new TestScheduler((actual, expected) => {
      expect(actual).toEqual(expected);
    });
  });

  it('debe debounce búsquedas', () => {
    scheduler.run(({ cold, expectObservable }) => {
      const service = TestBed.inject(SearchService);
      
      // Simular inputs rápidos
      const input$ = cold('a-b-c---|', { a: 'a', b: 'ab', c: 'abc' });
      
      // Solo el último valor después de 300ms debounce
      const expected = '------c-|';
      
      expectObservable(service.search(input$)).toBe(expected, { c: 'abc' });
    });
  });
});
```

## Patrón de Test para Servicios

```typescript
describe('MyService', () => {
  // 1. Setup
  let service: MyService;
  
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [/* dependencias */]
    });
    service = TestBed.inject(MyService);
  });

  // 2. Tests agrupados por método/funcionalidad
  describe('methodName', () => {
    it('debe [comportamiento esperado] cuando [condición]', () => {
      // Arrange
      const input = { /* ... */ };
      
      // Act
      const result = service.methodName(input);
      
      // Assert
      expect(result).toBe(/* expected */);
    });
  });

  // 3. Cleanup si es necesario
  afterEach(() => {
    httpMock?.verify();
  });
});
```

## Auto-fix

El agente debe:
1. Usar `provideHttpClientTesting()` para tests HTTP
2. Llamar `httpMock.verify()` en `afterEach`
3. Testear casos de éxito y error
4. Mockear dependencias con `vi.fn()`
5. Verificar método HTTP, URL y body de requests
