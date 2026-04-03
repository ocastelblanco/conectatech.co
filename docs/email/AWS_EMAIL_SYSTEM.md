# Sistema de Correo Electrónico en AWS con SES, Lambda y S3

## Guía Técnica de Implementación para Agentes IA

> **Alcance:** Este documento describe la arquitectura y los pasos de implementación para un sistema de correo electrónico basado en AWS, aplicable a cualquier proyecto que necesite enviar y recibir correos con dominios personalizados. Cubre tanto proyectos desplegados en EC2 (como plataformas LMS o aplicaciones web tradicionales) como proyectos serverless cuyo backend corre en AWS Lambda (como APIs REST).

---

## 1. Contexto y Proyectos Objetivo

Este sistema se aplica a los siguientes tipos de proyecto:

| Proyecto | Dominio(s) | Infraestructura | Uso de correo |
|---|---|---|---|
| **ACG** | `acg.edu.co` (ejemplo) | EC2 + Moodle u otra plataforma | Notificaciones, registro, recuperación de contraseña |
| **conectatech.co** | `conectatech.co` | EC2 o Lambda | Correos transaccionales, contacto |
| **letiende.co** | `letiende.co`, `api.letiende.co` | API en AWS Lambda | Confirmaciones de pedido, notificaciones, marketing |

### Flujos cubiertos

1. **Envío de correos (outbound):** Desde la aplicación (EC2 o Lambda) a través de Amazon SES vía SMTP o SDK.
2. **Recepción y reenvío (inbound):** Correos entrantes a `@dominio.co` se almacenan en S3 y se reenvían a cuentas de Gmail (u otro destino) mediante una función Lambda.

---

## 2. Arquitectura General

```
┌─────────────────────────────────────────────────────────────┐
│                    FLUJO DE ENVÍO (OUTBOUND)                │
│                                                             │
│  App (EC2/Lambda) ──SMTP o SDK──► Amazon SES ──► Destino   │
│                                                             │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                  FLUJO DE RECEPCIÓN (INBOUND)               │
│                                                             │
│  Remitente ──MX──► SES (Receipt Rule) ──► S3 Bucket        │
│                                              │              │
│                                         S3 Trigger          │
│                                              │              │
│                                        Lambda Function      │
│                                              │              │
│                                     SES sendRawEmail        │
│                                              │              │
│                                        Gmail / Destino      │
└─────────────────────────────────────────────────────────────┘
```

### Servicios AWS involucrados

- **Amazon SES** — envío y recepción de correo
- **Amazon S3** — almacenamiento temporal de correos entrantes
- **AWS Lambda** — procesamiento y reenvío de correos entrantes
- **Amazon Route 53** — gestión de DNS (registros MX, TXT, CNAME)
- **AWS IAM** — roles y permisos para Lambda
- **Amazon CloudWatch** — logs y monitoreo

---

## 3. Prerrequisitos

Antes de iniciar la implementación, verificar:

- [ ] Cuenta de AWS activa con acceso a la consola y CLI configurado.
- [ ] Dominio(s) registrado(s) y zona DNS alojada en Route 53.
- [ ] Región de AWS que soporte recepción de correo SES: `us-east-1` (Virginia), `us-west-2` (Oregon) o `eu-west-1` (Irlanda).
- [ ] AWS CLI instalado y configurado con credenciales de un usuario IAM con permisos administrativos.
- [ ] Node.js 18+ o Python 3.12+ para el runtime de Lambda.

---

## 4. Fase 1 — Verificación de Identidades y DNS

### 4.1 Verificar dominio en SES

```bash
# Verificar dominio para envío
aws ses verify-domain-identity --domain midominio.co --region us-east-1

# La respuesta incluye un VerificationToken.
# Crear registro TXT en Route 53:
#   Nombre: _amazonses.midominio.co
#   Tipo:   TXT
#   Valor:  <VerificationToken>
```

### 4.2 Verificar dominio para recepción

```bash
# Verificar dominio para recepción (mismo proceso de TXT)
# El registro TXT de verificación es el mismo del paso anterior.
# Solo es necesario un registro TXT por dominio.
```

### 4.3 Configurar registro MX para recepción

Añadir en Route 53:

```
Nombre: midominio.co
Tipo:   MX
Valor:  10 inbound-smtp.us-east-1.amazonaws.com
```

> **IMPORTANTE:** Solo tres regiones soportan recepción: `us-east-1`, `us-west-2`, `eu-west-1`. El bucket S3 y la función Lambda deben estar en la misma región.

### 4.4 Habilitar Easy DKIM

```bash
aws ses verify-domain-dkim --domain midominio.co --region us-east-1

# La respuesta incluye 3 DkimTokens.
# Crear 3 registros CNAME en Route 53:
#   Nombre: <token>._domainkey.midominio.co
#   Tipo:   CNAME
#   Valor:  <token>.dkim.amazonses.com
```

### 4.5 Verificar dirección de correo remitente

```bash
aws ses verify-email-identity \
  --email-address no-reply@midominio.co \
  --region us-east-1

# Repetir para cada dirección que se usará como remitente:
# - no-reply@midominio.co (envío desde app)
# - forwarder@midominio.co (reenvío desde Lambda)
```

---

## 5. Fase 2 — Flujo de Envío (Outbound)

### 5.1 Para proyectos en EC2 (ACG, conectatech.co)

Configurar la aplicación para usar la interfaz SMTP de SES:

| Parámetro | Valor |
|---|---|
| Host SMTP | `email-smtp.us-east-1.amazonaws.com` |
| Puerto | `587` (STARTTLS) o `465` (TLS Wrapper) |
| Cifrado | TLS obligatorio |
| Usuario | Credencial SMTP de SES (Access Key) |
| Contraseña | Credencial SMTP de SES (Secret Key) |

Generar credenciales SMTP:

```bash
# Desde la consola de SES > SMTP Settings > Create SMTP Credentials
# O usando IAM para crear un usuario SMTP dedicado.
```

**Ejemplo de configuración en Moodle (PHP):**
- Administración > Servidor > Correo electrónico saliente
- Método: SMTP
- Host: `email-smtp.us-east-1.amazonaws.com`
- Puerto: 587
- Protocolo de seguridad: TLS

**Ejemplo genérico con Nodemailer (Node.js):**

```javascript
const nodemailer = require('nodemailer');

const transporter = nodemailer.createTransport({
  host: 'email-smtp.us-east-1.amazonaws.com',
  port: 587,
  secure: false,
  auth: {
    user: process.env.SES_SMTP_USER,
    pass: process.env.SES_SMTP_PASS,
  },
});

await transporter.sendMail({
  from: 'no-reply@midominio.co',
  to: 'usuario@ejemplo.com',
  subject: 'Confirmación',
  html: '<p>Tu registro fue exitoso.</p>',
});
```

### 5.2 Para proyectos serverless en Lambda (letiende.co)

En un backend Lambda, usar el SDK de AWS directamente (sin SMTP) es más eficiente:

```javascript
// Node.js 18+ con AWS SDK v3
import { SESClient, SendEmailCommand } from '@aws-sdk/client-ses';

const ses = new SESClient({ region: 'us-east-1' });

export const handler = async (event) => {
  const command = new SendEmailCommand({
    Source: 'no-reply@letiende.co',
    Destination: {
      ToAddresses: ['cliente@gmail.com'],
    },
    Message: {
      Subject: { Data: 'Confirmación de pedido' },
      Body: {
        Html: { Data: '<h1>Gracias por tu compra</h1>' },
      },
    },
  });

  await ses.send(command);
};
```

> **Nota sobre costos:** El envío desde EC2 tiene un umbral gratuito de 62,000 mensajes/mes. Desde Lambda, el envío consume los 3,000 créditos mensuales del Free Tier de SES (primer año), y luego cuesta ~$0.10 por cada 1,000 mensajes.

---

## 6. Fase 3 — Flujo de Recepción y Reenvío (Inbound)

### 6.1 Crear bucket S3

```bash
aws s3 mb s3://midominio-incoming-emails --region us-east-1

# Configurar política del bucket para permitir que SES escriba:
cat > /tmp/s3-policy.json << 'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "AllowSESPuts",
      "Effect": "Allow",
      "Principal": { "Service": "ses.amazonaws.com" },
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::midominio-incoming-emails/*",
      "Condition": {
        "StringEquals": {
          "AWS:SourceAccount": "<ACCOUNT_ID>"
        }
      }
    }
  ]
}
EOF

aws s3api put-bucket-policy \
  --bucket midominio-incoming-emails \
  --policy file:///tmp/s3-policy.json
```

### 6.2 Crear función Lambda de reenvío

**Runtime:** Node.js 20.x  
**Timeout:** 30 segundos  
**Memoria:** 256 MB

```javascript
// index.mjs — Función de reenvío de correos
import { S3Client, GetObjectCommand } from '@aws-sdk/client-s3';
import { SESClient, SendRawEmailCommand } from '@aws-sdk/client-ses';

const s3 = new S3Client({ region: 'us-east-1' });
const ses = new SESClient({ region: 'us-east-1' });

// Mapeo de direcciones destino
const FORWARD_MAP = {
  'info@midominio.co': ['admin@gmail.com'],
  'ventas@midominio.co': ['ventas-equipo@gmail.com'],
  // Catch-all (opcional):
  '@midominio.co': ['admin@gmail.com'],
};

const FORWARDER_ADDRESS = 'forwarder@midominio.co';

export const handler = async (event) => {
  const record = event.Records[0];
  const bucket = record.s3.bucket.name;
  const key = decodeURIComponent(record.s3.object.key.replace(/\+/g, ' '));

  // 1. Leer el correo desde S3
  const { Body } = await s3.send(new GetObjectCommand({ Bucket: bucket, Key: key }));
  let emailContent = await Body.transformToString();

  // 2. Extraer destinatario original del header To
  const toMatch = emailContent.match(/^To:\s*(.+)$/mi);
  const originalTo = toMatch ? toMatch[1].trim().replace(/[<>]/g, '') : '';

  // 3. Determinar destinos de reenvío
  let forwardTo = FORWARD_MAP[originalTo.toLowerCase()];
  if (!forwardTo) {
    // Buscar catch-all por dominio
    const domain = originalTo.split('@')[1];
    forwardTo = FORWARD_MAP[`@${domain}`];
  }
  if (!forwardTo) {
    console.log(`No hay destino configurado para: ${originalTo}`);
    return;
  }

  // 4. Reemplazar headers para reenvío
  emailContent = emailContent
    .replace(/^Return-Path:.*$/mi, `Return-Path: <${FORWARDER_ADDRESS}>`)
    .replace(/^From:.*$/mi, (match) => {
      // Preservar el nombre del remitente original
      const nameMatch = match.match(/^From:\s*(.+?)\s*<.*>$/i);
      const name = nameMatch ? nameMatch[1] : 'Forwarded';
      return `From: ${name} <${FORWARDER_ADDRESS}>`;
    });

  // 5. Enviar a cada destino
  for (const destination of forwardTo) {
    await ses.send(new SendRawEmailCommand({
      Source: FORWARDER_ADDRESS,
      Destinations: [destination],
      RawMessage: {
        Data: Buffer.from(
          emailContent.replace(/^To:.*$/mi, `To: ${destination}`)
        ),
      },
    }));
    console.log(`Reenviado a ${destination}`);
  }
};
```

### 6.3 Rol IAM para la función Lambda

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": "arn:aws:logs:*:*:*"
    },
    {
      "Effect": "Allow",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::midominio-incoming-emails/*"
    },
    {
      "Effect": "Allow",
      "Action": "ses:SendRawEmail",
      "Resource": "*"
    }
  ]
}
```

### 6.4 Configurar regla de recepción en SES

```bash
# Crear rule set
aws ses create-receipt-rule-set \
  --rule-set-name midominio-rules \
  --region us-east-1

# Activar rule set
aws ses set-active-receipt-rule-set \
  --rule-set-name midominio-rules \
  --region us-east-1

# Crear regla
aws ses create-receipt-rule \
  --rule-set-name midominio-rules \
  --region us-east-1 \
  --rule '{
    "Name": "forward-to-gmail",
    "Enabled": true,
    "Recipients": ["midominio.co"],
    "Actions": [
      {
        "S3Action": {
          "BucketName": "midominio-incoming-emails",
          "ObjectKeyPrefix": "incoming/"
        }
      }
    ],
    "ScanEnabled": true
  }'
```

### 6.5 Configurar trigger S3 → Lambda

```bash
# Añadir permiso para que S3 invoque la función
aws lambda add-permission \
  --function-name email-forwarder \
  --statement-id s3-trigger \
  --action lambda:InvokeFunction \
  --principal s3.amazonaws.com \
  --source-arn arn:aws:s3:::midominio-incoming-emails

# Configurar notificación en el bucket
aws s3api put-bucket-notification-configuration \
  --bucket midominio-incoming-emails \
  --notification-configuration '{
    "LambdaFunctionConfigurations": [
      {
        "LambdaFunctionArn": "arn:aws:lambda:us-east-1:<ACCOUNT_ID>:function:email-forwarder",
        "Events": ["s3:ObjectCreated:*"],
        "Filter": {
          "Key": {
            "FilterRules": [
              { "Name": "prefix", "Value": "incoming/" }
            ]
          }
        }
      }
    ]
  }'
```

---

## 7. Fase 4 — Salida del Sandbox de SES

Este paso es **obligatorio** antes de pasar a producción. Mientras la cuenta esté en sandbox, solo se pueden enviar correos a direcciones verificadas y con un máximo de 200 mensajes/día.

```bash
# Verificar estado actual
aws ses get-account --region us-east-1
# Buscar: "ProductionAccessEnabled": false → aún en sandbox
```

Para solicitar la salida del sandbox:

1. Ir a la consola de SES > Account dashboard > Request production access.
2. Proporcionar:
   - **Tipo de correo:** Transaccional
   - **Sitio web:** URL del proyecto (ej. `https://letiende.co`)
   - **Descripción del caso de uso:** Notificaciones transaccionales, confirmaciones de registro, recuperación de contraseña.
   - **Volumen estimado:** ej. 500-2,000 correos/día.
   - **Proceso de manejo de bounces y complaints:** Explicar que se monitorean vía SNS/CloudWatch.

> La aprobación suele tardar 24-48 horas. Sin ella, el sistema no es funcional en producción.

---

## 8. Costos y Límites del Free Tier

| Servicio | Free Tier (primer año) | Costo post-Free Tier |
|---|---|---|
| SES envío desde EC2 | 62,000 msgs/mes | $0.10 / 1,000 msgs |
| SES envío desde Lambda | 3,000 créditos/mes (compartidos) | $0.10 / 1,000 msgs |
| SES recepción | Incluido en 3,000 créditos/mes | $0.10 / 1,000 msgs |
| Lambda | 1M requests + 400K GB-s/mes | Pay-per-use |
| S3 | 5 GB + 20,000 GET/mes | ~$0.023/GB/mes |
| Route 53 | $0.50/zona/mes (no tiene free tier) | $0.50/zona/mes |

**Estimación mensual para uso moderado (post free tier):**
- 2,000 correos enviados + 500 recibidos ≈ $0.25 en SES
- Lambda y S3: prácticamente $0
- Route 53: $0.50/zona
- **Total estimado: < $1.00/mes** por dominio

---

## 9. Configuración por Proyecto

### 9.1 ACG / conectatech.co (EC2)

```env
# Variables de entorno o configuración de la aplicación
SES_SMTP_HOST=email-smtp.us-east-1.amazonaws.com
SES_SMTP_PORT=587
SES_SMTP_USER=<SMTP_ACCESS_KEY>
SES_SMTP_PASS=<SMTP_SECRET_KEY>
SES_FROM_ADDRESS=no-reply@conectatech.co
```

### 9.2 letiende.co (Lambda)

```env
# Variables de entorno de la función Lambda del backend
SES_REGION=us-east-1
SES_FROM_ADDRESS=no-reply@letiende.co
```

El backend en Lambda usa directamente el SDK de AWS, por lo que no necesita credenciales SMTP. Los permisos se otorgan a través del rol IAM de la función.

Agregar al rol IAM de la API Lambda:

```json
{
  "Effect": "Allow",
  "Action": [
    "ses:SendEmail",
    "ses:SendRawEmail"
  ],
  "Resource": "*"
}
```

---

## 10. Checklist de Verificación

- [ ] Dominio verificado en SES (registro TXT en Route 53)
- [ ] DKIM habilitado (3 registros CNAME en Route 53)
- [ ] Registro MX apuntando a `inbound-smtp.<region>.amazonaws.com`
- [ ] Dirección de correo remitente verificada en SES
- [ ] Credenciales SMTP generadas (para proyectos EC2)
- [ ] Bucket S3 creado con política para SES
- [ ] Función Lambda de reenvío desplegada y probada
- [ ] Rol IAM con permisos `s3:GetObject`, `ses:SendRawEmail`, `logs:*`
- [ ] Regla de recepción creada y rule set activado en SES
- [ ] Trigger S3 → Lambda configurado
- [ ] Sandbox de SES superado (producción habilitada)
- [ ] Prueba end-to-end: envío desde app exitoso
- [ ] Prueba end-to-end: correo a `info@dominio.co` llega a Gmail
- [ ] Monitoreo en CloudWatch configurado

---

## 11. Troubleshooting

| Síntoma | Causa probable | Solución |
|---|---|---|
| Correos no se envían desde la app | Sandbox activo | Solicitar salida del sandbox |
| Correos caen en spam | DKIM no configurado | Verificar registros CNAME de DKIM |
| `MessageRejected` en SES | Dirección remitente no verificada | Verificar email identity en SES |
| Lambda no se ejecuta | Trigger S3 mal configurado | Verificar notificaciones del bucket y permisos |
| Lambda falla al leer S3 | Permisos IAM insuficientes | Agregar `s3:GetObject` al rol |
| Reenvío falla | Dirección forwarder no verificada | Verificar `forwarder@dominio.co` en SES |
| Registro MX no funciona | Región no soportada | Usar `us-east-1`, `us-west-2` o `eu-west-1` |
| DNS no propaga | TTL alto o error en registro | Esperar propagación, verificar con `dig MX dominio.co` |
