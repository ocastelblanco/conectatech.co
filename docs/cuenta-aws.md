# Datos básicos de la cuenta IdeasMaestras en AWS

## 1. ID principales

| Ítem | Valor |
|---|---|
| ID de cuenta | 648232846223 |
| Nombre de la cuenta | IdeasMaestras |
| Rol asignado | AdministradorExterno |
| ID cuenta propia | 696912647258 |
| Key par EC2 | `~/.ssh/ClaveIM.pem` |
| Región | us-east-1 |

## 2. AWS CLI Cross-Account

```bash
# ~/.aws/config
[profile im]
role_arn = arn:aws:iam::648232846223:role/AdministradorExterno
source_profile = default
region = us-east-1
output = json

# Verificar acceso
aws sts get-caller-identity --profile im
```