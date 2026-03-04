# Uso del backend como CLI

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
