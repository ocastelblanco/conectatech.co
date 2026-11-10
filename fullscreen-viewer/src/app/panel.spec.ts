import { TestBed } from '@angular/core/testing';
import { Panel } from './panel';

describe('Panel', () => {
  it('muestra el título, los bloques y la posición', async () => {
    const fixture = TestBed.createComponent(Panel);
    fixture.componentRef.setInput('subtema', {
      titulo: 'Subtema 1',
      bloques: ['<p>uno</p>', '<p>dos</p>'],
    });
    fixture.componentRef.setInput('posicion', 0);
    fixture.componentRef.setInput('total', 3);
    await fixture.whenStable();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('.sectionname')?.textContent).toContain('Subtema 1');
    expect(el.querySelectorAll('.bloque').length).toBe(2);
    expect(el.querySelector('.contador')?.textContent).toContain('1 de 3');
    const [prev, next] = Array.from(el.querySelectorAll<HTMLButtonElement>('.paso'));
    expect(prev.disabled).toBe(true);
    expect(next.disabled).toBe(false);
  });

  it('sanitiza el HTML de los bloques', async () => {
    const fixture = TestBed.createComponent(Panel);
    fixture.componentRef.setInput('subtema', {
      titulo: 'S',
      bloques: [
        '<p class="ok">hola</p><script>window.__xss = 1</script><img src=x onerror="window.__xss = 1">',
      ],
    });
    fixture.componentRef.setInput('posicion', 0);
    fixture.componentRef.setInput('total', 1);
    await fixture.whenStable();

    const el: HTMLElement = fixture.nativeElement;
    expect(el.querySelector('.bloque p.ok')).not.toBeNull();
    expect(el.querySelector('.bloque script')).toBeNull();
    expect(el.querySelector('.bloque img')?.getAttribute('onerror')).toBeNull();
  });
});
