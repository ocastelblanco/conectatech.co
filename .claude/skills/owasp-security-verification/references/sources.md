# Fuentes y versiones

Contenido de este directorio: **CC BY-SA 4.0** (ver [`LICENSE`](LICENSE)).

| Fuente | Versión fijada | Uso en la skill | Verificada |
|---|---|---|---|
| [OWASP ASVS](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/docs_en) | 5.0.0 (mayo 2025), export CSV oficial | Requisito verificable de cada chequeo (`asvs-5.0.0.json`, 345 requisitos: 70 L1, 183 L2, 92 L3) | 2026-10-07 |
| [OWASP Top 10](https://top10.owasp.org/2025/) | 2025 | Clasificación de riesgo de cada hallazgo (`top10-2025.json`) y lectura de secciones OWASP declaradas con la versión 2021 | 2026-10-07 |
| [OWASP Cheat Sheet Series](https://cheatsheetseries.owasp.org/) | índice consultado el 2026-10-07 | Guía de corrección enlazada desde cada chequeo | 2026-10-07 |

## Formato de IDs

- ASVS: `v5.0.0-<capítulo>.<sección>.<requisito>`, formato recomendado por el propio estándar. Ejemplo: `v5.0.0-1.2.4`.
- Top 10: `A05:2025`.
- Cheat sheet: slug del archivo, por ejemplo `Docker_Security` → `https://cheatsheetseries.owasp.org/cheatsheets/Docker_Security_Cheat_Sheet.html`.

## Regenerar el índice de ASVS

```bash
curl -sSLO "https://raw.githubusercontent.com/OWASP/ASVS/v5.0.0/5.0/docs_en/OWASP_Application_Security_Verification_Standard_5.0.0_en.csv"
node scripts/build-asvs-index.mjs OWASP_Application_Security_Verification_Standard_5.0.0_en.csv
node scripts/validate-catalog.mjs   # comprueba que todo chequeo cite IDs existentes
```

Al cambiar de versión de ASVS, `validate-catalog.mjs` falla en cada chequeo que cite un ID que ya no exista. Así el desfase sale a la luz y no queda oculto.
