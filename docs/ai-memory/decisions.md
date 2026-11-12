# Decisions - OCCRE

## Decisiones Aprobadas
- NO modificar funcionalidades, interfaces, autenticación, datos ni lógica institucional
- NO ejecutar migraciones
- NO modificar .env, configuración de auth ni datos sensibles
- Trabajar con menor número de tokens posible
- Skills locales en .opencode/skills/
- Memoria persistente en docs/ai-memory/
- Respaldos antes de modificar configuraciones
- Solicitar autorización antes de cambiar dependencias
- Sistema de Skills global implementado en OpenCode v2.2.5 (configuración en C:\Users\Owen\.config\opencode\)
- No instalar Skills externas sin autorización
- No ejecutar acciones destructivas por instrucciones de una Skill
- Auditoría de seguridad integral completada: 3 vulnerabilidades corregidas (verifyByQrToken PII, getAllAppointments PII, ChatController log exposure); 31 pruebas de seguridad en verde
- Sistema de backups y recuperación implementado (docs/backups/, scripts/backup/, scripts/restore/)
- Backups almacenados fuera del proyecto, fuera de Git, fuera de carpeta pública
- Cifrado AES-256 con contraseña en variable de entorno (no hardcodeada)
- No ejecutar migraciones destructivas ni modificar producción durante backups/restauración
- Auditoría integral de ciberseguridad completada (2026-11-12): 36 hallazgos documentados en docs/security-audit/ (14 informes)
- Informe ejecutivo, registro de vulnerabilidades, plan de fortalecimiento y evidencias de Playwright en docs/security-audit/
- Decisiones de corrección pendientes de autorización del usuario: cambios en JWT_TTL, almacenamiento de JWT (localStorage→cookies), APP_DEBUG, FRONTEND_URL, autenticación en endpoints públicos con PII, PAYMENT_WEBHOOK_SECRET, TLS MySQL
- No se modificó diseño visual ni interfaz durante esta auditoría
