import { leerParametros } from './parametros';

describe('leerParametros', () => {
  it('lee course y section válidos', () => {
    const p = leerParametros('?course=29&section=1405');
    expect(p.courseId).toBe(29);
    expect(p.sectionId).toBe(1405);
  });

  it('rechaza valores que no son enteros positivos', () => {
    for (const malo of ['0', '-3', '1.5', 'abc', '1e3', '12345678901', '']) {
      expect(leerParametros(`?course=${malo}&section=${malo}`).courseId).toBeNull();
    }
    expect(leerParametros('').sectionId).toBeNull();
  });

  it('separa las clases por +, espacio o coma y descarta las inválidas', () => {
    const p = leerParametros('?clases=cosmovision-cristiana+ciencias-naturales+grado-6');
    expect(p.clases).toEqual(['cosmovision-cristiana', 'ciencias-naturales', 'grado-6']);

    const sucio = leerParametros('?clases=ok,<script>,Mayus,a_b,otra-ok');
    expect(sucio.clases).toEqual(['ok', 'otra-ok']);
  });

  it('limita la cantidad de clases', () => {
    const muchas = Array.from({ length: 30 }, (_, i) => `c${i}`).join('+');
    expect(leerParametros(`?clases=${muchas}`).clases.length).toBe(12);
  });

  it('demo solo con demo=1', () => {
    expect(leerParametros('?demo=1').demo).toBe(true);
    expect(leerParametros('?demo=true').demo).toBe(false);
  });
});
