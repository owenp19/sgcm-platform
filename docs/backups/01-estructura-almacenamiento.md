# Fase 1 - Auditoría de Almacenamiento OCCRE

## Resumen ejecutivo
Se identificaron todos los elementos de almacenamiento de OCCRE: base de datos MySQL, documentos de ciudadanos, archivos adjuntos, constancias, certificados, fotografías, archivos privados/públicos, configuraciones y claves criptográficas. Ningún dato personal de ciudadanos se almacena en este documento.

---

## 1. Base de Datos MySQL

**Motor:** MySQL (configurado en `backend/config/database.php`, conexión `mysql`)
**Base de datos:** `sgcm_db` (definida en `.env` como `DB_DATABASE`)
**Host:** `127.0.0.1` (puerto 3306)
**Driver PDO:** `pdo_mysql`

### Tablas identificadas (28 tablas)

| Grupo | Tablas | Datos sensibles |
|-------|--------|-----------------|
| Usuarios y roles | `users`, `roles`, `user_roles`, `user_preferences` | PII (nombre, email, documento, teléfono) |
| Trámites y solicitudes | `procedure_types`, `requests`, `request_documents`, `request_history` | PII (documento, notas, expedientes) |
| Auditoría | `audit_log` | IPs, user_agent, acciones de usuarios |
| Notificaciones y dispositivos | `notifications`, `device_tokens` | Tokens de push |
| Citas | `appointment_services`, `offices`, `appointments`, `appointment_documents` | PII (nombre, documento, email, teléfono del ciudadano) |
| Tarjeta de turismo | `tourism_tariffs`, `tourist_cards`, `tourist_card_payments`, `tourist_card_receipts`, `tourist_location_events`, `tourist_location_consents`, `tourist_return_reminders`, `tourist_device_tokens` | PII (nombre, documento, nacimiento, nacionalidad, email, teléfono, ubicación GPS, datos de pago) |
| Asistente IA | `knowledge_chunks` | Contenido de conocimiento institucional |
| Población | `population_zones` | Datos demográficos por zona (agregados, no individuales) |
| Sistema | `migrations`, `cache`, `sessions`, `jobs` | Metadatos del sistema |

### Relaciones críticas (claves foráneas)
- `requests.user_id` → `users.id`
- `request_documents.request_id` → `requests.id`
- `appointment_documents.appointment_id` → `appointments.id`
- `tourist_cards.*` → todas las tablas de turismo (`tourist_card_*`) con `cascadeOnDelete`
- `audit_log.user_id` → `users.id`

---

## 2. Archivos del Sistema de Archivos (Laravel Storage)

### Directorio `backend/storage/app/private` (disco `local`, privado)
- **Uso:** Documentos subidos por ciudadanos (RequestDocument.file_path, AppointmentDocument.file_path)
- **Acceso:** Solo a través de Laravel (no expuesto públicamente)
- **Ejemplo de rutas:** `uploads/{uuid}.{ext}`, `uploads/appointments/{uuid}.{ext}`

### Directorio `backend/storage/app/public` (disco `public`, expuesto vía symlink)
- **Uso:** Archivos públicos, constancias PDF, recibos de tarjeta de turismo (TouristCardReceipt.pdf_path)
- **Acceso:** Vía `public/storage` → `storage/app/public` (simbólico)
- **Ejemplo de rutas:** Constancias PDF, recibos de pago

### Otros directorios de storage
| Directorio | Uso | Crítico para recuperación |
|------------|-----|---------------------------|
| `storage/framework/cache` | Caché de aplicación | No crítico (se regenera) |
| `storage/framework/sessions` | Sesiones de Laravel | No crítico (se regenera) |
| `storage/framework/views` | Vistas compiladas | No crítico (se regenera) |
| `storage/framework/testing` | Pruebas | No crítico |
| `storage/logs/laravel.log` | Logs de aplicación | Bajo (auditoría) |
| `storage/api-docs/api-docs.json` | Documentación OpenAPI | Medio (referencia) |

---

## 3. Archivos del Frontend (Angular/Ionic)

| Ruta | Contenido | Crítico |
|------|-----------|---------|
| `src/environments/environment*.ts` | Configuración de API URLs por entorno | **SÍ** (conexión backend) |
| `src/app/` | Código fuente Angular/Ionic | **SÍ** (lógica de aplicación) |
| `src/assets/` | Imágenes, iconos, logos | Medio (identidad visual) |
| `capacitor.config.ts` | Configuración Capacitor (Android/iOS) | **SÍ** (compilación móvil) |
| `angular.json` | Configuración Angular | **SÍ** (build) |
| `package.json`, `package-lock.json` | Dependencias Node | **SÍ** (reinstalación) |

---

## 4. Configuraciones del Backend (Laravel)

| Ruta | Contenido | Crítico |
|------|-----------|---------|
| `backend/config/*.php` | Configuración Laravel (DB, JWT, filesystems, CORS, auth, servicios) | **SÍ** |
| `backend/database/migrations/` | Estructura de base de datos | **SÍ** |
| `backend/routes/api.php` | Rutas de la API REST | **SÍ** |
| `backend/composer.json`, `composer.lock` | Dependencias PHP | **SÍ** |
| `backend/app/` | Código fuente PHP (controladores, modelos, servicios) | **SÍ** |
| `backend/artisan` | CLI de Laravel | Medio |

---

## 5. Claves Criptográficas y Secretos (en `.env` - NO commitear)

| Variable | Uso | Crítico para recuperación |
|----------|-----|---------------------------|
| `APP_KEY` | Cifrado de cookies, sesiones Laravel | **SÍ** |
| `JWT_SECRET` | Firma de tokens JWT y firmas HMAC de QR | **SÍ** |
| `PAYMENT_WEBHOOK_SECRET` | Verificación de webhooks de pago | **SÍ** |
| `DB_PASSWORD` | Acceso a MySQL | **SÍ** |
| `MAIL_PASSWORD` | Envío de correos | Medio |
| `GEMINI_API_KEY` | Asistente virtual Miss Lorna | Medio |
| `FIREBASE_CREDENTIALS` | Notificaciones push | Medio |

> **ADVERTENCIA:** Estas claves viven en `.env` (no en el repositorio Git). Deben respaldarse por separado, cifradas y nunca incluirse en scripts ni en el repositorio.

---

## 6. Elementos Indispensables para Recuperación Completa

Para recuperar OCCRE completamente se requiere:

1. **Base de datos MySQL** completa (dump SQL de todas las tablas)
2. **Directorio `storage/app/private`** (documentos de ciudadanos)
3. **Directorio `storage/app/public`** (constancias, recibos PDF)
4. **Archivo `.env`** (claves APP_KEY, JWT_SECRET, DB credentials, etc.)
5. **Código fuente** (`backend/app/`, `src/app/`, configuraciones)
6. **Migraciones** (`backend/database/migrations/`) para recrear estructura si es necesario
7. **Dependencias** (`composer.lock`, `package-lock.json`) para reinstalación

---

## 7. Archivos NO críticos para recuperación (pueden regenerarse)

- `node_modules/` (se reinstala con `npm ci`)
- `vendor/` (se reinstala con `composer install`)
- `storage/framework/cache/`, `sessions/`, `views/`, `testing/`
- `.git/`, `node_modules/.bin/`
- Archivos de build (`dist/`, `www/`, `platforms/`)

---

## 8. Registro de hallazgos de la auditoría

| Elemento | Estado | Observación |
|----------|--------|-------------|
| Base de datos MySQL | Identificada | `sgcm_db`, 28 tablas, relaciones con cascadeOnDelete |
| Documentos ciudadanos | Identificados | `storage/app/private`, rutas en BD |
| Constancias/certificados | Identificados | `storage/app/public`, PDF generados por DomPDF |
| Claves criptográficas | Identificadas | En `.env`, no commiteadas |
| Configuración backend | Identificada | `backend/config/` completo |
| Código fuente | Identificado | Backend Laravel + Frontend Angular/Ionic |
| .gitignore | Presente | Excluye `.env`, `vendor`, `node_modules`, `storage/*.key` |
