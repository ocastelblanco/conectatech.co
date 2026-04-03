import { Component, ChangeDetectionStrategy, inject, OnInit, signal } from '@angular/core';
import { ApiService } from '../../core/services/api.service';

@Component({
  selector: 'cnt-dashboard',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [],
  templateUrl: './dashboard.component.html',
})
export class DashboardComponent implements OnInit {
  private readonly api = inject(ApiService);

  readonly apiStatus = signal<'ok' | 'error' | 'checking'>('checking');

  ngOnInit(): void {
    this.api.ping().subscribe({
      next:  () => this.apiStatus.set('ok'),
      error: () => this.apiStatus.set('error'),
    });
  }
}
