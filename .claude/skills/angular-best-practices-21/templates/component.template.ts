/**
 * Template de Componente Angular 21+
 * 
 * Uso: Copiar y adaptar para nuevos componentes
 * Reemplazar: {{ComponentName}}, {{selector}}, {{feature}}
 */
import { 
  Component, 
  ChangeDetectionStrategy,
  input,
  output,
  computed,
  signal,
  inject,
  OnInit,
  OnDestroy
} from '@angular/core';

@Component({
  selector: 'app-{{selector}}',
  standalone: true,
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    // Importar pipes/directivas específicas, no CommonModule
    // NgIf, NgFor, AsyncPipe, DatePipe, etc.
  ],
  template: `
    <!-- Template inline para componentes pequeños -->
    <div class="{{selector}}-container">
      @if (isLoading()) {
        <app-spinner />
      } @else {
        <h2>{{ title() }}</h2>
        <!-- Contenido del componente -->
      }
    </div>
  `,
  // O usar templateUrl para templates extensos:
  // templateUrl: './{{selector}}.component.html',
  styles: [`
    .{{selector}}-container {
      /* Estilos del componente */
    }
  `]
  // O usar styleUrl:
  // styleUrl: './{{selector}}.component.scss'
})
export class {{ComponentName}}Component implements OnInit, OnDestroy {
  // ============================================
  // 1. INYECCIÓN DE DEPENDENCIAS
  // ============================================
  private readonly someService = inject(SomeService);

  // ============================================
  // 2. INPUTS (señales de entrada)
  // ============================================
  /** Input requerido - debe ser proporcionado */
  readonly id = input.required<string>();
  
  /** Input opcional con valor por defecto */
  readonly showHeader = input<boolean>(true);
  
  /** Input con alias para binding en template */
  readonly data = input<DataType | null>(null, { alias: 'itemData' });

  // ============================================
  // 3. OUTPUTS (eventos)
  // ============================================
  /** Emitido cuando se guarda exitosamente */
  readonly saved = output<DataType>();
  
  /** Emitido cuando hay un error */
  readonly error = output<Error>();

  // ============================================
  // 4. VIEW QUERIES
  // ============================================
  // readonly contentRef = viewChild<ElementRef>('content');
  // readonly items = viewChildren(ItemComponent);

  // ============================================
  // 5. ESTADO INTERNO (signals)
  // ============================================
  protected readonly isLoading = signal(false);
  protected readonly errorMessage = signal<string | null>(null);
  
  /** Estado privado - no accesible desde template */
  private readonly internalState = signal<InternalState>({ /* ... */ });

  // ============================================
  // 6. COMPUTED (valores derivados)
  // ============================================
  /** Título derivado del input */
  protected readonly title = computed(() => {
    const data = this.data();
    return data?.title ?? 'Sin título';
  });

  /** Validación derivada */
  protected readonly isValid = computed(() => {
    return this.id().length > 0 && !this.isLoading();
  });

  // ============================================
  // 7. LIFECYCLE HOOKS
  // ============================================
  ngOnInit(): void {
    this.initializeComponent();
  }

  ngOnDestroy(): void {
    this.cleanup();
  }

  // ============================================
  // 8. MÉTODOS PÚBLICOS (API del componente)
  // ============================================
  /** Recarga los datos */
  refresh(): void {
    this.loadData();
  }

  // ============================================
  // 9. EVENT HANDLERS (protected - usados en template)
  // ============================================
  protected onSave(): void {
    if (!this.isValid()) return;
    
    this.isLoading.set(true);
    // Lógica de guardado...
    this.saved.emit(this.data()!);
  }

  protected onCancel(): void {
    this.errorMessage.set(null);
  }

  // ============================================
  // 10. MÉTODOS PRIVADOS
  // ============================================
  private initializeComponent(): void {
    this.loadData();
  }

  private loadData(): void {
    this.isLoading.set(true);
    this.someService.getData(this.id()).subscribe({
      next: (data) => {
        // Actualizar estado
        this.isLoading.set(false);
      },
      error: (err) => {
        this.errorMessage.set(err.message);
        this.isLoading.set(false);
        this.error.emit(err);
      }
    });
  }

  private cleanup(): void {
    // Limpieza de recursos
  }
}

// ============================================
// TIPOS LOCALES (si son específicos del componente)
// ============================================
interface InternalState {
  // Definir estado interno
}

interface DataType {
  id: string;
  title: string;
  // Definir estructura de datos
}
