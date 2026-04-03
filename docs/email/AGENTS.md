# AGENTS.md — Instrucciones para Agentes IA

> Este documento define las instrucciones, convenciones y contexto que un agente IA (Claude Code, Cursor, Copilot Workspace, etc.) debe seguir al implementar o mantener el sistema de correo electrónico AWS descrito en `AWS_EMAIL_SYSTEM.md`.

---

## Identidad del Proyecto

- **Propietario:** Oliver Castelblanco
- **Proyectos cubiertos:** ACG, conectatech.co, letiende.co
- **Stack principal:** Angular (frontend), PHP y Node.js/TypeScript (backends), AWS (infraestructura)
- **Infraestructura de correo:** Amazon SES + S3 + Lambda + Route 53
- **Región AWS primaria:** `us-east-1`

---

## Convenciones Generales

### Idioma

- Código: inglés (variables, funciones, comentarios inline).
- Documentación y commits: español.
- Nombres de recursos AWS: inglés, con prefijo del proyecto (`acg-`, `conectatech-`, `letiende-`).

### Nomenclatura de Recursos AWS

```
Patrón: {proyecto}-{servicio}-{propósito}

Ejemplos:
  letiende-ses-incoming-emails       (bucket S3)
  conectatech-email-forwarder        (función Lambda)
  acg-ses-receipt-rules              (rule set de SES)
```

### Variables de Entorno

Nunca hardcodear credenciales ni ARNs. Usar variables de entorno o AWS Systems Manager Parameter Store.

```
SES_REGION=us-east-1
SES_FROM_ADDRESS=no-reply@{dominio}
S3_INCOMING_BUCKET={proyecto}-ses-incoming-emails
FORWARD_MAP={"info@dominio.co":["admin@gmail.com"]}
```

---

## Tareas y Comandos para el Agente

### Tarea 1: Verificar dominio en SES

**Contexto:** Se necesita que SES pueda enviar y recibir correos para un dominio.

**Pasos:**
1. Ejecutar `aws ses verify-domain-identity --domain {DOMINIO} --region us-east-1`.
2. Tomar el `VerificationToken` de la respuesta.
3. Crear registro TXT en Route 53:
   - Nombre: `_amazonses.{DOMINIO}`
   - Valor: el token obtenido.
4. Esperar verificación: `aws ses get-identity-verification-attributes --identities {DOMINIO} --region us-east-1`.
5. Verificar que el status sea `Success`.

**Validación:** El comando del paso 4 debe retornar `"VerificationStatus": "Success"`.

---

### Tarea 2: Configurar DKIM

**Pasos:**
1. Ejecutar `aws ses verify-domain-dkim --domain {DOMINIO} --region us-east-1`.
2. Crear 3 registros CNAME en Route 53 con los tokens retornados.
3. Verificar: `aws ses get-identity-dkim-attributes --identities {DOMINIO} --region us-east-1`.

**Validación:** `"DkimVerificationStatus": "Success"` y `"DkimEnabled": true`.

---

### Tarea 3: Configurar registro MX

**Pasos:**
1. Obtener el Hosted Zone ID del dominio en Route 53.
2. Crear un registro MX:
   - Nombre: `{DOMINIO}`
   - Tipo: MX
   - Valor: `10 inbound-smtp.us-east-1.amazonaws.com`

**Validación:** `dig MX {DOMINIO}` debe resolver al endpoint de SES.

---

### Tarea 4: Crear bucket S3 para correos entrantes

**Pasos:**
1. Crear bucket: `aws s3 mb s3://{PROYECTO}-ses-incoming-emails --region us-east-1`.
2. Aplicar bucket policy que permita `s3:PutObject` desde `ses.amazonaws.com`.
3. Verificar con: `aws s3api get-bucket-policy --bucket {PROYECTO}-ses-incoming-emails`.

**Validación:** La política debe incluir el principal `ses.amazonaws.com` con acción `s3:PutObject`.

---

### Tarea 5: Desplegar función Lambda de reenvío

**Contexto:** La función lee correos de S3 y los reenvía vía SES.

**Pasos:**
1. Crear directorio del proyecto Lambda.
2. Escribir `index.mjs` siguiendo el código de referencia en `AWS_EMAIL_SYSTEM.md`, sección 6.2.
3. Configurar variables de entorno: `FORWARD_MAP`, `FORWARDER_ADDRESS`.
4. Crear rol IAM con permisos: `s3:GetObject`, `ses:SendRawEmail`, `logs:CreateLogGroup`, `logs:CreateLogStream`, `logs:PutLogEvents`.
5. Desplegar la función:
   ```bash
   zip -r function.zip index.mjs
   aws lambda create-function \
     --function-name {PROYECTO}-email-forwarder \
     --runtime nodejs20.x \
     --handler index.handler \
     --role arn:aws:iam::{ACCOUNT_ID}:role/{PROYECTO}-email-forwarder-role \
     --zip-file fileb://function.zip \
     --timeout 30 \
     --memory-size 256 \
     --region us-east-1
   ```
6. Configurar trigger S3 → Lambda.

**Validación:**
- `aws lambda invoke --function-name {PROYECTO}-email-forwarder /dev/stdout` no retorna errores de permisos.
- Enviar un correo de prueba a `test@{DOMINIO}` y verificar que llega al destino configurado.

---

### Tarea 6: Crear reglas de recepción en SES

**Pasos:**
1. Crear rule set: `aws ses create-receipt-rule-set --rule-set-name {PROYECTO}-rules`.
2. Activar rule set: `aws ses set-active-receipt-rule-set --rule-set-name {PROYECTO}-rules`.
3. Crear regla con acción S3 apuntando al bucket.

**Validación:** `aws ses describe-active-receipt-rule-set` debe mostrar el rule set activo con la regla configurada.

---

### Tarea 7: Integrar envío de correo en el backend

**Para backends en EC2 (PHP/Node.js con SMTP):**
1. Generar credenciales SMTP en la consola de SES.
2. Configurar la aplicación con host, puerto 587, TLS y credenciales.
3. Probar envío a una dirección verificada.

**Para backends en Lambda (Node.js/TypeScript con SDK):**
1. Agregar permisos `ses:SendEmail` y `ses:SendRawEmail` al rol IAM de la función.
2. Usar `@aws-sdk/client-ses` con `SendEmailCommand`.
3. Configurar variable de entorno `SES_FROM_ADDRESS`.
4. Probar envío.

**Validación:** Correo recibido en la bandeja de entrada del destinatario (no en spam).

---

### Tarea 8: Solicitar salida del sandbox

**Contexto:** Mientras SES esté en sandbox, solo se pueden enviar correos a direcciones verificadas (máximo 200/día).

**Pasos:**
1. Verificar estado: `aws ses get-account --region us-east-1` → buscar `ProductionAccessEnabled`.
2. Si es `false`, solicitar acceso de producción desde la consola de SES.
3. Proporcionar: tipo de correo (transaccional), URL del proyecto, volumen estimado, proceso de manejo de bounces.

**Este paso requiere intervención humana** (aprobación de AWS, 24-48h). El agente debe notificar al usuario y esperar confirmación.

---

## Reglas para el Agente

### Seguridad

- **NUNCA** incluir credenciales, access keys o secret keys en código fuente o commits.
- Usar variables de entorno o Parameter Store para toda configuración sensible.
- Los roles IAM deben seguir el principio de mínimo privilegio.
- Toda comunicación con SES debe usar TLS.

### Infraestructura como Código (IaC)

Cuando sea posible, preferir la creación de recursos mediante:
1. **AWS CDK (TypeScript)** — preferido para consistencia con el stack.
2. **AWS CloudFormation** — alternativa aceptable.
3. **AWS CLI** — solo para operaciones puntuales o verificaciones.

Ejemplo de estructura CDK para el sistema de correo:

```typescript
// lib/email-stack.ts
import * as cdk from 'aws-cdk-lib';
import * as s3 from 'aws-cdk-lib/aws-s3';
import * as lambda from 'aws-cdk-lib/aws-lambda';
import * as iam from 'aws-cdk-lib/aws-iam';
import * as s3n from 'aws-cdk-lib/aws-s3-notifications';

export class EmailStack extends cdk.Stack {
  constructor(scope: cdk.App, id: string, props?: cdk.StackProps) {
    super(scope, id, props);

    const bucket = new s3.Bucket(this, 'IncomingEmails', {
      bucketName: `${id.toLowerCase()}-ses-incoming-emails`,
      removalPolicy: cdk.RemovalPolicy.RETAIN,
    });

    const forwarder = new lambda.Function(this, 'EmailForwarder', {
      runtime: lambda.Runtime.NODEJS_20_X,
      handler: 'index.handler',
      code: lambda.Code.fromAsset('lambda/email-forwarder'),
      timeout: cdk.Duration.seconds(30),
      memorySize: 256,
      environment: {
        FORWARD_MAP: JSON.stringify({
          'info@dominio.co': ['admin@gmail.com'],
        }),
        FORWARDER_ADDRESS: 'forwarder@dominio.co',
      },
    });

    bucket.grantRead(forwarder);
    forwarder.addToRolePolicy(new iam.PolicyStatement({
      actions: ['ses:SendRawEmail'],
      resources: ['*'],
    }));

    bucket.addEventNotification(
      s3.EventType.OBJECT_CREATED,
      new s3n.LambdaDestination(forwarder),
      { prefix: 'incoming/' }
    );
  }
}
```

### Testing

- Antes de desplegar, probar la función Lambda localmente con un evento S3 simulado.
- Usar `aws ses send-test-email` cuando esté disponible.
- Verificar logs en CloudWatch después de cada prueba.
- Confirmar que los correos no caen en spam (revisar headers SPF, DKIM, DMARC).

### Monitoreo

Configurar alarmas en CloudWatch para:
- Errores en la función Lambda (`Errors` metric > 0).
- Bounces de SES (`Bounce` metric).
- Complaints de SES (`Complaint` metric).

---

## Estructura de Archivos Esperada

```
infrastructure/
├── email/
│   ├── README.md                  # → AWS_EMAIL_SYSTEM.md
│   ├── AGENTS.md                  # Este archivo
│   ├── docs/
│   ├── cdk/
│   │   ├── bin/
│   │   │   └── app.ts
│   │   ├── lib/
│   │   │   └── email-stack.ts
│   │   ├── package.json
│   │   └── tsconfig.json
│   ├── lambda/
│   │   └── email-forwarder/
│   │       ├── index.mjs
│   │       └── package.json
│   └── scripts/
│       ├── verify-domain.sh
│       ├── setup-dkim.sh
│       ├── create-receipt-rules.sh
│       └── test-send.sh
```

---

## Dependencias del Agente

El agente necesita acceso a:

- **AWS CLI v2** configurado con credenciales del usuario.
- **Node.js 24+** para desarrollo de funciones Lambda.
- **AWS CDK** (`npm install -g aws-cdk`) si se usa IaC.
- **jq** para parsear respuestas JSON de AWS CLI.

---

## Flujo de Trabajo del Agente

```
1. Leer AWS_EMAIL_SYSTEM.md completo
2. Identificar el proyecto objetivo (ACG / conectatech / letiende)
3. Verificar prerrequisitos (AWS CLI, región, zona DNS)
4. Ejecutar Tareas 1-4 (DNS y verificación) → esperar propagación
5. Ejecutar Tareas 5-6 (Lambda y reglas SES)
6. Ejecutar Tarea 7 (integración con backend)
7. Notificar al usuario para Tarea 8 (sandbox — requiere humano)
8. Ejecutar pruebas end-to-end
9. Configurar monitoreo
```

> **Importante:** Las tareas 1-4 tienen dependencias de propagación DNS (hasta 48h). El agente debe verificar el estado antes de continuar con las tareas siguientes.
