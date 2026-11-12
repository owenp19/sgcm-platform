# Procedimiento de Recuperación OCCRE - Scripts y Pasos

Este directorio contiene la documentación y scripts para restaurar OCCRE desde los respaldos.

## Archivos disponibles

- `docs/backups/04-procedimiento-recuperacion.md` - Procedimiento detallado paso a paso
- `scripts/backup/backup-completo.ps1` - Script de backup (PowerShell)
- `scripts/backup/verificar-integridad.ps1` - Script de verificación SHA-256

## Pasos rápidos de recuperación

1. **Verificar integridad de los respaldos:**
   ```powershell
   .\scripts\backup\verificar-integridad.ps1
   ```

2. **Descifrar los respaldos:**
   Usar 7-Zip con la contraseña `OCCRE_BACKUP_PASSWORD` para descifrar los archivos `.aes256`

3. **Restaurar base de datos:**
   ```sql
   CREATE DATABASE sgcm_db_recover CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   mysql -u usuario_db -p sgcm_db_recover < db_dump.sql
   ```

4. **Restaurar archivos:**
   Copiar los archivos descifrados a sus ubicaciones originales:
   - Documentos privados → `backend/storage/app/private/`
   - Archivos públicos → `backend/storage/app/public/`
   - Configuraciones → `backend/config/`
   - Código → `backend/app/`, `src/app/`

5. **Verificar funcionamiento:**
   ```bash
   cd backend
   php vendor/bin/phpunit tests/Feature/Security/
   ```

## Reglas importantes

- NUNCA restaurar sobre la base de datos original de producción
- Usar siempre una base de datos temporal para pruebas
- Verificar SHA-256 antes de descifrar cualquier respaldo
- No exponer claves (.env) en pantalla ni en logs
- La contraseña de backup está en la variable de entorno `OCCRE_BACKUP_PASSWORD`
