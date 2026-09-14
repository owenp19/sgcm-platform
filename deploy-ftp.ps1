# Despliegue por FTP a Hostinger: frontend Angular (www/) + backend Laravel (backend/)
#
# Uso:
#   .\deploy-ftp.ps1                    # pide la contrasena y sube frontend + backend (solo cambios)
#   .\deploy-ftp.ps1 -SoloCambios       # solo sube archivos cuyo tamano cambio (por defecto)
#   .\deploy-ftp.ps1 -Todo              # sube todos los archivos sin comparar
#   .\deploy-ftp.ps1 -SoloFrontend      # solo el frontend
#   .\deploy-ftp.ps1 -SoloBackend       # solo el backend
#   $env:FTP_PASS='xxx'; .\deploy-ftp.ps1   # contrasena por variable de entorno
#
# Seguridad: NUNCA se sube backend/.env ni storage/ ni vendor/ del backend.

param(
  [string]$FtpUser = $(if ($env:FTP_USER) { $env:FTP_USER } else { "u435094567.lightslategray-loris-726565.hostingersite.com" }),
  [string]$FtpPass = "",
  [string]$FtpHost = "82.29.157.100",
  [int]$FtpPort = 21,
  # La prueba con archivos sonda demostro que el dominio sirve desde la RAIZ del FTP,
  # NO desde /public_html (esa carpeta es una copia muerta dentro del docroot).
  [string]$RemoteRoot = "",
  [switch]$SoloFrontend,
  [switch]$SoloBackend,
  [switch]$IncluirVendor,
  [switch]$Todo,
  [switch]$WhatIf
)

$ErrorActionPreference = "Stop"

# Contrasena: parametro -> variable de entorno -> prompt seguro
if (-not $FtpPass -and $env:FTP_PASS) { $FtpPass = $env:FTP_PASS }
if (-not $FtpPass) {
    $sec = Read-Host -Prompt "Contrasena FTP para $FtpUser" -AsSecureString
    $bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($sec)
    $FtpPass = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}

$baseUri = "ftp://${FtpHost}:${FtpPort}"
$script:Failed = @()
$script:Uploaded = 0
$script:Skipped = 0
$script:DirsCreated = @{}
[System.Net.ServicePointManager]::DefaultConnectionLimit = 24

function New-FtpRequest([string]$method, [string]$remotePath) {
    $req = [System.Net.FtpWebRequest]::Create("$baseUri$remotePath")
    $req.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
    $req.Method = $method
    $req.UseBinary = $true
    $req.UsePassive = $true
    $req.KeepAlive = $true
    $req.Timeout = 30000
    return $req
}

function Test-FtpLogin {
    try {
        $req = New-FtpRequest ([System.Net.WebRequestMethods+Ftp]::ListDirectory) "/"
        $resp = $req.GetResponse()
        Write-Host "[OK] Login FTP correcto como $FtpUser" -ForegroundColor Green
        $resp.Close()
        return $true
    }
    catch {
        Write-Host "[ERROR] Login FTP fallido: $($_.Exception.Message)" -ForegroundColor Red
        return $false
    }
}

function Ensure-FtpDirectory([string]$remotePath) {
    if ($script:DirsCreated.ContainsKey($remotePath)) { return }
    $parts = $remotePath.Trim("/") -split "/" | Where-Object { -not [string]::IsNullOrEmpty($_) }
    $current = ""
    foreach ($p in $parts) {
        $current += "/$p"
        if ($script:DirsCreated.ContainsKey($current)) { continue }
        try {
            $resp = New-FtpRequest ([System.Net.WebRequestMethods+Ftp]::MakeDirectory) $current
            $resp.GetResponse().Close() | Out-Null
        }
        catch [System.Net.WebException] {
            if ($_.Exception.Response) { $_.Exception.Response.Close() }  # libera la conexion
            $code = $null
            try { $code = [int]$_.Exception.Response.StatusCode } catch { }
            if ($code -ne 550) { throw }
        }
        $script:DirsCreated[$current] = $true
    }
}

function Get-FtpFileSize([string]$remotePath) {
    try {
        $req = New-FtpRequest ([System.Net.WebRequestMethods+Ftp]::GetFileSize) $remotePath
        $resp = $req.GetResponse()
        $len = $resp.ContentLength
        $resp.Close()
        return $len
    }
    catch { return -1 }
}

# Mapa nombre->tamano por carpeta remota, en UNA peticion LIST por carpeta
$script:RemoteDirCache = @{}
function Get-RemoteSizes([string]$dir) {
    if ($script:RemoteDirCache.ContainsKey($dir)) { return $script:RemoteDirCache[$dir] }
    $map = @{}
    try {
        $req = New-FtpRequest ([System.Net.WebRequestMethods+Ftp]::ListDirectoryDetails) ($dir.TrimEnd("/") + "/")
        $resp = $req.GetResponse()
        $sr = New-Object System.IO.StreamReader($resp.GetResponseStream())
        while ($null -ne ($line = $sr.ReadLine())) {
            if ([string]::IsNullOrWhiteSpace($line)) { continue }
            # formato LIST unix: perms links owner group size mes dia hora|anio nombre...
            $parts = $line -split "\s+", 8
            if ($parts.Count -ge 8 -and $parts[0].StartsWith("-")) {
                $size64 = 0L
                if ([long]::TryParse($parts[4], [ref]$size64)) { $map[$parts[7]] = $size64 }
            }
        }
        $sr.Close()
        $resp.Close()
    }
    catch { }
    $script:RemoteDirCache[$dir] = $map
    return $map
}

function Test-Excluded([string]$rel, [string[]]$patterns) {
    foreach ($pat in $patterns) {
        if ($rel -match $pat) { return $true }
    }
    return $false
}

function Invoke-Sync([string]$label, [string]$localDir, [string]$remoteBase, [string[]]$exclusions) {
    if (-not (Test-Path -LiteralPath "$localDir")) {
        Write-Host "[AVISO] $label : no existe $localDir, se omite" -ForegroundColor Yellow
        return
    }

    $files = Get-ChildItem -LiteralPath $localDir -Recurse -File | Where-Object {
        $relPath = $_.FullName.Substring($localDir.Length).Replace("\", "/").TrimStart("/")
        -not (Test-Excluded -rel $relPath -patterns $exclusions)
    }

    Write-Host "`n=== $label : $($files.Count) archivos ===" -ForegroundColor Cyan
    $i = 0
    foreach ($f in $files) {
        $i++
        $rel = $f.FullName.Substring($localDir.Length).Replace("\", "/")
        $remotePath = "$remoteBase$rel"
        try {
            if (-not $WhatIf) {
                $remoteDir = $remotePath.Substring(0, $remotePath.LastIndexOf("/"))
                Ensure-FtpDirectory $remoteDir

                # comparar tamano con el listado cacheado de la carpeta remota
                $sizes = Get-RemoteSizes $remoteDir
                $name = ($remotePath -split "/")[-1]
                if (-not $Todo -and $sizes.ContainsKey($name) -and $sizes[$name] -eq $f.Length) {
                    $script:Skipped++
                    continue
                }

                $req = New-FtpRequest ([System.Net.WebRequestMethods+Ftp]::UploadFile) $remotePath
                $bytes = [System.IO.File]::ReadAllBytes($f.FullName)
                $stream = $req.GetRequestStream()
                $stream.Write($bytes, 0, $bytes.Length)
                $stream.Close()
                $req.GetResponse().Close() | Out-Null
                $script:Uploaded++
                Write-Host ("  [{0}/{1}] {2}" -f $i, $files.Count, $rel.TrimStart("/")) -ForegroundColor DarkGray
            }
        }
        catch {
            if ($_.Exception.Response) { try { $_.Exception.Response.Close() } catch { } }
            Write-Host ("  FALLO {0}: {1}" -f $rel.TrimStart("/"), $_.Exception.Message) -ForegroundColor Red
            $script:Failed += "${label}: $($rel.TrimStart('/'))"
        }
    }
}

if (-not (Test-FtpLogin)) { exit 1 }

# ---------- FRONTEND ----------
if (-not $SoloBackend) {
    if (-not (Test-Path -LiteralPath "$PSScriptRoot\www\index.html")) {
        throw "No existe www\index.html. Ejecuta primero: npm run build"
    }
    Invoke-Sync -label "FRONTEND (www/)" -localDir "$PSScriptRoot\www" -remoteBase $RemoteRoot -exclusions @("^\.git")
}

# ---------- BACKEND ----------
if (-not $SoloFrontend) {
    $backendExclusions = @(
        "^\.env",                 # credenciales de produccion (nunca sobreescribir)
        "^storage/",              # logs, cache y documentos subidos en produccion
        "^node_modules/",
        "^tests/"
    )
    if (-not $IncluirVendor) { $backendExclusions += "^vendor/" }

    Invoke-Sync -label "BACKEND (backend/ -> api/)" `
        -localDir "$PSScriptRoot\backend" `
        -remoteBase "$RemoteRoot/api" `
        -exclusions $backendExclusions
}

# ---------- Resumen ----------
if ($WhatIf) {
    Write-Host "`nDry-run completado." -ForegroundColor Cyan
    exit 0
}

if ($script:Failed.Count -eq 0) {
    Write-Host "`n[OK] Despliegue completado: $($script:Uploaded) subidos, $($script:Skipped) sin cambios." -ForegroundColor Green
} else {
    Write-Host "`n[AVISO] Completado con $($script:Failed.Count) fallos:" -ForegroundColor Yellow
    $script:Failed | ForEach-Object { Write-Host "  - $_" -ForegroundColor Yellow }
    exit 1
}
