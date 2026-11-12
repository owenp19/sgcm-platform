# Auditoría de Seguridad OCCRE — Informe

Fecha de cierre parcial: 2026-10-30
Alcance: backend Laravel 12, API REST JWT, MySQL, frontend Angular/Ionic.
Entorno: local aislado, BD `occre_test_db` con datos ficticios.

## Corregido y verificado

| Hallazgo | Corrección | Evidencia |
|---|---|---|
| Escalada de rol funcionario→admin | Validación de rol admin + `exists:roles,name` | `AuthorizationSecurityTest` |
| IDOR de citas / documentos | Comprobación de propietario o staff | `AuthorizationSecurityTest` |
| Autoaprobación de solicitudes | Bloqueo si `user_id` coincide con el funcionario | `AuthorizationSecurityTest` |
| Rate limit público | Throttle en búsqueda, QR y health | `PublicEndpointProtectionTest` |
| Token de recuperación en claro/logs | Hash SHA-256 y log sin token | `PasswordResetTokenTest` |
| QR de constancias manipulable | Firma HMAC + estado aprobado/finalizado + datos coincidentes | `CertificateQrIntegrityTest` |
| Códigos turísticos enumerables | Códigos de 12 caracteres + throttle específico | `TourismReceiptPrivacyTest` |
| PII en recibo turístico | Acceso completo solo staff/alta entropía/token HMAC; enmascarado | `TourismReceiptPrivacyTest` |
| `/api/health` y `/api/status` exponían internos | Redacción de host, BD, versiones, conteos y errores | `HealthExposureTest` |
| JWT sin revocación | `refresh`/`logout` + revocación por `iat` | `JwtSessionSecurityTest` |
| `shareLocation` 500 con coordenadas nulas | `required` + rangos válidos | `TourismReceiptPrivacyTest` |
| Dependencias vulnerables | `composer update`; `composer audit` limpio | composer.lock / audit |

Resultado: 31 pruebas de seguridad en verde, 0 fallos.

## Pendiente / residual
- Reducir `JWT_TTL` (hoy 10080 min) requiere autorización.
- Revocación JWT en caché `file`: usar Redis/DB compartida en multi-instancia.
- QR legacy sin firma se acepta solo si los datos coinciden con el trámite aprobado; residual de replay de bajo riesgo.
- Sin vectores `innerHTML`/`bypassSecurityTrust` en `src/`.

## Notas
- La API usa 400 para errores de validación (contrato heredado del backend Node); no se modificó.
