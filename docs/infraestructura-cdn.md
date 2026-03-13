# Infraestructura CDN — assets.conectatech.co

## Recursos AWS

| Recurso | ID / Valor |
|---|---|
| Bucket S3 | `assets.conectatech.co` (región `us-east-1`) |
| CloudFront Distribution | `E2KULI3BS0YJDX` |
| CloudFront Domain | `dvlgey5i48r61.cloudfront.net` |
| Response Headers Policy | `65f8d048-1426-492f-9acd-ccc02c4b1151` (`conectatech-assets-policy`) |

## Estructura del bucket S3

```
assets.conectatech.co/
├── herramientas/
│   └── visor-pdf/          ← Visor PDF Angular (pendiente deploy)
│       └── index.html, *.js, *.css
└── recursos/
    └── pdf/                ← Archivos PDF gestionados desde admin
        ├── index.json      ← Índice de recursos PDF (gestionado vía API)
        └── *.pdf
```

## CloudFront

- **Origen:** `assets.conectatech.co.s3-website-us-east-1.amazonaws.com` (S3 static website)
- **Protocol:** redirect-to-https
- **Compresión:** habilitada
- **Response Headers Policy:** `conectatech-assets-policy` (ver abajo)

### Response Headers Policy (`conectatech-assets-policy`)

| Header | Valor |
|---|---|
| `Content-Security-Policy` | `frame-ancestors https://conectatech.co https://admin.conectatech.co` |
| `X-XSS-Protection` | `1; mode=block` |
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |

El header `frame-ancestors` es crítico: permite que el visor PDF sea incrustado via `<iframe>` desde Moodle (`conectatech.co`) y desde la app de admin (`admin.conectatech.co`).

> **NOTA:** `X-Frame-Options` **no** está configurado intencionalmente — `Content-Security-Policy: frame-ancestors` lo reemplaza con mayor flexibilidad.

## CORS del bucket S3

Configurado para soportar carga directa de PDFs via URLs pre-firmadas:

```json
{
  "AllowedHeaders": ["*"],
  "AllowedMethods": ["GET", "HEAD", "PUT"],
  "AllowedOrigins": [
    "https://conectatech.co",
    "https://www.conectatech.co",
    "https://admin.conectatech.co"
  ],
  "ExposeHeaders": ["ETag"],
  "MaxAgeSeconds": 86400
}
```

## DNS

- Zona Route 53: `Z0767805255ZNR9CRNWLH`
- Registro CNAME: `assets` → `dvlgey5i48r61.cloudfront.net`

## Índice JSON de PDFs

Archivo: `s3://assets.conectatech.co/recursos/pdf/index.json`

Estructura de cada entrada (compatible con NoSQL / DynamoDB / DocumentDB):

```json
{
  "id": "uuid-v4",
  "title": "Nombre legible del PDF",
  "filename": "nombre-archivo.pdf",
  "s3key": "recursos/pdf/nombre-archivo.pdf",
  "url": "https://assets.conectatech.co/recursos/pdf/nombre-archivo.pdf",
  "pages": null,
  "createdAt": "2026-03-12T00:00:00Z",
  "updatedAt": "2026-03-12T00:00:00Z"
}
```

El índice completo es un array JSON de estas entradas.

## Autenticación (provisional)

Por ahora, la API (`api.conectatech.co`) autoriza según el origen de la solicitud:

| Origen | Acceso |
|---|---|
| `https://admin.conectatech.co` | CRUD completo (gestión de PDFs) |
| `https://conectatech.co` | Solo lectura (funciones informativas) |

En una fase posterior se implementará Cognito para autenticación basada en JWT.

## API — api.conectatech.co

| Recurso | ID / Valor |
|---|---|
| API Gateway HTTP | `7xv6etd54i` |
| Endpoint temporal | `https://7xv6etd54i.execute-api.us-east-1.amazonaws.com` |
| Dominio custom | `api.conectatech.co` → `d-j9xoul7vac.execute-api.us-east-1.amazonaws.com` |
| ACM Certificate | `a47014a1-092e-4e1a-a2eb-406a3a9f642c` (ISSUED) |
| Lambda | `conectatech-api-pdfs` (arm64, Node.js 22, 256MB) |
| IAM Role | `conectatech-api-lambda-role` |
| Código fuente | `api-service/functions/pdfs/index.mjs` |

### Rutas

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| `GET` | `/pdfs` | read+admin | Lista todos los PDFs del índice |
| `POST` | `/pdfs` | admin | Prepara subida: devuelve `{ item, uploadUrl }` pre-firmada (15 min) |
| `POST` | `/pdfs/{id}/confirm` | admin | Confirma que la subida a S3 terminó, activa el item |
| `PATCH` | `/pdfs/{id}` | admin | Renombra el título del PDF en el índice |
| `GET` | `/pdfs/{id}/download` | read+admin | Devuelve URL pre-firmada de descarga (5 min) |
| `DELETE` | `/pdfs/{id}` | admin | Elimina el archivo de S3 y del índice |

### Flujo de subida de PDF

```
Admin app → POST /pdfs { title, filename }
         ← { item (status:pending), uploadUrl }
Admin app → PUT uploadUrl (S3 directo, max 15 min)
Admin app → POST /pdfs/{id}/confirm
         ← { item (status:active) }
```

### Deploy de la Lambda

```bash
cd api-service/functions/pdfs
npm install --omit=dev
zip -r /tmp/lambda-pdf.zip .
aws lambda update-function-code --profile im \
  --function-name conectatech-api-pdfs \
  --zip-file fileb:///tmp/lambda-pdf.zip
```
