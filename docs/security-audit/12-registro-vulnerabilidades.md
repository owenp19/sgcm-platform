# Registro de Vulnerabilidades — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Estado:** Abiertas — pendiente de autorización para corrección crítica  
**Clasificación:** Según severidad (Crítica, Alta, Media, Baja, Preventiva)

---

## CRÍTICAS (requieren acción inmediata)

| ID | Vulnerabilidad | Evidencia | Causa raíz | Archivos afectados | Riesgo de modificación |
|----|---------------|-----------|------------|-------------------|----------------------|
| VULN-01 | Endpoint público expone PII sin autenticación | `POST /tourism-card/search` devuelve nombre, email, teléfono, contacto emergencia, alojamiento sin `auth:api` | Endpoint sin middleware de autenticación | `backend/routes/api.php` (línea 168), `backend/app/Http/Controllers/Api/TourismCardController.php` (líneas 431-452) | Medio — requiere agregar auth o filtrar datos |
| VULN-02 | Endpoint público permite registrar ubicación sin auth | `POST /tourism-card/card/{code}/share-location` sin middleware de autenticación | Endpoint sin middleware de autenticación | `backend/routes/api.php` (línea 166), `backend/app/Http/Controllers/Api/TourismCardController.php` (líneas 650-676) | Medio — requiere agregar auth o validar propiedad |
| VULN-03 | JWT almacenado en localStorage | `SecureStorageService.save()` escribe siempre en `localStorage` | Almacenamiento en localStorage en lugar de cookies HttpOnly | `src/app/services/secure-storage.service.ts` (líneas 56-61) | Alto — requiere cambiar mecanismo de almacenamiento (verificar compatibilidad con todos los clientes) |

---

## ALTAS

| ID | Vulnerabilidad | Evidencia | Causa raíz | Archivos afectados | Riesgo de modificación |
|----|---------------|-----------|------------|-------------------|----------------------|
| VULN-04 | APP_DEBUG=true en entorno de desarrollo | `APP_DEBUG=true` en `backend/.env` | Configuración no ajustada para producción | `backend/.env` | Bajo — cambiar valor, pero requiere autorización (modifica .env) |
| VULN-05 | CORS permite origen `*` por defecto | `allowed_origins => env('FRONTEND_URL', '*')` | Fallback a `*` si FRONTEND_URL no está configurado | `backend/config/cors.php` (línea 19) | Bajo — cambiar valor por defecto o exigir configuración |
| VULN-06 | JWT TTL de 7 días (10080 min) | `JWT_TTL=10080` en `.env` y `jwtTtl()` hardcodeado | TTL demasiado larga | `backend/.env`, `backend/app/Http/Controllers/Api/AuthController.php` (línea 25) | Medio — reducir TTL requiere autorización (cambia autenticación) |
| VULN-07 | Contraseña mínima de 6 caracteres | `'password' => ['required', 'string', 'min:6']` | Política de contraseñas débil | `backend/app/Http/Controllers/Api/AuthController.php` (líneas 63-71) | Bajo — cambiar validación |
| VULN-08 | `listUsers` expone PII de todos los ciudadanos | Devuelve email, documento, teléfono de todos los usuarios | Sin filtrado por necesidad de saber | `backend/app/Http/Controllers/Api/AuthController.php` (líneas 589-620) | Medio — filtrar campos sensibles |
| VULN-09 | API Key de Gemini en URL query string | `?key={$apiKey}` en URL de petición a Google Gemini | API key en URL en lugar de header | `backend/app/Services/GeminiService.php` (líneas 57-58) | Medio — cambiar a header Authorization |
| VULN-010 | PAYMENT_WEBHOOK_SECRET con valor por defecto | `PAYMENT_WEBHOOK_SECRET=cambia-este-secreto-hmac` | Valor por defecto no cambiado | `backend/.env` | Bajo — cambiar valor, requiere autorización (modifica .env) |

---

## MEDIAS

| ID | Vulnerabilidad | Evidencia | Causa raíz | Archivos afectados | Riesgo de modificación |
|----|---------------|-----------|------------|-------------------|----------------------|
| VULN-011 | No hay bloqueo de cuenta por fuerza bruta | Solo `throttle:auth` a nivel de ruta, no bloqueo de cuenta | Falta de mecanismo de bloqueo | `backend/routes/api.php`, `backend/app/Http/Controllers/Api/AuthController.php` | Medio — requiere implementar bloqueo de cuenta |
| VULN-012 | Revocación JWT en caché file (no multi-instancia) | `CACHE_STORE=file`, `RejectRevokedTokens` usa caché file | Caché no compartido entre nodos | `backend/.env`, `backend/app/Http/Middleware/RejectRevokedTokens.php` | Medio — migrar a Redis/DB, requiere autorización |
| VULN-013 | Uso de `forceFill` bypassa mass assignment | `$user->forceFill([...])` en resetPassword y paymentWebhook | forceFill ignora `$fillable` | `backend/app/Http/Controllers/Api/AuthController.php` (líneas 388-391, 450-454), `backend/app/Http/Controllers/Api/TourismCardController.php` (líneas 751-760, 794-797) | Bajo — reemplazar con `fill` + campos explícitos |
| VULN-014 | `changeEmail` no envía confirmación ni revoca sesiones | Email cambia inmediatamente sin verificación | Falta de flujo de confirmación | `backend/app/Http/Controllers/Api/AuthController.php` (líneas 539-573) | Medio — agregar confirmación y revocación |
| VULN-015 | MySQL sin TLS configurado | `MYSQL_ATTR_SSL_CA` no definido en database.php | No se configura SSL para MySQL | `backend/config/database.php` (líneas 62-64) | Medio — habilitar TLS, requiere autorización |
| VULN-016 | AuthGuard solo verifica presencia de token, no validez | `isAuthenticated()` retorna `!!token` | No verifica expiración ni validez del token | `src/app/guards/auth.guard.ts`, `src/app/services/auth.service.ts` (línea 58-60) | Bajo — mejorar validación en guard |
| VULN-017 | Ruta admin sin AuthGuard directo | Ruta `admin` no tiene `canActivate: [AuthGuard]` | Protección delegada al módulo feature | `src/app/app-routing.module.ts` (líneas 244-250) | Bajo — agregar AuthGuard |
| VULN-018 | No hay límite de sesiones concurrentes | No se rastrea tokens activos por usuario | Falta de control de sesiones | `backend/app/Http/Controllers/Api/AuthController.php` | Medio — implementar tracking de sesiones |
| VULN-019 | Logout invalida solo el token actual | `auth('api')->logout()` invalida solo el token enviado | No invalida todos los tokens del usuario | `backend/app/Http/Controllers/Api/AuthController.php` (líneas 217-226) | Bajo — invalidar todos los tokens en logout |
| VULN-020 | No filtrado de prompt injection en chatbot | Mensaje del usuario se envía directamente a Gemini | Sin sanitización de entrada | `backend/app/Http/Controllers/Api/ChatController.php` (líneas 64-70) | Medio — agregar filtrado |
| VULN-021 | Contexto del cliente no validado en chatbot | Parámetro `context` (max 12000) se inserta en system instruction sin validación | Falta de validación de contexto entrante | `backend/app/Http/Controllers/Api/ChatController.php` (líneas 74-79) | Medio — validar contexto |

---

## BAJAS

| ID | Vulnerabilidad | Evidencia | Causa raíz | Archivos afectados | Riesgo de modificación |
|----|---------------|-----------|------------|-------------------|----------------------|
| VULN-022 | Respuestas de Gemini no sanitizadas | `['answer' => $answer]` sin sanitización | Confianza en Angular sanitización | `backend/app/Http/Controllers/Api/ChatController.php` (línea 97) | Bajo — Angular ya sanitiza (no innerHTML) |
| VULN-023 | `laravel/tinker` en require (producción) | Paquete en `require` en lugar de `require-dev` | Error de configuración de composer | `backend/composer.json` | Bajo — mover a require-dev |
| VULN-024 | `l5-swagger` expone documentación de endpoints | `/api/documentation` accesible | Swagger no protegido en producción | `backend/config/l5-swagger.php`, `backend/routes/api.php` | Medio — proteger ruta de documentación |
| VULN-025 | Backup: tarea programada no activada | Programador de Tareas requiere activación manual | No automatizado | `scripts/backup/configurar-tarea-programada.ps1` | Bajo — activar tarea (acción del usuario) |
| VULN-026 | Backup: variable OCCRE_BACKUP_PASSWORD no configurada | Variable de entorno no definida | No configurada en el sistema | `docs/ai-memory/pending-work.md` (línea 46) | Bajo — configurar variable (acción del usuario) |
| VULN-027 | No cabeceras de seguridad (CSP, HSTS, X-Frame-Options) | No configuradas en backend | Falta de middleware de cabeceras | `backend/` | Medio — agregar middleware de seguridad |
| VULN-028 | Almacenamiento de archivos sin verificación de contenido | Solo valida MIME type, no contenido real | Falta de escaneo de archivos | `backend/app/Http/Controllers/Api/DocumentController.php` (líneas 26-34) | Medio — agregar verificación de contenido |

---

## PREVENTIVAS

| ID | Medida preventiva | Justificación | Archivos afectados |
|----|------------------|---------------|-------------------|
| PREV-01 | Agregar cabeceras de seguridad (CSP, X-Content-Type-Options, X-Frame-Options, HSTS, Referrer-Policy) | Reducir superficie de ataque en navegador | `backend/` (middleware global) |
| PREV-02 | Implementar logs de acceso de lectura a PII | Auditoría de quién consultó qué dato de qué ciudadano | `backend/app/Http/Controllers/Api/` |
| PREV-03 | Implementar rate limiting por usuario en chatbot | Prevenir abuso del presupuesto de Gemini API | `backend/routes/api.php` |
| PREV-04 | Verificar `usesCleartextTraffic="false"` en Android | Prevenir tráfico claro en Android | `android/app/src/main/AndroidManifest.xml` |
| PREV-05 | Ejecutar `npm audit` periódicamente | Detectar vulnerabilidades en dependencias frontend | `package.json`, `package-lock.json` |
| PREV-06 | Ejecutar `composer audit` periódicamente | Detectar vulnerabilidades en dependencias PHP | `backend/composer.json`, `backend/composer.lock` |
| PREV-07 | Probar restauración de backups trimestralmente | Verificar que los backups son recuperables | `scripts/restore/` |
| PREV-08 | Rotar `JWT_SECRET` y `PAYMENT_WEBHOOK_SECRET` periódicamente | Reducir impacto de posible compromiso | `backend/.env` |

---

## Resumen por Severidad

| Severidad | Cantidad | IDs |
|-----------|----------|-----|
| Crítica | 3 | VULN-01, VULN-02, VULN-03 |
| Alta | 7 | VULN-04, VULN-05, VULN-06, VULN-07, VULN-08, VULN-09, VULN-010 |
| Media | 11 | VULN-011 a VULN-021 |
| Baja | 7 | VULN-022 a VULN-028 |
| Preventiva | 8 | PREV-01 a PREV-08 |
| **Total** | **36** | |

---

## Vulnerabilidades Ya Corregidas (Historial)

| ID | Vulnerabilidad | Corrección | Fecha | Pruebas |
|----|---------------|------------|-------|---------|
| VULN-FIXED-01 | Escalada de rol funcionario→admin | Validación de rol admin + `exists:roles,name` | 2026-10-28 | `AuthorizationSecurityTest` |
| VULN-FIXED-02 | IDOR de citas y documentos | Comprobación de propietario o staff | 2026-10-28 | `AuthorizationSecurityTest` |
| VULN-FIXED-03 | Autoaprobación de solicitudes | Bloqueo si `user_id` coincide con funcionario | 2026-10-28 | `AuthorizationSecurityTest` |
| VULN-FIXED-04 | Rate limit insuficiente en endpoints públicos | Throttle en búsqueda, QR y health | 2026-10-28 | `PublicEndpointProtectionTest` |
| VULN-FIXED-05 | Token de recuperación en logs | Hash SHA-256, log sin token | 2026-10-28 | `PasswordResetTokenTest` |
| VULN-FIXED-06 | QR de constancias manipulable | Firma HMAC + estado aprobado/finalizado | 2026-10-28 | `CertificateQrIntegrityTest` |
| VULN-FIXED-07 | Códigos turísticos enumerables | Códigos 12 caracteres + throttle específico | 2026-10-28 | `TourismReceiptPrivacyTest` |
| VULN-FIXED-08 | PII en recibo turístico | Máscarado + acceso completo solo staff | 2026-10-28 | `TourismReceiptPrivacyTest` |
| VULN-FIXED-09 | `/api/health` y `/api/status` exponían internos | Redacción de host, BD, versiones, conteos, errores | 2026-10-28 | `HealthExposureTest` |
| VULN-FIXED-10 | JWT sin revocación | `refresh`/`logout` + revocación por `iat` | 2026-10-28 | `JwtSessionSecurityTest` |
| VULN-FIXED-11 | `shareLocation` 500 con coordenadas nulas | Validación obligatoria + rangos | 2026-10-28 | `TourismReceiptPrivacyTest` |
| VULN-FIXED-12 | XSS en anuncios | Sin `innerHTML`/`bypassSecurityTrust` en `src/` | 2026-10-28 | Revisión de código |
| VULN-FIXED-13 | PII en `verifyByQrToken` | Documento enmascarado, sin nombres completos | 2026-11-12 | Pruebas de seguridad |
| VULN-FIXED-14 | PII en `getAllAppointments` | Sin citizenEmail/citizenPhone de todos | 2026-11-12 | Pruebas de seguridad |
| VULN-FIXED-15 | ChatController logueaba excepción | `Log::error` sin mensaje de excepción | 2026-11-12 | Pruebas de seguridad |

**Total corregidas:** 15 vulnerabilidades

---

## Notas

- Todas las vulnerabilidades críticas requieren autorización antes de ser corregidas (cambios en autenticación, .env, estructura de BD, almacenamiento).
- No se afirma que ninguna vulnerabilidad esté solucionada sin pruebas de verificación.
- No se declara que el sistema sea invulnerable.
- Los backups se crearon antes de cualquier modificación (ver docs/backups/).
