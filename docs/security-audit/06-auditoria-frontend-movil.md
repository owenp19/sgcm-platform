# Auditoría de Seguridad Frontend y Aplicación Móvil — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** Angular 20, Ionic 8, Capacitor 8, protección de rutas, almacenamiento, peticiones HTTP, WebView, deep links  
**Restricción:** NO se modificó diseño, interfaz, ni componentes visuales.

---

## 1. Arquitectura Frontend

| Componente | Tecnología | Ubicación | Observación |
|------------|-----------|-----------|-------------|
| Framework | Angular 20 | `src/app/` | Componentes standalone, lazy loading |
| UI Mobile | Ionic 8 | `src/app/features/` | Componentes Ionic |
| Runtime móvil | Capacitor 8 | `capacitor.config.ts` | Android, iOS, PWA |
| Routing | Angular Router | `src/app/app-routing.module.ts` | AuthGuard en rutas protegidas |
| Estado | Servicios inyectados | `src/app/services/` | AuthService, SecureStorageService, API services |
| HTTP | HttpClient | Servicios diversos | Peticiones REST al backend |
| Almacenamiento | localStorage + Capacitor Preferences | `secure-storage.service.ts` | Token y usuario |
| Biometría | @aparajita/capacitor-biometric-auth | `biometric.service.ts` | Preferencia de usuario |

---

## 2. Protección de Rutas (`src/app/app-routing.module.ts`)

### Rutas con AuthGuard (requieren autenticación)
- `home`, `procedures`, `profile`, `settings`, `help-contact`, `legal`, `notifications`, `data-protection`
- `procedures/:slug`, `request-create`, `request-tracking`, `request-tracking/:trackingNumber`
- `appointments`, `announcements`, `announcements/:id`, `my-procedures`, `procedure-detail/:id`
- `admin` (sin AuthGuard directo, ver nota abajo)

### Rutas públicas (sin AuthGuard)
- `welcome`, `login`, `register`, `recover-password`, `reset-password`
- `record-search` (búsqueda pública de trámites)
- `tarjeta-turismo`, `tarjeta-turismo/pago/:code`, `tarjeta-turismo/recibo/:code`
- `consultar-tarjeta-turismo`, `validar-tarjeta-turismo/qr/:qr_token`, `validar-tarjeta-turismo/:code`
- `**` → redirect to `welcome`

### AuthGuard (`src/app/guards/auth.guard.ts`)
```typescript
canActivate(): boolean {
    if (this.authService.isAuthenticated()) {
      return true;
    }
    this.router.navigate(['/login']);
    return false;
}
```

### Hallazgo FM-01: AuthGuard solo verifica presencia de token, no validez
- **Causa raíz:** `isAuthenticated()` retorna `!!this.secureStorage.getToken()` — solo verifica que exista un token, no que sea válido ni no expirado.
- **Riesgo:** Un token expirado en localStorage permite acceso a rutas protegidas en el frontend, aunque el backend rechazará la petición (401). El usuario ve una UI "autenticada" que falla en todas las peticiones.
- **Archivo:** `src/app/guards/auth.guard.ts`, `src/app/services/auth.service.ts` (línea 58-60)

### Hallazgo FM-02: Ruta `admin` sin AuthGuard directo
- **Causa raíz:** La ruta `admin` no tiene `canActivate: [AuthGuard]` en `app-routing.module.ts`. La protección parece delegarse al módulo admin feature.
- **Riesgo:** Si el módulo admin no implementa protección interna, cualquier usuario puede acceder a la ruta admin.
- **Archivo:** `src/app/app-routing.module.ts` (líneas 244-250)

---

## 3. Almacenamiento Local (`secure-storage.service.ts`)

### Hallazgo FM-03: Token JWT en localStorage (vulnerable a XSS)
- **Descripción:** El token se almacena en `localStorage` bajo clave `occre_token` y los datos del usuario bajo `occre_user`.
- **Riesgo:** Cualquier vulnerability XSS en la aplicación Angular permite robar el token y suplantar al usuario.
- **Archivo:** `src/app/services/secure-storage.service.ts` (líneas 17-24, 56-68)

### Hallazgo FM-04: Datos de usuario en localStorage sin cifrado
- **Descripción:** `occre_user` contiene nombre, email, documento, teléfono, roles, foto.
- **Riesgo:** Acceso físico al dispositivo o XSS expone todos los datos del usuario.
- **Archivo:** `src/app/services/secure-storage.service.ts` (líneas 46-47, 59-60)

---

## 4. Peticiones HTTP y Comunicación

### Servicios de API observados
- `auth.service.ts` — autenticación
- `request.service.ts` — solicitudes/radicados
- `appointment-api.service.ts` — citas
- `tourism-card.service.ts` — tarjeta de turismo
- `document.service.ts` — documentos
- `chat.service.ts` — chatbot
- `notification.service.ts` — notificaciones
- `device.service.ts` — dispositivos/push
- `secure-storage.service.ts` — almacenamiento

### Configuración de entorno (`src/environments/`)
- `environment.ts` — `apiUrl: '/api'`, `useFallback: false`, `production: false`
- `environment.prod.ts` — producción
- `environment.lan.ts` — LAN
- `environment.tunnel.ts` — túnel (Cloudflare/ngrok)

### Hallazgo FM-05: apiUrl relativo (`/api`) en desarrollo
- **Causa raíz:** `environment.ts` usa `apiUrl: '/api'` que depende del proxy configurado.
- **Archivo:** `src/environments/environment.ts`

### Hallazgo FM-06: No se observó uso de interceptores HTTP para inyectar JWT automáticamente
- Revisar si los servicios añaden manualmente el header `Authorization: Bearer <token>` a cada petición.
- Si un servicio olvida añadir el header, la petición fallaría con 401 (no es una vulnerabilidad de seguridad, pero es un problema de consistencia).

---

## 5. Formularios y Validaciones

### Validación en frontend
Los formularios de Angular/Ionic usan validaciones de template/reactive forms. Sin embargo:
- **La validación de seguridad crítica debe estar en el servidor** (ya verificado: todos los controladores Laravel validan con `$request->validate()`).
- Validaciones frontend son solo UX, no seguridad.

### Hallazgo FM-07: No se observó `innerHTML` ni `bypassSecurityTrustHtml`
- **Estado:** Positivo. Per el informe anterior, no hay vectores XSS por innerHTML en `src/`.
- **Archivo:** Revisado en `src/` (confirmado por decisiones previas)

---

## 6. Aplicación Móvil (Capacitor 8)

### `capacitor.config.ts`
```typescript
const config: CapacitorConfig = {
  appId: 'com.occre.app',
  appName: 'SGCM',
  webDir: 'www',
  server: {
    androidScheme: 'https',
  },
  plugins: {
    PushNotifications: { presentationOptions: ['badge', 'sound', 'alert'] },
    SplashScreen: { launchShowDuration: 2000, backgroundColor: '#071a2f', showSpinner: false },
  },
};
```

### Plugins Capacitor instalados (desde package.json)
| Plugin | Propósito | Evaluación |
|--------|-----------|------------|
| `@capacitor/camera` | Cámara | Permiso de cámara necesario |
| `@capacitor/geolocation` | Geolocalización | Usado para `shareLocation` |
| `@capacitor/device` | Info dispositivo | Bajo riesgo |
| `@capacitor/preferences` | Almacenamiento nativo | Usado para token/usuario |
| `@capacitor/push-notifications` | Push FCM | Token de dispositivo |
| `@capacitor/local-notifications` | Notificaciones locales | Bajo riesgo |
| `@capacitor/share` | Compartir | Bajo riesgo |
| `@capacitor/browser` | Navegador externo | Para pagos/web externa |
| `@aparajita/capacitor-biometric-auth` | Biometría | Preferencia de usuario, no auth real |
| `@capacitor-mlkit/barcode-scanning` | Escaneo QR | Para validar tarjetas |

### Hallazgo FM-08: `androidScheme: 'https'` en Capacitor server
- **Evaluación:** Forza HTTPS para llamadas desde WebView a archivos locales. Positivo para seguridad.

### Hallazgo FM-09: Almacenamiento de token en Capacitor Preferences
- **Evaluación:** Preferences almacena en texto plano en el dispositivo. No está cifrado. Si el dispositivo está root/jailbreak, los datos son accesibles.
- **Mitigación:** Biometría es solo preferencia (UI), no se usa para proteger el storage.

### Hallazgo FM-10: No se observó configuración de WebView hardening
- No se observó deshabilitación de JavaScript en WebView (necesario para la app).
- No se observó configuración de Cleartext Traffic (permitir HTTP en Android).
- **Recomendación:** Verificar `AndroidManifest.xml` y `config.xml` para `usesCleartextTraffic="false"`.

---

## 7. Deep Links

No se observó configuración explícita de deep links en el código revisado. La aplicación usa rutas internas de Angular/Ionic. Si existen deep links externos (para pagos, verificación QR), deben validarse para evitar open redirect.

---

## 8. Caché

- **PWA/Service Worker:** Configurado (`ngsw-config.json`, `@angular/service-worker`).
- **Caché de respuestas HTTP:** No se observó caché explícito de respuestas sensibles en el frontend.
- **Caché de tarifa turismo:** En backend (`Cache::remember('tourism.active_tariff', 300, ...)`).

---

## 9. Documentos Descargados

- Los documentos se descargan desde `/documents/{id}/download` (Laravel Storage download).
- En el móvil, los archivos se guardan en el sistema de archivos del dispositivo a través del navegador/WebView.
- **Riesgo:** Los documentos descargados contienen PII y quedan en el almacenamiento del dispositivo sin cifrado adicional.

---

## 10. Resumen de Hallazgos Frontend/Móvil

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| FM-01 | AuthGuard solo verifica presencia de token | Media | Pendiente |
| FM-02 | Ruta admin sin AuthGuard directo | Alta | Pendiente (verificar módulo admin) |
| FM-03 | JWT en localStorage (XSS) | Crítica | Pendiente (requiere autorización para cambiar) |
| FM-04 | Datos de usuario en localStorage sin cifrado | Alta | Pendiente |
| FM-05 | apiUrl relativo en entorno desarrollo | Baja | Informativo |
| FM-08 | Almacenamiento Preferences sin cifrado | Media | Pendiente |
| FM-10 | No se observó WebView hardening (cleartext traffic) | Media | Pendiente |

---

## 11. Recomendaciones (requieren autorización para cambios críticos)

1. **Migrar token de localStorage a cookies HttpOnly/Secure/SameSite** o al menos añadir cifrado al almacenamiento local.
2. **Mejorar AuthGuard** para validar expiración del token (interceptor HTTP con 401 → redirect a login).
3. **Verificar protección de ruta admin** en el módulo feature.
4. **Verificar `usesCleartextTraffic="false"`** en AndroidManifest.xml.
5. **Evaluar cifrado de Preferences** en dispositivo móvil para datos sensibles.
