import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  effect,
  input,
  output,
  viewChild,
} from '@angular/core';
import { Subtema } from './tema';

@Component({
  selector: 'cnt-panel',
  templateUrl: './panel.html',
  styleUrl: './panel.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Panel {
  readonly subtema = input.required<Subtema>();
  readonly posicion = input.required<number>();
  readonly total = input.required<number>();
  readonly anterior = output<void>();
  readonly siguiente = output<void>();

  private readonly scroller = viewChild.required<ElementRef<HTMLElement>>('scroller');

  constructor() {
    // Al cambiar de subtema, el panel vuelve arriba.
    effect(() => {
      this.subtema();
      this.scroller().nativeElement.scrollTop = 0;
    });
  }
}
