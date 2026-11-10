import { HttpErrorResponse, httpResource } from '@angular/common/http';
import { Injectable, computed } from '@angular/core';
import { leerParametros } from './parametros';

export interface Subtema {
  titulo: string;
  /** HTML de cada bloque (label de Moodle). Se inserta con el sanitizador por defecto de Angular. */
  bloques: string[];
}

export interface Tema {
  titulo: string;
  /** Referente bíblico del tema, o null. */
  cabecera: string | null;
  subtemas: Subtema[];
}

/** Valida la forma de la respuesta del endpoint; lanza si no coincide con el contrato. */
export function validarTema(raw: unknown): Tema {
  const r = raw as Record<string, unknown> | null;
  if (!r || typeof r['titulo'] !== 'string' || !Array.isArray(r['subtemas'])) {
    throw new Error('Respuesta del servidor con formato inesperado.');
  }
  const subtemas = r['subtemas'].map((s: unknown): Subtema => {
    const st = s as Record<string, unknown> | null;
    if (
      !st ||
      typeof st['titulo'] !== 'string' ||
      !Array.isArray(st['bloques']) ||
      !st['bloques'].every((b) => typeof b === 'string')
    ) {
      throw new Error('Subtema con formato inesperado.');
    }
    return { titulo: st['titulo'], bloques: st['bloques'] as string[] };
  });
  const cabecera = typeof r['cabecera'] === 'string' && r['cabecera'] !== '' ? r['cabecera'] : null;
  return { titulo: r['titulo'], cabecera, subtemas };
}

/** Mensaje en español para un error de carga. */
export function mensajeDeError(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    switch (error.status) {
      case 401:
        return 'Tu sesión expiró. Vuelve a iniciar sesión en la plataforma e inténtalo de nuevo.';
      case 403:
        return 'No tienes permiso para ver este contenido.';
      case 404:
        return 'No encontramos este tema. Puede que haya sido eliminado o movido.';
      case 0:
        return 'No hay conexión con la plataforma. Revisa tu internet e inténtalo de nuevo.';
    }
    return 'La plataforma no pudo entregar el contenido. Inténtalo de nuevo en unos minutos.';
  }
  return 'No pudimos leer el contenido de este tema.';
}

@Injectable({ providedIn: 'root' })
export class TemaService {
  readonly parametros = leerParametros(globalThis.location?.search ?? '');

  private readonly url = computed(() => {
    const { courseId, sectionId, demo } = this.parametros;
    if (demo) return 'demo/tema.json';
    if (courseId === null || sectionId === null) return undefined;
    return `/admin-api/visor/tema?course=${courseId}&section=${sectionId}`;
  });

  /** Falta course o section: no hay nada que cargar. */
  readonly sinParametros = this.url() === undefined;

  readonly tema = httpResource<Tema>(() => this.url(), { parse: validarTema });
}
