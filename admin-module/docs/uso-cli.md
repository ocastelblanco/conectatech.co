# Uso del backend como CLI

## Resumen de scripts disponibles

| Script | Propósito |
|--------|-----------|
| `procesar-markdown.php` | Convierte un Markdown en contenido de un curso repositorio |
| `crear-cursos.php` | Crea cursos finales (por colegio/grado) desde un CSV |
| `poblar-cursos.php` | Clona secciones de repositorios a cursos finales |

---

## Conversión de fuentes Markdown en Cursos repositorios

### 1. Cargar los archivos Markdown a la carpeta `/tmp` del servidor.

```bash
scp -i ~/.ssh/ClaveIM.pem archivo.md ec2-user@54.86.113.27:/tmp/
```

### 2. Ingresar al servidor con SSH.

```bash
ssh -i ~/.ssh/ClaveIM.pem ec2-user@54.86.113.27
```

### 3. Como usuario `apache` ejecutar el script correspondiente

```bash
sudo -u apache php /var/www/html/admin/backend/procesar-markdown.php -file /tmp/archivo.md -course <shortname>
```

Reemplazar `<shortname>` con el **nombre corto del curso** destino, que puede ser:

| Nombre corto del curso | Nombre largo del curso | Ruta destino |
| --- | --- | --- |
| repo-cc-cn-4-5 | Ciencias Naturales — Grados 4° y 5° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Naturales |
| repo-cc-cn-6-7 | Ciencias Naturales — Grados 6° y 7° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Naturales |
| repo-cc-cn-8-9 | Ciencias Naturales — Grados 8° y 9° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Naturales |
| repo-cc-fi-10-11 | Física — Grados 10° y 11° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Naturales |
| repo-cc-qu-10-11 | Química — Grados 10° y 11° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Naturales |
| repo-cc-ma-4-5 | Matemáticas — Grados 4° y 5° | REPOSITORIOS/Cosmovisión Cristiana/Matemáticas |
| repo-cc-ma-6-7 | Matemáticas — Grados 6° y 7° | REPOSITORIOS/Cosmovisión Cristiana/Matemáticas |
| repo-cc-ma-8-9 | Matemáticas — Grados 8° y 9° | REPOSITORIOS/Cosmovisión Cristiana/Matemáticas |
| repo-cc-ma-10-11 | Matemáticas — Grados 10° y 11° | REPOSITORIOS/Cosmovisión Cristiana/Matemáticas |
| repo-cc-le-4-5 | Lenguaje — Grados 4° y 5° | REPOSITORIOS/Cosmovisión Cristiana/Lenguaje |
| repo-cc-le-6-7 | Lenguaje — Grados 6° y 7° | REPOSITORIOS/Cosmovisión Cristiana/Lenguaje |
| repo-cc-le-8-9 | Lenguaje — Grados 8° y 9° | REPOSITORIOS/Cosmovisión Cristiana/Lenguaje |
| repo-cc-le-10-11 | Lenguaje — Grados 10° y 11° | REPOSITORIOS/Cosmovisión Cristiana/Lenguaje |
| repo-cc-cs-4-5 | Ciencias Sociales — Grados 4° y 5° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Sociales |
| repo-cc-cs-6-7 | Ciencias Sociales — Grados 6° y 7° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Sociales |
| repo-cc-cs-8-9 | Ciencias Sociales — Grados 8° y 9° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Sociales |
| repo-cc-cs-10-11 | Ciencias Sociales — Grados 10° y 11° | REPOSITORIOS/Cosmovisión Cristiana/Ciencias Sociales |
| repo-uc-cn-4-5 | Ciencias Naturales — Grados 4° y 5° | REPOSITORIOS/Unidades Curriculares/Ciencias Naturales |
| repo-uc-cn-6-7 | Ciencias Naturales — Grados 6° y 7° | REPOSITORIOS/Unidades Curriculares/Ciencias Naturales |
| repo-uc-cn-8-9 | Ciencias Naturales — Grados 8° y 9° | REPOSITORIOS/Unidades Curriculares/Ciencias Naturales |
| repo-uc-fi-10-11 | Física — Grados 10° y 11° | REPOSITORIOS/Unidades Curriculares/Ciencias Naturales |
| repo-uc-qu-10-11 | Química — Grados 10° y 11° | REPOSITORIOS/Unidades Curriculares/Ciencias Naturales |
| repo-uc-ma-4-5 | Matemáticas — Grados 4° y 5° | REPOSITORIOS/Unidades Curriculares/Matemáticas |
| repo-uc-ma-6-7 | Matemáticas — Grados 6° y 7° | REPOSITORIOS/Unidades Curriculares/Matemáticas |
| repo-uc-ma-8-9 | Matemáticas — Grados 8° y 9° | REPOSITORIOS/Unidades Curriculares/Matemáticas |
| repo-uc-ma-10-11 | Matemáticas — Grados 10° y 11° | REPOSITORIOS/Unidades Curriculares/Matemáticas |
| repo-uc-le-4-5 | Lenguaje — Grados 4° y 5° | REPOSITORIOS/Unidades Curriculares/Lenguaje |
| repo-uc-le-6-7 | Lenguaje — Grados 6° y 7° | REPOSITORIOS/Unidades Curriculares/Lenguaje |
| repo-uc-le-8-9 | Lenguaje — Grados 8° y 9° | REPOSITORIOS/Unidades Curriculares/Lenguaje |
| repo-uc-le-10-11 | Lenguaje — Grados 10° y 11° | REPOSITORIOS/Unidades Curriculares/Lenguaje |
| repo-uc-cs-4-5 | Ciencias Sociales — Grados 4° y 5° | REPOSITORIOS/Unidades Curriculares/Ciencias Sociales |
| repo-uc-cs-6-7 | Ciencias Sociales — Grados 6° y 7° | REPOSITORIOS/Unidades Curriculares/Ciencias Sociales |
| repo-uc-cs-8-9 | Ciencias Sociales — Grados 8° y 9° | REPOSITORIOS/Unidades Curriculares/Ciencias Sociales |
| repo-uc-cs-10-11 | Ciencias Sociales — Grados 10° y 11° | REPOSITORIOS/Unidades Curriculares/Ciencias Sociales |

---

## Creación de cursos finales (`crear-cursos.php`)

Los **cursos finales** son los cursos visibles por colegio y grado (e.g., `Ciencias Naturales - 6` del Colegio San Marino). Se crean a partir del archivo `config/cursos-finales.csv` y copian formato e imagen del curso plantilla indicado.

### Prerequisito

Crear manualmente en Moodle un curso con el shortname de plantilla (e.g., `PL-CC-CN`) con la configuración visual deseada (formato, imagen de portada, tema, etc.).

### Formato del CSV (`config/cursos-finales.csv`)

```csv
shortname,fullname,category,templatecourse
san-marino-cn-6,"Ciencias Naturales - 6","COLEGIOS/San Marino/Ciencias Naturales",PL-CC-CN
san-marino-ma-6,"Matemáticas - 6","COLEGIOS/San Marino/Matemáticas",PL-CC-MA
```

- **shortname**: `{slug-colegio}-{asignatura}-{grado}` (e.g., `san-marino-cn-6`)
- **fullname**: `{Materia} - {Grado}` (e.g., `Ciencias Naturales - 6`)
- **category**: ruta jerárquica separada por `/`; se crea automáticamente si no existe
- **templatecourse**: shortname del curso plantilla del que se copian formato e imagen

### Uso

```bash
# Crear todos los cursos del CSV
sudo -u apache php /var/www/html/admin/backend/crear-cursos.php

# Crear solo un curso específico
sudo -u apache php /var/www/html/admin/backend/crear-cursos.php \
    --course san-marino-cn-6

# Ver qué se haría sin ejecutar
sudo -u apache php /var/www/html/admin/backend/crear-cursos.php \
    --course san-marino-cn-6 --dry-run

# Usar un CSV diferente
sudo -u apache php /var/www/html/admin/backend/crear-cursos.php \
    --file /tmp/otros-cursos.csv
```

### Argumentos

| Argumento | Descripción | Default |
|-----------|-------------|---------|
| `--file <ruta>` | Ruta al CSV de cursos | `config/cursos-finales.csv` |
| `--course <sn>` | Procesar solo este shortname | (todos) |
| `--dry-run` | Mostrar qué se haría sin ejecutar | — |

### Comportamiento

- Si el curso ya existe → **skip** (no lo modifica)
- Si la categoría de destino no existe → la crea automáticamente
- Copia el formato del curso (topics, semanal, etc.) y sus opciones del curso plantilla
- Copia la imagen de portada del curso plantilla
- Los cursos se crean **ocultos** (`visible=0`); activarlos manualmente cuando estén listos
- Genera reporte en `report-ultimo-creacion.json`

---

## Poblamiento de cursos finales (`poblar-cursos.php`)

Clona secciones desde los cursos repositorio al curso final usando la API nativa de Backup/Restore de Moodle. Requiere que los cursos finales ya existan (crear primero con `crear-cursos.php`).

### Formato del mapping (`config/poblamiento.json`)

```json
{
    "comment": "shortname del curso final → secciones a clonar de repositorios",
    "courses": [
        {
            "shortname": "san-marino-cn-6",
            "sections": [
                { "repo": "repo-cc-cn-6-7", "section_num": 1 },
                { "repo": "repo-cc-cn-6-7", "section_num": 2 }
            ]
        }
    ]
}
```

- **shortname**: shortname del curso final destino
- **repo**: shortname del curso repositorio origen
- **section_num**: número de sección **1-based** (corresponde al orden del H1 en el Markdown)
- Un curso final puede mezclar secciones de distintos repositorios

### Uso

```bash
# Poblar todos los cursos del mapping
sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php

# Poblar solo un curso específico
sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php \
    --course san-marino-cn-6

# Ver qué se haría sin ejecutar
sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php \
    --course san-marino-cn-6 --dry-run

# Usar un mapping diferente
sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php \
    --mapping /tmp/otro-mapping.json
```

### Argumentos

| Argumento | Descripción | Default |
|-----------|-------------|---------|
| `--mapping <ruta>` | Ruta al JSON de mapeo | `config/poblamiento.json` |
| `--course <sn>` | Poblar solo este curso | (todos) |
| `--dry-run` | Mostrar qué se haría sin ejecutar | — |

### Comportamiento

- Verifica que el curso final existe antes de intentar clonar
- Cada sección se clona con backup/restore **sin datos de usuarios**
- Las secciones se agregan al final del curso destino (TARGET_EXISTING_ADDING)
- Si una sección falla, continúa con las siguientes y registra el error
- Genera reporte en `report-ultimo-poblamiento.json`

### Flujo completo (ejemplo)

```bash
# En la máquina local: desplegar archivos al servidor
scp -i ~/.ssh/ClaveIM.pem \
    admin-module/backend/crear-cursos.php \
    admin-module/backend/poblar-cursos.php \
    ec2-user@54.86.113.27:/tmp/

scp -i ~/.ssh/ClaveIM.pem \
    admin-module/backend/lib/MoodleSectionCloner.php \
    ec2-user@54.86.113.27:/tmp/

scp -i ~/.ssh/ClaveIM.pem \
    admin-module/backend/config/cursos-finales.csv \
    admin-module/backend/config/poblamiento.json \
    ec2-user@54.86.113.27:/tmp/

ssh ec2-user@54.86.113.27 "
    sudo cp /tmp/crear-cursos.php /tmp/poblar-cursos.php /var/www/html/admin/backend/
    sudo cp /tmp/MoodleSectionCloner.php /var/www/html/admin/backend/lib/
    sudo cp /tmp/cursos-finales.csv /tmp/poblamiento.json /var/www/html/admin/backend/config/
    sudo chown -R apache:apache /var/www/html/admin/backend/
"

# En el servidor: crear el curso final
sudo -u apache php /var/www/html/admin/backend/crear-cursos.php \
    --course san-marino-cn-6

# En el servidor: poblar con secciones del repositorio
sudo -u apache php /var/www/html/admin/backend/poblar-cursos.php \
    --course san-marino-cn-6

# Verificar reportes
cat /var/www/html/admin/backend/report-ultimo-creacion.json
cat /var/www/html/admin/backend/report-ultimo-poblamiento.json
```
