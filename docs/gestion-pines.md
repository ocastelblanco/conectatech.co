# Sistema de gestión de pines

## Objetivo

Añadir un sistema de gestión de creación y matriculación de usuarios en la plataforma Moodle de ConectaTech a partir de un sistema de pines.

## Flujo básico

ConectaTech genera una serie de pines con una vigencia determinada, los asigna a una organización creada en el sistema de administración y un gestor de dicha organización los activa y asigna a docentes y estudiantes, organizados en grupos.

## Roles del sistema

### Administrador ConectaTech

El rol ya existente, que tiene acceso a la [aplicación de administración actual](https://admin.conectatech.co).

En la aplicación de administración, este rol podrá:

- Gestionar organizaciones (ver **Organización** en [Glosario](#glosario))
	- Crear
	- Renombrar
	- Eliminar
	- Asignar los cursos de una subcategoría (una categoría dentro de la categoría **COLEGIOS**) a una organización
- Gestionar gestores (ver [Gestor](#gestor))
	- Crear un pin de gestor en una organización
	- Eliminar
- Gestionar pines (ver **Pin** en [Glosario](#glosario))
	- Crear paquetes de pines
	- Definir la vigencia de un paquete de pines creado
	- Definir el tipo de rol `profesor` que el gestor puede asignar a los pines:
		- Rol `profesor` con permiso de edición
		- Rol `profesor sin permiso de edición`
	- Asignar paquetes de pines a una organización
- Generar reportes de uso de pines

### Gestor

Un funcionario de una organización que puede gestionar los pines que le han sido asignados a dicha organización.

En la aplicación de administración, este rol podrá:
- A partir del pin que el administrador le entregue, crear un usuario gestor en el sistema con
	- Nombres
	- Apellidos
	- Email
	- Usuario
	- Contraseña
- Crear grupos (ver **Grupo** en [Glosario](#glosario))
- Gestionar pines disponibles en la organización
	- Activar
	- Asignarles un rol (profesor o estudiante)
	- Asignarles un grupo
	- Asignarles un curso
- Descargar listados de pines con asignación, curso y grupo

En Moodle el **Gestor** tiene acceso a todos los cursos de la categoría asignada a la organización, pero sin permisos de edición.

### Profesor

En la aplicación de administración, este rol podrá:
- Si no ha sido creado anteriormente, podrá crear un usuario `profesor`en el sistema (y, por lo tanto, en Moodle) con
	- Nombres
	- Apellidos
	- Email
	- Usuario
	- Contraseña
- Si ya existe en el sistema, luego de autenticarse con su usuario y contraseña, podrá activar su pin y el sistema
	- Creará el grupo del pin (si no existe) en el curso del pin
	- Matriculará al usuario como `profesor` o `profesor sin permisos de edición` en el curso asignado, con acceso únicamente al grupo, con la vigencia del pin

### Estudiante

En la aplicación de administración, este rol podrá:
- Si no ha sido creado anteriormente, podrá crear un usuario `estudiante`en el sistema (y, por lo tanto, en Moodle) con
	- Nombres
	- Apellidos
	- Email
	- Usuario
	- Contraseña
- Si ya existe en el sistema, luego de autenticarse con su usuario y contraseña, podrá activar su pin y el sistema
	- Creará el grupo del pin (si no existe) en el curso del pin
	- Matriculará al usuario como `estudiante` en el curso asignado, con acceso únicamente al grupo, con la vigencia del pin

## Flujos básicos

### Creación de organización

1. Usuario **administrador** crea una **organización**, asignándole una subcategoría dentro de la categoría **COLEGIOS**.
2. Usuario **administrador** genera un pin de gestor.
3. Usuario **gestor** ingresa el pin y crea su usuario con sus datos personales.

### Asignación de pines a organización

1. Usuario **administrador** crea un paquete de pines.
2. Usuario **administrador** define la vigencia de los pines creados.
3. Usuario **administrador** define el tipo de rol `profesor`de los pines creados.
4. Usuario **administrador** asigna paquete de pines a **organización**.

### Asignación de pines a usuarios

1. Usuario **gestor** selecciona un conjunto de pines que le han sido asignados a la **organización** y que aún no han sido activados.
2. Usuario **gestor** asigna los pines seleccionados a un **grupo**; si el grupo no existe, lo puede crear.
3. Usuario **gestor** asigna los pines seleccionados a un curso, dentro de los cursos disponibles en la **organización**.
4. Usuario **gestor** determina el rol (`profesor`o `estudiante`) que tendrá cada pin.
5. Usuario **gestor** activa los pines seleccionados.
6. Usuario **gestor** descarga listado de pines en Excel con
	- Código _hash_
	- Rol
	- Vigencia
	- Grupo
	- Curso

## Glosario

- **Organización:** una institución educativa o una empresa intermediaria, que le compra a ConectaTech un número determinado de pines, con una vigencia específica.
- **Pin:** un 'cupo' o 'silla' dentro de un curso de Moodle, asignado a un grupo, representado por un _hash_ de 32 caracteres, con una vigencia específica que, al ser activado por el usuario final (profesor o estudiante), determina la fecha de ingreso y salida de dicho usuario del curso.
- **Grupo:** una categoría dentro de la aplicación de administración que, al momento de matricular usuarios, se convertirá en un grupo de Moodle dentro del curso destino.

## Aclaraciones

1. El pin de gestor crea el usuario en Moodle, y luego la app verifica en sus propias tablas si ese usuario Moodle es gestor de alguna organización
2. El gestor, al autenticarse en admin.conectatech.co, ve solo su sección de gestión de pines (sin acceso al resto del panel de  administración)
3. Se necesita una ruta pública dentro de `admin.conectatech.co` para el proceso de activación.
4. El administrador determina una fecha de expiración de los pines al crearlos en paquete.
5. Todos los pines del paquete tienen el mismo tipo de rol para profesor; el gestor no puede cambiar ese tipo de rol.
6. Los pines pueden ser reutilizados solo después de que hayan vencido.
7. Se usarán tablas propias en la BD de Moodle, pero con prefijo `ct_` (de **ConectaTech**).
8. Los grupos que el gestor crea en la app son concepto propio de la app que luego se convierten en grupos de Moodle al matricular.
9. Los shortnames de los roles de profesor, ya existen en la instancia de Moodle, así:
	- **Profesor:** `editingteacher`
	- **Profesor sin permiso de edición:** `teacher`
10. El gestor necesita ver los cursos de su categoría en Moodle.
