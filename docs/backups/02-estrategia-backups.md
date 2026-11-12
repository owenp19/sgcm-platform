# Fase 2 - Estrategia de Respaldos OCCRE

## Resumen
Estrategia de copias de seguridad gratuita, local y automatizada para Windows (PowerShell + Programador de Tareas). No se utilizan servicios de pago ni suscripciones en la nube.

---

## 1. Alcance de los respaldos

| Componente | Qué se respalda | Frecuencia | Retención |
|------------|----------------|------------|-----------|
| Base de datos MySQL | Dump completo de `sgcm_db` | Diario | 30 días |
| Documentos privados | `storage/app/private/` | Diario | 30 días |
| Archivos públicos | `storage/app/public/` | Diario | 30 días |
| Configuraciones | `backend/config/`, `backend/database/migrations/`, `backend/routes/api.php` | Semanal | 90 días |
| Código fuente | `backend/app/`, `src/app/`, `capacitor.config.ts`, `angular.json` | Semanal | 90 días |
| Dependencias | `composer.lock`, `package-lock.json` | Semanal | 90 días |
| Claves criptográficas | `.env` (cifrado) | Semanal | 90 días |
| Logs de auditoría | `storage/logs/laravel.log` | Semanal | 30 días |

---

## 2. Esquema de rotación (Grandfather-Father-Son simplificado)

| Tipo | Frecuencia | Retención | Propósito |
|------|------------|-----------|-----------|
| Diario | Cada día (hora fija) | 7 días | Recuperación rápida de errores recientes |
| Semanal | Cada domingo | 4 semanas | Recuperación de fallos semanales |
| Mensual | Primer domingo del mes | 3 meses | Recuperación de fallos mensuales y cumplimiento |

**Total de espacio estimado:** Modesto. Los dumps SQL y documentos se comprimen. Los archivos antiguos se eliminan automáticamente al superar la retención.

---

## 3. Ubicación de los respaldos

**Directorio base:** `C:\Users\Owen\Backups\OCCRE\` (fuera del proyecto, fuera de la carpeta pública de Laravel)

```
C:\Users\Owen\Backups\OCCRE\
├── daily\
│   ├── 2026-11-05/
│   │   ├── database/
│   │   ├── files/
│   │   └── config/
│   └── ...
├── weekly\
│   ├── 2026-11-01/
│   └── ...
├── monthly\
│   ├── 2026-11-01/
│   └── ...
├── checksums\        # Archivos SHA-256 de verificación
└── logs\             # Logs de ejecución del backup
```

> **Nota:** Este directorio está fuera del repositorio Git y fuera de `backend/storage/app/public` (no accesible vía web).

---

## 4. Integridad de los respaldos

- Cada archivo de backup se comprime con ZIP (Algoritmo Deflate, nivel máximo)
- Cada archivo comprimido tiene su correspondiente archivo `.sha256` con el hash SHA-256
- Antes de restaurar, se verifica el hash SHA-256 para asegurar que el archivo no está corrupto ni fue modificado
- Los checksums se generan con `Get-FileHash` (PowerShell nativo, algoritmo SHA256)

---

## 5. Versión y identificación

Cada conjunto de respaldo incluye un archivo `manifest.json` con:

```json
{
  "fecha": "2026-11-05T20:00:00-05:00",
  "tipo": "diario",
  "version": "1.0",
  "componentes": ["database", "files_private", "files_public", "config", "code"],
  "checksums": {
    "database": "sha256:abc123...",
    "files_private": "sha256:def456...",
    ...
  },
  "notas": "Backup automático diario"
}
```

---

## 6. Verificación de respaldos

Después de cada ejecución del backup:

1. Verificar que los archivos ZIP existen y no están vacíos (>0 bytes)
2. Verificar que los archivos `.sha256` coinciden con los ZIP
3. Verificar que el `manifest.json` es JSON válido
4. Registrar éxito/fallo en el log de ejecución
5. Si falla, notificar mediante evento de Windows (log del sistema) y archivo de log

---

## 7. Espacio en disco

Antes de ejecutar cada backup, el script verifica que haya al menos 1 GB libre en el disco de destino. Si no hay espacio suficiente, el backup se cancela y se registra el error.

---

## 8. Limitaciones conocidas

- No se incluye almacenamiento en la nube (requisito del usuario: sin servicios de pago)
- Los respaldos residen en el mismo equipo físico que el servidor. En caso de fallo total del hardware, se pierde todo. Se recomienda copiar manualmente los respaldos mensuales a un medio externo (USB, disco externo) mensualmente.
- La prueba de restauración debe realizarse en entorno aislado (ver Fase 5)
