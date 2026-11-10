# Git Flow para Agentes IA — Template

> Copiar esta sección en el `CLAUDE.md` de cualquier proyecto, adaptando los valores entre corchetes.

---

## Git Flow para Agentes IA

Las siguientes reglas son **obligatorias** para cualquier agente que opere en este repositorio. No existe excepción, aunque el usuario lo solicite explícitamente.

### Ramas protegidas

Las ramas `[RAMA_PRINCIPAL]` y `[RAMA_DESARROLLO]` están protegidas. **Ningún agente puede hacer commits directos a ellas.**

### Protocolo antes de cualquier cambio de código

**Paso 1 — Verificar la rama actual:**
```bash
git branch --show-current
```
Si el resultado es `[RAMA_PRINCIPAL]` o `[RAMA_DESARROLLO]`, ejecutar el Paso 2. Si ya hay una feature branch activa, continuar desde el Paso 3.

**Paso 2 — Crear feature branch:**
```bash
git checkout [RAMA_DESARROLLO]
git pull origin [RAMA_DESARROLLO]
git checkout -b [PREFIJO]/descripcion-corta-en-kebab-case
```

Prefijos válidos: `feature/`, `fix/`, `hotfix/`, `docs/`, `refactor/`

**Paso 3 — Cambios y commit:**
```bash
[COMANDO_BUILD]   # Si falla: NO commitear. Resolver primero.
git add [archivos específicos]   # Nunca git add . ni git add -A
git commit -m "tipo(alcance): descripción en [IDIOMA_DEL_PROYECTO]"
```

**Paso 4 — Pull Request:**
```bash
git push -u origin HEAD
gh pr create \
  --base [RAMA_DESARROLLO] \
  --title "tipo(alcance): descripción breve" \
  --body "$(cat <<'EOF'
## Cambios realizados
- [bullet]

## Cómo probar
- [pasos verificables]

## Checklist
- [ ] Build pasa sin errores
- [ ] No hay secretos hardcodeados
- [ ] Seguí las convenciones del proyecto

🤖 Generado con Claude Code
EOF
)"
```

### Prohibiciones absolutas

| Acción prohibida | Por qué |
|---|---|
| `git push origin [RAMA_PRINCIPAL]` | Commit directo a producción |
| `git push --force` en cualquier rama | Destruye historial |
| `git merge` de cualquier PR | Solo humanos aprueban y fusionan |
| `--no-verify` en commits o pushes | Omite hooks de seguridad |
| `git add .` o `git add -A` | Puede incluir secretos o archivos no deseados |
| Commitear `secrets.ts`, `.env`, `*.pem` | Exposición de credenciales |

El agente **nunca** debe fusionar un PR, aprobar su propio PR, ni cerrarlo sin fusionar.

---

## Valores a reemplazar

| Placeholder | Ejemplo Angular/Node | Ejemplo Python |
|---|---|---|
| `[RAMA_PRINCIPAL]` | `main` | `main` |
| `[RAMA_DESARROLLO]` | `develop` | `develop` |
| `[COMANDO_BUILD]` | `npm run build` | `python -m pytest` |
| `[IDIOMA_DEL_PROYECTO]` | `español colombiano` | `inglés` |
