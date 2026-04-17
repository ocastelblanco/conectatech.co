# Proyección de Costos — ConectaTech.co

**Fecha:** Abril 2026  
**Región AWS:** us-east-1 (Virginia)  
**Perfil AWS:** `im` (cuenta `648232846223`)

---

## Infraestructura actual (confirmada)

| Servicio | Especificación real | Precio unitario | Costo/mes |
|---|---|---|---:|
| EC2 | t4g.small (2 vCPU, 2 GB RAM, ARM64) | $0.0168/hr | $12.26 |
| EBS Root | 15 GB gp3 | $0.08/GB | $1.20 |
| EBS Data | 25 GB gp3 | $0.08/GB | $2.00 |
| RDS | db.t4g.micro (MariaDB 10.11) | $0.016/hr | $11.68 |
| RDS Storage | 20 GB gp3 | $0.115/GB | $2.30 |
| Elastic IP | 1 IPv4 pública | $0.005/hr | $3.60 |
| Route 53 | 1 hosted zone + queries | $0.50 + queries | $0.60 |
| S3 | assets.conectatech.co (PDFs + visor) | ~1 GB | $0.10 |
| CloudFront | CDN assets (~5 GB/mes transfer) | $0.0085/GB | $1.00 |
| API Gateway + Lambda | HTTP API + `conectatech-api-pdfs` | Pay-per-use | $0.01 |
| CloudWatch | Agent + 5 métricas custom + 5 alarmas | $0.30/métrica + $0.10/alarma | $2.50 |
| ACM (SSL) | 2 certificados | Gratis | $0.00 |
| **Total actual** | | | **$37.25** |

---

## Solución de email — Amazon SES (corto plazo)

| Componente | Detalle | Costo/mes |
|---|---|---:|
| SES envío outbound | Desde EC2: **62,000 msgs/mes gratis** (permanente) | $0.00 |
| SES recepción inbound | ~500 correos entrantes/mes | $0.05 |
| Lambda email-forwarder | Dentro del free tier perpetuo (1M req/mes) | $0.00 |
| S3 emails entrantes | Almacenamiento temporal (< 100 MB) | $0.00 |
| **Total incremental email** | | **$0.05** |

> El beneficio de 62K emails/mes gratis desde EC2 es **permanente**, no del primer año. Para el volumen habitual de Moodle (registros, notificaciones de cursos, recuperación de contraseña) el costo de SES es prácticamente $0.

**Total proyectado con email: ~$37.30/mes**

---

## Escalamiento por usuarios concurrentes

La siguiente tabla refleja el costo estimado por tramo de crecimiento. El costo **no escala linealmente** — la mayor parte es fijo (EC2, RDS). Los componentes variables son CloudFront (más transfer con más PDFs) y EBS Data (más archivos de usuarios).

| Componente | Actual (≤100 cc) | 100–300 cc | 300–1.000 cc | 1.000–2.500 cc | 2.500–5.000 cc |
|---|---:|---:|---:|---:|---:|
| EC2 | t4g.small $12.26 | t4g.medium $24.26 | t4g.large $48.26 | t4g.xlarge $96.00 | t4g.2xlarge $192.00 |
| EBS Root | $1.20 | $1.20 | $1.20 | $1.20 | $1.20 |
| EBS Data | $2.00 (25 GB) | $3.20 (40 GB) | $5.00 (60 GB) | $8.00 (100 GB) | $16.00 (200 GB) |
| RDS | db.t4g.micro $11.68 | db.t4g.small $24.00 | db.t4g.small $24.00 | db.t4g.medium $48.00 | db.t4g.large $96.00 |
| RDS Storage | $2.30 (20 GB) | $3.45 (30 GB) | $5.75 (50 GB) | $11.50 (100 GB) | $23.00 (200 GB) |
| Elastic IP | $3.60 | $3.60 | $3.60 | $3.60 | $3.60 |
| Route 53 | $0.60 | $0.60 | $0.60 | $0.60 | $0.60 |
| S3 + CloudFront | $1.10 | $2.00 | $4.00 | $8.00 | $15.00 |
| API GW + Lambda | $0.01 | $0.10 | $0.50 | $2.00 | $5.00 |
| CloudWatch | $2.50 | $3.00 | $4.00 | $5.00 | $6.00 |
| SES Email | $0.05 | $0.10 | $0.50 | $2.00 | $5.00 |
| **Total/mes** | **~$37** | **~$65** | **~$97** | **~$186** | **~$363** |
| **Total/año** | **~$444** | **~$780** | **~$1.164** | **~$2.232** | **~$4.356** |

> **Nota para 1.000+ usuarios:** a este nivel conviene evaluar Multi-AZ en RDS (+$48/mes) y un segundo volumen EBS para backups. También se recomienda revisar Reserved Instances de 1 año para EC2 y RDS (ahorro del 35–38%).

### Pasos de escalamiento recomendados

```
≤100 cc   →  t4g.small  + db.t4g.micro   →  ~$37/mes   (configuración actual)
100–300   →  t4g.medium + db.t4g.small   →  ~$65/mes   (upgrade EC2 + RDS)
300–1000  →  t4g.large  + db.t4g.small   →  ~$97/mes   (upgrade EC2, RDS aguanta)
1000–2500 →  t4g.xlarge + db.t4g.medium  →  ~$186/mes  (upgrade ambos)
2500–5000 →  t4g.2xlarge + db.t4g.large  →  ~$363/mes  (+ evaluar arquitectura HA)
```

---

## Ahorro con Reserved Instances (1 año)

Si se proyecta operar en un tramo durante al menos 12 meses, las Reserved Instances reducen el costo de EC2 y RDS en ~35–38%.

| Tramo | Costo on-demand/mes | Costo reservado/mes | Ahorro/mes | Ahorro/año |
|---|---:|---:|---:|---:|
| Actual (≤100 cc) | $37 | ~$28 | ~$9 | ~$108 |
| 100–300 cc | $65 | ~$50 | ~$15 | ~$180 |
| 300–1.000 cc | $97 | ~$74 | ~$23 | ~$276 |
| 1.000–2.500 cc | $186 | ~$140 | ~$46 | ~$552 |
| 2.500–5.000 cc | $363 | ~$270 | ~$93 | ~$1.116 |

---

## Notas técnicas

- **Arquitectura ARM64 (Graviton):** todos los componentes usan instancias t4g/db.t4g, que ofrecen 20–40% mejor precio/performance vs. equivalentes x86.
- **SES desde EC2:** el umbral de 62.000 correos/mes gratuitos es permanente (no se limita al primer año).
- **CloudFront:** el costo variable crece con el consumo de PDFs por parte de los usuarios. A mayor catálogo de contenido disponible, mayor transfer esperado.
- **Escala >5.000 cc:** a este nivel se recomienda revisar la arquitectura hacia Multi-EC2 + ALB + EFS para moodledata, lo que cambia significativamente el modelo de costos.
- **Datos usados para calcular la tabla:** precios on-demand us-east-1, abril 2026. Los precios AWS pueden variar.
