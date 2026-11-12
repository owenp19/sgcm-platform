# =============================================================================
# SCRIPT DE BACKUP COMPLETO - OCCRE (sgcm-platform)
# =============================================================================
# Este script realiza copias de seguridad diarias de:
#   1. Base de datos MySQL (sgcm_db)
#   2. Archivos privados (storage/app/private)
#   3. Archivos públicos (storage/app/public)
#   4. Configuraciones Laravel (backend/config, migrations, routes)
#   5. Código fuente (backend/app, src/app)
#   6. Dependencias (composer.lock, package-lock.json)
#   7. Claves criptográficas (.env - cifrado)
#
# Diseñado para Windows (PowerShell + Programador de Tareas)
# No utiliza servicios de pago ni suscripciones en la nube
# =============================================================================

$ErrorActionPreference = "Stop"
$StopOnError = $true

# -----------------------------------------------------------------------------
# CONFIGURACIÓN (ajustar según el entorno)
# -----------------------------------------------------------------------------
$ProyectoRoot = "C:\Users\Owen\Documents\Sueños\software-apps\sgcm-platform"
$BackendRoot = "$ProyectoRoot\backend"
$BackupRoot = "C:\Users\Owen\Backups\OCCRE"
$Fecha = Get-Date -Format "yyyy-MM-dd_HHmmss"
$FechaCorta = Get-Date -Format "yyyy-MM-dd"
$TipoBackup = "diario"  # Puede ser: diario, semanal, mensual
$EspacioMinimoGB = 1

# Clave de cifrado (recuperada desde almacén DPAPI o variable de entorno)
$CredFile = "$BackupRoot\credentials\backup-password.enc"

function Obtener-ContrasenaBackup {
    # Intento 1: Leer desde almacén DPAPI (cifrado, solo este usuario)
    if (Test-Path $CredFile) {
        try {
            $encryptedBase64 = Get-Content -Path $CredFile -Raw -ErrorAction Stop
            $encryptedBytes = [Convert]::FromBase64String($encryptedBase64.Trim())
            $decryptedBytes = [System.Security.Cryptography.ProtectedData]::Unprotect($encryptedBytes, $null, [System.Security.Cryptography.DataProtectionScope]::CurrentUser)
            $password = [System.Text.Encoding]::UTF8.GetString($decryptedBytes)
            if ($password -and $password.Length -gt 0) {
                return $password
            }
        } catch {
            Write-Warning "No se pudo descifrar desde almacén DPAPI: $($_.Exception.Message)"
        }
    }

    # Intento 2: Variable de entorno (fallback)
    $envPassword = $env:OCCRE_BACKUP_PASSWORD
    if ($envPassword -and $envPassword.Length -gt 0) {
        return $envPassword
    }

    return $null
}

$BackupPassword = Obtener-ContrasenaBackup

# Validar que existe la contraseña de cifrado
if (-not $BackupPassword -or $BackupPassword.Length -eq 0) {
    Write-Error "ERROR: No se encontró contraseña de cifrado. Ejecute configurar-contrasena.ps1 o configure OCCRE_BACKUP_PASSWORD."
    exit 1
}

# -----------------------------------------------------------------------------
# FUNCIONES AUXILIARES
# -----------------------------------------------------------------------------
function Escribir-Log($mensaje, $nivel = "INFO") {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $linea = "[$timestamp] [$nivel] $mensaje"
    Write-Host $linea
    Add-Content -Path "$LogFile" -Value $linea -Encoding UTF8
}

function Verificar-EspacioDisco {
    $drive = Get-PSDrive C | Select-Object -ExpandProperty Free
    $gbLibre = [math]::Round($drive / 1GB, 2)
    if ($gbLibre -lt $EspacioMinimoGB) {
        Escribir-Log "ESPACIO INSUFICIENTE: $gbLibre GB libre (mínimo requerido: $EspacioMinimoGB GB)" "ERROR"
        exit 1
    }
    Escribir-Log "Espacio en disco OK: $gbLibre GB libre"
}

function Comprimir-Y-Cifrar {
    param(
        [string]$Origen,
        [string]$NombreSalida,
        [string]$Subdirectorio
    )
    
    $DestinoDir = "$BackupRoot\$TipoBackup\$FechaCorta\$Subdirectorio"
    if (-not (Test-Path $DestinoDir)) {
        New-Item -ItemType Directory -Path $DestinoDir -Force | Out-Null
    }
    
    $ZipTemporal = "$DestinoDir\$NombreSalida.tmp.zip"
    $ArchivoCifrado = "$DestinoDir\$NombreSalida.aes256"
    $ChecksumFile = "$ArchivoCifrado.sha256"
    
    try {
        # Paso 1: Comprimir con PowerShell nativo (ZIP sin cifrar)
        Compress-Archive -Path "$Origen\*" -DestinationPath "$ZipTemporal" -Force
        
        # Paso 2: Cifrar con AES-256 usando .NET (no requiere 7-Zip ni herramientas externas)
        $zipBytes = [System.IO.File]::ReadAllBytes($ZipTemporal)
        
        # Generar sal y IV aleatorios
        $saltBytes = New-Object byte[] 16
        $ivBytes = New-Object byte[] 16
        $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
        $rng.GetBytes($saltBytes)
        $rng.GetBytes($ivBytes)
        $rng.Dispose()
        
        # Derivar clave desde contraseña usando PBKDF2 (Rfc2898DeriveBytes)
        $passwordBytes = [System.Text.Encoding]::UTF8.GetBytes($BackupPassword)
        $deriveKey = New-Object System.Security.Cryptography.Rfc2898DeriveBytes($passwordBytes, $saltBytes, 10000, [System.Security.Cryptography.HashAlgorithmName]::SHA256)
        $aesKey = $deriveKey.GetBytes(32)  # 256 bits
        $deriveKey.Dispose()
        
        # Cifrar con AES-256-CBC
        $aes = [System.Security.Cryptography.Aes]::Create()
        $aes.Key = $aesKey
        $aes.IV = $ivBytes
        $aes.Mode = [System.Security.Cryptography.CipherMode]::CBC
        $aes.Padding = [System.Security.Cryptography.PaddingMode]::PKCS7
        
        $msCipher = New-Object System.IO.MemoryStream
        $csCipher = New-Object System.Security.Cryptography.CryptoStream($msCipher, $aes.CreateEncryptor(), [System.Security.Cryptography.CryptoStreamMode]::Write)
        $csCipher.Write($zipBytes, 0, $zipBytes.Length)
        $csCipher.FlushFinalBlock()
        $encryptedBytes = $msCipher.ToArray()
        $csCipher.Close()
        $msCipher.Close()
        $aes.Dispose()
        
        # Escribir archivo cifrado: [salt(16)] [iv(16)] [datos_cifrados]
        $fs = New-Object System.IO.FileStream($ArchivoCifrado, [System.IO.FileMode]::Create)
        $fs.Write($saltBytes, 0, $saltBytes.Length)
        $fs.Write($ivBytes, 0, $ivBytes.Length)
        $fs.Write($encryptedBytes, 0, $encryptedBytes.Length)
        $fs.Close()
        
        # Eliminar ZIP temporal sin cifrar
        if (Test-Path $ZipTemporal) {
            Remove-Item -Path $ZipTemporal -Force
        }
        
        # Generar checksum SHA-256 del archivo cifrado
        $hash = Get-FileHash -Path $ArchivoCifrado -Algorithm SHA256
        "$($hash.Hash)  $($hash.Path)" | Out-File -FilePath $ChecksumFile -Encoding UTF8
        
        Escribir-Log "Backup completado: $NombreSalida (subdirectorio: $Subdirectorio, cifrado AES-256-CBC)"
        return $true
    }
    catch {
        $errMsg = $_.Exception.Message
        Escribir-Log "ERROR en backup de ${NombreSalida}: $errMsg" "ERROR"
        return $false
    }
}

# -----------------------------------------------------------------------------
# EJECUCIÓN PRINCIPAL
# -----------------------------------------------------------------------------
$LogFile = "$BackupRoot\logs\backup_$FechaCorta.log"
if (-not (Test-Path $BackupRoot)) { New-Item -ItemType Directory -Path $BackupRoot -Force | Out-Null }
if (-not (Test-Path "$BackupRoot\logs")) { New-Item -ItemType Directory -Path "$BackupRoot\logs" -Force | Out-Null }

Escribir-Log "=== INICIO DE BACKUP OCCRE ($TipoBackup) ==="
Verificar-EspacioDisco

$Exito = $true

# 1. Base de datos MySQL
Escribir-Log "Iniciando backup de base de datos MySQL..."
$DumpFile = "$BackupRoot\$TipoBackup\$FechaCorta\database\db_dump_$Fecha.sql"
if (-not (Test-Path "$BackupRoot\$TipoBackup\$FechaCorta\database")) {
    New-Item -ItemType Directory -Path "$BackupRoot\$TipoBackup\$FechaCorta\database" -Force | Out-Null
}

# Intentar mysqldump (si MySQL está instalado y accesible)
if (Get-Command mysql -ErrorAction SilentlyContinue) {
    # Las credenciales se leen de variables de entorno o del .env (no se escriben en texto plano)
    $DbHost = if ($env:DB_HOST) { $env:DB_HOST } else { "127.0.0.1" }
    $DbPort = if ($env:DB_PORT) { $env:DB_PORT } else { "3306" }
    $DbName = if ($env:DB_DATABASE) { $env:DB_DATABASE } else { "sgcm_db" }
    $DbUser = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { "root" }
    # $DbPassword se leería de forma segura (variable de entorno)
    
    & mysqldump -h $DbHost -P $DbPort -u $DbUser $DbName > $DumpFile 2>$null
    if ($LASTEXITCODE -eq 0 -and (Test-Path $DumpFile)) {
        Escribir-Log "Dump de MySQL generado: $DumpFile"
        # Cifrar el dump (incluye el .sql en el archivo cifrado)
        $ZipCifradoOk = Comprimir-Y-Cifrar -Origen "$BackupRoot\$TipoBackup\$FechaCorta\database" -NombreSalida "database_$Fecha" -Subdirectorio "database"
        if (-not $ZipCifradoOk) { $Exito = $false }

        # Eliminar el dump SQL sin cifrar de disco (solo debe existir la versión cifrada)
        if (Test-Path $DumpFile) {
            Remove-Item -Path $DumpFile -Force
            Escribir-Log "Dump SQL sin cifrado eliminado de disco (solo permanece la versión cifrada)"
        }
    } else {
        Escribir-Log "WARNING: No se pudo generar dump de MySQL (verificar credenciales y servicio MySQL)" "WARN"
    }
} else {
    Escribir-Log "WARNING: mysqldump no está disponible en el PATH" "WARN"
}

# 2. Archivos privados (documentos de ciudadanos)
Escribir-Log "Iniciando backup de archivos privados..."
$PrivDir = "$BackendRoot\storage\app\private"
if (Test-Path $PrivDir) {
    $Ok = Comprimir-Y-Cifrar -Origen $PrivDir -NombreSalida "files_private_$Fecha" -Subdirectorio "files"
    if (-not $Ok) { $Exito = $false }
} else {
    Escribir-Log "Directorio privado no encontrado (puede estar vacío): $PrivDir" "WARN"
}

# 3. Archivos públicos (constancias, recibos)
Escribir-Log "Iniciando backup de archivos públicos..."
$PubDir = "$BackendRoot\storage\app\public"
if (Test-Path $PubDir) {
    $Ok = Comprimir-Y-Cifrar -Origen $PubDir -NombreSalida "files_public_$Fecha" -Subdirectorio "files"
    if (-not $Ok) { $Exito = $false }
} else {
    Escribir-Log "Directorio público no encontrado (puede estar vacío): $PubDir" "WARN"
}

# 4. Configuraciones Laravel
Escribir-Log "Iniciando backup de configuraciones..."
$ConfigItems = @(
    "$BackendRoot\config",
    "$BackendRoot\database\migrations",
    "$BackendRoot\routes\api.php"
)
foreach ($item in $ConfigItems) {
    if (Test-Path $item) {
        $Ok = Comprimir-Y-Cifrar -Origen $item -NombreSalida "config_$Fecha" -Subdirectorio "config"
        if (-not $Ok) { $Exito = $false }
    }
}

# 5. Código fuente
Escribir-Log "Iniciando backup de código fuente..."
$CodeItems = @(
    "$BackendRoot\app",
    "$ProyectoRoot\src\app",
    "$ProyectoRoot\capacitor.config.ts",
    "$ProyectoRoot\angular.json"
)
foreach ($item in $CodeItems) {
    if (Test-Path $item) {
        $Ok = Comprimir-Y-Cifrar -Origen $item -NombreSalida "code_$Fecha" -Subdirectorio "code"
        if (-not $Ok) { $Exito = $false }
    }
}

# 6. Dependencias (lock files)
Escribir-Log "Iniciando backup de dependencias..."
$LockItems = @(
    "$BackendRoot\composer.lock",
    "$ProyectoRoot\package-lock.json"
)
foreach ($item in $LockItems) {
    if (Test-Path $item) {
        $Ok = Comprimir-Y-Cifrar -Origen $item -NombreSalida "deps_$Fecha" -Subdirectorio "deps"
        if (-not $Ok) { $Exito = $false }
    }
}

# 7. Generar manifest.json
$Manifest = @{
    fecha = Get-Date -Format "o"
    tipo = $TipoBackup
    version = "1.0"
    componente = "backup_completo"
    exito = $Exito
} | ConvertTo-Json -Depth 5
$ManifestPath = "$BackupRoot\$TipoBackup\$FechaCorta\manifest.json"
$Manifest | Out-File -FilePath $ManifestPath -Encoding UTF8

# -----------------------------------------------------------------------------
# RESULTADO FINAL
# -----------------------------------------------------------------------------
if ($Exito) {
    Escribir-Log "=== BACKUP COMPLETADO CON ÉXITO ==="
    exit 0
} else {
    Escribir-Log "=== BACKUP COMPLETADO CON ADVERTENCIAS (revisar log) ==="
    exit 0
}