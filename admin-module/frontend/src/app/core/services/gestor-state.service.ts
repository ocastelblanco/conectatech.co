import { Injectable, signal } from '@angular/core';

export interface GestorOrg {
  id: number;
  name: string;
  moodle_category_id: number;
  courses: { id: number; fullname: string; shortname: string }[];
}

@Injectable({ providedIn: 'root' })
export class GestorStateService {
  readonly org = signal<GestorOrg | null>(null);

  setOrg(data: GestorOrg): void {
    this.org.set(data);
  }

  clear(): void {
    this.org.set(null);
  }
}
