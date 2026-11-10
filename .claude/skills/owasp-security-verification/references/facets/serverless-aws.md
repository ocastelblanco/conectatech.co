# Faceta `serverless-aws`

> CC BY-SA 4.0. Fuentes: Serverless FaaS Security, Secure Cloud Architecture, NoSQL Security.

| Chequeo | Qué revisar en `serverless.yml`, CDK o SAM |
|---|---|
| SLS-01 IAM | `iam.role.statements`: cada `Action` con verbo concreto y `Resource` con ARN de la tabla o bucket (`!GetAtt Tabla.Arn`). `logs:*` sobre `*` lo genera el framework y no cuenta. |
| SLS-02 secretos | `environment:` solo con `${ssm:…}`, `${env:…}` o `${aws:…}` para valores sensibles. |
| SLS-05 autorizadores | Para cada `httpApi` o `http` event: `authorizer:` o verificación en código (NODE-03). Lista las rutas públicas por diseño en la nota. |
| SLS-06 abuso y costo | `provider.httpApi` o `apiGateway` con throttling, `timeout` por función, `reservedConcurrency` en funciones costosas, alarma de presupuesto. |

## DynamoDB

- Las condiciones de negocio (aforo, inventario) usan `ConditionExpression` (BASE-27).
- Sin `Scan` expuestos a la entrada del usuario sin `Limit`.
