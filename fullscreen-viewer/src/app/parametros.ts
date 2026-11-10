/** Parámetros de la URL del visor. Todo lo que llega por la URL se trata como no confiable. */
export interface Parametros {
  courseId: number | null;
  sectionId: number | null;
  /** Clases CSS de proyecto/área/grado que se aplican al <body> para heredar los estilos del curso. */
  clases: string[];
  /** Datos de ejemplo locales (solo existen en el build de desarrollo). */
  demo: boolean;
}

const ENTERO_POSITIVO = /^[1-9]\d{0,9}$/;
const CLASE_VALIDA = /^[a-z0-9-]{1,40}$/;
const MAX_CLASES = 12;

function entero(valor: string | null): number | null {
  return valor !== null && ENTERO_POSITIVO.test(valor) ? Number(valor) : null;
}

export function leerParametros(search: string): Parametros {
  const q = new URLSearchParams(search);
  const clases = (q.get('clases') ?? '')
    .split(/[\s+,]+/)
    .filter((c) => CLASE_VALIDA.test(c))
    .slice(0, MAX_CLASES);

  return {
    courseId: entero(q.get('course')),
    sectionId: entero(q.get('section')),
    clases,
    demo: q.get('demo') === '1',
  };
}
