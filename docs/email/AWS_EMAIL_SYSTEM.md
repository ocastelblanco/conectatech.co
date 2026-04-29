# Sistema de Correo Electrónico con AWS SES

> **Audiencia:** Agente de IA o desarrollador que necesita configurar envío de correo transaccional con AWS SES desde cero, o migrar desde otro proveedor (Brevo, Mailgun, SendGrid, etc.).
>
> **Casos cubiertos:** (A) Moodle en EC2, (B) interfaz propia (Node.js SDK, PHP, Nodemailer vía SMTP).
>
> **Región recomendada:** `us-east-1` — única región que soporta tanto envío como recepción.

---

## Estado actual — conectatech.co

| Componente | Valor |
| --- | --- |
| Dominio verificado | `conectatech.co` |
| Estado de cuenta SES | Producción (`ProductionAccessEnabled: true`) |
| Cuota diaria | 50.000 mensajes/día, 14 msg/seg |
| DKIM | RSA-2048, 3 CNAMEs en Route 53 ✓ |
| SPF | `v=spf1 include:amazonses.com -all` ✓ |
| DMARC | `p=quarantine`, reportes a `somos.conectatech@gmail.com` ✓ |
| IAM SMTP user | `ses-smtp-user.20260425-155404` |
| From address | `no-reply@conectatech.co` (display: ConectaTech) |
| Moodle SMTP host | `email-smtp.us-east-1.amazonaws.com:587` (TLS/STARTTLS) |

---

## 1. Prerrequisitos

- AWS CLI instalado y perfil configurado (este proyecto usa `--profile im`).
- Dominio registrado con zona DNS en Route 53.
- Python 3 disponible en local (para derivar la contraseña SMTP).
- Acceso SSH al servidor si se usa Moodle en EC2.

Verificar acceso:

```bash
aws sts get-caller-identity --profile im
```

---

## 2. Verificar dominio e identidades en SES

Usar la API v2 (`sesv2`) — la v1 (`ses`) está en modo mantenimiento.

### 2.1 Crear identidad de dominio

```bash
aws sesv2 create-email-identity \
  --email-identity midominio.co \
  --profile im
```

La respuesta incluye tres `DkimTokens` para Easy DKIM. Si el dominio ya existía, obtenerlos con:

```bash
aws sesv2 get-email-identity \
  --email-identity midominio.co \
  --profile im \
  --output json | jq '.DkimAttributes.Tokens'
```

### 2.2 Verificar estado

```bash
aws sesv2 get-email-identity \
  --email-identity midominio.co \
  --profile im \
  --output json | jq '{
    VerificationStatus: .VerificationStatus,
    DkimStatus: .DkimAttributes.Status,
    SendingEnabled: .VerifiedForSendingStatus
  }'
```

Esperar hasta obtener `"VerificationStatus": "SUCCESS"` (puede tardar hasta 72 horas tras añadir los registros DNS).

### 2.3 Verificar estado de la cuenta (sandbox vs producción)

```bash
aws sesv2 get-account --profile im --output json | jq '{
  ProductionAccess: .ProductionAccessEnabled,
  SendingEnabled: .SendingEnabled,
  Max24h: .SendQuota.Max24HourSend,
  MaxRate: .SendQuota.MaxSendRate
}'
```

Si `ProductionAccessEnabled` es `false`, la cuenta está en sandbox (solo puede enviar a direcciones verificadas, máx. 200/día). Ver sección 6 para salir del sandbox.

---

## 3. Registros DNS en Route 53

Todos los cambios se hacen en un solo batch para ser atómicos. Obtener el Hosted Zone ID:

```bash
aws route53 list-hosted-zones --profile im \
  --output json | jq '.HostedZones[] | {Name, Id}'
```

### 3.1 Registros requeridos

| Registro | Nombre | Tipo | Valor |
| --- | --- | --- | --- |
| Verificación SES | `_amazonses.midominio.co` | TXT | token de `create-email-identity` |
| DKIM (×3) | `<token>._domainkey.midominio.co` | CNAME | `<token>.dkim.amazonses.com` |
| SPF | `midominio.co` | TXT | `"v=spf1 include:amazonses.com -all"` |
| DMARC | `_dmarc.midominio.co` | TXT | ver abajo |
| MX (recepción) | `midominio.co` | MX | `10 inbound-smtp.us-east-1.amazonaws.com` |

**SPF:** usar `-all` (hard fail) si SES es el único proveedor de envío. Usar `~all` (soft fail) si hay otros.

**DMARC recomendado:**

```text
"v=DMARC1; p=quarantine; rua=mailto:admin@midominio.co; pct=100"
```

- `p=none` → solo monitoreo (no protege)
- `p=quarantine` → correos no conformes van a spam (recomendado para producción)
- `p=reject` → rechaza correos no conformes (máxima protección, activar después de validar)

### 3.2 Script de aplicación (batch Route 53)

Reemplazar `ZONE_ID`, los tres `DkimTokens`, y `VERIFICATION_TOKEN` con los valores reales.

```bash
ZONE_ID="Z0767805255ZNR9CRNWLH"   # hosted zone ID
DOMAIN="midominio.co"
DKIM1="token1aqui"
DKIM2="token2aqui"
DKIM3="token3aqui"
ADMIN_EMAIL="admin@midominio.co"

aws route53 change-resource-record-sets \
  --hosted-zone-id "$ZONE_ID" \
  --profile im \
  --change-batch "{
    \"Changes\": [
      {
        \"Action\": \"UPSERT\",
        \"ResourceRecordSet\": {
          \"Name\": \"${DOMAIN}.\",
          \"Type\": \"TXT\",
          \"TTL\": 300,
          \"ResourceRecords\": [
            {\"Value\": \"\\\"v=spf1 include:amazonses.com -all\\\"\"}
          ]
        }
      },
      {
        \"Action\": \"UPSERT\",
        \"ResourceRecordSet\": {
          \"Name\": \"_dmarc.${DOMAIN}.\",
          \"Type\": \"TXT\",
          \"TTL\": 300,
          \"ResourceRecords\": [
            {\"Value\": \"\\\"v=DMARC1; p=quarantine; rua=mailto:${ADMIN_EMAIL}; pct=100\\\"\"}
          ]
        }
      },
      {
        \"Action\": \"UPSERT\",
        \"ResourceRecordSet\": {
          \"Name\": \"${DKIM1}._domainkey.${DOMAIN}.\",
          \"Type\": \"CNAME\",
          \"TTL\": 300,
          \"ResourceRecords\": [{\"Value\": \"${DKIM1}.dkim.amazonses.com\"}]
        }
      },
      {
        \"Action\": \"UPSERT\",
        \"ResourceRecordSet\": {
          \"Name\": \"${DKIM2}._domainkey.${DOMAIN}.\",
          \"Type\": \"CNAME\",
          \"TTL\": 300,
          \"ResourceRecords\": [{\"Value\": \"${DKIM2}.dkim.amazonses.com\"}]
        }
      },
      {
        \"Action\": \"UPSERT\",
        \"ResourceRecordSet\": {
          \"Name\": \"${DKIM3}._domainkey.${DOMAIN}.\",
          \"Type\": \"CNAME\",
          \"TTL\": 300,
          \"ResourceRecords\": [{\"Value\": \"${DKIM3}.dkim.amazonses.com\"}]
        }
      }
    ]
  }" 2>&1
```

Verificar que el cambio se aplicó:

```bash
aws route53 get-change --id /change/<CHANGE_ID> --profile im \
  --output json | jq '.ChangeInfo.Status'
# "INSYNC" = propagado
```

### 3.3 Migración desde otro proveedor (Brevo, Mailgun, etc.)

Si el dominio tenía otro proveedor de correo, eliminar sus registros **en el mismo batch** que se crean los de SES.

**Trampas al eliminar registros:**

- El TTL del DELETE debe coincidir exactamente con el TTL del registro existente. Consultarlo primero:

```bash
aws route53 list-resource-record-sets \
  --hosted-zone-id "$ZONE_ID" --profile im \
  --output json | jq '.ResourceRecordSets[] | select(.Name | startswith("brevo")) | {Name, TTL}'
```

- Incluir los DELETEs en el mismo batch que los UPSERTs (Route 53 aplica todos o ninguno).

---

## 4. Usuario IAM y credenciales SMTP

### 4.1 Crear usuario IAM

```bash
aws iam create-user \
  --user-name ses-smtp-user \
  --profile im
```

### 4.2 Adjuntar política de envío

```bash
aws iam put-user-policy \
  --user-name ses-smtp-user \
  --policy-name SES-SendRawEmail \
  --policy-document '{
    "Version": "2012-10-17",
    "Statement": [{
      "Effect": "Allow",
      "Action": "ses:SendRawEmail",
      "Resource": "*"
    }]
  }' \
  --profile im
```

### 4.3 Crear access key

```bash
aws iam create-access-key \
  --user-name ses-smtp-user \
  --profile im \
  --output json
```

Guardar `AccessKeyId` y `SecretAccessKey` — la clave secreta **solo se muestra una vez**.

### 4.4 Derivar la contraseña SMTP

⚠️ **La contraseña SMTP de SES no es el `SecretAccessKey` directamente.** Se deriva con un algoritmo específico de 5 rondas HMAC-SHA256 más un byte de versión prefijado.

**Script Python (local):**

```python
#!/usr/bin/env python3
"""
Derivar contraseña SMTP para AWS SES v4.
Uso: python3 derive_ses_smtp_password.py <SECRET_ACCESS_KEY> <REGION>
"""
import sys
import hmac
import hashlib
import base64

def sign(key: bytes, msg: str) -> bytes:
    return hmac.new(key, msg.encode('utf-8'), hashlib.sha256).digest()

def derive_smtp_password(secret_access_key: str, region: str) -> str:
    VERSION = 0x04                    # byte de versión requerido por SES

    sig = sign(("AWS4" + secret_access_key).encode('utf-8'), "11111111")
    sig = sign(sig, region)
    sig = sign(sig, "ses")
    sig = sign(sig, "aws4_request")
    sig = sign(sig, "SendRawEmail")   # ronda final obligatoria

    return base64.b64encode(bytes([VERSION]) + sig).decode('utf-8')

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Uso: python3 derive_ses_smtp_password.py <SECRET_ACCESS_KEY> <REGION>")
        sys.exit(1)
    print(derive_smtp_password(sys.argv[1], sys.argv[2]))
```

Ejecutar:

```bash
python3 derive_ses_smtp_password.py "miSecretAccessKeyAqui" "us-east-1"
# → BJkXNXVKeJr...  (la contraseña SMTP real)
```

**Parámetros SMTP resultantes:**

| Parámetro | Valor |
| --- | --- |
| Host | `email-smtp.us-east-1.amazonaws.com` |
| Puerto | `587` (STARTTLS) |
| Seguridad | `TLS` |
| Auth | `LOGIN` |
| Usuario | `AccessKeyId` (ej. `AKIAZN3NQIOHSQ3T6VYC`) |
| Contraseña | salida del script Python |

---

## 5. Configuración A — Moodle en EC2

### 5.1 Vía CLI (recomendado para automatización)

```bash
MOODLE_CLI="sudo -u apache php /var/www/html/moodle/admin/cli/cfg.php"

$MOODLE_CLI --name=smtphosts      --set="email-smtp.us-east-1.amazonaws.com:587"
$MOODLE_CLI --name=smtpsecure     --set="tls"
$MOODLE_CLI --name=smtpauthtype   --set="LOGIN"
$MOODLE_CLI --name=smtpuser       --set="<AccessKeyId>"
$MOODLE_CLI --name=smtppass       --set="<contraseña derivada>"
$MOODLE_CLI --name=noreplyaddress --set="no-reply@midominio.co"
$MOODLE_CLI --name=noreplyname    --set="NombreDeLaPlataforma"
```

Verificar que se guardó:

```bash
$MOODLE_CLI --name=smtphosts
$MOODLE_CLI --name=smtpuser
$MOODLE_CLI --name=noreplyaddress
```

### 5.2 Vía interfaz web

Administración del sitio → Servidor → Correo electrónico → Correo saliente:

- **Hosts SMTP:** `email-smtp.us-east-1.amazonaws.com:587`
- **Cifrado SMTP:** TLS
- **Tipo de autenticación SMTP:** LOGIN
- **Usuario SMTP:** `<AccessKeyId>`
- **Contraseña SMTP:** `<contraseña derivada>`
- **Sin respuesta (dirección):** `no-reply@midominio.co`

Usar el botón "Probar la configuración del correo saliente" para validar antes de salir.

### 5.3 Enviar correo de prueba desde CLI

```bash
sudo -u apache php -r "
define('CLI_SCRIPT', true);
require('/var/www/html/moodle/config.php');
\$user = \$DB->get_record('user', ['username' => 'admin']);
\$result = email_to_user(
    \$user,
    \$user,
    'Prueba SMTP AWS SES',
    'Correo de prueba enviado correctamente.',
    '<p>Correo de prueba enviado correctamente.</p>'
);
echo \$result ? 'ENVIADO OK' : 'ERROR AL ENVIAR';
"
```

> **Nota:** El warning `chdir(): Permission denied` es inocuo — aparece porque el script se ejecuta desde un directorio al que el usuario `apache` no puede hacer `chdir`, pero no afecta el envío.

---

## 6. Configuración B — Interfaz propia

### 6.1 Node.js con AWS SDK v3 (sin credenciales SMTP)

Para backends en Lambda o EC2 donde el rol IAM ya tiene `ses:SendRawEmail`, usar el SDK directamente — es más eficiente que SMTP.

```javascript
// npm install @aws-sdk/client-ses
import { SESClient, SendEmailCommand } from '@aws-sdk/client-ses';

const ses = new SESClient({ region: 'us-east-1' });

export async function sendEmail({ to, subject, html, text }) {
  const command = new SendEmailCommand({
    Source: 'no-reply@midominio.co',
    Destination: { ToAddresses: Array.isArray(to) ? to : [to] },
    Message: {
      Subject: { Data: subject, Charset: 'UTF-8' },
      Body: {
        Html: { Data: html, Charset: 'UTF-8' },
        ...(text ? { Text: { Data: text, Charset: 'UTF-8' } } : {}),
      },
    },
  });
  return ses.send(command);
}
```

Política IAM mínima para el rol:

```json
{
  "Effect": "Allow",
  "Action": ["ses:SendEmail", "ses:SendRawEmail"],
  "Resource": "*"
}
```

### 6.2 Node.js con Nodemailer (vía SMTP)

Para proyectos que ya usan Nodemailer o que no corren en AWS.

```javascript
// npm install nodemailer
import nodemailer from 'nodemailer';

const transporter = nodemailer.createTransport({
  host: 'email-smtp.us-east-1.amazonaws.com',
  port: 587,
  secure: false,          // false = STARTTLS en puerto 587
  auth: {
    user: process.env.SES_SMTP_USER,   // AccessKeyId
    pass: process.env.SES_SMTP_PASS,   // contraseña derivada (no el SecretAccessKey)
  },
});

await transporter.sendMail({
  from: '"Mi Plataforma" <no-reply@midominio.co>',
  to: 'usuario@example.com',
  subject: 'Bienvenido',
  html: '<p>Tu cuenta fue creada exitosamente.</p>',
});
```

### 6.3 PHP con PHPMailer (vía SMTP)

```php
<?php
require 'vendor/autoload.php'; // composer require phpmailer/phpmailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
$mail->isSMTP();
$mail->Host       = 'email-smtp.us-east-1.amazonaws.com';
$mail->SMTPAuth   = true;
$mail->Username   = $_ENV['SES_SMTP_USER'];  // AccessKeyId
$mail->Password   = $_ENV['SES_SMTP_PASS'];  // contraseña derivada
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
$mail->Port       = 587;
$mail->CharSet    = 'UTF-8';

$mail->setFrom('no-reply@midominio.co', 'Mi Plataforma');
$mail->addAddress('usuario@example.com');
$mail->Subject = 'Bienvenido';
$mail->isHTML(true);
$mail->Body = '<p>Tu cuenta fue creada exitosamente.</p>';
$mail->send();
```

### 6.4 Lambda + Serverless Framework (sin servidor, sin credenciales SMTP)

Este es el camino más limpio para proyectos 100% serverless: Lambda recibe permisos a través de su **rol de ejecución IAM**, por lo que no hay usuario SMTP, no hay access key en variables de entorno, y no se necesita derivar ninguna contraseña.

**Secciones del documento que aplican:** §2, §3, §7, §9.
**Secciones que se omiten:** §4 (usuario IAM / SMTP), §5 (Moodle).

#### Prerequisito: `serverless.yml`

```yaml
# serverless.yml
service: mi-proyecto

provider:
  name: aws
  runtime: nodejs22.x
  region: us-east-1
  stage: ${opt:stage, 'dev'}
  environment:
    SES_FROM_ADDRESS: "no-reply@midominio.co"
    SES_FROM_NAME: "Mi Plataforma"
    SES_REGION: us-east-1
  iam:
    role:
      statements:
        - Effect: Allow
          Action:
            - ses:SendEmail
            - ses:SendRawEmail
          Resource: "*"

functions:
  miHandler:
    handler: src/handler.main
    timeout: 30

plugins:
  - serverless-esbuild          # o serverless-webpack / serverless-plugin-typescript

custom:
  esbuild:
    bundle: true
    minify: false
    target: node22
```

> No hay `SES_SMTP_USER` ni `SES_SMTP_PASS`. El SDK usa automáticamente las credenciales del rol de ejecución.

#### Módulo de correo reutilizable (TypeScript)

Crear `src/lib/email.ts` y reutilizarlo en cualquier handler:

```typescript
// src/lib/email.ts
import {
  SESClient,
  SendEmailCommand,
  SendEmailCommandInput,
} from '@aws-sdk/client-ses';

const ses = new SESClient({ region: process.env.SES_REGION ?? 'us-east-1' });

const FROM = `"${process.env.SES_FROM_NAME}" <${process.env.SES_FROM_ADDRESS}>`;

export interface EmailOptions {
  to: string | string[];
  subject: string;
  html: string;
  text?: string;
  cc?: string | string[];
  replyTo?: string;
}

export async function sendEmail(opts: EmailOptions): Promise<void> {
  const toAddresses = Array.isArray(opts.to) ? opts.to : [opts.to];

  const input: SendEmailCommandInput = {
    Source: FROM,
    Destination: {
      ToAddresses: toAddresses,
      ...(opts.cc ? { CcAddresses: Array.isArray(opts.cc) ? opts.cc : [opts.cc] } : {}),
    },
    Message: {
      Subject: { Data: opts.subject, Charset: 'UTF-8' },
      Body: {
        Html: { Data: opts.html, Charset: 'UTF-8' },
        ...(opts.text ? { Text: { Data: opts.text, Charset: 'UTF-8' } } : {}),
      },
    },
    ...(opts.replyTo ? { ReplyToAddresses: [opts.replyTo] } : {}),
  };

  await ses.send(new SendEmailCommand(input));
}
```

#### Uso en un handler

```typescript
// src/handler.ts
import { sendEmail } from './lib/email';

export const main = async (event: any) => {
  const { email, nombre } = JSON.parse(event.body ?? '{}');

  await sendEmail({
    to: email,
    subject: `Bienvenido, ${nombre}`,
    html: `<h1>Hola ${nombre}</h1><p>Tu cuenta fue creada exitosamente.</p>`,
    text: `Hola ${nombre}. Tu cuenta fue creada exitosamente.`,
  });

  return { statusCode: 200, body: JSON.stringify({ ok: true }) };
};
```

#### Múltiples entornos (dev / staging / prod)

Gestionar distintas direcciones y comportamientos por stage en `serverless.yml`:

```yaml
custom:
  emailConfig:
    dev:
      fromAddress: "no-reply-dev@midominio.co"
      # En dev, redirigir todo a una bandeja de pruebas
      overrideRecipient: "dev-inbox@midominio.co"
    prod:
      fromAddress: "no-reply@midominio.co"
      overrideRecipient: ""

provider:
  environment:
    SES_FROM_ADDRESS: ${self:custom.emailConfig.${self:provider.stage}.fromAddress}
    SES_OVERRIDE_RECIPIENT: ${self:custom.emailConfig.${self:provider.stage}.overrideRecipient}
```

Y en el módulo de correo, respetar el override:

```typescript
const destination = process.env.SES_OVERRIDE_RECIPIENT || recipient;
```

> **Trampas de entornos:** En `dev`, la cuenta SES puede estar en sandbox. Verificar que `SES_OVERRIDE_RECIPIENT` apunte a una dirección verificada. En `prod`, asegurarse de haber salido del sandbox (§7).

#### Manejo de bounces y quejas (SNS)

SES puede notificar rebotes y quejas vía SNS. Ignorarlos deteriora la reputación del dominio.

Añadir en `serverless.yml`:

```yaml
functions:
  sesNotifications:
    handler: src/handlers/ses-notifications.handler
    events:
      - sns:
          arn: arn:aws:sns:us-east-1:${aws:accountId}:ses-notifications
          topicName: ses-notifications
```

Handler mínimo:

```typescript
// src/handlers/ses-notifications.ts
export const handler = async (event: any) => {
  for (const record of event.Records) {
    const message = JSON.parse(record.Sns.Message);

    if (message.notificationType === 'Bounce') {
      const bounced = message.bounce.bouncedRecipients.map((r: any) => r.emailAddress);
      console.error('Bounce:', bounced);
      // Marcar en BD como no contactable
    }

    if (message.notificationType === 'Complaint') {
      const complained = message.complaint.complainedRecipients.map((r: any) => r.emailAddress);
      console.error('Complaint:', complained);
      // Marcar en BD como dado de baja
    }
  }
};
```

Configurar el topic SNS en SES (consola o CLI):

```bash
# Asociar topic SNS al dominio verificado en SES
aws sesv2 put-email-identity-feedback-attributes \
  --email-identity midominio.co \
  --email-forwarding-enabled true \
  --profile im
```

#### Prueba local con `serverless invoke local`

```bash
# Requiere credenciales AWS con permisos ses:SendEmail en el perfil local
serverless invoke local \
  --function miHandler \
  --stage dev \
  --data '{"body":"{\"email\":\"yo@gmail.com\",\"nombre\":\"Oliver\"}"}'
```

O bien, mockear SES en tests unitarios:

```typescript
// tests/email.test.ts (Jest + aws-sdk-mock)
import AWSMock from 'aws-sdk-mock';
import AWS from 'aws-sdk';

beforeAll(() => {
  AWSMock.setSDKInstance(AWS);
  AWSMock.mock('SES', 'sendEmail', (_params: any, callback: Function) => {
    callback(null, { MessageId: 'mock-id' });
  });
});

afterAll(() => AWSMock.restore('SES'));
```

> Para AWS SDK v3 (recomendado), usar `@aws-sdk/client-ses` con el paquete `aws-sdk-client-mock`:
>
> ```bash
> npm install --save-dev aws-sdk-client-mock
> ```

#### Checklist específico para Lambda + Serverless

- [ ] `serverless.yml` tiene `iam.role.statements` con `ses:SendEmail` y `ses:SendRawEmail`
- [ ] No hay `SES_SMTP_USER` ni `SES_SMTP_PASS` en variables de entorno (no se necesitan)
- [ ] `SES_FROM_ADDRESS` usa una dirección del dominio verificado
- [ ] En `dev`: `SES_OVERRIDE_RECIPIENT` apunta a una dirección verificada (si está en sandbox)
- [ ] En `prod`: cuenta SES en producción (§7)
- [ ] Bounce/complaint handler configurado (recomendado antes de envíos masivos)
- [ ] `timeout` del handler ≥ 10s (SES puede tardar 2–5s en responder bajo carga)

---

## 7. Solicitar salida del Sandbox

Mientras la cuenta esté en sandbox, solo se puede enviar a direcciones verificadas y el límite es 200 correos/día.

```bash
# Verificar estado actual
aws sesv2 get-account --profile im \
  --output json | jq '.ProductionAccessEnabled'
# false = sandbox, true = producción
```

Para solicitar producción, ir a la consola SES → Account dashboard → **Request production access** y proporcionar:

- **Mail type:** Transactional
- **Website URL:** `https://midominio.co`
- **Use case description:** Describir los flujos de correo (registro, recuperación de contraseña, notificaciones).
- **Additional contacts:** Correo del responsable técnico.

La aprobación suele tardar 24–72 horas. AWS puede pedir aclaraciones por email.

---

## 8. Recepción de correo (inbound)

Ver [redireccion-emails.md](redireccion-emails.md) para las direcciones configuradas.

El flujo es: `Remitente → MX SES → S3 Bucket → Lambda → Gmail`.

El registro MX requerido en Route 53:

```text
midominio.co.  MX  10 inbound-smtp.us-east-1.amazonaws.com
```

Ver secciones 6.1–6.5 del documento original para el código Lambda de reenvío (aún válido).

---

## 9. Verificación end-to-end

### 9.1 Verificar DNS propagado

```bash
# SPF
dig TXT midominio.co +short

# DKIM (reemplazar con uno de los tres tokens)
dig CNAME <token>._domainkey.midominio.co +short

# DMARC
dig TXT _dmarc.midominio.co +short

# MX
dig MX midominio.co +short
```

### 9.2 Verificar estado en SES

```bash
aws sesv2 get-email-identity \
  --email-identity midominio.co \
  --profile im \
  --output json | jq '{
    Verificado: .VerificationStatus,
    DKIM: .DkimAttributes.Status,
    Envio: .VerifiedForSendingStatus
  }'
```

Los tres campos deben ser `SUCCESS` / `true`.

### 9.3 Enviar correo de prueba vía AWS CLI

```bash
aws sesv2 send-email \
  --from-email-address no-reply@midominio.co \
  --destination ToAddresses=mi-correo-verificado@gmail.com \
  --content '{
    "Simple": {
      "Subject": {"Data": "Prueba SES"},
      "Body": {"Text": {"Data": "Correo de prueba desde AWS SES CLI"}}
    }
  }' \
  --profile im
```

### 9.4 Ver métricas de envío

```bash
aws sesv2 get-account --profile im \
  --output json | jq '.SendQuota'
```

---

## 10. Trampas comunes — errores reales cometidos

### ❌ Error 1: Contraseña SMTP derivada incorrectamente

**Síntoma:** `SMTP connect() failed` en PHPMailer/Nodemailer aunque las credenciales parecen correctas.

**Causa:** La contraseña SMTP de SES **no es** el `SecretAccessKey` directamente ni una derivación simple. Requiere exactamente 5 rondas de HMAC-SHA256 más un byte de versión `0x04` prefijado.

**Algoritmo correcto:**

```text
1. sign("AWS4" + secret_key, "11111111")
2. sign(resultado, region)               # ej. "us-east-1"
3. sign(resultado, "ses")
4. sign(resultado, "aws4_request")
5. sign(resultado, "SendRawEmail")       ← ronda que más se olvida
6. base64( bytes([0x04]) + resultado )   ← byte de versión que más se olvida
```

Ver el script completo en la sección 4.4.

### ❌ Error 2: DELETE en Route 53 falla con "values do not match"

**Síntoma:** `InvalidChangeBatch: Tried to delete resource record set [...] but the values provided do not match`.

**Causa:** El TTL especificado en el DELETE no coincide con el TTL real del registro en Route 53.

**Solución:** Consultar el TTL exacto antes de construir el batch:

```bash
aws route53 list-resource-record-sets \
  --hosted-zone-id "$ZONE_ID" --profile im \
  --output json | jq '.ResourceRecordSets[] | select(.Name == "registro-a-borrar.midominio.co.") | .TTL'
```

Usar ese TTL exacto en el `Action: DELETE`.

### ❌ Error 3: Usuario IAM creado sin política

**Síntoma:** El usuario SMTP existe y tiene access key activa, pero SES rechaza los mensajes con `AccessDenied`.

**Causa:** La consola de SES crea el usuario IAM pero a veces la política no se adjunta si se interrumpe el flujo de creación.

**Verificación:**

```bash
aws iam list-user-policies --user-name ses-smtp-user --profile im
aws iam list-attached-user-policies --user-name ses-smtp-user --profile im
```

Si ambas listas están vacías, adjuntar la política manualmente (sección 4.2).

### ❌ Error 4: SPF con `~all` en lugar de `-all`

**Síntoma:** Los correos pasan SPF pero con resultado `softfail`, lo que reduce la reputación.

**Causa:** Migrar el registro SPF de un proveedor anterior con `~all` (soft fail) sin actualizarlo.

**Solución:** Si SES es el único servidor de envío, usar `-all` (hard fail).

### ❌ Error 5: Pipe a `jq` cuando AWS CLI devuelve error

**Síntoma:** Error `jq: parse error: Invalid numeric literal` en lugar del error real de AWS.

**Causa:** Cuando AWS CLI falla, escribe a stderr y devuelve texto plano en lugar de JSON. `jq` intenta parsear ese texto y falla, ocultando el error original.

**Solución:** Capturar stderr con `2>&1` y no pipear a `jq` en comandos que pueden fallar. O usar `python3 -c "import sys,json; ..."` que es más robusto.

### ❌ Error 6: SSH timeout por IP dinámica

**Síntoma:** `ssh: connect to host X.X.X.X port 22: Operation timed out`.

**Causa:** El Security Group tiene una regla SSH que permite solo una IP fija. Si la IP del cliente cambia (ISP con IP dinámica), la conexión es bloqueada sin error explícito.

**Solución rápida:**

```bash
MY_IP=$(curl -s https://checkip.amazonaws.com)
OLD_IP=$(aws ec2 describe-security-groups \
  --group-ids sg-XXXXXXXXX --profile im \
  --output json | python3 -c "
import sys,json
sg=json.load(sys.stdin)['SecurityGroups'][0]
for r in sg['IpPermissions']:
    if r.get('FromPort')==22:
        print(r['IpRanges'][0]['CidrIp'])
")

aws ec2 revoke-security-group-ingress \
  --group-id sg-XXXXXXXXX --protocol tcp --port 22 \
  --cidr "$OLD_IP" --profile im

aws ec2 authorize-security-group-ingress \
  --group-id sg-XXXXXXXXX --protocol tcp --port 22 \
  --cidr "$MY_IP/32" --profile im
```

---

## 11. Checklist de implementación

### DNS y SES

- [ ] Dominio creado como identidad en SES (`sesv2 create-email-identity`)
- [ ] TXT `_amazonses.midominio.co` en Route 53
- [ ] 3 CNAMEs DKIM (`<token>._domainkey.midominio.co`) en Route 53
- [ ] TXT SPF en raíz del dominio (`v=spf1 include:amazonses.com -all`)
- [ ] TXT DMARC (`_dmarc.midominio.co`) con política adecuada
- [ ] MX apuntando a `inbound-smtp.us-east-1.amazonaws.com` (si se usa recepción)
- [ ] `VerificationStatus: SUCCESS` y `DkimStatus: SUCCESS` confirmados

### IAM y credenciales

- [ ] Usuario IAM creado
- [ ] Política `ses:SendRawEmail` adjuntada (verificar con `list-user-policies`)
- [ ] Access key creada y secret guardado en lugar seguro
- [ ] Contraseña SMTP derivada con el script de la sección 4.4
- [ ] Cuenta SES en producción (`ProductionAccessEnabled: true`)

### Moodle (si aplica)

- [ ] `smtphosts` configurado vía `cfg.php`
- [ ] `smtpsecure = tls` y `smtpauthtype = LOGIN`
- [ ] `noreplyaddress` y `noreplyname` configurados
- [ ] Correo de prueba enviado y recibido

### Interfaz propia (si aplica)

- [ ] Variables de entorno `SES_SMTP_USER` / `SES_SMTP_PASS` configuradas en el servidor
- [ ] Correo de prueba enviado y recibido
- [ ] Verificar que no hay credenciales en el repositorio git

---

## 12. Costos de referencia

| Servicio | Costo |
| --- | --- |
| Envío desde EC2 | 62.000 msgs/mes gratis, luego $0.10/1.000 |
| Envío desde Lambda | $0.10/1.000 msgs |
| Recepción | $0.10/1.000 msgs |
| S3 (correos recibidos) | ~$0 para volúmenes normales |

Para 2.000 correos/mes en producción: **< $1 USD/mes**.
