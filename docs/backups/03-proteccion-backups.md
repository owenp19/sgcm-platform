# Fase 3 - Protección de los Respaldos OCCRE

## Resumen
Los respaldos contienen información personal de ciudadanos (PII) y claves criptográficas. Deben protegerse con cifrado, permisos de SO, ubicación fuera de carpetas públicas, exclusión de Git y verificación de integridad.

---

## 1. Cifrado de respaldos

Cada archivo ZIP de respaldo se cifra con AES-256 utilizando una contraseña derivada de una frase secreta almacenada de forma segura (variable de entorno `OCCRE_BACKUP_PASSWORD`).

**Flujo de cifrado:**
1. Se comprime el componente (base de datos, archivos, configuraciones) en un ZIP temporal
2. El ZIP se cifra con AES-256 usando `7-Zip` o `PowerShell` con la contraseña de respaldo
3. Se elimina el ZIP temporal sin cifrar
4. Se genera el archivo `.sha256` del ZIP cifrado

**Nota:** La contraseña de respaldo NO se almacena en scripts ni en el repositorio. Debe configurarse como variable de entorno del sistema o almacenarse en un gestor de contraseñas local.

---

## 2. Permisos del sistema operativo

Los directorios de respaldo se configuran con permisos restringidos:

| Directorio | Usuario/Grupo | Permisos | Razón |
|------------|---------------|----------|-------|
| `C:\Users\Owen\Backups\OCCRE\` | Usuario actual | Lectura/Escritura | Propietario del backup |
| `C:\Users\Owen\Backups\OCCRE\daily\` | Usuario actual | Lectura/Escritura | Solo el usuario ejecuta backups |
| `C:\Users\Owen\Backups\OCCRE\checksums\` | Usuario actual | Lectura | Solo lectura para verificaciones |
| `C:\Users\Owen\Backups\OCCRE\logs\` | Usuario actual | Lectura/Escritura | Logs de ejecución |

En PowerShell se puede aplicar con:
```powershell
icacls "C:\Users\Owen\Backups\OCCRE" /grant "UsuarioActual:(OI)(CI)F"
icacls "C:\Users\Owen\Backups\OCCRE\checksums" /grant "UsuarioActual:(OI)(CI)R"
```

---

## 3. Almacenamiento fuera de la carpeta pública de Laravel

Los respaldos NUNCA se almacenan en:
- `backend/storage/app/public/` (expuesto vía web)
- `backend/public/` (directorio público de Laravel)
- `src/assets/` (accesible desde el frontend)
- Cualquier ruta accesible por el servidor web

Se almacenan en `C:\Users\Owen\Backups\OCCRE\` que está completamente fuera del proyecto y del servidor web.

---

## 4. Exclusión del repositorio Git

Los respaldos están excluidos del control de versiones:

- El directorio `C:\Users\Owen\Backups\OCCRE\` está fuera del repositorio Git del proyecto
- El `.gitignore` del proyecto ya excluye `vendor/`, `node_modules/`, `.env`, `*.log`, etc.
- Los scripts de backup en `scripts/backup/` pueden incluirse en Git (no contienen secretos)
- Los archivos `.sha256` y los ZIP cifrados NO se commitean nunca

---

## 5. Verificación mediante SHA-256

Cada archivo de backup cifrado tiene un archivo correspondiente `.sha256`:

```
backup-database-2026-11-05.zip.aes256
backup-database-2026-11-05.zip.aes256.sha256
```

El archivo `.sha256` contiene:
```
sha256_hash  nombre_del_archivo
```

Antes de cualquier restauración, se ejecuta:
```powershell
Get-FileHash -Algorithm SHA256 "archivo.zip.aes256"
```
y se compara con el valor en el archivo `.sha256`. Si no coinciden, el respaldo se considera corrupto y no se utiliza.

---

## 6. Protección contra sobrescritura accidental

- Cada respaldo diario usa una marca de tiempo única (`YYYY-MM-DD_HHmmss`)
- Los respaldos semanales y mensuales usan carpetas separadas por fecha
- Nunca se sobrescribe un respaldo existente; se crea uno nuevo con nueva fecha
- Si se necesita rotación (eliminar antiguos), se hace por fecha de retención, no por sobrescritura

---

## 7. Separación entre archivos originales y respaldos

| Tipo | Ubicación original | Ubicación de respaldo |
|------|-------------------|----------------------|
| Base de datos | MySQL `sgcm_db` | `C:\Users\Owen\Backups\OCCRE\*/database/` |
| Documentos privados | `backend/storage/app/private/` | `C:\Users\Owen\Backups\OCCRE\*/files/private/` |
| Archivos públicos | `backend/storage/app/public/` | `C:\Users\Owen\Backups\OCCRE\*/files/public/` |
| Configuraciones | `backend/config/` | `C:\Users\Owen\Backups\OCCRE\*/config/` |
| Código fuente | `backend/app/`, `src/app/` | `C:\Users\Owen\Backups\OCCRE\*/code/` |
| Claves (.env) | `backend/.env` | `C:\Users\Owen\Backups\OCCRE\*/config/.env.cifrado` |

Los respaldos son copias de seguridad, no el almacenamiento primario. Los archivos originales permanecen en su ubicación de trabajo.

---

## 8. Protección de claves de cifrado

- La contraseña de cifrado de backups (`OCCRE_BACKUP_PASSWORD`) se almacena como variable de entorno del sistema Windows
- Nunca se escribe en scripts `.ps1` ni en archivos de configuración commiteados
- Nunca se expone en logs de ejecución
- Los logs de backup registran solo: fecha, componente, éxito/fallo, tamaño en bytes (sin nombres de archivos sensibles)
