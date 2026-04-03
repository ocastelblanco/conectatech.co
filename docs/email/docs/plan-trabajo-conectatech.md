# Plan de Trabajo — Sistema de Correo AWS para conectatech.co

> Documento generado el 2026-03-16 tras verificación del estado actual de la infraestructura.

---

## 1. Diagnóstico del Estado Actual

### Resultado de la verificación

| Componente | Estado | Detalle |
|---|---|---|
| **SES — dominio** | ❌ Sin verificar | `conectatech.co` no tiene ninguna identidad registrada en SES |
| **SES — sandbox** | ⚠️ Activo | `Max24HourSend = 200`, solo se puede enviar a direcciones verificadas |
| **SES — rule sets** | ❌ Vacío | Sin reglas de recepción configuradas |
| **Route 53 — TXT** | ❌ Falta | Sin registro `_amazonses.conectatech.co` |
| **Route 53 — MX** | ❌ Falta | Sin registro MX en el dominio raíz |
| **Route 53 — DKIM** | ❌ Falta | Sin CNAMEs `*._domainkey.conectatech.co` |
| **S3 — bucket emails** | ❌ Falta | No existe bucket de correos entrantes |
| **Lambda — forwarder** | ❌ Falta | No existe función de reenvío |
| **IAM — rol forwarder** | ❌ Falta | No existe rol para la Lambda de correo |
| **Moodle — SMTP** | ⚠️ Sin verificar | SSH no accesible (IP cambió); presumiblemente sin configurar |

### Infraestructura AWS existente (no afectada)

| Recurso | Nombre/ARN | Uso |
|---|---|---|
| Lambda | `conectatech-api-pdfs` (nodejs22.x) | API de PDFs — no se toca |
| IAM Role | `conectatech-api-lambda-role` | Para Lambda de PDFs — no se toca |
| IAM Role | `conectatech-prod-ec2-role-*` | Para instancia EC2 — no se toca |
| S3 | `assets.conectatech.co` | CDN de assets — no se toca |
| Route 53 | Zona `Z0767805255ZNR9CRNWLH` | Se agregan registros; los existentes no se modifican |

### Registros DNS existentes (se preservan)

```
conectatech.co.                 A       → 54.86.113.27
admin.conectatech.co.           A       → 54.86.113.27
assets.conectatech.co.          A       → CloudFront
api.conectatech.co.             CNAME   → API Gateway
_*.api / _*.assets              CNAME   → validaciones ACM (no tocar)
```

---

## 2. Recursos a Crear

| Recurso | Nombre | Notas |
|---|---|---|
| SES Identity | `conectatech.co` | Dominio completo |
| SES Identity | `no-reply@conectatech.co` | Remitente para Moodle |
| SES Identity | `forwarder@conectatech.co` | Remitente para Lambda |
| Route 53 TXT | `_amazonses.conectatech.co` | Verificación dominio SES |
| Route 53 CNAME ×3 | `<token>._domainkey.conectatech.co` | DKIM |
| Route 53 MX | `conectatech.co` | Recepción SES |
| S3 Bucket | `conectatech-ses-incoming-emails` | Almacén de correos entrantes |
| IAM Role | `conectatech-email-forwarder-role` | Permisos para Lambda |
| Lambda | `conectatech-email-forwarder` | nodejs22.x, reenvío a Gmail |
| SES Rule Set | `conectatech-rules` | Reglas de recepción |
| SES Receipt Rule | `forward-to-gmail` | Dentro de `conectatech-rules` |

---

## 3. Tabla de Redirecciones

Configuración a implementar en el `FORWARD_MAP` de la Lambda (fuente: `redireccion-emails.md`):

| Dirección entrante | Destino Gmail |
|---|---|
| `info@conectatech.co` | `ideasmaestrasltda@gmail.com` |
| `admin@conectatech.co` | `ocastelblanco@gmail.com` |
| `ventas@conectatech.co` | `ajumoto@gmail.com` |
| `ana.mora@conectatech.co` | `ajumoto@gmail.com` |
| `oliver.castelblanco@conectatech.co` | `ocastelblanco@gmail.com` |
| `@conectatech.co` (catch-all) | `ideasmaestrasltda@gmail.com` |

---

## 4. Advertencia crítica — Sandbox SES

Mientras la cuenta esté en sandbox (`Max24HourSend = 200`):

- **Solo se puede enviar correos a direcciones verificadas en SES.**
- El reenvío de Lambda fallará silenciosamente si el Gmail de destino no está verificado.
- **Para las pruebas en sandbox**, hay que verificar los 3 Gmail de destino como identidades en SES:
  - `ideasmaestrasltda@gmail.com`
  - `ocastelblanco@gmail.com`
  - `ajumoto@gmail.com`

La solicitud de salida del sandbox (Fase 4) requiere intervención humana y aprobación de AWS (24–48h). Sin ella, el sistema no es funcional en producción.

---

## 5. Plan de Trabajo por Fases

### Fase 0 — Preparación (≈15 min)
*Prerequisito para SSH a EC2 y para pruebas en sandbox.*

| # | Acción | Responsable | Comando / Detalle |
|---|--------|-------------|-------------------|
| 0.1 | Actualizar SG para SSH | Agente | `aws ec2 authorize-security-group-ingress --group-id sg-039bcb1cb3a57db7f --protocol tcp --port 22 --cidr 186.154.53.75/32 --profile im` (revocar IP anterior primero) |
| 0.2 | Verificar config SMTP de Moodle | Agente | SSH → `php /var/www/html/moodle/admin/cli/cfg.php --name=smtphosts` |
| 0.3 | Verificar Gmail destinos en SES | Agente | `aws ses verify-email-identity` para los 3 Gmail (necesario para pruebas en sandbox) |

---

### Fase 1 — Verificación DNS y SES (≈30 min + espera propagación)

> ⚠️ **Dependencia de tiempo:** Los registros DNS pueden tardar hasta 48h en propagar. SES verifica el dominio en minutos tras la propagación. Ejecutar esta fase primero y continuar con Fase 2 en paralelo.

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 1.1 | Verificar dominio `conectatech.co` en SES | Agente | `aws ses verify-domain-identity --domain conectatech.co` → obtener VerificationToken |
| 1.2 | Crear TXT `_amazonses.conectatech.co` | Agente | Route 53, zona `Z0767805255ZNR9CRNWLH`, valor = VerificationToken de 1.1 |
| 1.3 | Habilitar DKIM | Agente | `aws ses verify-domain-dkim --domain conectatech.co` → obtener 3 DkimTokens |
| 1.4 | Crear 3 CNAMEs DKIM | Agente | `<token>._domainkey.conectatech.co → <token>.dkim.amazonses.com` ×3 en Route 53 |
| 1.5 | Crear registro MX | Agente | Route 53: `conectatech.co MX 10 inbound-smtp.us-east-1.amazonaws.com` |
| 1.6 | Verificar email `no-reply@conectatech.co` | Agente | `aws ses verify-email-identity --email-address no-reply@conectatech.co` → confirmar enlace recibido |
| 1.7 | Verificar email `forwarder@conectatech.co` | Agente | Mismo proceso que 1.6 |
| 1.8 | Confirmar verificación del dominio | Agente | `aws ses get-identity-verification-attributes --identities conectatech.co` → esperar `"VerificationStatus": "Success"` |
| 1.9 | Confirmar DKIM | Agente | `aws ses get-identity-dkim-attributes --identities conectatech.co` → esperar `"DkimVerificationStatus": "Success"` |

**Validación de Fase 1:**
- `VerificationStatus: Success` para `conectatech.co`
- `DkimEnabled: true` y `DkimVerificationStatus: Success`
- `dig MX conectatech.co` resuelve a `inbound-smtp.us-east-1.amazonaws.com`
- Emails `no-reply@` y `forwarder@` con estado `Success`

---

### Fase 2 — Infraestructura Inbound (≈45 min)

*Se puede ejecutar mientras se espera la propagación DNS de Fase 1, excepto los pasos 2.5 y 2.6 que requieren que el dominio esté verificado.*

#### 2A — Bucket S3

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.1 | Crear bucket `conectatech-ses-incoming-emails` | Agente | `aws s3 mb s3://conectatech-ses-incoming-emails --region us-east-1` |
| 2.2 | Aplicar bucket policy | Agente | `Principal: ses.amazonaws.com`, `Action: s3:PutObject`, condición `AWS:SourceAccount: 648232846223` |

#### 2B — Rol IAM

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.3 | Crear rol `conectatech-email-forwarder-role` | Agente | Trust policy: `lambda.amazonaws.com` |
| 2.4 | Adjuntar política inline | Agente | Permisos: `s3:GetObject` en bucket, `ses:SendRawEmail` en `*`, `logs:CreateLogGroup/Stream/PutLogEvents` |

#### 2C — Lambda

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.5 | Escribir `index.mjs` | Agente | Node.js 22.x; FORWARD_MAP con las 6 reglas de `redireccion-emails.md`; `FORWARDER_ADDRESS = forwarder@conectatech.co` |
| 2.6 | Empaquetar y desplegar | Agente | `zip function.zip index.mjs && aws lambda create-function --function-name conectatech-email-forwarder --runtime nodejs22.x ...` |
| 2.7 | Configurar variables de entorno | Agente | Variables en la función: ninguna hardcodeada; FORWARD_MAP va en el código (no sensible) |

#### 2D — SES Receipt Rules (requiere dominio verificado de Fase 1)

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.8 | Crear rule set `conectatech-rules` | Agente | `aws ses create-receipt-rule-set --rule-set-name conectatech-rules` |
| 2.9 | Activar rule set | Agente | `aws ses set-active-receipt-rule-set --rule-set-name conectatech-rules` |
| 2.10 | Crear regla `forward-to-gmail` | Agente | Recipients: `["conectatech.co"]`, acción S3 → `conectatech-ses-incoming-emails`, prefix `incoming/`, ScanEnabled: true |

#### 2E — Trigger S3 → Lambda

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.11 | Añadir permiso Lambda | Agente | `aws lambda add-permission` para que `s3.amazonaws.com` invoque `conectatech-email-forwarder` |
| 2.12 | Configurar notificación en bucket | Agente | `aws s3api put-bucket-notification-configuration`, evento `s3:ObjectCreated:*`, prefix `incoming/` |

**Validación de Fase 2:**
- `aws ses describe-active-receipt-rule-set` muestra `conectatech-rules` activo con la regla
- `aws lambda get-function --function-name conectatech-email-forwarder` retorna estado `Active`
- `aws s3api get-bucket-notification-configuration --bucket conectatech-ses-incoming-emails` muestra el trigger

---

### Fase 3 — Outbound desde Moodle (≈15 min)

*Requiere SSH habilitado (Fase 0.1) y dominio verificado en SES (Fase 1).*

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 3.1 | Generar credenciales SMTP | Humano | SES Console → SMTP Settings → Create SMTP Credentials → guardar user/pass |
| 3.2 | Configurar Moodle SMTP | Agente | SSH → `php admin/cli/cfg.php --name=smtphosts --set=email-smtp.us-east-1.amazonaws.com:587` |
| 3.3 | Configurar usuario SMTP en Moodle | Agente | `--name=smtpuser --set=<SMTP_ACCESS_KEY>` |
| 3.4 | Configurar contraseña SMTP | Agente | `--name=smtppass --set=<SMTP_SECRET_KEY>` |
| 3.5 | Configurar dirección remitente | Agente | `--name=noreplyaddress --set=no-reply@conectatech.co` |
| 3.6 | Configurar protocolo TLS | Agente | `--name=smtpsecure --set=tls` |

> **Nota:** Las credenciales SMTP se generan en la consola AWS (no mediante CLI) y se pasan al agente de forma segura. No se almacenan en el repositorio.

**Validación de Fase 3:**
- Moodle Admin → Servidor → Correo saliente → Enviar correo de prueba → recibido exitosamente.

---

### Fase 4 — Salida del Sandbox *(requiere intervención humana)*

> ⏱️ **Bloqueo de 24–48h.** Sin completar esta fase, el sistema no funciona en producción.

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 4.1 | Verificar estado del sandbox | Agente | `aws ses get-send-quota --profile im --region us-east-1` → confirmar `Max24HourSend = 200` |
| 4.2 | Abrir solicitud de producción | **Humano** | SES Console → Account dashboard → Request production access |
| 4.3 | Completar formulario | **Humano** | Ver datos sugeridos más abajo |
| 4.4 | Esperar aprobación | **Humano** | AWS responde en 24–48h |
| 4.5 | Confirmar salida del sandbox | Agente | `aws ses get-send-quota` → `Max24HourSend >> 200` |

**Datos sugeridos para el formulario:**
- **Tipo de correo:** Transaccional
- **Website URL:** `https://conectatech.co`
- **Descripción:** Plataforma de aprendizaje online. Se envían notificaciones de matrícula, recuperación de contraseña, y recordatorios de actividades desde Moodle. Volumen estimado: 200–1,000 correos/día.
- **Proceso de bounces/complaints:** Monitoreo activo vía CloudWatch Alarms; supresión automática de lista de rebotes habilitada.

---

### Fase 5 — Pruebas End-to-End y Monitoreo (≈30 min)

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 5.1 | Prueba outbound Moodle | Agente/Humano | Moodle → Admin → Correo de prueba → verificar llegada en bandeja (no spam) |
| 5.2 | Prueba inbound `info@` | Humano | Enviar correo a `info@conectatech.co` desde Gmail externo → verificar llegada en `ideasmaestrasltda@gmail.com` |
| 5.3 | Prueba inbound `admin@` | Humano | Enviar a `admin@conectatech.co` → verificar en `ocastelblanco@gmail.com` |
| 5.4 | Prueba catch-all | Humano | Enviar a `algo-inexistente@conectatech.co` → verificar en `ideasmaestrasltda@gmail.com` |
| 5.5 | Revisar headers | Humano | En Gmail: "Mostrar original" → verificar SPF pass, DKIM pass, no spam |
| 5.6 | Crear alarma Lambda errors | Agente | CloudWatch: `conectatech-email-forwarder Errors > 0`, SNS → `ocastelblanco@gmail.com` |
| 5.7 | Crear alarma SES bounces | Agente | CloudWatch: métrica `Bounce` de SES > 5% |
| 5.8 | Crear alarma SES complaints | Agente | CloudWatch: métrica `Complaint` de SES > 0 |

---

## 6. Diagrama de Dependencias

```
Fase 0 (preparación)
  │
  ├─→ Fase 1 (DNS + SES) ──────────────────────────┐
  │     │                                           │
  │     │ (propagación DNS, hasta 48h)              │
  │     ↓                                           ↓
  │   Fase 2A/B/C (S3 + IAM + Lambda)        Fase 2D/E (SES rules + trigger)
  │     │                                           │
  │     └───────────────────┬─────────────────────┘
  │                         ↓
  ├─→ Fase 3 (Moodle SMTP) ─┤
  │                         ↓
  └─────────────────→ Fase 4 (Sandbox → Producción) [24–48h, humano]
                            ↓
                      Fase 5 (Pruebas + Monitoreo)
```

---

## 7. Estructura de Archivos a Crear

```
infraestructure/email/
├── docs/
│   └── plan-trabajo-conectatech.md    ← este archivo
├── lambda/
│   └── email-forwarder/
│       ├── index.mjs                  (función Node.js 22.x con FORWARD_MAP)
│       └── package.json               (sin dependencias externas — solo AWS SDK built-in)
└── scripts/
    ├── 01-verify-domain.sh            (Fase 1.1–1.2)
    ├── 02-setup-dkim.sh               (Fase 1.3–1.4)
    ├── 03-setup-mx.sh                 (Fase 1.5)
    ├── 04-verify-emails.sh            (Fase 1.6–1.7 + Fase 0.3)
    ├── 05-create-s3-bucket.sh         (Fase 2.1–2.2)
    ├── 06-create-iam-role.sh          (Fase 2.3–2.4)
    ├── 07-deploy-lambda.sh            (Fase 2.5–2.7)
    ├── 08-create-receipt-rules.sh     (Fase 2.8–2.10)
    ├── 09-setup-s3-trigger.sh         (Fase 2.11–2.12)
    ├── 10-configure-moodle-smtp.sh    (Fase 3.2–3.6)
    └── 11-setup-cloudwatch-alarms.sh  (Fase 5.6–5.8)
```

---

## 8. Estimación de Costos Post-Free Tier

| Servicio | Uso estimado | Costo mensual |
|---|---|---|
| SES envío (desde EC2) | ~1,000 msgs/mes | $0 (dentro de los 62,000 gratuitos) |
| SES recepción | ~200 msgs/mes | ~$0.02 |
| Lambda | ~200 invocaciones/mes | $0 (dentro del free tier permanente) |
| S3 | < 1 MB/mes | $0 (dentro del free tier) |
| CloudWatch | Alarmas básicas | ~$0.30/alarma/mes |
| **Total estimado** | | **< $1.00/mes** |

---

## 9. Checklist de Completación

### Fase 0
- [ ] SG actualizado para SSH (IP `186.154.53.75`)
- [ ] Config SMTP de Moodle verificada
- [ ] 3 Gmail destino verificados en SES (para pruebas en sandbox)

### Fase 1
- [ ] Dominio `conectatech.co` verificado en SES (`VerificationStatus: Success`)
- [ ] DKIM habilitado (`DkimEnabled: true`, `DkimVerificationStatus: Success`)
- [ ] Registro MX creado en Route 53
- [ ] `no-reply@conectatech.co` verificado en SES
- [ ] `forwarder@conectatech.co` verificado en SES

### Fase 2
- [ ] Bucket `conectatech-ses-incoming-emails` creado con bucket policy
- [ ] Rol IAM `conectatech-email-forwarder-role` creado
- [ ] Lambda `conectatech-email-forwarder` desplegada (nodejs22.x)
- [ ] Rule set `conectatech-rules` creado y activo
- [ ] Regla `forward-to-gmail` configurada en SES
- [ ] Trigger S3 → Lambda configurado

### Fase 3
- [ ] Credenciales SMTP generadas (guardadas de forma segura, fuera del repo)
- [ ] Moodle configurado con SMTP SES

### Fase 4
- [ ] Solicitud de producción enviada a AWS
- [ ] Aprobación recibida (`Max24HourSend >> 200`)

### Fase 5
- [ ] Prueba outbound Moodle: correo recibido, no spam
- [ ] Prueba inbound `info@`: correo reenviado a Gmail correctamente
- [ ] Prueba inbound `admin@`: correo reenviado correctamente
- [ ] Prueba catch-all: correo reenviado correctamente
- [ ] Headers verificados: SPF pass, DKIM pass
- [ ] Alarmas CloudWatch configuradas

---

*Cuenta AWS: `648232846223` | Región: `us-east-1` | Zona Route 53: `Z0767805255ZNR9CRNWLH`*
