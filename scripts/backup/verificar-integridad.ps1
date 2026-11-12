# =============================================================================
# SCRIPT DE VERIFICACIÓN DE INTEGRIDAD - OCCRE
# =============================================================================
# Verifica que los archivos de backup cifrados tengan un SHA-256 válido
# y que los checksums coincidan.
# =============================================================================

$ErrorActionPreference = "Stop"
$BackupRoot = "C:\Users\Owen\Backups\OCCRE"

function Escribir-Log($mensaje, $nivel = "INFO") {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    Write-Host "[$timestamp] [$nivel] $mensaje"
}

$Errores = 0
$Verificados = 0

# Buscar todos los archivos .sha256
$ChecksumFiles = Get-ChildItem -Path $BackupRoot -Filter "*.sha256" -Recurse -ErrorAction SilentlyContinue

if (-not $ChecksumFiles) {
    Escribir-Log "No se encontraron archivos .sha256 para verificar" "WARN"
    exit 0
}

foreach ($cf in $ChecksumFiles) {
    $Contenido = Get-Content -Path $cf.FullName -Raw -ErrorAction SilentlyContinue
    if (-not $Contenido) {
        Escribir-Log "Archivo checksum vacío: $($cf.FullName)" "ERROR"
        $Errores++
        continue
    }
    
    # Extraer hash esperado y nombre de archivo del contenido .sha256
    $Lineas = $Contenido.Trim() -split "`n"
    foreach ($linea in $Lineas) {
        if (-not $linea) { continue }
        
        # Formato: hash  nombre_archivo
        $Partes = $linea.Trim() -split "\s{2,}"
        if ($Partes.Length -lt 2) { continue }
        
        $HashEsperado = $Partes[0]
        $NombreArchivo = $Partes[1]
        
        # El archivo .sha256 puede contener una ruta relativa o absoluta
        $ArchivoAVerificar = $NombreArchivo
        if (-not (Test-Path $ArchivoAVerificar)) {
            # Intentar construir ruta relativa al directorio del .sha256
            $DirChecksum = Split-Path -Parent $cf.FullName
            $ArchivoAVerificar = Join-Path $DirChecksum $NombreArchivo
        }
        
        if (-not (Test-Path $ArchivoAVerificar)) {
            Escribir-Log "Archivo no encontrado para checksum: $ArchivoAVerificar" "ERROR"
            $Errores++
            continue
        }
        
        $HashReal = Get-FileHash -Path $ArchivoAVerificar -Algorithm SHA256 | Select-Object -ExpandProperty Hash
        
        if ($HashReal -eq $HashEsperado) {
            $Verificados++
        } else {
            Escribir-Log "HASH NO COINCIDE: $ArchivoAVerificar (esperado: $HashEsperado, obtenido: $HashReal)" "ERROR"
            $Errores++
        }
    }
}

Escribir-Log "Verificación completada: $Verificados archivos verificados, $Errores errores"

if ($Errores -gt 0) {
    exit 1
} else {
    exit 0
}