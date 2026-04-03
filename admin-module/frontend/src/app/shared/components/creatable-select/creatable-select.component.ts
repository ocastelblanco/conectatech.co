import {
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
} from '@angular/core';
import { FormsModule } from '@angular/forms';
import { AutoCompleteModule } from 'primeng/autocomplete';

/**
 * CreatableSelectComponent — select que permite escoger un valor existente
 * o escribir uno nuevo (creatable select / combobox).
 *
 * Uso:
 *   <cnt-creatable-select
 *     [options]="['valor-a', 'valor-b']"
 *     [value]="campo"
 *     placeholder="Seleccionar o escribir..."
 *     (valueChange)="campo = $event" />
 */
@Component({
  selector: 'cnt-creatable-select',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [AutoCompleteModule, FormsModule],
  template: `
    <p-autocomplete
      [ngModel]="value()"
      (ngModelChange)="onModelChange($event)"
      [suggestions]="filtered()"
      (completeMethod)="search($event)"
      [dropdown]="true"
      [forceSelection]="false"
      [placeholder]="placeholder()"
      class="w-full"
      inputclass="w-full text-sm"
    />
  `,
})
export class CreatableSelectComponent {
  readonly options = input<string[]>([]);
  readonly value = input<string>('');
  readonly placeholder = input<string>('Seleccionar o escribir...');

  readonly valueChange = output<string>();

  readonly filtered = signal<string[]>([]);

  search(event: { query: string }): void {
    const q = event.query.trim().toLowerCase();
    this.filtered.set(
      q
        ? this.options().filter(o => o.toLowerCase().includes(q))
        : [...this.options()],
    );
  }

  onModelChange(val: string | null): void {
    this.valueChange.emit(val ?? '');
  }
}
