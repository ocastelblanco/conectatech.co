# Redirecciones de inbound

| Dirección | Propósito | Redirige a |
| --- | --- | --- |
| `info@conectatech.co` | Información general | <buzón corporativo> |
| `digital@conectatech.co` | Consultas técnicas | <buzón de Oliver> |
| `ana.mora@conectatech.co` | Correo de Ana Julia Mora | <buzón de Ana> |
| `oliver.castelblanco@conectatech.co` | Correo de Oliver Castelblanco | <buzón de Oliver> |
| catch-all `@conectatech.co` | Cualquier otra dirección | <buzón corporativo> |

> Los buzones de destino reales no se publican (el repo es público). Viven solo en la variable de entorno `FORWARD_MAP` de la Lambda `conectatech-email-forwarder`, como arreglo JSON `[{"match","dest"}]` evaluado en orden (la última regla es el catch-all).
