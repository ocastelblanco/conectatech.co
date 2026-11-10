import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';
import { Subtema } from './tema';

@Component({
  selector: 'cnt-indice',
  templateUrl: './indice.html',
  styleUrl: './indice.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Indice {
  readonly subtemas = input.required<Subtema[]>();
  readonly actual = input.required<number>();
  readonly seleccionar = output<number>();
}
