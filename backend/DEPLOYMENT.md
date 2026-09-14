# Guía de despliegue — Backend Laravel SGCM

Backend Laravel 12 + MySQL que reemplaza al backend Node.js/Express manteniendo el contrato de API idéntico (`{ error }`, `{ message, token, user }`, etc.). El frontend Ionic/Angular NO requiere cambios salvo apuntar `apiUrl` a este backend.

## 1. Requisitos del servidor

| Componente | Versión mínima |
|---|---|
| PHP | 8.2+ |
| MySQL / MariaDB | 10.4+ / 5.7+ |
| Composer | 2.x |

Extensiones PHP requeridas: `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`, `gd` (QR en PDF), `zip` (export Excel), `fileinfo` (uploads), `sodium` (opcional).

En XAMPP local ya están habilitadas (se añadieron `extension=sodium` y `extension=gd` a `C:\xampp\php\php.ini`).

## 2. Configuración local (desarrollo)

```powershell
cd backend
composer install
Copy-Item .env.example .env   # luego editar credenciales DB
& "C:\xampp\php\php.exe" artisan key:generate
& "C:\xampp\php\php.exe" artisan jwt:secret
# Crear la base de datos (local se usa sgcm_db)
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS sgcm_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& "C:\xampp\php\php.exe" artisan migrate --force
& "C:\xampp\php\php.exe" artisan db:seed --force
& "C:\xampp\php\php.exe" artisan serve --host=127.0.0.1 --port=8000
```

API disponible en `http://127.0.0.1:8000/api`.

Usuarios sembrados (configurables con `SEED_*` en `.env`; roles exactos vía `sync()`):
- Usuario de prueba (solo app `/login`, rol ciudadano — NO entra al panel): `owen@sgcm.app` / `1234567`
- Funcionario de prueba (panel `/admin/login`, rol funcionario — gestiona usuarios): `funcionario@sgcm.app` / `1234567`

Separación de roles: un usuario `ciudadano` no puede ingresar a `/admin/*` (rechazado en el login del panel, en el `AdminGuard` y con `403` en la API).

## 3. Despliegue en hosting compartido (cPanel)

### 3.1 Subir archivos
Sube el contenido de `backend/` (con `vendor/` incluido o ejecutando `composer install --no-dev --optimize-autoloader` en el servidor). **No subas** `.env`, ni `storage/logs/*`, ni `storage/app/private/*`.

### 3.2 Apuntar el dominio a `/public`
Opción A (preferente): en cPanel, define el document root del subdominio (p. ej. `api.tudominio.com`) como `backend/public`.

Opción B (dominio principal): crea un `.htaccess` en la raíz `public_html`:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ backend/public/$1 [L]
</IfModule>
```

### 3.3 Configurar `.env` de producción
Parte de `.env.example`. Claves críticas:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.tudominio.com
FRONTEND_URL=https://tu-app.vercel.app        # origen CORS del frontend Ionic
DB_DATABASE=usuario_occre
JWT_SECRET=<php artisan jwt:secret>
PAYMENT_WEBHOOK_SECRET=<secreto aleatorio fuerte>
PAYMENT_WEBHOOK_VERIFY=true
GEMINI_API_KEY=<clave de Google AI Studio>
FIREBASE_CREDENTIALS=/home/usuario/firebase-service-account.json
MAIL_MAILER=smtp ...
```

Genera claves en el servidor:

```bash
php artisan key:generate --force
php artisan jwt:secret --force
```

### 3.4 Base de datos y seeders
```bash
php artisan migrate --force
php artisan db:seed --force
```

### 3.5 Permisos
```bash
chmod -R 775 storage bootstrap/cache
```

Los documentos subidos viven en `storage/app/private/uploads/` (privados; solo se descargan vía endpoint autenticado). No requieren `storage:link`.

### 3.6 Scheduler (cron)
El panel de cPanel → Cron Jobs, cada minuto:

```
* * * * * cd /home/usuario/backend && php artisan schedule:run >> /dev/null 2>&1
```

Esto ejecuta cada hora:
- Marcado de tarjetas de turismo vencidas.
- Envío de recordatorios de retorno (email/push) pendientes.

### 3.7 Webhook de pagos
URL para la pasarela: `https://api.tudominio.com/api/tourism-card/payment/webhook`

Exige header `X-Payment-Signature: HMAC-SHA256(rawBody, PAYMENT_WEBHOOK_SECRET)` en hex. Para probar sin pasarela real puedes firmar tú mismo el payload (el frontend NUNCA llama a este endpoint).

## 4. Frontend Ionic

Solo cambia la URL de la API:

- Desarrollo: `proxy.conf.json` → `{"target": "http://127.0.0.1:8000"}` (elimina `/api` duplicado si aplica; las rutas ya incluyen `/api/...`).
- Producción: `src/environments/environment.prod.ts` → `apiUrl: 'https://api.tudominio.com/api'`.

## 5. Diferencias intencionales respecto al backend Node

| Aspecto | Node | Laravel |
|---|---|---|
| Webhook de pagos | aceptaba cualquier llamada | exige firma HMAC-SHA256 (`PAYMENT_WEBHOOK_VERIFY=false` para desactivar) |
| DELETE de documentos | cualquiera autenticado | solo dueño o staff |
| Archivos subidos | carpeta pública `uploads/` | almacenamiento privado `storage/app/private/uploads` |
| Bug `searchRecord` (citas) | filtraba por `request.id` | filtra por `user_id` correcto |
| Recordatorios de retorno | solo se guardaban en BD | se envían por email/push y se marcan `sent` |

Todo lo demás (rutas, formatos JSON, códigos HTTP, JWT con claim `email`, hashes bcrypt existentes) es idéntico.
