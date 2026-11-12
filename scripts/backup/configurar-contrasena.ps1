# =============================================================================
# CONFIGURACIÓN DE CONTRASEÑA DE BACKUP - OCCRE
# =============================================================================
# Este script configura la contraseña de cifrado de backups usando Windows DPAPI.
# La contraseña se cifra de forma que SOLO el usuario actual de Windows pueda
# descifrarla. No se almacena en texto plano en ningún lado.
#
# Requisitos:
#   - Ejecutar como el usuario que ejecutará la tarea programada
# =============================================================================

$ErrorActionPreference = "Stop"

$BackupRoot = "C:\Users\Owen\Backups\OCCRE"
$CredDir = "$BackupRoot\credentials"
$CredFile = "$CredDir\backup-password.enc"

# Crear directorio de credenciales
if (-not (Test-Path $CredDir)) {
    New-Item -ItemType Directory -Path $CredDir -Force | Out-Null
}

# Solicitar contraseña de forma segura (no se muestra en pantalla)
Write-Host "Configuración de contraseña de cifrado de backups OCCRE" -ForegroundColor Cyan
Write-Host "Esta contraseña se cifrará con Windows DPAPI y solo podrá ser descifrada" -ForegroundColor Gray
Write-Host "por el usuario actual de Windows. No se guardará en texto plano." -ForegroundColor Gray
Write-Host ""

$password = Read-Host -AsSecureString -Prompt "Ingrese la contraseña de cifrado (mínimo 12 caracteres)"

if (-not $password -or $password.Length -lt 12) {
    Write-Error "ERROR: La contraseña debe tener al menos 12 caracteres."
    exit 1
}

# Confirmar contraseña
$passwordConfirm = Read-Host -AsSecureString -Prompt "Confirme la contraseña"

# Verificar que coincidan
$passwordText = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))
$confirmText = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordConfirm))

if ($passwordText -ne $confirmText) {
    Write-Error "ERROR: Las contraseñas no coinciden."
    exit 1
}

# Limpiar variables de texto plano
[System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($password))
[System.Runtime.InteropServices.Marshal]::ZeroFreeBSTR([System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($passwordConfirm))

# Cifrar con Windows DPAPI (CurrentUser - solo este usuario puede descifrar)
$bytes = [System.Text.Encoding]::UTF8.GetBytes($passwordText)
$encryptedBytes = [System.Security.Cryptography.ProtectedData]::Protect($bytes, $null, [System.Security.Cryptography.DataProtectionScope]::CurrentUser)
$encryptedBase64 = [Convert]::ToBase64String($encryptedBytes)

# Limpiar variable de texto plano
$passwordText = $null

# Guardar contraseña cifrada
$encryptedBase64 | Out-File -FilePath $CredFile -Encoding UTF8 -NoNewline

# Configurar permisos NTFS: solo el usuario actual puede leer
$acl = Get-Acl -Path $CredFile
$currentUser = [System.Security.Principal.WindowsIdentity]::Current.Name
$acl.SetAccessRuleProtection($true, $false) # Deshabilitar herencia, copiar reglas existentes
$rule = New-Object System.Security.AccessControl.FileSystemAccessRule($currentUser, "Read", "Allow")
$acl.SetAccessRule($rule)
Set-Acl -Path $CredFile -AclObject $acl

Write-Host ""
Write-Host "CONTRASEÑA CONFIGURADA CON ÉXITO" -ForegroundColor Green
Write-Host "Archivo cifrado: $CredFile" -ForegroundColor Gray
Write-Host "Permisos: Solo lectura para el usuario actual ($currentUser)" -ForegroundColor Gray
Write-Host ""
Write-Host "IMPORTANTE: Guarde una copia de esta contraseña en un medio externo" -ForegroundColor Yellow
Write-Host "seguro (USB cifrado, papel, etc.). Si pierde el acceso al usuario de" -ForegroundColor Yellow
Write-Host "Windows, no podrá descifrar los respaldos." -ForegroundColor Yellow
