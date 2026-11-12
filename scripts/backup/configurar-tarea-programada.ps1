# =============================================================================
# CONFIGURACIÓN DEL PROGRAMADOR DE TAREAS - OCCRE BACKUP
# =============================================================================
# Este script crea una tarea programada en Windows que ejecuta el backup
# completo de OCCRE diariamente a las 02:00 AM.
#
# Requisitos:
#   - Ejecutar como Administrador
#   - Contraseña de cifrado configurada (ejecutar configurar-contrasena.ps1)
# =============================================================================

$ErrorActionPreference = "Stop"

$NombreTarea = "OCCRE_Backup_Diario"
$Descripcion = "Backup completo diario de OCCRE (base de datos, archivos, configuraciones)"
$ScriptBackup = "C:\Users\Owen\Documents\Sueños\software-apps\sgcm-platform\scripts\backup\backup-completo.ps1"
$HoraEjecucion = "02:00"
$DiasEjecucion = "*"  # Todos los días

# Verificar que el script existe
if (-not (Test-Path $ScriptBackup)) {
    Write-Error "El script de backup no existe: $ScriptBackup"
    exit 1
}

# Verificar credenciales de cifrado (almacén DPAPI)
$CredFile = "C:\Users\Owen\Backups\OCCRE\credentials\backup-password.enc"
if (-not (Test-Path $CredFile)) {
    Write-Warning "ADVERTENCIA: La contraseña de cifrado no está configurada."
    Write-Warning "Ejecutar scripts\backup\configurar-contrasena.ps1 antes de activar la tarea programada."
}

# Crear la tarea programada
$comando = @"
schtasks /create /tn "$NombreTarea" /tr "powershell.exe -ExecutionPolicy Bypass -File ""$ScriptBackup""" /sc daily /st $HoraEjecucion /ru "%USERNAME%" /rl HIGHEST /f
"@

Write-Host "Creando tarea programada: $NombreTarea"
Invoke-Expression $comando

if ($LASTEXITCODE -eq 0) {
    Write-Host "EXITO: Tarea programada creada correctamente"
    Write-Host "  Nombre: $NombreTarea"
    Write-Host "  Ejecución: Diaria a las $HoraEjecucion"
    Write-Host "  Script: $ScriptBackup"
    Write-Host ""
    Write-Host "Para verificar: schtasks /query /tn `"$NombreTarea`""
    Write-Host "Para ejecutar manualmente: schtasks /run /tn `"$NombreTarea`""
    Write-Host "Para eliminar: schtasks /delete /tn `"$NombreTarea`" /f"
} else {
    Write-Error "FALLO al crear la tarea programada (código: $LASTEXITCODE)"
    exit 1
}

# =============================================================================
# INSTRUCCIONES PARA CONFIGURAR LA CONTRASEÑA DE CIFRADO (DPAPI)
# =============================================================================
Write-Host ""
Write-Host "=== PASO OBLIGATORIO: Configurar contraseña de cifrado (DPAPI) ==="
Write-Host "La contraseña de cifrado de backups debe configurarse usando Windows DPAPI:"
Write-Host ""
Write-Host "  1. Ejecutar: scripts\backup\configurar-contrasena.ps1"
Write-Host "  2. Ingresar una contraseña segura (mínimo 12 caracteres) cuando se solicite"
Write-Host "  3. La contraseña se cifra con DPAPI y solo el usuario actual puede descifrarla"
Write-Host "  4. Guardar una copia de la contraseña en un medio externo seguro"
Write-Host ""
Write-Host "No almacenar esta contraseña en scripts, variables de entorno, Git ni documentación."
