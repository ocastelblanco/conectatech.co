# Mini manual de imagen visual corporativo

## Logotipo: Anatomía y Uso

El logo se compone de un **imagotipo** (símbolo + texto). El símbolo representa la convergencia entre la tecnología y el crecimiento humano.

### Variantes de Marca

- **Principal:** Isotipo a la izquierda, nombre a la derecha. Es la versión para encabezados de Moodle y papelería.
- **Vertical/Centrada:** Isotipo sobre el nombre. Ideal para perfiles de redes sociales y portadas de presentaciones.
- **Versión Negativa:** Logo totalmente blanco para usar sobre fondos azul oscuro o imágenes complejas.
- **Versión Monocromática:** En **Azul Medianoche** para usar sobre fondos claros o imágenes complejas claras.
- **Isotipo:** A color, totalmente blanco o azul medianoche, para uso como _favicon_, avatares o imágenes de pequeña resolución; los colores dependerán del fondo donde se use.


### Usos Incorrectos

- No alterar las proporciones (no estirar ni comprimir).
- No cambiar la disposición de los colores del isotipo.
- No usar fondos que dificulten la lectura (usar las variantes de color para maximizar la legibilidad).

## Sistema Cromático

La paleta técnica equilibra la seriedad tecnológica con la calidez educativa.

| Color | HEX | Uso Sugerido |
|---|---|---|
| Azul Medianoche | #1D2B36 | Textos principales, fondos de sidebar en Moodle, logotipos. |
| Azul Tech | #4A90E2 | Enlaces, iconos de navegación, acentos de marca. |
| Verde Crecimiento | #52A467 | Éxito, botones de "completado", elementos orgánicos. |
| Naranja Coral | #FF7F50 | Call to Action (CTA), botones de "Crear Curso", alertas. |

## Tipografía Corporativa

Para asegurar la coherencia en plataformas digitales y materiales impresos, se usarán las siguientes fuentes de Google Fonts (gratuitas y de alto rendimiento).

**Logotipo:** **Fredoka**. Se usa únicamente para el logotipo.
**Principal (Títulos):** **Montserrat** (Bold/SemiBold). Es geométrica y moderna, se debe usar en encabezados de Moodle y portadas.
**Secundaria (Cuerpo):** **Inter**. Diseñada específicamente para pantallas. Es la que mejor funciona para los contenidos educativos y el texto de lectura larga en la plataforma.

## Aplicación en Moodle y otras plataformas digitales

Dado que la aplicación de la marca se verá principalmente en plataformas educativas digitales, esta es la parte más crítica del manual.

### Configuración de Interfaz

- **Sidebar (Menú lateral):** Fondo en **Azul Medianoche** con iconos en blanco o **Azul Tech**.
- **Botones Principales:** Usar el **Naranja Coral** para el botón de "Acceder" o "Inscribirse". Esto genera un contraste alto y guía al profesor.
- **Tarjetas de Curso:** Bordes redondeados (border-radius: 8px) para mantener la estética "orgánica" de la marca.
- **Header:** Logo alineado a la izquierda con un alto máximo de 50px para evitar ruido visual en dispositivos móviles.

## Otras aplicaciones digitales e impresas

### E-mail & Redes Sociales
- **Firma de Email:** Nombre en **Azul Medianoche**, cargo en Gris, y el isotipo (el símbolo solo) como un detalle al final.
- **Redes Sociales:** Usar el Isotipo dentro de un círculo blanco o **Azul Medianoche** como imagen de perfil para asegurar reconocimiento instantáneo.

### Tarjetas de Negocio (Impreso)
- **Frente:** Fondo blanco limpio, logo centrado.
- **Vuelta:** Fondo **Azul Medianoche**, datos de contacto en blanco y un patrón sutil basado en los nodos del logo en una esquina.

## Guía de Estilos CSS técnica

Los códigos CSS generados se deben poder inyectar directamente en el área de "CSS personalizado" del tema de Moodle.

El código utiliza variables de CSS (:root) para facilirar la edición de los colores.

### Configuración de Variables y Tipografía

Se define la identidad cromática; algunos temas de Moodle no permiten la importación de estilos externos, como las fuentes de Google.

```css
/* Importación de tipografías corporativas si lo permite el tema */
/* @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@600;700&family=Inter:wght@400;500&display=swap');*/

:root {
    /* Paleta Primaria */
    --conecta-midnight: #1D2B36; /* Azul Medianoche - Profesionalismo */
    --conecta-tech-blue: #4A90E2; /* Azul Tech - Confianza */
    --conecta-growth-green: #52A467; /* Verde - Crecimiento */
    --conecta-coral: #FF7F50;    /* Coral - Acción y Calidez */
    
    /* Tipografía */
    --font-headings: 'Montserrat', sans-serif;
    --font-body: 'Inter', sans-serif;
}
```

### Estilos Globales para Moodle

Estos estilos aseguran que la plataforma respire la identidad de "mentor experto" y cercano.

```css
body {
    font-family: var(--font-body);
    color: var(--conecta-midnight);
    line-height: 1.6;
}

h1, h2, h3, h4, h5, h6 {
    font-family: var(--font-headings);
    font-weight: 700;
    color: var(--conecta-midnight);
}

/* Enlaces con el azul tecnológico */
a {
    color: var(--conecta-tech-blue);
    transition: color 0.3s ease;
}

a:hover {
    color: var(--conecta-coral);
    text-decoration: none;
}
```

### Componentes Específicos (UI/UX)
Para que el tema o framework se alinee con la marca, se aplican estos "overrides":

#### Botones y Llamados a la Acción (CTA)

El **Naranja Coral** es el color de acento para que el profesor sepa exactamente dónde interactuar.

```css
.btn-primary, 
#id_submitbutton, 
.login-container .btn-primary {
    background-color: var(--conecta-coral) !important;
    border-color: var(--conecta-coral) !important;
    border-radius: 8px; /* Bordes orgánicos */
    font-family: var(--font-headings);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.btn-primary:hover {
    background-color: var(--conecta-midnight) !important;
    border-color: var(--conecta-midnight) !important;
}
```

#### Barra Lateral (Sidebar) y Navegación

Se usa el **Azul Medianoche** para dar una estructura sólida y seria.

```css
#nav-drawer, 
.drawer {
    background-color: var(--conecta-midnight) !important;
    color: #ffffff;
}

#nav-drawer .list-group-item {
    background-color: transparent;
    color: #e0e0e0;
    border: none;
}

#nav-drawer .list-group-item.active {
    background-color: var(--conecta-tech-blue) !important;
    color: #ffffff !important;
}
```

#### Tarjetas de Curso (Dashboard)

Se fortalece la "conexión orgánica" con sombras suaves y bordes redondeados.

```css
.card.coursebox {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(29, 43, 54, 0.08); /* Sombra sutil en Azul Medianoche */
    transition: transform 0.3s ease;
}

.card.coursebox:hover {
    transform: translateY(-5px);
    border-left: 5px solid var(--conecta-growth-green); /* Acento de crecimiento */
}
```

### Notas de Implementación Tecnológica

- **Legibilidad:** Se prioriza la fuente **Inter** para el cuerpo de texto, ya que la audiencia son profesores que pasan mucho tiempo leyendo en pantalla.
- **Flexibilidad:** El uso de `!important` es necesario en Moodle para sobrescribir los estilos nativos de los temas sin tener que editar los archivos PHP del servidor.
- **Contraste:** Los colores seleccionados cumplen con las normas de accesibilidad para que directivos y docentes vean el contenido con claridad. Se debe garantizar que todos los elementos cumplan con dichas normas.
