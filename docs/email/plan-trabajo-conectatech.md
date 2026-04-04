# Plan de Trabajo — Sistema de Correo AWS para conectatech.co

> Documento generado el 2026-04-03 tras verificación del estado actual de la infraestructura.  
> Ruta del código: `docs/email/` (documentación), `lambda/email-forwarder/` (función), `scripts/email/` (scripts bash).

---

## 1. Diagnóstico del Estado Actual

### Resultado de la verificación (2026-04-03)

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
| **Moodle — SMTP** | ⚠️ Sin configurar | Usa PHP mail() por defecto. `noreplyaddress = noreply@conectatech.co`. Sin host SMTP. |

### Infraestructura AWS existente (no afectada)

| Recurso | Nombre | Runtime / Notas |
|---|---|---|
| Lambda | `conectatech-api-pdfs` | ~~nodejs22.x~~ → **nodejs24.x** ✅ (actualizada 2026-04-03) |
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
| Lambda | `conectatech-email-forwarder` | **nodejs24.x**, reenvío a Gmail |
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

> Esto requiere que cada propietario del Gmail confirme el enlace de verificación que AWS envía.
> Programado para inicios de la semana del 2026-04-07.

La solicitud de salida del sandbox (Fase 4) requiere intervención humana y aprobación de AWS (24–48h). Sin ella, el sistema no es funcional en producción.

---

## 5. Plan de Trabajo por Fases

### Fase 0 — Preparación ✅ Parcialmente completada (2026-04-03)

| # | Acción | Estado | Detalle |
|---|--------|--------|---------|
| 0.1 | Actualizar SG para SSH | ✅ Hecho | IP `190.25.64.127/32` autorizada en `sg-039bcb1cb3a57db7f` |
| 0.2 | Actualizar `conectatech-api-pdfs` a nodejs24.x | ✅ Hecho | Runtime actualizado a `nodejs24.x` |
| 0.3 | Verificar config SMTP de Moodle | ✅ Hecho | Sin SMTP configurado (usa PHP mail). `noreplyaddress = noreply@conectatech.co`, `smtpauthtype = LOGIN`. Host/user/pass vacíos. |
| 0.4 | Verificar Gmail destinos en SES | ⏳ Pendiente semana 7-abr | `aws ses verify-email-identity` para los 3 Gmail (necesario para pruebas en sandbox) |

> **Nota IP:** Usar siempre `checkip.amazonaws.com` para obtener la IP pública real — `ifconfig.me` puede diferir.

---

### Fase 1 — Verificación DNS y SES (≈30 min + espera propagación)

> ⚠️ **Dependencia de tiempo:** Los registros DNS pueden tardar hasta 48h en propagar. SES verifica el dominio en minutos tras la propagación. Ejecutar esta fase primero y continuar con Fase 2 en paralelo.

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 1.1 | Verificar dominio `conectatech.co` en SES | Agente | `aws ses verify-domain-identity --domain conectatech.co --profile im --region us-east-1` → obtener VerificationToken |
| 1.2 | Crear TXT `_amazonses.conectatech.co` | Agente | Route 53, zona `Z0767805255ZNR9CRNWLH`, tipo TXT, valor = VerificationToken |
| 1.3 | Habilitar DKIM | Agente | `aws ses verify-domain-dkim --domain conectatech.co --profile im --region us-east-1` → obtener 3 DkimTokens |
| 1.4 | Crear 3 CNAMEs DKIM | Agente | `<token>._domainkey.conectatech.co → <token>.dkim.amazonses.com` ×3 en Route 53 |
| 1.5 | Crear registro MX | Agente | Route 53: `conectatech.co MX 10 inbound-smtp.us-east-1.amazonaws.com` |
| 1.6 | Verificar email `no-reply@conectatech.co` | Agente | `aws ses verify-email-identity --email-address no-reply@conectatech.co` → confirmar enlace recibido en Gmail |
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

*Pasos 2A y 2B pueden ejecutarse sin esperar propagación DNS. Pasos 2D y 2E requieren dominio verificado.*

#### 2A — Bucket S3

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.1 | Crear bucket `conectatech-ses-incoming-emails` | Agente | `aws s3 mb s3://conectatech-ses-incoming-emails --region us-east-1 --profile im` |
| 2.2 | Aplicar bucket policy | Agente | `Principal: ses.amazonaws.com`, `Action: s3:PutObject`, condición `AWS:SourceAccount: 648232846223` |

#### 2B — Rol IAM

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.3 | Crear rol `conectatech-email-forwarder-role` | Agente | Trust policy: `lambda.amazonaws.com` |
| 2.4 | Adjuntar política inline | Agente | `s3:GetObject` en bucket, `ses:SendRawEmail` en `*`, `logs:CreateLogGroup/Stream/PutLogEvents` |

#### 2C — Lambda

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.5 | Escribir `index.mjs` | Agente | **Node.js 24.x**; FORWARD_MAP con las 6 reglas; `FORWARDER_ADDRESS = forwarder@conectatech.co` |
| 2.6 | Empaquetar y desplegar | Agente | `zip function.zip index.mjs && aws lambda create-function --runtime nodejs24.x ...` |

#### 2D — SES Receipt Rules *(requiere dominio verificado de Fase 1)*

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.7 | Crear rule set `conectatech-rules` | Agente | `aws ses create-receipt-rule-set --rule-set-name conectatech-rules --profile im --region us-east-1` |
| 2.8 | Activar rule set | Agente | `aws ses set-active-receipt-rule-set --rule-set-name conectatech-rules` |
| 2.9 | Crear regla `forward-to-gmail` | Agente | Recipients: `["conectatech.co"]`, acción S3 → bucket, prefix `incoming/`, ScanEnabled: true |

#### 2E — Trigger S3 → Lambda

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 2.10 | Añadir permiso Lambda | Agente | `aws lambda add-permission` para que `s3.amazonaws.com` invoque la función |
| 2.11 | Configurar notificación en bucket | Agente | `aws s3api put-bucket-notification-configuration`, evento `s3:ObjectCreated:*`, prefix `incoming/` |

**Validación de Fase 2:**
- `aws ses describe-active-receipt-rule-set` → muestra `conectatech-rules` con la regla
- `aws lambda get-function --function-name conectatech-email-forwarder` → estado `Active`
- `aws s3api get-bucket-notification-configuration --bucket conectatech-ses-incoming-emails` → trigger presente

---

### Fase 3 — Outbound desde Moodle (≈20 min)

*Requiere SSH habilitado (Fase 0.1 ✅) y dominio verificado en SES (Fase 1).*

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 3.1 | Verificar config SMTP actual | Agente | SSH → `sudo -u apache php /var/www/html/moodle/admin/cli/cfg.php --name=smtphosts` |
| 3.2 | Generar credenciales SMTP | **Humano** | SES Console → SMTP Settings → Create SMTP Credentials |
| 3.3 | Configurar Moodle SMTP host | Agente | `sudo -u apache php admin/cli/cfg.php --name=smtphosts --set=email-smtp.us-east-1.amazonaws.com:587` |
| 3.4 | Configurar usuario SMTP | Agente | `--name=smtpuser --set=<SMTP_ACCESS_KEY>` |
| 3.5 | Configurar contraseña SMTP | Agente | `--name=smtppass --set=<SMTP_SECRET_KEY>` |
| 3.6 | Configurar remitente | Agente | `--name=noreplyaddress --set=no-reply@conectatech.co` *(actualmente `noreply@` sin guion — decidir cuál usar y verificar esa dirección en SES)* |
| 3.7 | Configurar protocolo TLS | Agente | `--name=smtpsecure --set=tls` |

> **Nota:** Las credenciales SMTP no se almacenan en el repositorio. Se proporcionan al agente en tiempo de ejecución.

**Validación:** Moodle Admin → Servidor → Correo saliente → Enviar correo de prueba → recibido en bandeja (no spam).

---

### Fase 4 — Salida del Sandbox *(requiere intervención humana)*

> ⏱️ **Bloqueo de 24–48h.** Sin completar esta fase, el sistema no funciona en producción.

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 4.1 | Verificar estado | Agente | `aws ses get-send-quota --profile im --region us-east-1` → confirmar `Max24HourSend = 200` |
| 4.2 | Abrir solicitud | **Humano** | SES Console → Account dashboard → Request production access |
| 4.3 | Completar formulario | **Humano** | Ver datos sugeridos abajo |
| 4.4 | Esperar aprobación | **Humano** | 24–48h |
| 4.5 | Confirmar salida | Agente | `aws ses get-send-quota` → `Max24HourSend >> 200` |

**Datos para el formulario de producción:**
- **Tipo de correo:** Transaccional
- **Website URL:** `https://conectatech.co`
- **Descripción:** Plataforma de aprendizaje online (Moodle). Se envían notificaciones de matrícula, recuperación de contraseña y recordatorios de actividades. Volumen estimado: 200–1,000 correos/día.
- **Proceso de bounces/complaints:** Monitoreo activo vía CloudWatch Alarms; lista de supresión de rebotes habilitada.

---

### Fase 5 — Pruebas End-to-End y Monitoreo (≈30 min)

*Ejecutar después de Fase 4 (sandbox superado) y verificación de Gmail destinos (Fase 0.4).*

| # | Acción | Responsable | Detalle |
|---|--------|-------------|---------|
| 5.1 | Prueba outbound Moodle | Agente/Humano | Moodle → Admin → Correo de prueba → verificar llegada (no spam) |
| 5.2 | Prueba inbound `info@` | Humano | Enviar a `info@conectatech.co` → verificar en `ideasmaestrasltda@gmail.com` |
| 5.3 | Prueba inbound `admin@` | Humano | Enviar a `admin@conectatech.co` → verificar en `ocastelblanco@gmail.com` |
| 5.4 | Prueba catch-all | Humano | Enviar a `xyz@conectatech.co` → verificar en `ideasmaestrasltda@gmail.com` |
| 5.5 | Verificar headers | Humano | Gmail → "Mostrar original" → SPF pass, DKIM pass, no spam |
| 5.6 | Alarma Lambda errors | Agente | CloudWatch: `conectatech-email-forwarder Errors > 0` → SNS → `ocastelblanco@gmail.com` |
| 5.7 | Alarma SES bounces | Agente | CloudWatch: métrica `Bounce` SES > 5% |
| 5.8 | Alarma SES complaints | Agente | CloudWatch: métrica `Complaint` SES > 0 |

---

## 6. Diagrama de Dependencias

```
Fase 0 (preparación)
  │  ✅ 0.1 SG SSH      ✅ 0.2 Lambda nodejs24
  │  ⏳ 0.3 Moodle SMTP  ⏳ 0.4 Gmail verify (sem. 7-abr)
  │
  ├─→ Fase 1 (DNS + SES) ──────────────────────────┐
  │     │ ⏱ propagación DNS (hasta 48h)             │
  │     ↓                                           ↓
  │   Fase 2A/B/C (S3 + IAM + Lambda)     Fase 2D/E (SES rules + trigger)
  │     │                                           │
  │     └──────────────────┬────────────────────────┘
  │                        ↓
  ├─→ Fase 3 (Moodle SMTP)─┤
  │                        ↓
  └──────────────→ Fase 4 (Sandbox → Producción) [24–48h, humano]
                           ↓
                     Fase 5 (Pruebas + Monitoreo)
```

---

## 7. Estructura de Archivos

```
docs/email/
├── AGENTS.md
├── AWS_EMAIL_SYSTEM.md
├── redireccion-emails.md
└── plan-trabajo-conectatech.md    ← este archivo

lambda/email-forwarder/            (por crear en Fase 2C)
├── index.mjs                      (Node.js 24.x, FORWARD_MAP completo)
└── package.json

scripts/email/                     (por crear)
├── 01-verify-domain.sh
├── 02-setup-dkim.sh
├── 03-setup-mx.sh
├── 04-verify-emails.sh
├── 05-create-s3-bucket.sh
├── 06-create-iam-role.sh
├── 07-deploy-lambda.sh
├── 08-create-receipt-rules.sh
├── 09-setup-s3-trigger.sh
├── 10-configure-moodle-smtp.sh
└── 11-setup-cloudwatch-alarms.sh
```

---

## 8. Estimación de Costos Post-Free Tier

| Servicio | Uso estimado | Costo mensual |
|---|---|---|
| SES envío (desde EC2) | ~1,000 msgs/mes | $0 (62,000 gratuitos desde EC2) |
| SES recepción | ~200 msgs/mes | ~$0.02 |
| Lambda | ~200 invocaciones/mes | $0 (free tier permanente) |
| S3 | < 1 MB/mes | $0 (free tier) |
| CloudWatch | 3 alarmas | ~$0.90/mes |
| **Total estimado** | | **< $1.00/mes** |

---

## 9. Checklist de Completación

### Fase 0 — Preparación
- [x] SG actualizado para SSH (IP `190.25.64.127`)
- [x] `conectatech-api-pdfs` actualizada a nodejs24.x
- [x] Config SMTP de Moodle verificada (sin SMTP; `noreplyaddress = noreply@conectatech.co`)
- [ ] 3 Gmail destino verificados en SES (semana 7-abr)

### Fase 1 — DNS y SES
- [ ] Dominio `conectatech.co` verificado en SES
- [ ] DKIM habilitado y verificado
- [ ] Registro MX creado en Route 53
- [ ] `no-reply@conectatech.co` verificado en SES
- [ ] `forwarder@conectatech.co` verificado en SES

### Fase 2 — Infraestructura Inbound
- [ ] Bucket `conectatech-ses-incoming-emails` con bucket policy
- [ ] Rol IAM `conectatech-email-forwarder-role` creado
- [ ] Lambda `conectatech-email-forwarder` desplegada (nodejs24.x)
- [ ] Rule set `conectatech-rules` activo con regla `forward-to-gmail`
- [ ] Trigger S3 → Lambda configurado

### Fase 3 — Moodle SMTP
- [ ] Config SMTP actual verificada
- [ ] Credenciales SMTP generadas
- [ ] Moodle configurado con SMTP SES

### Fase 4 — Sandbox
- [ ] Solicitud de producción enviada
- [ ] Aprobación recibida

### Fase 5 — Pruebas y Monitoreo
- [ ] Prueba outbound exitosa
- [ ] Prueba inbound `info@` exitosa
- [ ] Prueba inbound `admin@` exitosa
- [ ] Prueba catch-all exitosa
- [ ] Headers SPF/DKIM verificados
- [ ] Alarmas CloudWatch configuradas

---

*Cuenta AWS: `648232846223` | Región: `us-east-1` | Zona Route 53: `Z0767805255ZNR9CRNWLH`*  
*Última actualización: 2026-04-03*
