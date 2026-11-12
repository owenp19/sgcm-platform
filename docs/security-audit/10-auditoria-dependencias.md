# Auditoría de Dependencias y Cadena de Suministro — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** package.json, package-lock.json, composer.json, composer.lock  
**Restricción:** NO se actualizaron dependencias masivamente. NO se introdujeron suscripciones ni servicios de pago.

---

## 1. Dependencias Backend (Composer)

### Archivo: `backend/composer.json`

#### Paquetes de producción (`require`)

| Paquete | Versión | Propósito | Estado de auditoría |
|---------|---------|-----------|---------------------|
| `laravel/framework` | ^12.0 | Framework Laravel | ✅ Laravel 12.70.2 (actualizado) |
| `tymon/jwt-auth` | ^2.3 | Autenticación JWT | Revisar vulnerabilidades |
| `barryvdh/laravel-dompdf` | ^3.1 | Generación PDF | Revisar |
| `chillerlan/php-qrcode` | ^6.0 | Generación QR | Revisar |
| `kreait/laravel-firebase` | ^6.2 | Firebase integration | Revisar |
| `darkaonline/l5-swagger` | ^11.1 | Documentación OpenAPI/Swagger | Revisar (expone endpoints internos) |
| `maatwebsite/excel` | ^3.1 | Exportación Excel | Revisar |
| `laravel/tinker` | ^2.10.1 | Consola interactiva | Solo desarrollo (en require, no require-dev) |

#### Paquetes de desarrollo (`require-dev`)

| Paquete | Versión | Propósito |
|---------|---------|-----------|
| `fakerphp/faker` | ^1.23 | Datos de prueba |
| `laravel/pail` | ^1.2.2 | Visor de logs |
| `laravel/pint` | ^1.24 | Formateador de código |
| `laravel/sail` | ^1.41 | Docker para Laravel |
| `mockery/mockery` | ^1.6 | Mocks para pruebas |
| `nunomaduro/collision` | ^8.6 | Manejo de errores en consola |
| `phpunit/phpunit` | ^11.5.50 | Framework de pruebas |

### Resultado de `composer audit`
- **Estado:** Sin advisories (limpio)
- **Última verificación:** `composer.lock` actualizado a Laravel 12.70.2, Symfony 7.4.x
- **Verificación:** `composer install --dry-run` sin cambios

### Observaciones
- `laravel/tinker` está en `require` (producción) en lugar de `require-dev`. Tinker permite ejecutar código PHP arbitrario desde la consola. En producción no debería estar disponible.
- `darkaonline/l5-swagger` expone documentación OpenAPI de todos los endpoints, incluyendo rutas administrativas. Debe estar protegido o deshabilitado en producción.

---

## 2. Dependencias Frontend (npm)

### Archivo: `package.json`

#### Dependencias de producción

| Paquete | Versión | Propósito | Estado |
|---------|---------|-----------|--------|
| `@angular/*` | ^20.0.0 | Framework Angular | ✅ Angular 20 |
| `@ionic/angular` | ^8.0.0 | UI Ionic | ✅ Ionic 8 |
| `@capacitor/*` | ^8.0.0 - ^8.4.0 | Runtime móvil | ✅ Capacitor 8 |
| `@aparajita/capacitor-biometric-auth` | ^10.0.0 | Biometría | — |
| `@capacitor-mlkit/barcode-scanning` | ^8.2.1 | Escaneo QR | — |
| `chart.js` | ^4.5.1 | Gráficos | — |
| `leaflet` | ^1.9.4 | Mapas | — |
| `rxjs` | ~7.8.0 | Reactivo | — |
| `tslib` | ^2.3.0 | TypeScript runtime | — |
| `zone.js` | ~0.15.0 | Zone.js | — |
| `mini-css-extract-plugin` | ^2.9.4 | Webpack plugin | Build tool |
| `ionicons` | ^7.0.0 | Iconos | — |

#### Dependencias de desarrollo (`devDependencies`)

| Paquete | Versión | Propósito |
|---------|---------|-----------|
| `@angular-devkit/build-angular` | ^20.0.0 | Build Angular |
| `@angular-eslint/*` | ^20.0.0 | Linting Angular |
| `@angular/cli` | ^20.0.0 | CLI Angular |
| `@angular/compiler-cli` | ^20.0.0 | Compilador |
| `@angular/language-service` | ^20.0.0 | Servicio de lenguaje |
| `@capacitor/assets` | ^3.0.5 | Assets Capacitor |
| `@ionic/angular-toolkit` | ^12.0.0 | Toolkit Ionic |
| `@playwright/test` | ^1.65.0 | Pruebas E2E |
| `@types/leaflet` | ^1.9.22 | Tipos Leaflet |
| `@typescript-eslint/*` | ^8.18.0 | Linting TypeScript |
| `concurrently` | ^10.0.3 | Ejecutar procesos en paralelo |
| `eslint` | ^9.16.0 | Linter |
| `typescript` | ^5.9.3 | TypeScript 5.9 |

### Playwright
- `@playwright/test` versión ^1.65.0 está instalado en devDependencies
- `node_modules/@playwright/test` existe (verificado)
- `playwright.config.ts` configurado en la raíz

---

## 3. Auditoría de Vulnerabilidades

### Composer (PHP)
- `composer audit`: **Sin advisories** (0 vulnerabilidades conocidas)
- `composer.lock`: Actualizado y verificado
- `composer install --dry-run`: Sin cambios (vendor coincide con lock)

### npm (JavaScript/TypeScript)
- No se ejecutó `npm audit` (requiere conexión y podría modificar dependencias)
- Playwright está instalado y configurado
- Backups de package.json y package-lock.json existen:
  - `package.json.backup.before-concurrently-fix`
  - `package.json.backup.before-npm-ci`
  - `package-lock.json.backup.before-concurrently-fix`
  - `package-lock.json.backup.before-npm-ci`

---

## 4. Paquetes Abandonados o Innecesarios

| Paquete | Observación | Acción recomendada |
|---------|-------------|-------------------|
| `laravel/tinker` | En `require` (producción), debería ser `require-dev` | Mover a require-dev (requiere autorización) |
| `darkaonline/l5-swagger` | Expone todos los endpoints en `/api/documentation` | Proteger o deshabilitar en producción |
| `laravel/sail` | Solo para desarrollo local con Docker | Está en require-dev, correcto |

---

## 5. Backups de Archivos de Dependencias

Existen archivos de backup antes de modificaciones:
- `composer.lock` (actualizado, sin backups visibles específicos)
- `package.json.backup.before-concurrently-fix`
- `package.json.backup.before-npm-ci`
- `package-lock.json.backup.before-concurrently-fix`
- `package-lock.json.backup.before-npm-ci`
- `opencode.json.backup.before-skill-orchestrator`

---

## 6. Resumen de Hallazgos Dependencias

| ID | Hallazgo | Severidad | Estado |
|----|----------|-----------|--------|
| DEP-01 | `laravel/tinker` en `require` (producción) | Media | Pendiente (mover a require-dev) |
| DEP-02 | `l5-swagger` expone documentación de endpoints internos | Media | Pendiente (proteger/deshabilitar en producción) |
| DEP-03 | `composer audit` sin advisories | — | OK |
| DEP-04 | `@playwright/test` instalado y configurado | — | OK |
| DEP-05 | Backups de package.json/package-lock.json existen | — | OK |

---

## 7. Recomendaciones (requieren autorización para cambios)

1. Mover `laravel/tinker` de `require` a `require-dev` en `backend/composer.json`.
2. Proteger o deshabilitar `l5-swagger` (`/api/documentation`) en producción para no exponer endpoints administrativos.
3. Ejecutar `npm audit` para verificar vulnerabilidades en dependencias de frontend (no actualizar masivamente).
4. Mantener `composer.lock` y `package-lock.json` bajo control de versiones para reproducción de builds.
5. No instalar paquetes innecesarios ni servicios de pago.
