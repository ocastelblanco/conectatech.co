# Árbol curricular

## Definición

Un árbol curricular es la estructura compuesta por un grupo de cursos con sus contenidos (organizados como secciones de Moodle, que llamaremos **Temas**), categorizados en áreas curriculares (por ejemplo, Matemáticas, Ciencias Naturales, Lenguaje, Ciencias Sociales, etc.) y en grados o niveles (los grados de la educación básica y media colombiana, por ejemplo) que pertenecen a una misma institución educativa.

Por ejemplo, para el colegio ficticio **San Marino** se crearon 32 cursos, categorizados así:

- 4 áreas básicas
  - Matemáticas
  - Ciencias Naturales (Biología hasta 9º grado, Física y Química en 10º y 11º grado)
  - Lenguaje
  - Ciencias Sociales
- 8 grados o niveles:
  - 4º de básica primaria
  - 5º de básica primaria
  - 6º de básica secundaria
  - 7º de básica secundaria
  - 8º de básica secundaria
  - 9º de básica secundaria
  - 10º de educación media
  - 11º de educación media

La estructura compuesta por los 32 cursos, su categorización en cada área y en cada grado, y los contenidos de cada curso, conforman el árbol curricular del colegio **San Marino**.

## Flujos de generación de un árbol curricular

### Flujo de creación

1. Usuario crea un árbol curricular, como estructura vacía, seleccionando de un desplegable la categoría raíz de Moodle (generalmente, **COLEGIOS**).
2. Usuario define los metadatos del árbol:
   - Nombre largo: por ejemplo, `Cosmovisión Cristiana San Marino`.
   - Nombre corto: por ejemplo, `CCSM`.
   - Período escolar: por ejemplo, `2026-2027`.
   - Institución educativa: por ejemplo, `San Marino`.
3. Usuario crea un grado, definiendo:
   - Nombre largo: por ejemplo, `6º de básica secundaria`.
   - Nombre corto: por ejemplo, `6`.
4. Usuario crea un curso (un área curricular en un grado específico), definiendo:
   - Nombre largo: por ejemplo, `Ciencias Naturales`.
   - Nombre corto: por ejemplo, `CN`.
5. Usuario determina fecha de inicio y de finalización del curso.
6. Usuario selecciona un curso plantilla que se usará como _templatecourse_ al momento de la creación. Los cursos plantilla están organizados en la estructura de categorías de Moodle de la siguiente forma:
   - Categoría raíz: **PLANTILLAS** (no se muestra).
      - Proyecto: por ejemplo, **Cosmovisión Cristiana**.
         - Curso plantilla: por ejemplo, **Ciencias Naturales**.
7. Usuario arrastra y suelta temas (el _label_) de los cursos repositorios al curso creado; los puede ordenar y eliminar.
   ***NOTA IMPORTANTE:** Sistema debe desplegar los _labels_ de los temas de los repositorios en Moodle a partir de la organización en categorías:
      - Categoría raíz: **REPOSITORIOS** (no se muestra).
         - Proyecto: por ejemplo, **Cosmovisión Cristiana**.
            - Área curricular: por ejemplo, **Ciencias Naturales**.
               - Curso repositorio de grupo de cursos: por ejemplo, **Ciencias Naturales — Grados 6° y 7°** (temas de 6º y 7º grado).
8. Sistema, en todos los pasos, almacena la estructura del árbol curricular para, a partir de ella, crear los cursos en Moodle y poblarlos.

### Flujo de duplicación

1. Usuario selecciona un árbol curricular existente.
2. Usuario edita los metadatos del árbol destino a partir de los metadatos del árbol fuente:
   - Nombre largo (puede ser el mismo)
   - Nombre corto (puede ser el mismo)
   - Periodo escolar (tiene que ser diferente del curso fuente)
   - Institución educativa (puede ser la misma)
3. Sistema duplica el árbol curricular con los nuevos metadatos.

### Flujo de edición

1. Usuario selecciona un árbol existente y desde un desplegable elige la nueva categoría raíz.
2. Usuario edita los metadatos del árbol.
3. Usuario ordena los temas de los cursos existentes, los elimina o añade otros de los repositorios de temas.
4. Sistema actualiza la estructura del árbol.

## Flujo de creación y poblamiento de cursos a partir de un árbol curricular

1. Usuario selecciona un árbol curricular existente e inicia proceso de creación y poblamiento de cursos.
2. Sistema crea los cursos del árbol en Moodle siguiendo las siguientes reglas:
   - El nombre largo del curso está compuesto por el nombre largo del área curricular, un guión simple y el nombre corto del grado; por ejemplo, `Ciencias Naturales - 6`.
   - El nombre corto del curso está compuesto por el nombre corto del árbol, el periodo escolar, el nombre corto del área curricular y el nombre corto del grado, separados por un guión simple; por ejemplo, `CCSM-2006-2007-CN-6`.
   - Se usa el curso plantilla determinado como _templatecourse_ en el paso 6 de [Flujo de creación](#flujo-de-creación).
   - Se ubica en las categorías correspondientes en Moodle, de la siguiente forma:
      - Categoría raíz (primer nivel): la determinada en el paso 1 de [Flujo de creación](#flujo-de-creación), generalmente **COLEGIOS**.
      - Categoría institución educativa (segundo nivel): la determinada en el paso 2 de [Flujo de creación](#flujo-de-creación); por ejemplo, **San Marino**.
      - Categoría área curricular (tercer nivel): la determinada en el paso 4 de [Flujo de creación](#flujo-de-creación); por ejemplo, **Ciencias Naturales**.
   **NOTA IMPORTANTE:** si el nombre corto del curso ya existe en Moodle, previa validación del Usuario (un diálogo de advertencia), se elimina el curso existente y se crea el nuevo.
3. Sistema copia los temas (secciones) de los cursos repositorios al curso creado.
