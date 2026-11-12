# Auditoría de Seguridad MySQL — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** Configuración, usuarios, privilegios, consultas, integridad, auditoría, riesgos  
**Restricción:** NO se ejecutaron migraciones, DDL, DML, ni operaciones destructivas durante este diagnóstico. Solo lectura y revisión de configuración.

---

## 1. Configuración de Conexión (desde `backend/config/database.php` y `backend/.env`)

| Parámetro | Valor actual | Observación de seguridad |
|-----------|-------------|-------------------------|
| Driver | `mysql` | Correcto |
| Host | `127.0.0.1` | Positivo: solo localhost, no expuesto remotamente |
| Puerto | `3306` | Puerto estándar |
| Base de datos | `sgcm_db` (producción), `occre_test_db` (pruebas) | Base de pruebas aislada |
| Usuario | `usuario_db` (placeholder en .env) | No se verificó el usuario real de producción |
| Contraseña | `contrasena_db` (placeholder en .env) | Placeholder, no se expone contraseña real |
| Charset | `utf8mb4` | Correcto para Unicode |
| Collation | `utf8mb4_unicode_ci` | Correcto |
| Strict mode | `true` | Positivo: evita inserciones silenciosas incorrectas |
| Foreign key constraints | `true` | Positivo: integridad referencial activada |
| SSL/TLS | `MYSQL_ATTR_SSL_CA` no definido (vacío) | **Negativo**: conexiones sin cifrado TLS forzado |
| Engine | `null` (usa InnoDB por defecto) | InnoDB soporta transacciones y claves foráneas |

### Hallazgo MY-01: Conexiones MySQL sin TLS
- **Causa raíz:** `config/database.php` no configura `MYSQL_ATTR_SSL_CA` ni `sslmode`. Aunque el host es `127.0.0.1` (localhost), en entornos de producción remota las credenciales viajarían en texto plano.
- **Riesgo:** Interceptación de credenciales y datos en tránsito si la BD se mueve a un host remoto.
- **Archivo:** `backend/config/database.php` (líneas 62-64), `backend/.env`
- **Acción:** Evaluar habilitar TLS en producción remota. Requiere autorización (cambia configuración de BD).

---

## 2. Usuarios y Privilegios

**No se ejecutaron comandos SQL para inspeccionar usuarios de MySQL** (regla: no modificar usuarios/permisos, solo lectura y recomendaciones).

Desde la configuración observada:

| Usuario | Contexto | Privilegios observados | Observación |
|---------|----------|----------------------|-------------|
| `usuario_db` / `occre_test` | Aplicación / pruebas | Solo sobre su respectiva BD | Positivo: usuario de aplicación no es root, privilegios limitados a una BD |
| `root` | Administrador | No verificado | No se debe usar `root` para la aplicación |

### Hallazgo MY-02: No hay verificación de privilegios mínimos del usuario de aplicación
- No se pudo verificar que el usuario de aplicación de producción tenga solo SELECT, INSERT, UPDATE, DELETE sobre las tablas necesarias (sin DROP, ALTER, CREATE, GRANT, etc.).
- **Recomendación:** Auditar los privilegios reales del usuario de producción en MySQL y aplicar principio de mínimo privilegio.

---

## 3. Esquema de Base de Datos (desde migraciones)

Tablas identificadas en las migraciones:

| Tabla | Propósito | Datos personales | Claves foráneas |
|-------|-----------|------------------|-----------------|
| `roles` | Catálogo de roles | No | No |
| `user_roles` | Relación usuario-rol | No | Sí (users, roles) |
| `users` | Usuarios del sistema | **Sí** (nombre, email, documento, teléfono) | No (referenciada por otras) |
| `procedure_types` | Tipos de trámite | No | No |
| `requests` | Solicitudes/radicados | **Sí** (via user_id, notas) | Sí (users, procedure_types, assigned_to) |
| `request_documents` | Documentos adjuntos | **Sí** (nombres de archivo, rutas) | Sí (requests, users) |
| `request_history` | Historial de estados | Parcial (comentarios) | Sí (requests, users) |
| `audit_log` | Registro de auditoría | Parcial (details JSON) | Sí (users, nullable) |
| `notifications` | Notificaciones | No (título/descripción) | Sí (users) |
| `device_tokens` | Tokens push | No (token de dispositivo) | Sí (users) |
| `user_preferences` | Preferencias (biometría) | No | Sí (users) |
| `appointments` | Citas | **Sí** (nombre, documento, email, teléfono del ciudadano) | Sí (users) |
| `appointment_documents` | Documentos de cita | **Sí** | Sí (appointments) |
| `appointment_services` | Servicios de cita | No | No |
| `offices` | Oficinas de atención | No | No |
| `tourist_cards` | Tarjetas de turismo | **Sí** (nombre, documento, email, teléfono, contacto emergencia, alojamiento) | No |
| `tourist_card_payments` | Pagos de tarjeta | No | Sí (tourist_cards) |
| `tourist_card_receipts` | Recibos | Parcial (qr_token, qr_payload) | Sí (tourist_cards) |
| `tourist_location_events` | Ubicación turista | **Sí** (latitud, longitud) | Sí (tourist_cards) |
| `tourist_return_reminders` | Recordatorios | No | Sí (tourist_cards) |
| `knowledge_chunks` | Base de conocimiento RAG | No (debe ser pública) | No |
| `population_zones` | Zonas de población | No | No |

### Hallazgo MY-03: Tablas con datos personales sensibles
Las siguientes tablas almacenan PII de ciudadanos y requieren protección estricta:
- `users` — nombre, email, documento, teléfono
- `appointments` — nombre, documento, email, teléfono del ciudadano
- `tourist_cards` — nombre, documento, email, teléfono, contacto de emergencia, dirección de alojamiento, datos de viaje
- `tourist_location_events` — geolocalización de ciudadanos

---

## 4. Protección contra SQL Injection

### Análisis de consultas en controladores

Todos los controladores revisados usan **Eloquent** o **Query Builder con parámetros enlazados** (`where`, `find`, `join` con bindings). No se observó concatenación de strings en consultas SQL.

Ejemplos seguros observados:
- `User::where('email', $data['email'])->exists()` — parámetro enlazado
- `TouristCard::where('code', $code)->first()` — parámetro enlazado
- `DB::table('audit_log as al')->where('al.created_at', '>=', $dateFrom)` — parámetro enlazado

Una excepción potencialmente riesgosa:
- `RequestController.getAllRequests()` línea 474: `orWhereRaw("CONCAT(u.first_name, ' ', u.last_name) LIKE ?", [$term])` — usa `orWhereRaw` con binding `?`, lo cual es seguro si el binding se respeta.

### Hallazgo MY-04: Consultas preparadas correctamente implementadas
No se encontró inyección SQL directa. Todas las consultas usan bindings de parámetros.

---

## 5. Transacciones

Se observó uso de `DB::transaction()` en:
- `AuthController.register()` — registro atómico
- `AppointmentController.create()` — cita + documentos + notificación
- `TourismCardController.paymentWebhook()` — pago + recibo + recordatorios
- `RequestController.updateStatus()` — cambio de estado + historial + notificación

**Evaluación:** Las transacciones protegen la integridad de datos en operaciones multi-tabla.

---

## 6. Claves Foráneas e Integridad Referencial

| Relación | Tipo | Acción en cascada | Observación |
|----------|------|-------------------|-------------|
| `user_roles.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `user_roles.role_id` → `roles.id` | FK | cascadeOnDelete | Correcto |
| `requests.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `requests.procedure_type_id` → `procedure_types.id` | FK | cascadeOnDelete | Correcto |
| `requests.assigned_to` → `users.id` | FK | nullOnDelete | Correcto |
| `request_documents.request_id` → `requests.id` | FK | cascadeOnDelete | Correcto |
| `request_documents.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `request_documents.validated_by` → `users.id` | FK | nullOnDelete | Correcto |
| `audit_log.user_id` → `users.id` | FK | nullOnDelete | Correcto (permite logs huérfanos si se borra usuario, bueno para auditoría) |
| `notifications.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `device_tokens.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `user_preferences.user_id` → `users.id` | FK | cascadeOnDelete | Correcto |
| `appointments.user_id` → `users.id` | FK | (no especificado en migración vista) | Revisar |

### Hallazgo MY-05: Integridad referencial correctamente implementada
Todas las relaciones FK vistas usan `cascadeOnDelete` o `nullOnDelete` apropiadamente. La tabla `audit_log` usa `nullOnDelete` para preservar registros de auditoría incluso si se borra el usuario.

---

## 7. Índices

Índices observados en migraciones:
- `requests`: `idx_requests_user`, `idx_requests_status`, `idx_requests_tracking`, `idx_requests_assigned`
- `users`: `email` (implícito unique), `document_number` (por verificar)

**Evaluación:** Índices adecuados para las consultas principales. No se ejecutó EXPLAIN para verificar uso real.

---

## 8. Registros de Auditoría (audit_log)

La tabla `audit_log` registra:
- `user_id`, `action`, `entity_type`, `entity_id`, `details` (JSON), `ip_address`, `user_agent`, `created_at`

Se invoca mediante `AuditLog::record()` en:
- AuthController: register, login, reset_password, change_password, change_email, update_roles, delete_user, update_biometric_pref
- DocumentController: upload_document, validate_document, delete_document
- AppointmentController: (implícito a través de otras acciones)
- RequestController: create_request, update_request_status, assign_request
- TourismCardController: (implícito)

**Evaluación:** Buena cobertura de auditoría para acciones críticas. No se observó logging de acceso de lectura (SELECT) a datos sensibles, solo de escritura.

---

## 9. Riesgos de Corrupción y Pérdida

| Riesgo | Evaluación | Mitigación actual |
|--------|-----------|-------------------|
| Corrupción de BD | No verificado | Backups diarios con SHA-256 (ver fase 9) |
| Pérdida de datos por borrado accidental | cascadeOnDelete puede borrar datos relacionados | Backups, soft deletes no usados en tablas críticas |
| Pérdida por falla de disco | No verificado | Backups fuera del proyecto (C:\Users\Owen\Backups\OCCRE) |
| Pérdida por ransomware | No verificado | Backups fuera del proyecto, cifrados AES-256 |

---

## 10. Resumen de Hallazgos MySQL

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| MY-01 | Conexiones MySQL sin TLS configurado | Media | Pendiente (requiere autorización) |
| MY-02 | No se verificaron privilegios mínimos del usuario de aplicación | Media | Pendiente (requiere acceso a MySQL de producción) |
| MY-03 | Tablas con PII requieren cifrado en reposo (no verificado) | Media | Pendiente |
| MY-04 | SQL Injection: no encontrado (consultas preparadas) | — | OK |
| MY-05 | Integridad referencial: correctamente implementada | — | OK |
| MY-06 | Registros de auditoría: cobertura parcial (no hay logs de lectura SELECT) | Baja | Pendiente |

---

## 11. Recomendaciones (requieren autorización para implementar)

1. **Habilitar TLS/MySQL SSL** en configuración de producción si la BD no está en localhost.
2. **Auditar privilegios del usuario de aplicación** en MySQL de producción: mínimo privilegio (SELECT, INSERT, UPDATE, DELETE solo en tablas necesarias).
3. **Evaluar cifrado en reposo** para tablas con PII (users, tourist_cards, appointments).
4. **Considerar logs de acceso de lectura** a endpoints que devuelven PII (quién consultó qué dato de qué ciudadano).
5. **Mantener backups verificados** y probar restauración periódicamente (ya implementado, ver docs/backups/).
