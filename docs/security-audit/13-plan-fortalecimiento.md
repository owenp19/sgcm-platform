# Plan de Fortalecimiento de Seguridad — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Estado:** Pendiente de autorización para correcciones críticas y de autenticación

---

## Metodología

Cada corrección de bajo riesgo que sea reversible, esté respaldada y no altere datos ni contratos existentes puede implementarse directamente.

Para cambios de autenticación, permisos, estructura de base de datos, almacenamiento, infraestructura o compatibilidad entre clientes, se presenta el plan y se espera autorización.

Cada corrección documentada debe incluir:
1. Vulnerabilidad identificada
2. Evidencia
3. Causa raíz
4. Solución
5. Archivos afectados
6. Riesgo de modificación
7. Plan de rollback
8. Pruebas
9. Resultado de verificación

---

## CORRECCIONES DE BAJO RIESGO (pueden implementarse directamente)

Estas correcciones no alteran autenticación, permisos, estructura de BD, ni contratos existentes. Son reversibles y están respaldadas.

### CORR-01: Proteger ruta de documentación Swagger en producción

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | `l5-swagger` expone documentación de todos los endpoints incluyendo administrativos |
| Evidencia | `darkaonline/l5-swagger` instalado, `/api/documentation` accesible |
| Causa raíz | Swagger no protegido por middleware de autenticación en producción |
| Solución | Agregar middleware `auth:api` y `role:admin` a la ruta de documentación Swagger, o deshabilitar en producción |
| Archivos | `backend/config/l5-swagger.php`, `backend/routes/api.php` |
| Riesgo | Bajo |
| Rollback | Revertir cambios en configuración de Swagger |
| Pruebas | Verificar que `/api/documentation` retorne 401/403 sin token |
| Verificación | Pendiente |

---

## CORRECCIONES QUE REQUIEREN AUTORIZACIÓN

### CORR-02: Establecer APP_DEBUG=false en producción

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | APP_DEBUG=true expone stack traces y variables de entorno |
| Evidencia | `APP_DEBUG=true` en `backend/.env` |
| Causa raíz | Configuración de desarrollo no ajustada para producción |
| Solución | Cambiar `APP_DEBUG=false` en `.env` de producción |
| Archivos | `backend/.env` |
| Riesgo | Bajo (cambio de configuración) |
| Rollback | Cambiar de vuelta a `true` si es necesario |
| Pruebas | Verificar que errores 500 no devuelven stack traces |
| Verificación | Pendiente de autorización |

### CORR-03: Configurar FRONTEND_URL específico en CORS

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | CORS permite `*` por defecto |
| Evidencia | `allowed_origins => env('FRONTEND_URL', '*')` |
| Causa raíz | Fallback a `*` si FRONTEND_URL no está configurado |
| Solución | Configurar `FRONTEND_URL` con dominios específicos en producción, o cambiar el fallback |
| Archivos | `backend/.env`, `backend/config/cors.php` |
| Riesgo | Bajo |
| Rollback | Revertir valor de FRONTEND_URL |
| Pruebas | Verificar que orígenes no autorizados reciban CORS error |
| Verificación | Pendiente de autorización |

### CORR-04: Reducir JWT_TTL

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | JWT TTL de 7 días (10080 min) |
| Evidencia | `JWT_TTL=10080` en `.env`, `jwtTtl()` hardcodeado a 10800 |
| Causa raíz | TTL configurada para 7 días |
| Solución | Reducir a valor menor (ej. 60 min o 24h) con refresh token |
| Archivos | `backend/.env`, `backend/app/Http/Controllers/Api/AuthController.php` |
| Riesgo | Medio (cambia autenticación, afecta todos los clientes) |
| Rollback | Restaurar JWT_TTL anterior |
| Pruebas | `JwtSessionSecurityTest`, verificar expiración y refresh |
| Verificación | Pendiente de autorización |

### CORR-05: Migrar JWT de localStorage a cookies HttpOnly/Secure/SameSite

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | JWT en localStorage vulnerable a XSS |
| Evidencia | `SecureStorageService.save()` escribe en `localStorage` |
| Causa raíz | Almacenamiento en localStorage |
| Solución | Usar cookies HttpOnly, Secure, SameSite=Strict (verificar compatibilidad con Capacitor WebView y PWA) |
| Archivos | `src/app/services/secure-storage.service.ts`, `src/app/services/auth.service.ts`, backend auth |
| Riesgo | Alto (cambia mecanismo de autenticación, todos los clientes) |
| Rollback | Restaurar localStorage |
| Pruebas | Verificar login, logout, refresh en web y móvil |
| Verificación | Pendiente de autorización |

### CORR-06: Agregar autenticación a endpoints públicos con PII

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | `/tourism-card/search` y `/tourism-card/card/{code}/share-location` sin auth |
| Evidencia | Rutas sin middleware `auth:api` |
| Causa raíz | Endpoints diseñados como públicos sin considerar exposición de PII |
| Solución | Agregar `auth:api` o validar propiedad de la tarjeta antes de devolver datos |
| Archivos | `backend/routes/api.php`, `backend/app/Http/Controllers/Api/TourismCardController.php` |
| Riesgo | Medio (cambia contrato de API, afecta clientes existentes) |
| Rollback | Restaurar middleware anterior |
| Pruebas | Verificar que endpoints requieran token válido |
| Verificación | Pendiente de autorización |

### CORR-07: Cambiar PAYMENT_WEBHOOK_SECRET

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | Valor por defecto débil |
| Evidencia | `PAYMENT_WEBHOOK_SECRET=cambia-este-secreto-hmac` |
| Causa raíz | Valor por defecto no cambiado |
| Solución | Generar secreto fuerte y aleatorio |
| Archivos | `backend/.env` |
| Riesgo | Bajo |
| Rollback | Restaurar valor anterior |
| Pruebas | Verificar que webhooks con firma antigua sean rechazados |
| Verificación | Pendiente de autorización |

### CORR-08: Cambiar API Key de Gemini a header Authorization

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | API Key en URL query string |
| Evidencia | `?key={$apiKey}` en URL de Gemini |
| Causa raíz | API Key en URL en lugar de header |
| Solución | Enviar API key en header `Authorization` o `x-goog-api-key` |
| Archivos | `backend/app/Services/GeminiService.php` |
| Riesgo | Bajo |
| Rollback | Restaurar URL con query parameter |
| Pruebas | Verificar que el chatbot siga funcionando |
| Verificación | Pendiente de autorización |

### CORR-09: Habilitar TLS para MySQL

| Campo | Valor |
|-------|-------|
| Vulnerabilidad | Conexiones MySQL sin TLS |
| Evidencia | `MYSQL_ATTR_SSL_CA` no definido |
| Causa raíz | No se configura SSL para MySQL |
| Solución | Configurar `MYSQL_ATTR_SSL_CA` y habilitar SSL en conexión |
| Archivos | `backend/config/database.php` |
| Riesgo | Medio (cambia configuración de BD) |
| Rollback | Revertir configuración SSL |
| Pruebas | Verificar conexión a MySQL con SSL |
| Verificación | Pendiente de autorización |

---

## IMPLEMENTACIÓN POR FASES

### Fase 1 — Correcciones de bajo riesgo (inmediato, sin autorización)
- [ ] CORR-01: Proteger Swagger documentation

### Fase 2 — Correcciones que requieren autorización (presentar plan, esperar aprobación)
- [ ] CORR-02: APP_DEBUG=false
- [ ] CORR-03: FRONTEND_URL específico en CORS
- [ ] CORR-04: Reducir JWT_TTL
- [ ] CORR-05: JWT en cookies HttpOnly (verificar compatibilidad)
- [ ] CORR-06: Autenticación en endpoints públicos con PII
- [ ] CORR-07: Cambiar PAYMENT_WEBHOOK_SECRET
- [ ] CORR-08: API Key Gemini en header
- [ ] CORR-09: TLS para MySQL

### Fase 3 — Correcciones medias (requieren evaluación de impacto)
- [ ] Implementar bloqueo de cuenta por fuerza bruta
- [ ] Migrar caché de revocación JWT a Redis/DB
- [ ] Reemplazar `forceFill` con `fill` + campos explícitos
- [ ] Agregar confirmación de email en `changeEmail`
- [ ] Mejorar AuthGuard para validar expiración de token
- [ ] Agregar AuthGuard a ruta admin
- [ ] Implementar límite de sesiones concurrentes
- [ ] Invalidar todos los tokens en logout
- [ ] Agregar filtrado de prompt injection en chatbot
- [ ] Validar contexto del cliente en chatbot

### Fase 4 — Correcciones bajas y preventivas
- [ ] Sanitizar respuestas de Gemini (defensa en profundidad)
- [ ] Mover `laravel/tinker` a require-dev
- [ ] Agregar cabeceras de seguridad (CSP, HSTS, etc.)
- [ ] Verificar `usesCleartextTraffic="false"` en Android
- [ ] Ejecutar `npm audit` y `composer audit` periódicamente
- [ ] Probar restauración de backups trimestralmente

---

## PRINCIPIOS APLICADOS

| Principio | Aplicación en este plan |
|-----------|------------------------|
| Defensa en profundidad | Múltiples capas: auth, rate limiting, validación, cabeceras de seguridad, CORS |
| Mínimo privilegio | Usuario de BD sin privilegios de admin, roles RBAC, endpoints filtrados |
| Validación en servidor | Todos los controladores validan con `$request->validate()` |
| Separación de responsabilidades | Controladores, servicios, modelos, middleware con roles definidos |
| Reducción de superficie de ataque | Endpoints públicos minimizados, Swagger protegido, CORS restringido |
| No modificar sin autorización | Cambios en auth, .env, BD, almacenamiento esperan aprobación |
| Backups verificables | Backups creados antes de modificaciones, restauración probada en entorno aislado |

---

## CRITERIOS DE ÉXITO

- [ ] 0 vulnerabilidades críticas sin corregir (o con plan autorizado)
- [ ] `composer audit` sin advisories
- [ ] Pruebas de seguridad: 31+ en verde
- [ ] Backups verificados y restaurables
- [ ] Documentación actualizada en `docs/security-audit/`
