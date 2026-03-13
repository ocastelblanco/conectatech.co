import { Component, OnInit, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { NgxExtendedPdfViewerModule } from 'ngx-extended-pdf-viewer';
import { CommonModule } from '@angular/common';

const INDEX_URL = 'https://assets.conectatech.co/recursos/pdf/index.json';

interface PdfEntry {
  id: string;
  url: string;
  [key: string]: unknown;
}

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [NgxExtendedPdfViewerModule, CommonModule],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App implements OnInit {
  pdfUrl      = signal<string | null>(null);
  error       = signal<string | null>(null);
  loading     = signal(true);
  minPage     = signal<number | undefined>(undefined);
  maxPage     = signal<number | undefined>(undefined);
  currentPage = signal(1);

  constructor(private http: HttpClient) {}

  ngOnInit() {
    const params = new URLSearchParams(window.location.search);
    const id     = params.get('id');
    const start  = params.get('start') ? Number(params.get('start')) : undefined;
    const end    = params.get('end')   ? Number(params.get('end'))   : undefined;

    if (!id) {
      this.error.set('No se especificó el parámetro id.');
      this.loading.set(false);
      return;
    }

    if (start !== undefined && !isNaN(start)) { this.minPage.set(start); this.currentPage.set(start); }
    if (end   !== undefined && !isNaN(end))   { this.maxPage.set(end); }

    this.http.get<PdfEntry[]>(INDEX_URL).subscribe({
      next: (items) => {
        const item = items.find(i => i.id === id);
        if (!item) {
          this.error.set(`PDF con id "${id}" no encontrado.`);
        } else {
          this.pdfUrl.set(item.url);
        }
        this.loading.set(false);
      },
      error: () => {
        this.error.set('No se pudo cargar el índice de PDFs.');
        this.loading.set(false);
      }
    });
  }

  onPageChange(page: number) {
    this.currentPage.set(page);
  }

  prevPage() {
    const min = this.minPage() ?? 1;
    const next = Math.max(min, this.currentPage() - 2);
    this.currentPage.set(next);
  }

  nextPage() {
    const max = this.maxPage();
    const next = this.currentPage() + 2;
    this.currentPage.set(max !== undefined ? Math.min(max, next) : next);
  }
}
