# Ajuste de estructura

## Estructura básica markdown

Ahora los archivos markdown fuente, del repositorio **Cosmovisión cristiana**, tienen una estructura un poco diferente, pero más recurrente. Una unidad (lo que será una **sección** en Moodle) es de la siguiente forma:

```markdown
# Pellentesque finibus consequat dolor: at imperdiet purus venenatis ac

## Referente bíblico
*"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."*
**Lorem ipsum**

## At imperdiet purus venenatis ac

### Introducción motivadora

In efficitur metus eget interdum euismod. **Etiam lobortis aliquam** eros sed molestie. Aliquam euismod imperdiet auctor. Vivamus eu ultricies diam, et lobortis nisi. Nullam lectus lectus, viverra vel maximus eu, auctor at ligula. Suspendisse potenti. Nullam vel nibh venenatis, **vestibulum eros sed**, vestibulum felis.

Orci varius natoque *penatibus et magnis* dis parturient montes, nascetur ridiculus mus. Suspendisse molestie augue sed libero tristique auctor eget sit amet tortor. Ut sit amet diam dui. Fusce nunc tortor, **venenatis bibendum** nisl vitae, semper mollis nunc. In aliquet molestie sollicitudin. Integer sollicitudin odio et sem fringilla dictum.

### ¿Qué tiene que ver esto contigo?

Nulla facilisi. Donec pellentesque semper tincidunt. Nunc porta eu nunc vel bibendum. Phasellus imperdiet ipsum in erat aliquam, at bibendum tortor blandit.

### Objetivos de aprendizaje con sentido cristiano

Cras *posuere ipsum* viverra velit ultricies, ac lobortis dolor maximus:

* **Sed feugiat** tortor et turpis facilisis, vel consequat velit porttitor.
* **Nam nec lorem** sapien. Aenean lobortis accumsan ullamcorper.
* **Vivamus ullamcorper** feugiat purus eu placerat.

Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet **ligula pulvinar, imperdiet erat id, bibendum ex**.

## 1\. Maecenas congue lorem nisl: in scelerisque ex

### Referente bíblico

*"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."*
**Lorem ipsum**

### Reflexiona

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

### Ponte en acción

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

## 2\. Cras sed est venenatis: aliquam metus non

### Referente bíblico

*"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."*
**Lorem ipsum**

### Reflexiona

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

### Ponte en acción

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

## 3\. Aliquam neque lorem: finibus a eros at

### Referente bíblico

*"Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."*
**Lorem ipsum**

### Reflexiona

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

### Ponte en acción

Integer eget tincidunt mauris. **Suspendisse vehicula** hendrerit eros non interdum.

* Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.
* Maecenas mattis dolor at pharetra lacinia.
* Sed sagittis vestibulum laoreet.

## Reflexiona y aplica con sentido cristiano

### Piensa, responde y transforma [evaluacion]

#### ¿Aenean suscipit placerat lacus, ut malesuada ex commodo ut?

{tipo: ensayo, variante: texto}

#### ¿Integer quis finibus velit. Sed eget metus enim?

{tipo: ensayo, variante: texto}

#### ¿Praesent dui lorem, gravida et commodo imperdiet, aliquet sit amet dolor?

{tipo: ensayo, variante: texto}

### ¿Cómo puedes mostrar y compartir lo aprendido? [evaluacion]

#### Curabitur vitae tortor *"a nibh semper interdum"* id eget justo.

{tipo: ensayo, variante: adjunto}

#### Sed eu urna sed nisi porta commodo at id metus. Maecenas nec nisi quis erat gravida tincidunt ut sed quam.

{tipo: ensayo, variante: texto}

### Cierre espiritual

*“Curabitur metus quam, vehicula in tristique sed, suscipit ut lectus. Curabitur eget congue lacus. Proin mi turpis, laoreet dignissim commodo sagittis, facilisis at nisl. Suspendisse potenti.”*

```

## Estructura resultante en Moodle

El markdown anterior debe traducirse en la siguiente estructura:

* [SECCIÓN] Pellentesque finibus consequat dolor: at imperdiet purus venenatis ac
	* [AREA DE MEDIOS Y TEXTO] Referente bíblico
		```html
		<div class="resaltado cita-biblica">
		  <p><em>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."</em></p>
		  <p><strong>Lorem ipsum</strong></p>
		</div>
		```
	* [SUBSECCIÓN] At imperdiet purus venenatis ac
		* [AREA DE MEDIOS Y TEXTO] Introducción motivadora
			```html
			<div class="introduccion-motivadora>
			  <p>In efficitur metus eget interdum euismod. <strong>Etiam lobortis aliquam</strong> eros sed molestie. Aliquam euismod imperdiet auctor. Vivamus eu ultricies diam, et lobortis nisi. Nullam lectus lectus, viverra vel maximus eu, auctor at ligula. Suspendisse potenti. Nullam vel nibh venenatis, <strong>vestibulum eros sed</strong>, vestibulum felis.</p>
			  <p>Orci varius natoque <em>penatibus et magnis</em> dis parturient montes, nascetur ridiculus mus. Suspendisse molestie augue sed libero tristique auctor eget sit amet tortor. Ut sit amet diam dui. Fusce nunc tortor, <strong>venenatis bibendum</strong> nisl vitae, semper mollis nunc. In aliquet molestie sollicitudin. Integer sollicitudin odio et sem fringilla dictum.</p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] ¿Qué tiene que ver esto contigo?
			```html
			<div class="que-tiene-que-ver-esto-contigo">
				<h3>¿Qué tiene que ver esto contigo?</h3>
				<p>Nulla facilisi. Donec pellentesque semper tincidunt. Nunc porta eu nunc vel bibendum. Phasellus imperdiet ipsum in erat aliquam, at bibendum tortor blandit.</p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Objetivos de aprendizaje con sentido cristiano
			```html
			<div class="objetivos-de-aprendizaje-con-sentido-cristiano">
				<h3>Objetivos de aprendizaje con sentido cristiano</h3>
				<p>Cras <em>posuere ipsum</em> viverra velit ultricies, ac lobortis dolor maximus:</p>
				<ul>
					<li><strong>Sed feugiat</strong> tortor et turpis facilisis, vel consequat velit porttitor.</li>
					<li><strong>Nam nec lorem</strong> sapien. Aenean lobortis accumsan ullamcorper.</li>
					<li><strong>Vivamus ullamcorper</strong> feugiat purus eu placerat.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
	* [SUBSECCIÓN] Maecenas congue lorem nisl: in scelerisque ex
		* [AREA DE MEDIOS Y TEXTO] Referente bíblico
			```html
			<div class="resaltado cita-biblica">
			  <p><em>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."</em></p>
			  <p><strong>Lorem ipsum</strong></p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Reflexiona
			```html
			<div class="reflexiona">
				<h3>Reflexiona</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Ponte en acción
			```html
			<div class="ponte-en-accion">
				<h3>Ponte en acción</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
	* [SUBSECCIÓN] Cras sed est venenatis: aliquam metus non
		* [AREA DE MEDIOS Y TEXTO] Referente bíblico
			```html
			<div class="resaltado cita-biblica">
			  <p><em>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."</em></p>
			  <p><strong>Lorem ipsum</strong></p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Reflexiona
			```html
			<div class="reflexiona">
				<h3>Reflexiona</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Ponte en acción
			```html
			<div class="ponte-en-accion">
				<h3>Ponte en acción</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
	* [SUBSECCIÓN] Aliquam neque lorem: finibus a eros at
		* [AREA DE MEDIOS Y TEXTO] Referente bíblico
			```html
			<div class="resaltado cita-biblica">
			  <p><em>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Phasellus ornare massa libero, at egestas nulla bibendum id. Curabitur in libero sit amet purus sagittis bibendum eu eget nulla. Morbi tincidunt nulla velit, eget ultricies lorem venenatis ut."</em></p>
			  <p><strong>Lorem ipsum</strong></p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Reflexiona
			```html
			<div class="reflexiona">
				<h3>Reflexiona</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
		* [AREA DE MEDIOS Y TEXTO] Ponte en acción
			```html
			<div class="ponte-en-accion">
				<h3>Ponte en acción</h3>
				<p>Integer eget tincidunt mauris. <strong>Suspendisse vehicula</strong> hendrerit eros non interdum.</p>
				<ul>
					<li>Nulla facilisi. Donec turpis justo, vehicula id leo nec, elementum euismod tortor.</li>
					<li>Maecenas mattis dolor at pharetra lacinia.</li>
					<li>Sed sagittis vestibulum laoreet.</li>
				</ul>
				<p>Proin efficitur massa id lacus pulvinar efficitur. Praesent aliquet <strong>ligula pulvinar, imperdiet erat id, bibendum ex</strong>.
				</p>
			</div>
			```
	* [SUBSECCIÓN] Reflexiona y aplica con sentido cristiano
		* [CUESTIONARIO] Piensa, responde y transforma
			* [PREGUNTA TIPO ENSAYO DE SOLO TEXTO] ¿Aenean suscipit placerat lacus, ut malesuada ex commodo ut?
			* [PREGUNTA TIPO ENSAYO DE SOLO TEXTO] ¿Integer quis finibus velit. Sed eget metus enim?
			* [PREGUNTA TIPO ENSAYO DE SOLO TEXTO] ¿Praesent dui lorem, gravida et commodo imperdiet, aliquet sit amet dolor?
		* [CUESTIONARIO] ¿Cómo puedes mostrar y compartir lo aprendido?
			* [PREGUNTA TIPO ENSAYO CON ADJUNTO SIN TEXTO] Curabitur vitae tortor "a nibh semper interdum" id eget justo.
			* [PREGUNTA TIPO ENSAYO DE SOLO TEXTO] Sed eu urna sed nisi porta commodo at id metus. Maecenas nec nisi quis erat gravida tincidunt ut sed quam.
		* [AREA DE MEDIOS Y TEXTO] Cierre espiritual
			```html
			<div class="resaltado cierre-espiritual">
			  <p><em>Curabitur metus quam, vehicula in tristique sed, suscipit ut lectus. Curabitur eget congue lacus. Proin mi turpis, laoreet dignissim commodo sagittis, facilisis at nisl. Suspendisse potenti."</em></p>
			</div>
			```

**NOTAS IMPORTANTES:**
* En el markdown original, algunas subsecciones (títulos de segundo nivel) vienen precedidas por un número, tipo "1\."; es necesario eliminar esta numeración.
* Debido a que los markdowns son exportados desde Google Docs, algunas convenciones pueden cambiar; por ejemplo, la marca para las itálicas es un asterisco (*) y no un guión bajo (_).



