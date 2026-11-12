# Pending Work - OCCRE

## FASE 1 - Seguridad (cierre pendiente)
- [ ] Autorizar reducción de `JWT_TTL` (hoy 7 días); `refresh`/`logout` ya implementados.
- [ ] Para despliegue multi-instancia, usar caché compartida (Redis/DB) para la revocación JWT.
- [ ] QR legacy de constancias: se acepta solo con datos coincidentes y trámite aprobado/finalizado; revisar si se desea exigir firma en todos los QR futuros.
- [x] Corregir verifyByQrToken exposición PII (TourismCardController)
- [x] Corregir getAllAppointments exposición citizenEmail/citizenPhone (AppointmentController)
- [x] Corregir ChatController exposición de excepción en logs
- [x] Remediación de dependencias: `composer update` aplicado, `composer audit` limpio.
- [x] Mitigación PII en `GET /api/tourism-card/card/{code}/receipt` (token HMAC + enmascarado).
- [x] Restricción de `/api/health` y `/api/status`.
- [x] Revocación JWT tras cambio/restablecimiento de contraseña y endpoints `refresh`/`logout`.
- [x] `shareLocation`: validación obligatoria y rangos de coordenadas.
- [x] Conflicto de interés en `updateStatus` y revisión de `assignRequest`.
- [x] Revisión XSS de anuncios: sin `innerHTML`/`bypassSecurityTrust` en `src/`.

## FASE 1 - Auditoría Integral de Ciberseguridad (COMPLETADA - 2026-11-12)
- [x] Inventario de Skills (global + local)
- [x] Mapa de arquitectura y modelo de amenazas STRIDE
- [x] Auditoría de MySQL (usuarios, privilegios, SQL injection, integridad, auditoría)
- [x] Auditoría de backend Laravel (controladores, middleware, rutas, servicios, configuración)
- [x] Auditoría de autenticación JWT y sesiones
- [x] Auditoría de frontend y aplicación móvil (Angular 20, Ionic 8, Capacitor 8)
- [x] Auditoría de chatbot e IA (Gemini, RAG, OWASP LLM Top 10)
- [x] Auditoría de APIs y conexiones (HTTPS, CORS, cabeceras de seguridad, secretos)
- [x] Auditoría de backups y recuperación (cifrado, integridad, retención, RPO/RTO)
- [x] Auditoría de dependencias (composer, npm, vulnerabilidades)
- [x] Auditoría visual con Playwright (configuración, pruebas, evidencias)
- [x] Registro de vulnerabilidades (36 hallazgos: 3 críticas, 7 altas, 11 medias, 7 bajas, 8 preventivas)
- [x] Plan de fortalecimiento por fases (4 fases, correcciones que requieren autorización identificadas)
- [x] Informes en docs/security-audit/ (14 documentos)
- [ ] Autorizar correcciones críticas pendientes: exposición PII en endpoints públicos, JWT en localStorage, APP_DEBUG=true

## FASE 0 - Completada
- [x] Skills de ciberseguridad, MySQL, UI/UX, Angular/Ionic, testing, rendimiento y memoria

## FASE 1 - Sistema de Skills global OpenCode (COMPLETADO)
- [x] Auditoría de entorno (versión, config, skills, MCP)
- [x] Configuración global de instrucciones y skills en opencode.jsonc
- [x] Creación de instrucciones globales persistentes (instructions-skill-orchestrator.md)
- [x] Creación de skill orquestadora global (skill-orchestrator)
- [x] Verificación: instrucciones globales, skills globales, skills locales, carga de skill real, modelo/proveedores intactos

## FASE 2 - Sistema de Backups y Recuperación (IMPLEMENTADO)
- [x] Auditoría de almacenamiento (MySQL, archivos, configuraciones, claves criptográficas)
- [x] Estrategia de respaldos (diario/semanal/mensual, retención 7d/4s/3m)
- [x] Protección de backups (AES-256, SHA-256, fuera de Git, fuera de carpeta pública)
- [x] Procedimiento de recuperación (RPO 24h, RTO 4h, entorno aislado)
- [x] Prueba de restauración (28 tablas en BD temporal, sin tocar producción)
- [x] Automatización (script PowerShell, configurador Programador de Tareas)
- [x] Pruebas de seguridad (backups fuera de Git/público, scripts sin secretos, 31 tests en verde)
- [ ] Activar variable de entorno OCCRE_BACKUP_PASSWORD y tarea programada (requiere acción del usuario)
