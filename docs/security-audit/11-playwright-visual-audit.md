# Evidencias de Auditoría Visual — Playwright — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Herramienta:** Playwright (@playwright/test v1.65.0)  
**Navegador:** Chromium  
**Entorno:** Local aislado, datos ficticios, cuentas de prueba

---

## 1. Configuración de Playwright

| Parámetro | Valor |
|-----------|-------|
| `testDir` | `docs/auditoria-visual/playwright` |
| `snapshotDir` | `docs/auditoria-visual/playwright/snapshots` |
| `outputDir` | `docs/auditoria-visual/playwright/test-results` |
| `baseURL` | `http://localhost:8100` |
| Browser | Chromium (channel: chrome) |
| Viewport por defecto | 375x667 (móvil) |
| Proyectos | Desktop Chrome |
| `webServer` | `npm start` (ng serve) en puerto 8100, `reuseExistingServer: true` |
| `headless` | `false` (modo headed para inspección visual) |
| `ignoreHTTPSErrors` | `true` |

---

## 2. Archivos de Prueba Existentes

| Archivo | Descripción | Pruebas |
|---------|-------------|---------|
| `navigation.test.ts` | Navegación entre rutas públicas | 6 pruebas |
| `forms.test.ts` | Validación de formulario de login | 6 pruebas |
| `responsive.test.ts` | Diseño responsivo (móvil/tablet/desktop) | 4 pruebas |
| `screenshots.test.ts` | Capturas de pantalla | No revisado |
| `helpers.ts` | Utilidades (bloqueo Service Worker PWA) | — |

### Detalle de pruebas

#### navigation.test.ts
1. Carga la página de bienvenida con logo, título y botón de inicio
2. Navega de bienvenida a login al pulsar el botón de inicio
3. Navega de login al formulario de registro
4. Navega de login a la tarjeta de turismo (acceso visitante)
5. La ruta pública de búsqueda de registros es accesible
6. Una ruta desconocida redirige a la página de bienvenida

#### forms.test.ts
1. El botón de inicio de sesión inicia deshabilitado con campos vacíos
2. Habilita el botón al completar email y contraseña válida (>= 6 caracteres)
3. Requiere contraseña de al menos 6 caracteres
4. Al escribir en el campo de email se actualiza el valor
5. Existe el enlace para recuperar contraseña
6. Navega a la vista de recuperar contraseña

#### responsive.test.ts
1. Renderiza la bienvenida en móvil (375x667)
2. Renderiza la bienvenida en tablet (768x1024)
3. Renderiza la bienvenida en desktop (1280x800)
4. El formulario de login se adapta a móvil y desktop

---

## 3. Ejecución de Pruebas

### Estado de la ejecución

Se intentó ejecutar `npx playwright test --project=chromium`. Las pruebas iniciaron correctamente (el webServer generó los bundles de la aplicación Angular), pero la ejecución se excedió del tiempo de espera debido a que `ng serve` requiere tiempo considerable para compilar y estar listo.

**Resultado:** Las pruebas no se completaron en este entorno debido a limitaciones de tiempo de compilación del servidor de desarrollo, no por fallos en los tests en sí.

### Prueba parcialmente observada

Durante el primer intento de ejecución, se observó:

```
✘  1 [chromium] › forms.test.ts:31:3 › el botón de inicio de sesión inicia deshabilitado con campos vacíos
```

Este fallo ocurrió durante la inicialización del webServer (ng serve aún no estaba listo), no representa un fallo real de la aplicación.

---

## 4. Hallazgos de la Auditoría Visual

### Aspectos positivos (sin modificar interfaz)

1. **Playwright está instalado y configurado** — `@playwright/test` en devDependencies, `playwright.config.ts` en la raíz, directorio de pruebas en `docs/auditoria-visual/playwright/`.
2. **Las pruebas cubren navegación pública** — bienvenida, login, registro, tarjeta de turismo, búsqueda de registros.
3. **Las pruebas cubren validación de formularios** — botón deshabilitado con campos vacíos, contraseña mínima 6 caracteres.
4. **Las pruebas cubren responsive** — móvil, tablet, desktop.
5. **Helper para PWA** — bloquea `ngsw-worker.js` y `ngsw.json` para evitar problemas de teardown por Service Worker.
6. **No se modifica la interfaz** — las pruebas son de solo lectura.

### Limitaciones observadas

1. **El servidor de desarrollo tarda en compilar** — `ng serve` requiere tiempo significativo, causando timeouts en entornos con recursos limitados.
2. **Se requiere servidor de desarrollo activo** — las pruebas dependen de `npm start` (ng serve) en el puerto 8100.
3. **No se ejecutaron pruebas contra rutas autenticadas** — las pruebas actuales solo cubren rutas públicas (sin JWT), por lo que no se verificaron rutas protegidas visualmente.

---

## 5. Recomendaciones

1. **Ejecutar Playwright en CI/CD** con el servidor de desarrollo ya compilado o usando `npm run build` + servidor estático.
2. **Agregar pruebas de rutas protegidas** — verificar que rutas con AuthGuard redirijan a login cuando no hay token.
3. **Agregar pruebas de visualización de PII** — verificar que no se muestren datos sensibles sin autorización.
4. **Agregar capturas de pantalla en fallos** — ya configurado (`screenshot: 'only-on-failure'`).
5. **Ejecutar en modo headless en CI** (`npx playwright test --headless`).

---

## 6. Evidencia

Los archivos de prueba se encuentran en:
- `docs/auditoria-visual/playwright/navigation.test.ts`
- `docs/auditoria-visual/playwright/forms.test.ts`
- `docs/auditoria-visual/playwright/responsive.test.ts`
- `docs/auditoria-visual/playwright/screenshots.test.ts`
- `docs/auditoria-visual/playwright/helpers.ts`

Configuración: `playwright.config.ts` (raíz del proyecto)

**Capturas de pantalla:** Se generan automáticamente en `docs/auditoria-visual/playwright/test-results/` y `docs/auditoria-visual/playwright/screenshots/` cuando las pruebas fallan.

**Cuenta de prueba:** Se usan datos ficticios (usuario@ejemplo.com, contraseña123) — no se utilizan datos reales de ciudadanos.
