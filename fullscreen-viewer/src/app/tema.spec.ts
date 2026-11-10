import { HttpErrorResponse } from '@angular/common/http';
import { mensajeDeError, validarTema } from './tema';

describe('validarTema', () => {
  it('acepta el contrato del endpoint y ignora ok', () => {
    const t = validarTema({
      ok: true,
      titulo: 'Tema',
      cabecera: '<div>x</div>',
      subtemas: [{ titulo: 'A', bloques: ['<p>1</p>'] }],
    });
    expect(t.titulo).toBe('Tema');
    expect(t.cabecera).toBe('<div>x</div>');
    expect(t.subtemas[0].bloques).toEqual(['<p>1</p>']);
  });

  it('normaliza cabecera ausente o vacía a null', () => {
    expect(validarTema({ titulo: 'T', cabecera: null, subtemas: [] }).cabecera).toBeNull();
    expect(validarTema({ titulo: 'T', cabecera: '', subtemas: [] }).cabecera).toBeNull();
  });

  it('rechaza formas inesperadas', () => {
    expect(() => validarTema(null)).toThrow();
    expect(() => validarTema({ titulo: 1, subtemas: [] })).toThrow();
    expect(() => validarTema({ titulo: 'T', subtemas: [{ titulo: 'A', bloques: [1] }] })).toThrow();
  });
});

describe('mensajeDeError', () => {
  it('distingue sesión, permiso y no encontrado', () => {
    const e = (status: number) => new HttpErrorResponse({ status });
    expect(mensajeDeError(e(401))).toContain('sesión');
    expect(mensajeDeError(e(403))).toContain('permiso');
    expect(mensajeDeError(e(404))).toContain('No encontramos');
    expect(mensajeDeError(e(0))).toContain('conexión');
    expect(mensajeDeError(new Error('x'))).toContain('No pudimos');
  });
});
