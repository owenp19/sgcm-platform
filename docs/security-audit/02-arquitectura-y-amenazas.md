# Mapa de Arquitectura y Modelo de Amenazas — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform) — Sistema de Gestión y Control Migratorio Raizal  
**Clasificación:** Interno — Contiene información de arquitectura y modelo de amenazas

---

## 1. Visión General de la Arquitectura

OCCRE es una plataforma híbrida que gestiona información migratoria de ciudadanos (incluidos datos personales sensibles: nombres, documentos, teléfonos, direcciones de alojamiento, datos de viaje, ubicación). Administra trámites, citas, solicitudes, documentos, pagos (tarjeta de turismo), certificados y un asistente virtual basado en IA.

### Stack tecnológico

| Capa | Tecnología | Versión | Ubicación |
|------|-----------|---------|-----------|
| Frontend Web | Angular 20 + Ionic 8 | 20 / 8 | `src/` (raíz) |
| Frontend Móvil | Capacitor 8 (Android APK, iOS preparado) | 8 | `android/`, `ios/`, `apk/my-occre-app.apk` |
| Backend API | Laravel 12 + PHP 8.2 | 12.67.0 / 8.2.12 | `backend/` |
| Base de datos | MySQL | — | `DB_HOST=127.0.0.1` (local) |
| Autenticación | JWT (tymon/jwt-auth) | ^2.3 | HS256, TTL=10080 min (7 días) |
| IA / Chatbot | Google Gemini API | gemini-3.6-flash | `backend/app/Services/GeminiService.php` |
| Notificaciones push | Firebase Cloud Messaging | — | `FIREBASE_CREDENTIALS` |
| Pagos | Pasarela sandbox (webhook HMAC-SHA256) | — | `PAYMENT_WEBHOOK_SECRET` |
| Almacenamiento archivos | Laravel Filesystem (disco local) | — | `storage/app/uploads/` |
| Caché / Sesiones | File-based | — | `CACHE_STORE=file`, `SESSION_DRIVER=file` |
| Colas | Database | — | `QUEUE_CONNECTION=database` |
| Documentos PDF/Excel | DomPDF, Maatwebsite Excel | — | `backend/app/Exports/` |
| Pruebas E2E | Playwright (@playwright/test) | ^1.65.0 | `docs/auditoria-visual/playwright/` |
| Pruebas backend | PHPUnit | ^11.5.50 | `backend/tests/` |

### Flujo de información (niveles altos)

```
Usuario (App Web/Android)
        │
        ▼ HTTPS (TLS)
[ Capacitor WebView / Navegador ]
        │
        ▼ HTTP/REST (JWT Bearer)
[ Laravel API (backend/) ]
        │
        ├────► MySQL (usuarios, solicitudes, citas, documentos, auditoría)
        │
        ├────► Google Gemini API (chatbot Miss Lorna)
        │
        ├────► Firebase (push notifications)
        │
        └────► Sistema de archivos local (subidas de documentos)
```

---

## 2. Límites de Confianza

| Límite | Descripción | Nivel de confianza |
|--------|-------------|-------------------|
| **Cliente ↔ API** | HTTPS/TLS. Tokens JWT almacenados en localStorage/Preferences (vulnerables a XSS). | Medio — depende de TLS y del cliente |
| **API ↔ MySQL** | Conexión local (127.0.0.1:3306). Usuario de aplicación sin privilegios de administrador (ver fase 3). TLS no configurado explícitamente (MYSQL_ATTR_SSL_CA no definido). | Medio-Alto (localhost) / Bajo (sin TLS forzado) |
| **API ↔ Gemini** | HTTPS a `generativelanguage.googleapis.com`. API key enviada en URL query string (`?key=...`). | Bajo — API key expuesta en URL |
| **API ↔ Firebase** | HTTPS. Credenciales de cuenta de servicio. | Medio-Alto |
| **API ↔ Pasarela pagos** | Webhook con firma HMAC-SHA256. Puede desactivarse con `PAYMENT_WEBHOOK_VERIFY=false`. | Medio |
| **Cliente ↔ Cliente** | No hay comunicación directa entre clientes. | N/A |

---

## 3. Activos Críticos

| Activo | Descripción | Criticidad | Protección actual |
|--------|-------------|------------|-------------------|
| Datos personales de ciudadanos | Nombres, documento, email, teléfono, dirección, datos de viaje, contacto de emergencia | Crítica | Cifrado en tránsito (TLS), JWT auth, roles, máscaras PII en algunos endpoints |
| Credenciales de usuarios | Contraseñas hasheadas (bcrypt via Hash::make) | Crítica | Hash bcrypt, min length 6 (débil) |
| Tokens JWT | Tokens de acceso con TTL de 7 días | Crítica | Almacenados en localStorage/Preferences, blacklist activada, revocación por iat |
| API Key de Gemini | Clave para asistente de IA | Alta | En .env (no en Git), pero enviada en URL de petición |
| Clave HMAC pagos | `PAYMENT_WEBHOOK_SECRET` | Alta | Valor por defecto "cambia-este-secreto-hmac" |
| Clave de aplicación Laravel | `APP_KEY` (base64) | Crítica | En .env, usada para firmar cookies, tokens de recibo, firmas QR |
| Documentos subidos | PDF, imágenes de ciudadanos | Alta | Almacenamiento local, nombres UUID, validación MIME |
| Registros de auditoría | audit_log con IP, user_agent, acciones | Alta | Tabla dedicada, sin soft deletes, registro de acciones clave |
| Claves de cifrado de backups | `OCCRE_BACKUP_PASSWORD` | Crítica | Variable de entorno, no hardcodeada |

---

## 4. Posibles Atacantes (Threat Actors)

| Atacante | Motivación | Capacidad |
|----------|------------|-----------|
| Ciudadano malintencionado | Acceder a datos de otros ciudadanos, escalar privilegios | Baja (tiene cuenta legítima) |
| Funcionario malintencionado | Acceder a datos no autorizados, modificar solicitudes | Media (tiene rol staff) |
| Administrador comprometido | Acceso total al sistema | Alta |
| Atacante externo (XSS) | Robar JWT de localStorage, secuestrar sesiones | Media |
| Atacante de red (MiTM) | Interceptar credenciales, tokens, datos | Media (mitigado por TLS) |
| Atacante de cadena de suministro | Dependencias vulnerables (npm/composer) | Media |
| Operador del servidor | Acceso a .env, base de datos, backups | Alta |

---

## 5. Modelo de Amenazas STRIDE

| Amenaza | Descripción | Stride | Endpoints/Componentes afectados | Riesgo |
|---------|-------------|--------|--------------------------------|--------|
| **S1** | Suplantación de identidad mediante token JWT robado de localStorage | Spoofing | Todos los endpoints `/auth/*`, `/documents/*`, `/appointments/*`, `/requests/*` | Crítica |
| **S2** | Suplantación mediante credenciales débiles (password min 6, sin lockout) | Spoofing | `/auth/login`, `/auth/register` | Alta |
| **T1** | Manipulación de registros mediante IDOR (acceder a solicitudes/documentos de otros usuarios) | Tampering | `/documents/{id}/download`, `/appointments/{code}`, `/requests/tracking/{id}` | Crítica (parcialmente mitigado) |
| **T2** | Modificación de datos de solicitud por funcionario que la creó (conflicto de interés) | Tampering | `/requests/{id}/status` | Media (mitigado) |
| **R1** | Repudio de acciones (falta de logs inmutables o con omitidos) | Repudiation | `audit_log` — no todos los endpoints loguean | Media |
| **I1** | Divulgación de PII mediante endpoint público `searchCard` sin autenticación | Information Disclosure | `/tourism-card/search` | Crítica |
| **I2** | Divulgación de PII mediante `listUsers` (todos los usuarios con documento, teléfono) | Information Disclosure | `/auth/users` | Alta |
| **I3** | Divulgación de stack traces internos (APP_DEBUG=true) | Information Disclosure | Todos los endpoints (error 500) | Alta |
| **I4** | Divulgación de detalles de proveedor Gemini en logs (antes corregido) | Information Disclosure | `/chat` | Media (mitigado) |
| **D1** | Denegación de servicio por falta de rate limiting en endpoints críticos | Denial of Service | `/auth/login`, `/auth/register`, `/auth/forgot-password` | Media |
| **E1** | Elevación de privilegios: funcionario→admin, ciudadano→staff | Elevation of Privilege | `/auth/users/{userId}/roles` | Crítica (mitigada) |
| **E2** | Autoaprobación de solicitudes (conflicto de interés) | Elevation of Privilege | `/requests/{id}/status` | Media (mitigada) |
| **M1** | Inyección de código malicioso mediante subida de archivos (DocumentController) | Execution | `/documents/upload` | Media (mitigada por validación MIME) |
| **M2** | Path traversal en descarga de documentos | Execution | `/documents/{id}/download` | Baja (almacenamiento con nombres UUID) |
| **P1** | Prompt injection en chatbot Miss Lorna | Manipulation | `/chat` | Media |
| **P2** | Extracción de datos privados mediante RAG si el knowledge base contiene PII | Information Disclosure | `/chat`, `RagKnowledgeService` | Media |
| **C1** | Compromiso de API Key de Gemini enviada en URL | Credential Exposure | `GeminiService::chat()` | Alta |
| **C2** | Webhook de pagos sin firma (PAYMENT_WEBHOOK_VERIFY=false) | Spoofing | `/tourism-card/payment/webhook` | Alta |

---

## 6. Escenarios de Ataque Prioritarios

### Prioridad 1 — Crítica (requiere acción inmediata)

| # | Escenario | Impacto | Probabilidad |
|---|-----------|---------|--------------|
| P1-1 | Atacante roba JWT mediante XSS (token en localStorage) y se hace pasar por el usuario | Acceso total a la cuenta del ciudadano | Media |
| P1-2 | Atacante consulta `/tourism-card/search` con códigos y documentos enumerados para obtener PII masiva | Exposión masiva de datos personales de ciudadanos | Alta |
| P1-3 | Atacante explota `APP_DEBUG=true` en producción para obtener stack traces con rutas, variables de entorno, estructura de BD | Información interna para ataques posteriores | Media (solo si se despliega a producción con DEBUG=true) |
| P1-4 | Escalada de rol funcionario→admin (si la validación falla) | Control total del sistema | Baja (mitigado en código) |

### Prioridad 2 — Alta

| # | Escenario | Impacto | Probabilidad |
|---|-----------|---------|--------------|
| P2-1 | Ataque de fuerza bruta a `/auth/login` sin bloqueo de cuenta | Compromiso de cuentas con contraseñas débiles | Alta |
| P2-2 | CORS con `allowed_origins: '*'` permite peticiones desde cualquier origen | Robo de tokens mediante ataques de origen cruzado | Media |
| P2-3 | Funcionario accede a `internal_notes` de solicitudes ajenas | Exposión de notas internas de ciudadanos | Media |
| P2-4 | `listUsers` expone documentos y teléfonos de todos los ciudadanos a cualquier funcionario | Exposión masiva de PII | Alta |

### Prioridad 3 — Media

| # | Escenario | Impacto | Probabilidad |
|---|-----------|---------|--------------|
| P3-1 | Prompt injection en chatbot para extraer instrucciones internas | Manipulación del asistente, posible ingeniería social | Media |
| P3-2 | Webhook de pagos con firma desactivada permite pagos fraudulentos | Pérdida financiera, tarjetas emitidas sin pago | Media |
| P3-3 | JWT de 7 días aumenta ventana de exposición si se roba | Secuestro de sesión prolongado | Media |
| P3-4 | Falta de cabeceras de seguridad (CSP, HSTS, X-Frame-Options) | Clickjacking, MIME sniffing, downgrade a HTTP | Media |

---

## 7. Consecuencias de un Compromiso

| Activo comprometido | Consecuencia |
|--------------------|--------------|
| Base de datos MySQL | Robo de datos personales de miles de ciudadanos, modificación de estados de trámites, eliminación de registros |
| Tokens JWT | Suplantación de identidad, acceso a trámites y documentos de ciudadanos |
| API Key Gemini | Uso abusivo del asistente, costo económico, posible extracción de conocimiento del sistema |
| Clave HMAC pagos | Emisión de tarjetas de turismo sin pago real |
| Backups cifrados sin contraseña | Pérdida de capacidad de recuperación, datos expuestos |
| Registros de auditoría | Borrado o manipulación de evidencias de acceso no autorizado |

---

## 8. Defensas Existentes (Fortalezas)

1. **Autorización en servidor**: Todas las rutas protegidas verifican `auth:api` y `role:*` en el backend, no solo en el frontend.
2. **JWT blacklist**: Revocación de tokens tras cambio/restablecimiento de contraseña mediante caché + middleware `RejectRevokedTokens`.
3. **Rate limiting**: Middleware `throttle` en endpoints críticos (auth, búsqueda pública, QR, tarjeta de turismo, chat).
4. **Validación de entrada**: Form Requests y `validate()` en todos los controladores.
5. **Máscaras PII**: `TourismCardController.getReceipt()` enmascara datos personales cuando no hay acceso completo.
6. **Integridad QR**: Firmas HMAC-SHA256 en certificados y recibos de tarjeta de turismo.
7. **Registro de auditoría**: `AuditLog` registra acciones con IP y user_agent.
8. **CORS configurado**: Orígenes restringidos (no `*` cuando se especifica `FRONTEND_URL`), credenciales soportadas.
9. **Propiedad de documentos**: Descarga y eliminación verifican que el usuario sea el dueño o staff.
10. **Dependencias actualizadas**: `composer audit` limpio, Laravel 12.70.2, Symfony 7.4.x.

---

## 9. Áreas que Requieren Plan de Acción (esperando autorización)

Según las reglas del proyecto, los siguientes cambios requieren autorización previa antes de implementarse:

- Reducción de `JWT_TTL` de 7 días (10080 min) a un valor menor.
- Migración de caché de revocación JWT de `file` a Redis/DB compartida para multi-instancia.
- Cambio de almacenamiento de JWT de localStorage a cookies HttpOnly/Secure/SameSite (requiere verificar compatibilidad con todos los clientes: web, Android Capacitor, iOS).
- Cambios en estructura de base de datos (índices adicionales, restricciones).
- Cambios en `.env`, configuración de autenticación, o datos sensibles.
- Instalación de nuevas dependencias (Redis, etc.).
