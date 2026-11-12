# Current State - OCCRE

## Estado
- FASE 0: completada (skills, AGENTS.md, opencode.json, memoria persistente).
- FASE 1: auditoría backend/API/MySQL en curso; dependencias Laravel restauradas y verificadas (`composer install --dry-run` sin cambios).
- Entorno: PHP 8.2.12, Composer 2.8.8, Laravel 12.67.0, Angular 20, Ionic 8, Capacitor 8, MySQL.
- BD de pruebas aislada: `occre_test_db` (usuario `occre_test`, privilegios solo sobre esa BD). Tests de seguridad con `DatabaseTransactions` sobre MySQL; suite completa 16 pruebas / 29 aserciones en verde.
- Vulnerabilidades corregidas y con retest: escalada de rol (funcionario→admin), IDOR de citas y documentos, conflicto de interés en `updateStatus`, rate limit en búsqueda pública/QR/health, hash del token de recuperación y eliminación del token en logs, firma HMAC + validación de estado/datos en QR de constancias, mayor entropía en códigos de tarjeta turística y throttle específico en recibo.
- Dependencias remediadas: `composer.lock` actualizado (Laravel 12.70.2, Symfony 7.4.x); `composer audit` sin advisories; `composer install --dry-run` sin cambios.
- PII en recibo turístico: acceso completo solo para staff, código de alta entropía o token HMAC firmado; códigos antiguos devuelven datos enmascarados y sin QR/PDF.
- `/api/health` y `/api/status` redactados: sin host, BD, credenciales, versiones, conteos ni mensajes de excepción.
- JWT: endpoints `refresh`/`logout`, revocación por `iat` tras cambio/restablecimiento de contraseña (`RejectRevokedTokens` + caché).
- `shareLocation`: lat/lon obligatorios y rangos válidos (evita 500 por columnas NOT NULL).
- Suite de seguridad: 31 pruebas en verde (3 archivos nuevos: JWT, health, privacidad de recibo).

## Pendiente
- Decisión (requiere autorización) sobre TTL JWT de 7 días (`JWT_TTL=10080`); refresh ya disponible.
- QR legacy de constancias: se acepta solo si los datos coinciden con el trámite aprobado/finalizado; residual de replay de bajo riesgo.
- Revocación JWT usa caché `file`: para multi-instancia se requiere Redis/DB compartida (documentado en middleware).

## Auditoría de seguridad integral (completada - 2026-11-12)
- Corregida previamente: `verifyByQrToken` en TourismCardController ya no expone PII completa; ahora devuelve documento enmascarado (últimos 4 dígitos).
- Corregida previamente: `getAllAppointments` en AppointmentController ya no expone citizenEmail/citizenPhone de todos los ciudadanos.
- Corregida previamente: ChatController ya no loguea `$e->getMessage()` (evita exposición de detalles del proveedor Gemini).
- Pruebas de seguridad previas: 31 tests / 90 aserciones en verde.
- Backups creados antes de modificar: TourismCardController.php, AppointmentController.php, ChatController.php.
- Auditoría integral completada (2026-11-12): 36 hallazgos documentados (3 críticas, 7 altas, 11 medias, 7 bajas, 8 preventivas); 15 vulnerabilidades ya corregidas en historial.
- Informes de auditoría en `docs/security-audit/` (14 documentos).
- Hallazgos críticos pendientes de autorización: exposición PII en `/tourism-card/search`, JWT en localStorage, APP_DEBUG=true.

## Sistema de Skills (implementado - Fase 0/1 completo)
- Configuración global en `C:\Users\Owen\.config\opencode\opencode.jsonc` con campos `instructions` y `skills`
- Instrucciones globales persistentes: `C:\Users\Owen\.config\opencode\instructions-skill-orchestrator.md`
- Skill orquestadora global: `C:\Users\Owen\.config\opencode\skills\skill-orchestrator\SKILL.md`
- Skills globales (21): en `C:\Users\Owen\.config\opencode\skills\`
- Skills locales (40): en `.opencode\skills\`
- MCP: context7 (needs auth), playwright (pending)
- Modelo default sin cambios: `omniroute/openrouter/cohere/north-mini-code:free`
- Proveedores sin cambios: omniroute

## Sistema de Backups y Recuperación (implementado - Fase 8 completo)
- Documentación: `docs/backups/01-05-estructura-almacenamiento, estrategia-backups, proteccion-backups, procedimiento-recuperacion, informe-final`
- Scripts: `scripts/backup/backup-completo.ps1`, `scripts/backup/verificar-integridad.ps1`, `scripts/backup/configurar-tarea-programada.ps1`
- Guía rápida: `scripts/restore/leeme-recuperacion.md`
- Ubicación de backups: `C:\Users\Owen\Backups\OCCRE\` (fuera del proyecto, fuera de Git, fuera de carpeta pública)
- Cifrado: AES-256 con contraseña en variable de entorno `OCCRE_BACKUP_PASSWORD`
- Integridad: SHA-256 por archivo, manifest.json por conjunto
- Automatización: Programador de Tareas Windows (diario 02:00 AM, requiere activación)
- RPO: 24 horas | RTO: 4 horas
- Prueba de restauración: Exitosa (28 tablas restauradas en BD temporal, sin tocar producción)
- Pruebas de seguridad: 31 tests, 90 aserciones, 0 fallos
