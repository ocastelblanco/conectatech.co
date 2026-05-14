import { Component, ChangeDetectionStrategy } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

interface NavItem {
  label: string;
  icon: string;
  route: string;
}

@Component({
  selector: 'cnt-sidebar',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './sidebar.component.html',
  styleUrl: './sidebar.component.scss'
})
export class SidebarComponent {
  readonly navItems: NavItem[] = [
    { label: 'Dashboard', icon: 'pi pi-home', route: '/dashboard' },
    { label: 'Instituciones', icon: 'pi pi-graduation-cap', route: '/instituciones' },
    { label: 'Organizaciones', icon: 'pi pi-building', route: '/organizaciones' },
    { label: 'Pines', icon: 'pi pi-ticket', route: '/pines' },
    { label: 'Matrículas', icon: 'pi pi-users', route: '/matriculas' },
    { label: 'Árboles Curriculares', icon: 'pi pi-sitemap', route: '/arboles' },
    { label: 'Crear Contenido', icon: 'pi pi-file-edit', route: '/contenido' },
    { label: 'Activos CDN', icon: 'pi pi-folder-open', route: '/activos' },
  ];
}
