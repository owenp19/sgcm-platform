# Auditoría de Autenticación, JWT y Sesiones — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** Sistema JWT, tiempos de expiración, renovación, revocación, almacenamiento, roles, recuperación de contraseña

---

## 1. Configuración JWT (`backend/config/jwt.php`)

| Parámetro | Valor | Evaluación |
|-----------|-------|------------|
| Algoritmo | HS256 (simétrico) | Adecuado para single-service, requiere proteger JWT_SECRET |
| Secreto | `JWT_SECRET` desde .env | Configurado |
| TTL (time to live) | `env('JWT_TTL', 60)` → **10080 min (7 días)** en .env | **Débil**: ventana de exposición larga si se roba el token |
| Refresh TTL | `env('JWT_REFRESH_TTL', 20160)` → 14 días | Permite refrescar durante 2 semanas sin re-autenticarse |
| Required claims | iss, iat, exp, nbf, sub, jti | Completo |
| Blacklist enabled | `true` | Positivo: permite invalidar tokens |
| Blacklist storage | `Illuminate\Storage` (caché file) | No compartido entre nodos |
| Lock subject | `true` | Positivo: previene impersonación entre modelos de auth |

### Hallazgo AJ-01: JWT TTL de 7 días (10080 minutos)
- **Causa raíz:** `JWT_TTL=10080` en `.env` y método `jwtTtl()` en AuthController hardcodeado a 10080.
- **Riesgo:** Si un token es robado (via XSS en localStorage), el atacante tiene hasta 7 días de acceso antes de expiración.
- **Archivo:** `backend/.env`, `backend/app/Http/Controllers/Api/AuthController.php` (línea 25)
- **Acción:** Reducir TTL. **Requiere autorización** (cambia autenticación).

---

## 2. Flujo de Autenticación

### Registro (`POST /auth/register`)
1. Valida datos de entrada (nombre, email, password min 6, documento, teléfono)
2. Verifica que email no exista
3. Crea usuario con contraseña hasheada (`Hash::make`)
4. Asigna rol `ciudadano`
5. Genera JWT con claim `email`
6. Retorna token + datos de usuario
7. Registra en AuditLog

### Login (`POST /auth/login`)
1. Valida email y password
2. Busca usuario por email con roles eager-loaded
3. Verifica contraseña con `Hash::check`
4. Genera JWT
5. Retorna token + datos de usuario (incluyendo documento, teléfono)
6. Registra en AuditLog

### Refresh (`POST /auth/refresh`)
1. Requiere `auth:api`, `jwt.fresh`
2. Renueva el token usando `auth('api')->refresh()`
3. Retorna nuevo token con `expires_in` basado en `jwt.ttl`

### Logout (`POST /auth/logout`)
1. Requiere `auth:api`, `jwt.fresh`
2. Llama `auth('api')->logout()` para invalidar el token actual
3. Captura JWTException si ya está expirado

---

## 3. Almacenamiento de Tokens (Frontend)

### `src/app/services/secure-storage.service.ts`

El servicio utiliza **doble almacenamiento**:
1. **Capacitor Preferences** (almacenamiento nativo móvil)
2. **localStorage** (fallback y sincronización)

```typescript
save(key, value) {
    localStorage.setItem(key, value);        // ← localStorage siempre se escribe
    await Preferences.set({ key, value });   // ← Preferences nativo
}
```

### Hallazgo AJ-02: JWT almacenado en localStorage
- **Causa raíz:** `SecureStorageService.save()` escribe siempre en `localStorage` además de Preferences.
- **Riesgo:** Cualquier XSS en la aplicación Angular puede robar el token JWT (`occre_token`) y los datos del usuario (`occre_user`).
- **Impacto:** Secuestro de sesión completo. El atacante puede hacer cualquier acción como el usuario.
- **Archivo:** `src/app/services/secure-storage.service.ts` (líneas 56-61)

### Alternativa evaluada: Cookies HttpOnly/Secure/SameSite
- **Compatibilidad:** La aplicación es una SPA Angular/Ionic con Capacitor (WebView en Android/iOS) + PWA. Las cookies HttpOnly funcionan en navegador pero requieren cambios en:
  - Backend Laravel (debe emitir cookie en lugar de JSON con token)
  - Frontend Angular (debe usar `withCredentials` y leer usuario de un endpoint separado)
  - App móvil Capacitor (WebView maneja cookies automáticamente)
- **Conclusión:** Cambiar el mecanismo de autenticación requiere verificar compatibilidad con todos los clientes. **Requiere autorización.**

---

## 4. Gestión de Sesiones

| Aspecto | Implementación | Evaluación |
|---------|---------------|------------|
| Almacenamiento token | localStorage + Capacitor Preferences | Vulnerable a XSS |
| Expiración del token | 7 días (JWT_TTL) | Demasiado largo |
| Refresh token | Endpoint `/auth/refresh` (renueva JWT) | No hay refresh token separado, se renueva el JWT mismo |
| Revocación | Blacklist en caché + `RejectRevokedTokens` middleware | Bueno, pero caché file no es multi-instancia |
| Revocación por cambio de contraseña | `Cache::forever("auth.tokens_revoked_before.{userId}")` | Bueno: invalida todos los JWT emitidos antes del cambio |
| Sesiones concurrentes | No limitado | Un usuario puede tener múltiples sesiones activas simultáneamente |
| Cierre de sesión | `/auth/logout` + limpieza localStorage/Preferences | Solo invalida el token actual, no todos los tokens del usuario |
| Persistencia | Token persiste en localStorage hasta logout | Si el usuario no cierra sesión, el token permanece |

### Hallazgo AJ-03: No hay límite de sesiones concurrentes
- **Causa raíz:** No se rastrea cuántos tokens JWT están activos por usuario.
- **Riesgo:** Un atacante con un token robado puede mantener acceso incluso si el usuario inicia sesión en otro dispositivo.
- **Archivo:** `backend/app/Http/Controllers/Api/AuthController.php`, `backend/app/Http/Middleware/RejectRevokedTokens.php`

### Hallazgo AJ-04: Logout solo invalida el token actual
- **Causa raíz:** `auth('api')->logout()` invalida solo el token enviado en la petición.
- **Riesgo:** Si el usuario tiene tokens en otros dispositivos, siguen activos después del logout.
- **Archivo:** `backend/app/Http/Controllers/Api/AuthController.php` (líneas 217-226)

---

## 5. Recuperación de Contraseña

### Flujo (`POST /auth/forgot-password` + `POST /auth/reset-password`)
1. Usuario solicita recuperación con email
2. Sistema genera `Str::uuid()` como token
3. Almacena `hash('sha256', $resetToken)` en `users.reset_token`
4. Establece `reset_token_expires = now()->addHour()`
5. Envía enlace por email (cola async): `{frontend}/reset-password?token={resetToken}`
6. **No loguea el token ni el enlace completo**
7. Usuario envía token + nueva contraseña
8. Sistema compara `hash('sha256', $token)` con stored hash
9. Verifica expiración
10. Actualiza contraseña, limpia reset_token, **revoca JWT vía caché**
11. Registra en AuditLog

### Evaluación
- **Positivo:** Token hashado con SHA-256 (no se almacena en claro), expira en 1 hora, no se loguea, revoca JWT tras restablecimiento.
- **Positivo:** Respuesta genérica "Si el correo existe, recibirás un enlace" (previene enumeración de emails).
- **Observación:** El token viaja en URL por email (no por HTTPS del backend, depende del cliente de email).

---

## 6. Protección contra Fuerza Bruta

| Endpoint | Middleware throttle | Evaluación |
|----------|-------------------|------------|
| `/auth/login` | `throttle:auth` | Protegido (rate limiting de ruta) |
| `/auth/register` | `throttle:auth` | Protegido |
| `/auth/forgot-password` | `throttle:auth` | Protegido |
| `/auth/reset-password` | `throttle:auth` | Protegido |
| Nivel de cuenta | Ninguno | **No hay bloqueo de cuenta tras N intentos fallidos** |

### Hallazgo AJ-05: No hay bloqueo de cuenta por intentos fallidos
- **Causa raíz:** Solo rate limiting a nivel de ruta (`throttle:auth`), no bloqueo de cuenta específico.
- **Riesgo:** Ataque de fuerza bruta contra cuentas específicas (si no hay bloqueo de IP o cuenta).
- **Archivo:** `backend/routes/api.php` (líneas 31-34)

---

## 7. Roles y Autorización

| Rol | Puede | No puede |
|-----|-------|----------|
| `ciudadano` | Ver/editar propio perfil, crear solicitudes, citas, documentos, ver propias notificaciones, chat | Acceder a datos de otros usuarios, admin endpoints |
| `funcionario` | Ver solicitudes asignadas, cambiar estados, validar documentos, ver dashboard, ver citas de otros | Gestionar rol admin, eliminar usuarios |
| `admin` | Todo lo de funcionario + gestionar roles, eliminar usuarios, ver audit log, exportar reportes | (ninguna restricción adicional) |

### Protecciones contra escalada de privilegios
- `updateUserRoles`: Solo admin puede asignar/quitar rol `admin`. Funcionario recibe 403 si intenta.
- `deleteUser`: No se puede eliminar el último admin. No se puede autoeliminar.
- `updateStatus`: Funcionario no puede cambiar estado de su propia solicitud (conflicto de interés).

---

## 8. Resumen de Hallazgos Autenticación/JWT

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| AJ-01 | JWT TTL de 7 días (10080 min) | Alta | Pendiente (requiere autorización) |
| AJ-02 | JWT en localStorage (vulnerable a XSS) | Crítica | Pendiente (requiere autorización para cambiar mecanismo) |
| AJ-03 | No hay límite de sesiones concurrentes | Media | Pendiente |
| AJ-04 | Logout invalida solo el token actual | Baja | Pendiente |
| AJ-05 | No hay bloqueo de cuenta por fuerza bruta | Media | Pendiente |
| AJ-06 | Revocación JWT en caché file (no multi-instancia) | Media | Documentado, pendiente |
| AJ-07 | Password min 6 caracteres | Alta | Pendiente |

---

## 9. Recomendaciones (requieren autorización)

1. **Reducir JWT_TTL** de 7 días a un valor menor (ej. 60 min o 24h) con refresh token.
2. **Migrar almacenamiento de JWT** de localStorage a cookies HttpOnly, Secure, SameSite=Strict (verificar compatibilidad con Capacitor WebView y PWA).
3. **Implementar límite de sesiones concurrentes** por usuario.
4. **Invalidar todos los tokens** de un usuario en logout, no solo el actual.
5. **Agregar bloqueo de cuenta** tras N intentos fallidos de login.
6. **Aumentar política de contraseñas** (mínimo 8-12 caracteres, complejidad).
7. **Migrar caché de revocación** a Redis/DB compartida para multi-instancia.
