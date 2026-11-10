# Faceta `iac`

> CC BY-SA 4.0. Fuentes: Infrastructure as Code Security, Secure Cloud Architecture, Secrets Management.

- **IAC-01..04** son automáticos sobre `*.tf`. Lo que está en la cuenta y no en el código lo cubren AWS-01..05 en modo live.
- **IAC-05:** CloudTrail activo, logs de aplicación en CloudWatch con retención y alarmas de facturación.
- El `terraform.tfstate` contiene secretos en claro: backend remoto cifrado (S3 + DynamoDB lock), nunca versionado (BASE-02).
