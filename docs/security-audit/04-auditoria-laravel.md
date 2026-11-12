# Auditoría de Seguridad Backend Laravel — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** Controladores, modelos, middleware, rutas, servicios, configuraciones  
**Restricción:** NO se modificó autenticación, .env, ni se ejecutaron migraciones.

---

## 1. Configuración de Seguridad Crítica

### Archivo `backend/.env` (entorno de desarrollo/local)

| Variable | Valor | Evaluación |
|----------|-------|------------|
| `APP_DEBUG` | `true` | **CRÍTICO**: expone stack traces, rutas internas, variables de entorno en errores 500 |
| `APP_ENV` | `local` | Adecuado para desarrollo |
| `APP_KEY` | `base64:...` | Configurado (clave de cifrado de Laravel) |
| `APP_URL` | `https://api.tudominio.com` | Placeholder |
| `DB_HOST` | `127.0.0.1` | Positivo: solo localhost |
| `DB_USERNAME` | `usuario_db` | Placeholder |
| `DB_PASSWORD` | `contrasena_db` | Placeholder |
| `JWT_SECRET` | Configurado | Presente |
| `JWT_TTL` | `10080` (7 días) | Largo: ventana de exposición extendida si se roba el token |
| `FRONTEND_URL` | `https://tu-app-ionic.com` | Placeholder (CORS usaría `*` si no se configura) |
| `GEMINI_API_KEY` | Vacío | Chatbot no configurado en este entorno |
| `PAYMENT_WEBHOOK_SECRET` | `cambia-este-secreto-hmac` | **Débil**: valor por defecto |
| `SESSION_DRIVER` | `file` | File-based, no apto para multi-instancia |
| `CACHE_STORE` | `file` | File-based, revocación JWT no consistente entre nodos |
| `LOG_LEVEL` | `warning` | Adecuado |

### Hallazgo LA-01: APP_DEBUG=true
- **Causa raíz:** Configuración de entorno no ajustada para producción.
- **Riesgo:** Los errores 500 devuelven stack traces con rutas de archivos, consultas SQL, variables de entorno.
- **Archivo:** `backend/.env`
- **Acción:** Establecer `APP_DEBUG=false` en producción. Requiere autorización (cambia .env).

---

## 2. Rutas y Middleware (`backend/routes/api.php`)

### Estructura de middleware por grupo

| Grupo de rutas | Middleware | Observación |
|---------------|------------|-------------|
| `/health` | `throttle:api` | Público, limitado |
| `/auth/register`, `/auth/login`, `/auth/forgot-password`, `/auth/reset-password` | `throttle:auth` | Público, limitado |
| `/auth/refresh`, `/auth/logout`, `/auth/profile`, `/auth/users/*`, `/auth/biometric-preference` | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado |
| `/notifications/*` | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado |
| `/procedures/*` (GET) | Público | Lectura pública de trámites |
| `/procedures/*` (POST/PUT/DELETE) | `auth:api`, `jwt.fresh`, `role:admin` | Solo admin |
| `/requests/search-record` | `throttle:api` | Público (requiere tracking + documento) |
| `/requests/*` (autenticado) | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado |
| `/requests/{id}/status`, `/requests/{id}/assign` | `role:funcionario,admin` o `role:admin` | Staff |
| `/documents/*` | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado + propiedad |
| `/admin/*` | `auth:api`, `jwt.fresh`, `role:admin,funcionario` o `role:admin` | Staff/Admin |
| `/appointments/*` (público) | `throttle:api` | Servicios, oficinas, disponibilidad |
| `/appointments/*` (autenticado) | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado |
| `/devices/*` | `auth:api`, `jwt.fresh`, `throttle:api` | Autenticado |
| `/announcements/*` (GET active) | Público | Lectura pública |
| `/announcements/*` (staff) | `auth:api`, `jwt.fresh`, `role:admin` | Solo admin |
| `/certificates/validate-qr` | `throttle:api` | Público |
| `/certificates/generate/{trackingNumber}` | `auth:api`, `jwt.fresh` | Autenticado + propiedad |
| `/tourism-card/*` | Mixto: público y autenticado | Ver detalle abajo |
| `/chat` | `auth:api`, `jwt.fresh`, `throttle:chat` | Autenticado |

### Endpoints públicos (sin autenticación)

1. `GET /health` — estado del sistema (redactado)
2. `POST /auth/register` — registro
3. `POST /auth/login` — login
4. `POST /auth/forgot-password` — recuperación
5. `POST /auth/reset-password` — restablecimiento
6. `GET /procedures/` — listar trámites
7. `GET /procedures/{slug}` — trámite por slug
8. `POST /requests/search-record` — búsqueda pública por radicado + documento
9. `GET /appointments/services`, `/offices`, `/availability` — datos públicos de citas
10. `GET /announcements/active` — anuncios públicos
11. `POST /certificates/validate-qr` — validar QR de constancia
12. `GET /tourism-card/tariff` — tarifa
13. `POST /tourism-card/quote` — cotización
14. `POST /tourism-card/card` — crear tarjeta
15. `POST /tourism-card/card/{code}/payment/init` — iniciar pago
16. `GET /tourism-card/card/{code}/payment/status` — estado de pago
17. `POST /tourism-card/search` — **buscar tarjeta por código + documento (PÚBLICO, devuelve PII)**
18. `GET /tourism-card/card/{code}/payment/status` — estado
19. `GET /tourism-card/card/{code}/receipt` — recibo (máscaras si no hay acceso completo)
20. `GET /tourism-card/card/{code}/verify` — verificar tarjeta
21. `GET /tourism-card/card/verify/qr/{qr_token}` — verificar QR
22. `POST /tourism-card/card/{code}/share-location` — **sin autenticación, cualquiera con el código puede postear ubicación**
23. `POST /tourism-card/payment/webhook` — webhook con firma HMAC
24. `POST /tourism-card/expired/check` — mantenimiento (autenticado)
25. `POST /chat` — chatbot (autenticado)

### Hallazgo LA-02: Endpoints públicos que devuelven PII sin autenticación
- `POST /tourism-card/search` acepta `code` + `document_number` y devuelve nombre, email, teléfono, contacto de emergencia, alojamiento, etc. Sin autenticación, un atacante puede intentar enumerar combinaciones.
- `POST /tourism-card/card/{code}/share-location` no requiere autenticación. Cualquiera que conozca un `code` puede registrar eventos de ubicación para esa tarjeta.

---

## 3. Controladores — Análisis Detallado

### AuthController.php

| Método | Endpoint | Riesgo | Observación |
|--------|----------|--------|-------------|
| `register` | POST /auth/register | Media | Password min 6 (débil). Retorna token inmediatamente. |
| `login` | POST /auth/login | Media | Password min 6. Sin bloqueo por intentos fallidos (solo throttle de ruta). Retorna documento/telefono del usuario. |
| `refresh` | POST /auth/register | Baja | Renueva token JWT. |
| `logout` | POST /auth/logout | Baja | Invalida token actual. |
| `getProfile` | GET /auth/profile | Baja | Retorna datos del usuario autenticado. |
| `updateProfile` | PUT /auth/profile | Baja | Actualiza perfil propio. |
| `uploadPhoto` | PUT /auth/photo | Baja | Actualiza photo_url (string, sin validación de URL). |
| `forgotPassword` | POST /auth/forgot-password | Baja | Token SHA-256 hash, expira 1 hora. No loguea el token. |
| `resetPassword` | POST /auth/reset-password | Media | Verifica hash del token. Revoca JWT vía caché. |
| `changePassword` | PUT /auth/change-password | Baja | Verifica contraseña actual. Revoca JWT. |
| `changeEmail` | PUT /auth/change-email | Baja | Verifica contraseña actual. No envía email de confirmación al nuevo correo. |
| `listUsers` | GET /auth/users | **Alta** | Devuelve TODOS los usuarios con email, documento, teléfono, roles. Accesible por admin/funcionario. |
| `updateUserRoles` | PUT /auth/users/{id}/roles | **Crítica** | Solo admin puede gestionar rol admin. Funcionario no puede escalar. |
| `deleteUser` | DELETE /auth/users/{id}/roles | Alta | Solo admin. Protege último admin. |

### Hallazgo LA-03: Contraseña mínima de 6 caracteres
- **Causa raíz:** Validación `'password' => ['required', 'string', 'min:6']` en registro.
- **Riesgo:** Contraseñas débiles susceptibles a fuerza bruta.
- **Archivo:** `backend/app/Http/Controllers/Api/AuthController.php` (líneas 63-71)

### Hallazgo LA-04: `listUsers` expone PII de todos los ciudadanos
- **Causa raíz:** Endpoint devuelve documento_number, phone, email de todos los usuarios sin filtrado por necesidad de saber.
- **Riesgo:** Funcionario malintencionado puede exportar PII masiva.
- **Archivo:** `backend/app/Http/Controllers/Api/AuthController.php` (líneas 589-620)

### Hallazgo LA-05: changeEmail no envía confirmación ni revoca sesiones
- **Causa raíz:** El email cambia inmediatamente sin verificar el nuevo correo ni invalidar tokens existentes.
- **Riesgo:** Atacante con acceso a la sesión puede cambiar el email y bloquear al propietario legítimo.
- **Archivo:** `backend/app/Http/Controllers/Api/AuthController.php` (líneas 539-573)

### DocumentController.php

| Método | Riesgo | Observación |
|--------|--------|-------------|
| `upload` | Media | Valida MIME types y tamaño (10MB). Almacena con nombre UUID. Verifica propiedad de la solicitud. No verifica contenido del archivo (solo extensión/MIME). |
| `download` | Baja | Verifica propiedad o staff. Descarga archivo almacenado. |
| `validateDocument` | Baja | Solo staff. |
| `delete` | Baja | Verifica propiedad o staff. |

### Hallazgo LA-06: Subida de archivos sin verificación de contenido
- **Causa raíz:** Se valida `getMimeType()` y extensión, pero no el contenido real del archivo (puede ser un archivo PHP con extensión .jpg).
- **Riesgo:** Subida de webshell si el servidor web interpreta archivos en `storage/app/uploads/`.
- **Archivo:** `backend/app/Http/Controllers/Api/DocumentController.php` (líneas 26-34)

### ChatController.php

| Método | Riesgo | Observación |
|--------|--------|-------------|
| `chat` | Media | Usa GeminiService + RagKnowledgeService. Log de error no incluye mensaje de excepción (ya corregido). Valida message (max 4000), context (max 12000), history. No filtra datos del usuario en el RAG (conocimiento público). |

### Hallazgo LA-07: Chatbot sin filtrado de datos por usuario
- **Causa raíz:** `RagKnowledgeService::search()` busca en todos los knowledge_chunks sin filtrar por usuario.
- **Riesgo:** Si el knowledge base contuviera PII, estaría accesible para cualquier usuario autenticado.
- **Archivo:** `backend/app/Http/Controllers/Api/ChatController.php`, `backend/app/Services/RagKnowledgeService.php`

### TourismCardController.php

| Método | Riesgo | Observación |
|--------|--------|-------------|
| `searchCard` | **Crítica** | Público, devuelve PII completa (email, teléfono, contacto emergencia, alojamiento) sin autenticación. |
| `getReceipt` | Alta | Devuelve PII completa si código de alta entropía o token válido. Máscaras aplicadas solo si no hay acceso completo. |
| `shareLocation` | Alta | Sin autenticación. Cualquiera con el código puede registrar ubicación. |
| `create` | Media | Retorna `receipt_token` en respuesta. Código de 12 caracteres alta entropía. |
| `verifyByQrToken` | Baja | Solo máscara de documento, no nombres completos. |
| `paymentWebhook` | Media | Verifica firma HMAC-SHA256 vía middleware. |

### Hallazgo LA-08: `searchCard` expone PII sin autenticación
- **Causa raíz:** Endpoint público sin middleware `auth:api`.
- **Riesgo:** Enumeración masiva de datos personales de ciudadanos.
- **Archivo:** `backend/app/Http/Controllers/Api/TourismCardController.php` (líneas 431-452)

---

## 4. Middleware

| Middleware | Propósito | Evaluación |
|------------|-----------|------------|
| `RejectRevokedTokens` | Revoca JWT emitidos antes de cambio/restablecimiento de contraseña | Bueno. Usa caché file (no compartido entre nodos). |
| `RoleMiddleware` | Verifica roles (admin, funcionario, ciudadano) | Bueno. Retorna 403 si no tiene el rol. |
| `VerifyPaymentSignature` | Verifica HMAC-SHA256 en webhook de pagos | Bueno. Puede desactivarse con `PAYMENT_WEBHOOK_VERIFY=false`. |

### Hallazgo LA-09: Revocación JWT en caché file
- **Causa raíz:** `CACHE_STORE=file` y `RejectRevokedTokens` usa `Cache::get/forever`.
- **Riesgo:** En despliegue multi-instancia, la revocación no es consistente entre nodos.
- **Archivo:** `backend/app/Http/Middleware/RejectRevokedTokens.php`, `backend/.env`

---

## 5. Modelos y Mass Assignment

| Modelo | $fillable incluye campos sensibles | $hidden | Observación |
|--------|-----------------------------------|---------|-------------|
| `User` | reset_token, reset_token_expires | password, reset_token, reset_token_expires, remember_token | `forceFill` usado en resetPassword (bypassa mass assignment protection) |
| `TouristCard` | (no revisado completamente) | (no revisado) | `forceFill` usado en paymentWebhook |

### Hallazgo LA-10: Uso de `forceFill` en User y TouristCard
- `AuthController.resetPassword()` usa `$user->forceFill([...])` para actualizar reset_token.
- `TourismCardController.paymentWebhook()` usa `$card->forceFill([...])` para actualizar estado de pago.
- **Riesgo:** `forceFill` bypassa la protección de mass assignment de Eloquent. Si un modelo tiene campos sensibles en `$fillable`, un atacante podría modificarlos.
- **Archivos:** `backend/app/Http/Controllers/Api/AuthController.php` (líneas 388-391, 450-454), `backend/app/Http/Controllers/Api/TourismCardController.php` (líneas 751-760, 794-797)

---

## 6. Resumen de Hallazgos Laravel

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| LA-01 | APP_DEBUG=true | Crítica | Pendiente (requiere autorización) |
| LA-02 | Endpoints públicos devuelven PII sin auth | Crítica | Pendiente |
| LA-03 | Contraseña mínima 6 caracteres | Alta | Pendiente |
| LA-04 | listUsers expone PII masiva | Alta | Pendiente |
| LA-05 | changeEmail sin confirmación ni revocación | Media | Pendiente |
| LA-06 | Subida de archivos sin verificación de contenido | Media | Pendiente |
| LA-07 | Chatbot sin filtrado por usuario | Media | Pendiente |
| LA-08 | searchCard expone PII sin autenticación | Crítica | Pendiente |
| LA-09 | Revocación JWT en caché file (no multi-instancia) | Media | Pendiente |
| LA-10 | Uso de forceFill bypassa mass assignment | Media | Pendiente |

---

## 7. Recomendaciones (requieren autorización)

1. Establecer `APP_DEBUG=false` en producción.
2. Agregar autenticación a `/tourism-card/search` o limitar los datos devueltos.
3. Agregar autenticación a `/tourism-card/card/{code}/share-location` o validar propiedad de la tarjeta.
4. Aumentar política de contraseñas (mínimo 8-12 caracteres).
5. Filtrar `listUsers` para no exponer documento/teléfono de todos los usuarios.
6. Agregar confirmación de email en `changeEmail` y revocar sesiones existentes.
7. Verificar contenido real de archivos subidos (no solo MIME type).
8. Filtrar knowledge base del chatbot por permisos de usuario si contiene PII.
9. Migrar caché de revocación JWT a Redis/DB compartida.
10. Reemplazar `forceFill` por `fill` + `save` con campos explícitos.
