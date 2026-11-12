# Fase 4 - Procedimiento de Recuperación ante Desastres OCCRE

## Resumen
Procedimiento paso a paso para recuperar OCCRE ante eliminación accidental, fallos de programación, corrupción de BD, ataques, fallos del servidor, pérdida de archivos o errores durante actualizaciones. Toda la recuperación se realiza en entorno aislado; nunca sobre producción.

---

## 1. Indicadores de recuperación (RPO/RTO)

| Indicador | Valor propuesto | Justificación |
|-----------|----------------|---------------|
| **RPO** (Recovery Point Objective) | 24 horas | Backups diarios; máximo 1 día de datos perdidos |
| **RTO** (Recovery Time Objective) | 4 horas | Tiempo para restaurar BD, archivos, configuraciones y verificar funcionamiento |

Estos valores son realistas para infraestructura local con MySQL y copias de archivos sin servicios en la nube.

---

## 2. Requisitos previos para la recuperación

Antes de iniciar cualquier restauración:

1. **Entorno aislado:** Usar una máquina o contenedor separado de producción
2. **Base de datos temporal:** MySQL en una base de datos diferente (ej. `sgcm_db_recover`)
3. **Sin sobrescritura:** Nunca restaurar sobre la base de datos original ni sobre archivos de producción
4. **Variables de entorno:** Tener disponibles las claves cifradas (APP_KEY, JWT_SECRET, etc.)
5. **Contraseña de backup:** Tener disponible `OCCRE_BACKUP_PASSWORD` para descifrar

---

## 3. Procedimiento de recuperación paso a paso

### Paso 1: Verificar integridad de los respaldos

```powershell
# Verificar SHA-256 de cada archivo antes de descifrar
Get-FileHash -Algorithm SHA256 "C:\Users\Owen\Backups\OCCRE\daily\2026-11-05\database\db_dump.sql.aes256"
# Comparar con el contenido del archivo .sha256 correspondiente
```

Si el hash no coincide, NO proceder con ese respaldo.

### Paso 2: Descifrar los respaldos

```powershell
# Ejemplo: descifrar ZIP de base de datos
7z x "db_dump.sql.aes256" -p"CONTRASEÑA_DE_BACKUP" -o"C:\Temp\OCCRE_Recupero\database\"
```

### Paso 3: Restaurar la base de datos

```sql
-- Crear base de datos temporal (NO sobre la original)
CREATE DATABASE sgcm_db_recover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importar el dump
mysql -u usuario_db -p sgcm_db_recover < C:\Temp\OCCRE_Recupero\database\db_dump.sql
```

### Paso 4: Verificar integridad de las tablas

```sql
-- Verificar que todas las tablas existen
USE sgcm_db_recover;
SHOW TABLES;

-- Verificar conteo de registros en tablas críticas
SELECT 'users' AS tabla, COUNT(*) AS registros FROM users
UNION ALL SELECT 'requests', COUNT(*) FROM requests
UNION ALL SELECT 'tourist_cards', COUNT(*) FROM tourist_cards
UNION ALL SELECT 'appointments', COUNT(*) FROM appointments;

-- Verificar integridad referencial (ejemplo)
SELECT COUNT(*) AS requests_huérfanos FROM requests r
LEFT JOIN users u ON r.user_id = u.id WHERE u.id IS NULL;
```

### Paso 5: Restaurar archivos y directorios

```powershell
# Restaurar documentos privados
Copy-Item -Path "C:\Temp\OCCRE_Recupero\files\private\*" -Destination "backend/storage/app/private/" -Recurse -Force

# Restaurar archivos públicos (constancias, recibos)
Copy-Item -Path "C:\Temp\OCCRE_Recupero\files\public\*" -Destination "backend/storage/app/public/" -Recurse -Force
```

### Paso 6: Restaurar configuraciones y código

```powershell
# Restaurar configuraciones Laravel
Copy-Item -Path "C:\Temp\OCCRE_Recupero\config\*" -Destination "backend/config/" -Recurse -Force

# Restaurar código fuente (si fuera necesario)
Copy-Item -Path "C:\Temp\OCCRE_Recupero\code\backend\app\*" -Destination "backend/app/" -Recurse -Force
Copy-Item -Path "C:\Temp\OCCRE_Recupero\code\src\app\*" -Destination "src/app/" -Recurse -Force
```

### Paso 7: Restaurar claves criptográficas (.env)

```powershell
# Descifrar y restaurar .env (hacer solo en entorno aislado)
# Nunca exponer el contenido del .env en pantalla ni en logs
```

### Paso 8: Reinstalación de dependencias

```bash
# Backend (PHP)
composer install --no-interaction --prefer-dist

# Frontend (Node)
npm ci
```

### Paso 9: Verificar que la aplicación vuelve a funcionar

1. Ejecutar `php artisan migrate --status` (verificar que las migraciones coinciden con la BD restaurada)
2. Ejecutar las pruebas de seguridad existentes: `php vendor/bin/phpunit tests/Feature/Security/`
3. Verificar que los endpoints críticos responden (health, auth, requests)
4. Verificar que los archivos de documentos son accesibles desde la aplicación
5. Verificar que las constancias QR se validan correctamente

---

## 4. Verificación post-recuperación

| Verificación | Método | Resultado esperado |
|--------------|--------|-------------------|
| Base de datos accesible | `mysql -e "SELECT 1"` | Éxito |
| Tablas críticas presentes | `SHOW TABLES` | Todas las 28 tablas |
| Datos de usuarios | `SELECT COUNT(*) FROM users` | > 0 |
| Documentos de ciudadanos | Verificar archivos en `storage/app/private/` | Archivos existen y son legibles |
| Constancias PDF | Verificar archivos en `storage/app/public/` | PDFs existen |
| Claves criptográficas | Verificar que JWT y APP_KEY funcionan | Tokens se generan y validan |
| Pruebas de seguridad | `phpunit tests/Feature/Security/` | 31 tests, 90 aserciones, 0 fallos |

---

## 5. Riesgos del procedimiento de recuperación

| Riesgo | Mitigación |
|--------|------------|
| Contraseña de backup perdida | Guardar copia impresa en lugar físico seguro |
| Corrupción del respaldo | Verificar SHA-256 antes de restaurar |
| Incompatibilidad de versiones MySQL | Usar misma versión de MySQL en entorno de recuperación |
| Archivos .env expuestos | Restaurar solo en entorno aislado, eliminar inmediatamente después |
| Dependencias faltantes | Usar `composer.lock` y `package-lock.json` para versiones exactas |
