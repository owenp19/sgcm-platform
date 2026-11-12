# Auditoría de Backups y Recuperación ante Desastres — OCCRE

**Fecha:** 2026-11-12  
**Proyecto:** OCCRE (SGCM Platform)  
**Alcance:** Verificación real de scripts de respaldo, cifrado, integridad, retención, procedimiento de recuperación

---

## 1. Documentación de Backups Existente

| Documento | Ubicación | Contenido |
|-----------|-----------|-----------|
| Estructura de almacenamiento | `docs/backups/01-estructura-almacenamiento.md` | Dónde se guardan los backups |
| Estrategia de backups | `docs/backups/02-estrategia-backups.md` | Frecuencia, retención, tipos |
| Protección de backups | `docs/backups/03-proteccion-backups.md` | Cifrado, permisos, ubicación |
| Procedimiento de recuperación | `docs/backups/04-procedimiento-recuperacion.md` | Cómo restaurar |
| Informe final | `docs/backups/05-informe-final-backups.md` | Resultado de pruebas |

---

## 2. Scripts de Backup

| Script | Ubicación | Propósito |
|--------|-----------|-----------|
| Backup completo | `scripts/backup/backup-completo.ps1` | Backup de BD y archivos |
| Verificar integridad | `scripts/backup/verificar-integridad.ps1` | SHA-256 por archivo |
| Configurar tarea programada | `scripts/backup/configurar-tarea-programada.ps1` | Automatización Windows Task Scheduler |
| Guía recuperación | `scripts/restore/leeme-recuperacion.md` | Instrucciones de restauración |

---

## 3. Verificación de Configuración de Backups

| Aspecto | Valor documentado | Verificación |
|---------|------------------|--------------|
| Ubicación de backups | `C:\Users\Owen\Backups\OCCRE\` | Fuera del proyecto, fuera de Git, fuera de carpeta pública |
| Cifrado | AES-256 | Contraseña en variable de entorno `OCCRE_BACKUP_PASSWORD` |
| Integridad | SHA-256 por archivo | `manifest.json` por conjunto de backup |
| Contraseña | Variable de entorno | No hardcodeada en scripts |
| DPAPI | No mencionado | No se utiliza DPAPI de Windows |
| Retención | Diario 7d / Semanal 4s / Mensual 3m | Documentado en estrategia |
| Automatización | Programador de Tareas Windows | Diario 02:00 AM, requiere activación |
| RPO | 24 horas | Documentado |
| RTO | 4 horas | Documentado |

### Hallazgo BK-01: DPAPI no utilizado
- **Observación:** La documentación menciona AES-256 con contraseña en variable de entorno, pero no menciona DPAPI (Data Protection API de Windows) para proteger la contraseña.
- **Evaluación:** La contraseña está en variable de entorno, lo cual es mejor que hardcodeada, pero podría ser más segura con DPAPI o un gestor de secretos.
- **Archivo:** `docs/backups/03-proteccion-backups.md`, `scripts/backup/`

### Hallazgo BK-02: Tarea programada no activada
- **Observación:** El programador de Tareas Windows está configurado pero requiere activación manual por el usuario.
- **Riesgo:** Si no se activa, los backups no se ejecutan automáticamente.
- **Archivo:** `scripts/backup/configurar-tarea-programada.ps1`, `docs/ai-memory/pending-work.md` (línea 46)

### Hallazgo BK-03: Variable de entorno OCCRE_BACKUP_PASSWORD no activada
- **Observación:** La variable de entorno para la contraseña de cifrado no está configurada en el sistema.
- **Riesgo:** Sin la contraseña, los backups no se pueden cifrar ni restaurar.
- **Archivo:** `docs/ai-memory/pending-work.md` (línea 46)

---

## 4. Prueba de Restauración (verificada previamente)

| Aspecto | Resultado |
|---------|-----------|
| Tablas restauradas | 28 tablas en BD temporal |
| Entorno aislado | Sí (BD temporal, no producción) |
| Datos ficticios | Sí |
| Producción tocada | No |
| Backups sobrescritos | No |
| Éxito | Sí |

---

## 5. Riesgos Identificados

| ID | Riesgo | Severidad | Mitigación actual |
|----|--------|-----------|-------------------|
| BK-01 | Tarea de backup no activada (no hay automatización real) | Alta | Scripts listos, requieren activación |
| BK-02 | Contraseña de cifrado no configurada (OCCRE_BACKUP_PASSWORD) | Alta | Variable de entorno no definida |
| BK-03 | No se usa DPAPI ni gestor de secretos | Media | Contraseña en variable de entorno |
| BK-04 | Backups fuera del proyecto pero en disco local | Media | Riesgo de pérdida si falla el disco local |
| BK-05 | No se verificó restauración en entorno aislado recientemente | Media | Última verificación exitosa (28 tablas) |

---

## 6. Objetivos RPO y RTO

| Objetivo | Valor documentado | ¿Alcanzable? | Evidencia |
|----------|------------------|--------------|-----------|
| RPO (Recovery Point Objective) | 24 horas | Sí, si el backup diario se ejecuta | Depende de activación de tarea programada |
| RTO (Recovery Time Objective) | 4 horas | Sí, procedimiento documentado | Prueba de restauración exitosa (28 tablas en BD temporal) |

**Nota:** RPO y RTO son alcanzables **solo si** se activa la tarea programada y se configura `OCCRE_BACKUP_PASSWORD`. Actualmente no hay automatización activa.

---

## 7. Reglas Respetadas (verificación)

| Regla | Estado |
|-------|--------|
| No eliminar información existente | ✓ Respetado |
| No borrar tablas ni registros | ✓ Respetado |
| No ejecutar operaciones destructivas | ✓ Respetado |
| No sobrescribir backups | ✓ Respetado |
| No modificar datos reales de ciudadanos | ✓ Respetado (solo datos ficticios en pruebas) |
| Ejecutar pruebas de restauración solo en entorno aislado | ✓ Respetado |
| No publicar credenciales | ✓ Respetado (contraseña en variable de entorno) |

---

## 8. Recomendaciones (requieren acción del usuario, no cambios de código)

1. **Activar la variable de entorno `OCCRE_BACKUP_PASSWORD`** en el sistema donde se ejecutarán los backups.
2. **Activar la tarea programada** (Programador de Tareas Windows, diario 02:00 AM).
3. **Considerar usar DPAPI** o un gestor de secretos para proteger la contraseña de cifrado.
4. **Considerar almacenar backups en ubicación adicional** (almacenamiento en la nube, unidad de red) para protección contra ransomware/local disk failure.
5. **Ejecutar prueba de restauración periódicamente** (trimestral) para verificar que los backups son recuperables.
6. **Documentar el procedimiento de restauración paso a paso** para personal de operaciones.
