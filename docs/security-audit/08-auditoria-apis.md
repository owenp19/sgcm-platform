# Auditoría de Seguridad de APIs y Conexiones — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** HTTPS/TLS, CORS, CSP, cabeceras de seguridad, autenticación entre servicios, secretos, WebSockets, servicios externos, conexiones MySQL, almacenamiento

---

## 1. Inventario de Endpoints

### Endpoints públicos (sin autenticación)

| Método | Ruta | Middleware | Sensitividad |
|--------|------|-----------|--------------|
| GET | `/health` | throttle:api | Baja (redactado) |
| POST | `/auth/register` | throttle:api | Alta (creación de cuenta) |
| POST | `/auth/login` | throttle:api | Alta (credenciales) |
| POST | `/auth/forgot-password` | throttle:api | Media (recuperación) |
| POST | `/auth/reset-password` | throttle:api | Media (restablecimiento) |
| GET | `/procedures/` | — | Baja (catálogo público) |
| GET | `/procedures/{slug}` | — | Baja |
| POST | `/requests/search-record` | throttle:api | **Alta (devuelve PII sin auth)** |
| GET | `/appointments/services` | throttle:api | Baja |
| GET | `/appointments/offices` | throttle:api | Baja |
| GET | `/appointments/availability` | throttle:api | Baja |
| GET | `/announcements/active` | throttle:api | Baja |
| POST | `/certificates/validate-qr` | throttle:api | Media (validación QR) |
| GET | `/tourism-card/tariff` | throttle:api | Baja |
| POST | `/tourism-card/quote` | throttle:tourism-card | Media |
| POST | `/tourism-card/card` | throttle:tourism-card | **Alta (crea tarjeta con PII)** |
| POST | `/tourism-card/card/{code}/payment/init` | throttle:tourism-card | Media |
| GET | `/tourism-card/card/{code}/payment/status` | throttle:api | Media |
| POST | `/tourism-card/search` | throttle:tourism-card | **Crítica (PII sin auth)** |
| GET | `/tourism-card/card/{code}/payment/status` | throttle:api | Media |
| GET | `/tourism-card/card/{code}/receipt` | throttle:tourism-card | **Alta (PII con máscaras)** |
| GET | `/tourism-card/card/{code}/verify` | throttle:api | Media |
| GET | `/tourism-card/card/verify/qr/{qr_token}` | throttle:api | Media |
| POST | `/tourism-card/card/{code}/share-location` | throttle:tourism-card | **Alta (sin auth, registra ubicación)** |
| POST | `/tourism-card/payment/webhook` | VerifyPaymentSignature | **Crítica (pagos, HMAC)** |
| POST | `/tourism-card/expired/check` | auth:api, jwt.fresh | Media |

### Endpoints autenticados (JWT Bearer)

Todos los demás endpoints requieren `auth:api` + `jwt.fresh`, muchos con `role:admin` o `role:funcionario,admin`.

---

## 2. HTTPS y TLS

### Configuración observada

| Capa | Configuración | Evaluación |
|------|--------------|------------|
| Frontend → Backend | `apiUrl: '/api'` (relativo, depende del proxy) | Depende del despliegue |
| Capacitor server | `androidScheme: 'https'` | Positivo |
| Backend → MySQL | `MYSQL_ATTR_SSL_CA` no definido | **Negativo** (sin TLS forzado) |
| Backend → Gemini | HTTPS a `generativelanguage.googleapis.com` | Positivo |
| Backend → Firebase | HTTPS | Positivo |
| Backend → Pasarela pagos | HTTPS (presumido) | Por verificar |

### Hallazgo API-01: MySQL sin TLS configurado
- **Causa raíz:** `config/database.php` no configura SSL para conexiones MySQL.
- **Riesgo:** Si la BD se despliega en host remoto, credenciales y datos viajan en texto plano.
- **Archivo:** `backend/config/database.php` (líneas 62-64)

---

## 3. CORS (`backend/config/cors.php`)

| Parámetro | Valor | Evaluación |
|-----------|-------|------------|
| `paths` | `['api/*', 'up']` | Adecuado |
| `allowed_methods` | GET, POST, PUT, PATCH, DELETE, OPTIONS | Adecuado |
| `allowed_origins` | `env('FRONTEND_URL', '*')` → `*` por defecto | **Riesgo**: si FRONTEND_URL no está configurado, permite cualquier origen |
| `allowed_origins_patterns` | `[]` | No usado |
| `allowed_headers` | `['Content-Type', 'Authorization']` | Adecuado |
| `exposed_headers` | `[]` | Ninguna |
| `max_age` | 0 | Sin preflight cache |
| `supports_credentials` | `true` si origen != `*` | Positivo cuando origen está especificado |

### Hallazgo API-02: CORS permite `*` por defecto
- **Causa raíz:** `FRONTEND_URL` tiene valor placeholder `https://tu-app-ionic.com` en .env, pero si no se configura correctamente, el fallback es `['*']`.
- **Riesgo:** Cualquier sitio web puede hacer peticiones a la API. Con `supports_credentials=false` cuando origen es `*`, las cookies no se envían, pero los tokens JWT en localStorage SÍ se enviarían (el frontend los añade manualmente en el header Authorization).
- **Archivo:** `backend/config/cors.php` (línea 19)

---

## 4. Cabeceras de Seguridad

### Cabeceras observadas

No se observó configuración explícita de las siguientes cabeceras de seguridad en el backend Laravel:

| Cabecera | Estado | Riesgo |
|----------|--------|--------|
| `Content-Security-Policy` | No configurada | XSS, inyección de recursos maliciosos |
| `X-Content-Type-Options: nosniff` | No configurada | MIME sniffing |
| `X-Frame-Options: DENY/SAMEORIGIN` | No configurada | Clickjacking |
| `Strict-Transport-Security` | No configurada | Downgrade a HTTP |
| `Referrer-Policy` | No configurada | Filtración de URLs en Referer |
| `X-XSS-Protection` | No configurada | XSS en navegadores antiguos |

### Hallazgo API-03: Ausencia de cabeceras de seguridad
- **Causa raíz:** No se observó middleware global ni configuración de servidor para cabeceras de seguridad.
- **Riesgo:** La aplicación es vulnerable a clickjacking, MIME sniffing, y otros ataques basados en navegador.
- **Archivo:** `backend/` (no se observó configuración)

---

## 5. Autenticación entre Servicios

| Servicio | Método de autenticación | Evaluación |
|----------|------------------------|------------|
| MySQL | Usuario + contraseña en .env | Básico, sin TLS |
| Gemini | API Key en URL query | **Riesgo** (expuesta en URL) |
| Firebase | Service Account JSON (`FIREBASE_CREDENTIALS`) | No configurado (vacío en .env) |
| Pasarela pagos | HMAC-SHA256 (`PAYMENT_WEBHOOK_SECRET`) | Bueno, pero valor por defecto |
| Push (FCM) | Device tokens en BD | OK |

---

## 6. Secretos y API Keys

| Secreto | Ubicación | Estado | Riesgo |
|---------|-----------|--------|--------|
| `APP_KEY` | `.env` | Configurado | Crítico (firma cookies, tokens, QR) |
| `JWT_SECRET` | `.env` | Configurado | Crítico (firma JWT) |
| `GEMINI_API_KEY` | `.env` | Vacío | Informativo (chatbot no configurado) |
| `PAYMENT_WEBHOOK_SECRET` | `.env` | `cambia-este-secreto-hmac` | **Valor por defecto débil** |
| `DB_PASSWORD` | `.env` | Placeholder | No se expone valor real |
| `MAIL_PASSWORD` | `.env` | Placeholder | No se expone valor real |
| `FIREBASE_CREDENTIALS` | `.env` | Vacío | No configurado |

### Hallazgo API-04: PAYMENT_WEBHOOK_SECRET con valor por defecto
- **Causa raíz:** `PAYMENT_WEBHOOK_SECRET=cambia-este-secreto-hmac` en .env.
- **Riesgo:** Un atacante podría falsificar webhooks de pago si conoce el valor por defecto.
- **Archivo:** `backend/.env`

---

## 7. WebSockets

No se observó uso de WebSockets en la aplicación. La comunicación es totalmente REST/HTTP.

---

## 8. Servicios Externos

| Servicio | Propósito | URL | Autenticación | Evaluación |
|----------|-----------|-----|--------------|------------|
| Google Gemini | Chatbot IA | `generativelanguage.googleapis.com` | API Key en URL | **Riesgo** (key en URL) |
| Firebase Cloud Messaging | Push notifications | `fcm.googleapis.com` | Service Account | No configurado |
| Pasarela pagos | Pagos tarjeta turismo | `PAYMENT_GATEWAY_URL` (vacío) | HMAC-SHA256 | Sandbox, no configurado |
| SMTP | Email de recuperación | `mail.tudominio.com` | Usuario + contraseña | Placeholder |
| Cloudflare/ngrok | Túnel de desarrollo | Variable | — | Solo desarrollo |

---

## 9. Conexiones MySQL

Ver sección de auditoría MySQL (docs/security-audit/03-auditoria-mysql.md).

Resumen: Host `127.0.0.1`, sin TLS, usuario no-root (presumido), charset utf8mb4.

---

## 10. Almacenamiento de Archivos

| Aspecto | Configuración | Evaluación |
|---------|--------------|------------|
| Disco | `local` (`FILESYSTEM_DISK=local`) | Archivos en `storage/app/uploads/` |
| Subida de documentos | MIME type validado, tamaño max 10MB | Nombre UUID, pero sin verificación de contenido |
| Subida de citas | MIME type validado, tamaño max 10MB | Misma observación |
| Acceso a archivos | Vía Laravel `Storage::download()` | Verifica propiedad antes de servir |

---

## 11. Resumen de Hallazgos APIs y Conexiones

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| API-01 | MySQL sin TLS | Media | Pendiente (requiere autorización) |
| API-02 | CORS permite `*` por defecto | Alta | Pendiente (verificar FRONTEND_URL en producción) |
| API-03 | Falta de cabeceras de seguridad (CSP, HSTS, X-Frame-Options, etc.) | Media | Pendiente |
| API-04 | API Key Gemini en URL | Alta | Pendiente |
| API-05 | PAYMENT_WEBHOOK_SECRET con valor por defecto | Alta | Pendiente |
| API-06 | Endpoints públicos devuelven PII (ver auditoría Laravel) | Crítica | Ya documentado (LA-02, LA-08) |

---

## 12. Recomendaciones (requieren autorización)

1. Configurar `FRONTEND_URL` con dominios específicos en producción (no `*`).
2. Agregar cabeceras de seguridad: CSP, X-Content-Type-Options, X-Frame-Options, Strict-Transport-Security, Referrer-Policy.
3. Mover GEMINI_API_KEY de URL a header de autorización.
4. Cambiar `PAYMENT_WEBHOOK_SECRET` por un valor fuerte y aleatorio.
5. Habilitar TLS para MySQL en producción remota.
6. Verificar que todos los endpoints que devuelven PII requieran autenticación y autorización adecuada.
