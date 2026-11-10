import { DOCUMENT } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { Indice } from './indice';
import { Panel } from './panel';
import { TemaService, mensajeDeError } from './tema';

/** Mensaje que el lanzador de Moodle escucha para cerrar el overlay (mismo origen). */
const MENSAJE_CERRAR = 'ct-visor:cerrar';

@Component({
  selector: 'cnt-root',
  imports: [Indice, Panel],
  templateUrl: './app.html',
  styleUrl: './app.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    '(window:keydown)': 'alPulsarTecla($event)',
  },
})
export class App {
  private readonly servicio = inject(TemaService);
  private readonly documento = inject(DOCUMENT);

  protected readonly recurso = this.servicio.tema;
  protected readonly sinParametros = this.servicio.sinParametros;
  protected readonly tema = computed(() => (this.recurso.hasValue() ? this.recurso.value() : null));
  protected readonly error = computed(() =>
    this.sinParametros
      ? 'Falta indicar el curso y el tema que se quiere mostrar.'
      : mensajeDeError(this.recurso.error()),
  );

  protected readonly actual = signal(0);
  protected readonly indiceAbierto = signal(
    !globalThis.matchMedia?.('(max-width: 767.98px)').matches,
  );
  protected readonly subtema = computed(() => this.tema()?.subtemas[this.actual()] ?? null);
  protected readonly total = computed(() => this.tema()?.subtemas.length ?? 0);

  /** Solo hay botón de cerrar cuando el visor corre dentro del overlay de Moodle. */
  protected readonly enOverlay = window.parent !== window;

  constructor() {
    // Hereda los estilos del curso: el SCSS de Moodle se ancla a clases del <body>.
    this.documento.body.classList.add(...this.servicio.parametros.clases);
  }

  protected ir(i: number): void {
    if (i < 0 || i >= this.total()) return;
    this.actual.set(i);
    // En pantallas estrechas el índice es un cajón: se cierra al elegir.
    if (globalThis.matchMedia?.('(max-width: 767.98px)').matches) this.indiceAbierto.set(false);
  }

  protected anterior(): void {
    this.ir(this.actual() - 1);
  }

  protected siguiente(): void {
    this.ir(this.actual() + 1);
  }

  protected alternarIndice(): void {
    this.indiceAbierto.update((v) => !v);
  }

  protected reintentar(): void {
    this.recurso.reload();
  }

  protected cerrar(): void {
    if (this.enOverlay) {
      window.parent.postMessage({ type: MENSAJE_CERRAR }, window.location.origin);
    }
  }

  protected alPulsarTecla(e: KeyboardEvent): void {
    if (e.altKey || e.ctrlKey || e.metaKey) return;
    switch (e.key) {
      case 'ArrowLeft':
        this.anterior();
        break;
      case 'ArrowRight':
        this.siguiente();
        break;
      case 'Escape':
        this.cerrar();
        break;
    }
  }
}
