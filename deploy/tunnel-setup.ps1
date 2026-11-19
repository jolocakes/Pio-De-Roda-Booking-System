#Requires -Version 5.1
<#
.SYNOPSIS
    One-time setup for exposing the PDR app through a Cloudflare Quick Tunnel.

.DESCRIPTION
    Prepares Windows + XAMPP so the project is served at http://pdrsystem.local
    (root of the vhost, exactly like production) and can be tunnelled:

      - installs cloudflared with winget when it is missing
      - adds an Apache vhost for pdrsystem.local
      - enables mod_remoteip so rate limits and audit logs see the real visitor
        IP from Cloudflare's CF-Connecting-IP header
      - adds the hosts entry (needs an elevated shell)
      - checks the Apache config
      - opens the XAMPP Control Panel if Apache/MySQL are not running
      - runs deploy/check.php

    Safe to re-run. Backups of edited config files are kept next to them.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\tunnel-setup.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\tunnel-setup.ps1 -SkipCheck
#>
[CmdletBinding()]
param(
    [string]$HostName = "pdrsystem.local",
    [switch]$SkipCheck
)

$ErrorActionPreference = "Stop"

$Root        = Split-Path -Parent $PSScriptRoot
$XamppRoot   = "C:\xampp"
$VhostsFile  = Join-Path $XamppRoot "apache\conf\extra\httpd-vhosts.conf"
$HttpdConf   = Join-Path $XamppRoot "apache\conf\httpd.conf"
$HttpdExe    = Join-Path $XamppRoot "apache\bin\httpd.exe"
$HostsFile   = Join-Path $env:WINDIR "System32\drivers\etc\hosts"
$PhpExe      = Join-Path $XamppRoot "php\php.exe"
$Marker      = "# PDR Booking System - Cloudflare Tunnel vhost (managed by deploy/tunnel-setup.ps1)"

function Step($message) { Write-Host "`n==> $message" -ForegroundColor Cyan }
function Ok($message)   { Write-Host "  [OK] $message" -ForegroundColor Green }
function Warn($message) { Write-Host "  [!] $message" -ForegroundColor Yellow }
function Err($message)  { Write-Host "  [x] $message" -ForegroundColor Red }

function Test-LocalPort([int]$Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect("127.0.0.1", $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne(800)) { return $false }
        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Resolve-Cloudflared {
    $cmd = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($cmd) { return $cmd.Source }
    $candidates = @(
        (Join-Path $env:ProgramFiles "cloudflared\cloudflared.exe"),
        (Join-Path $env:LOCALAPPDATA "Microsoft\WinGet\Links\cloudflared.exe"),
        "C:\cloudflared\cloudflared.exe"
    )
    if (${env:ProgramFiles(x86)}) {
        $candidates += (Join-Path ${env:ProgramFiles(x86)} "cloudflared\cloudflared.exe")
    }
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) { return $candidate }
    }
    return $null
}

Step "Checking the project"
if (-not (Test-Path (Join-Path $Root "index.php"))) {
    Err "index.php not found in $Root."
    Write-Host "    Run this script from the project's deploy folder."
    exit 1
}
Ok "Project root: $Root"
$RootApache = $Root -replace '\\', '/'

Step "Checking cloudflared"
$cloudflared = Resolve-Cloudflared
if (-not $cloudflared) {
    Warn "cloudflared is not installed - installing with winget"
    try {
        winget install --id Cloudflare.cloudflared --accept-source-agreements --accept-package-agreements --silent | Out-Host
    } catch {
        Warn "winget install failed: $($_.Exception.Message)"
    }
    $cloudflared = Resolve-Cloudflared
}
if (-not $cloudflared) {
    Err "Could not install cloudflared automatically."
    Write-Host "    Download cloudflared-windows-amd64.exe from"
    Write-Host "    https://github.com/cloudflare/cloudflared/releases/latest"
    Write-Host "    save it as C:\cloudflared\cloudflared.exe, then re-run this script."
    exit 1
}
Ok "cloudflared: $cloudflared"

Step "Configuring the Apache vhost ($HostName)"
$vhost = @"
$Marker
<VirtualHost *:80>
    ServerName $HostName
    DocumentRoot "$RootApache"

    <Directory "$RootApache">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php index.html
    </Directory>

    RemoteIPHeader CF-Connecting-IP
    RemoteIPTrustedProxy 127.0.0.1
    RemoteIPTrustedProxy ::1

    ErrorLog "logs/pdrsystem-error.log"
    CustomLog "logs/pdrsystem-access.log" combined
</VirtualHost>
"@

if (-not (Test-Path $VhostsFile)) {
    Err "Missing $VhostsFile - is XAMPP installed at C:\xampp?"
    exit 1
}
$vhostContent = Get-Content $VhostsFile -Raw
if ($vhostContent -match [regex]::Escape($Marker)) {
    Ok "vhost already present"
} else {
    $stamp = Get-Date -Format "yyyyMMdd-HHmmss"
    Copy-Item $VhostsFile "$VhostsFile.bak-$stamp"
    Add-Content -Path $VhostsFile -Value "`r`n$vhost"
    Ok "vhost appended (backup: $VhostsFile.bak-$stamp)"
}

Step "Enabling mod_remoteip"
$httpd = Get-Content $HttpdConf -Raw
if ($httpd -match '(?m)^LoadModule remoteip_module') {
    Ok "mod_remoteip already enabled"
} else {
    try {
        $updated = $httpd -replace '(?m)^#LoadModule remoteip_module modules/mod_remoteip\.so', 'LoadModule remoteip_module modules/mod_remoteip.so'
        if ($updated -eq $httpd) {
            Warn "Could not find the remoteip LoadModule line in httpd.conf"
        } else {
            Copy-Item $HttpdConf "$HttpdConf.bak-$(Get-Date -Format yyyyMMdd-HHmmss)"
            [IO.File]::WriteAllText($HttpdConf, $updated, (New-Object System.Text.UTF8Encoding($false)))
            Ok "mod_remoteip enabled"
        }
    } catch {
        Warn "Could not edit httpd.conf. Add this line manually and restart Apache:"
        Write-Host "    LoadModule remoteip_module modules/mod_remoteip.so"
    }
}

Step "Adding $HostName to the hosts file"
$isAdmin = ([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
try {
    $hosts = Get-Content $HostsFile -Raw -ErrorAction Stop
    $pattern = "(?m)^\s*127\.0\.0\.1\s+.*\b" + [regex]::Escape($HostName) + "\b"
    if ($hosts -match $pattern) {
        Ok "hosts entry already present"
    } else {
        $item = Get-Item $HostsFile
        if ($item.Attributes -band [IO.FileAttributes]::ReadOnly) {
            $item.Attributes = $item.Attributes -bxor [IO.FileAttributes]::ReadOnly
        }
        Add-Content -Path $HostsFile -Value "`r`n127.0.0.1`t$HostName"
        Ok "hosts entry added"
    }
} catch {
    if (-not $isAdmin) {
        Warn "Not elevated, so hosts was left alone. Re-run this from 'PowerShell (Admin)' to add it."
    } else {
        Warn "Could not write the hosts file even as Administrator."
        Write-Host "    Windows Security > Virus & threat protection > Ransomware protection"
        Write-Host "    > Controlled folder access may be blocking it. Allow PowerShell, or add this line manually:"
    }
    Write-Host "    127.0.0.1`t$HostName"
    Write-Host "    (Optional: the tunnel works without it via 127.0.0.1.)"
}

Step "Testing the Apache configuration"
# cmd.exe does the 2>&1 merge: Apache writes progress to stderr, and letting
# PowerShell merge it would raise NativeCommandError under Stop preference.
$apacheTest     = & cmd /c "`"$HttpdExe`" -t 2>&1"
$apacheTestExit = $LASTEXITCODE
$apacheTest | ForEach-Object { Write-Host "  $_" }
if ($apacheTestExit -ne 0) {
    Err "Apache rejected the configuration. Fix the errors above before continuing."
    exit 1
}
Ok "Apache configuration is valid"

Step "Checking XAMPP services"
$apacheUp = Test-LocalPort 80
$mysqlUp  = Test-LocalPort 3306

if ($apacheUp) {
    # XAMPP normally runs Apache in console mode, where `httpd -k restart` fails
    # with "No installed service named Apache2.4". Only a real service can be
    # restarted from the command line.
    $apacheService = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($apacheService) {
        try {
            Restart-Service $apacheService -Force
            Ok "Apache service restarted"
        } catch {
            Warn "Could not restart the Apache service - use the XAMPP Control Panel"
        }
    } else {
        Warn "Apache is in console mode (started from the XAMPP Control Panel)"
        Write-Host "    Restart it once now so httpd-vhosts.conf and mod_remoteip load:"
        Write-Host "    XAMPP Control Panel > Stop (Apache) > Start (Apache)"
    }
}

if (-not $apacheUp -or -not $mysqlUp) {
    Warn "Apache and/or MySQL are not running"
    Write-Host "    Opening the XAMPP Control Panel - click Start for Apache and MySQL."
    Start-Process (Join-Path $XamppRoot "xampp-control.exe")
    Write-Host "    Waiting up to 60 seconds..."
    for ($i = 0; $i -lt 30; $i++) {
        Start-Sleep -Seconds 2
        $apacheUp = Test-LocalPort 80
        $mysqlUp  = Test-LocalPort 3306
        if ($apacheUp -and $mysqlUp) { break }
    }
}

if ($apacheUp) { Ok "Apache is listening on port 80" } else { Warn "Apache is still down" }
if ($mysqlUp)  { Ok "MySQL is listening on port 3306" }  else { Warn "MySQL is still down" }

Step "Verifying the local site"
$verifyUrl = "http://$HostName/"
$hostResolves = $false
try {
    [void][System.Net.Dns]::GetHostAddresses($HostName)
    $hostResolves = $true
} catch {
    # No hosts entry: 127.0.0.1 still reaches the first (and only) vhost.
    $verifyUrl = "http://127.0.0.1/"
}

if ($apacheUp) {
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        try {
            $response = Invoke-WebRequest -Uri $verifyUrl -UseBasicParsing -TimeoutSec 12
            if ($response.Content -match 'Pio De Roda|PDR') {
                Ok "The app is served at $verifyUrl"
                break
            }
            if ($response.Content -match 'XAMPP') {
                Warn "Apache is still serving the XAMPP dashboard at $verifyUrl"
                Write-Host "    The new vhost has not been loaded yet."
                Write-Host "    XAMPP Control Panel > Stop (Apache) > Start (Apache), then re-run this script."
            } else {
                Warn "Got HTTP $($response.StatusCode) but the page does not look like the app"
                Write-Host "    Check the vhost in $VhostsFile"
            }
            break
        } catch {
            if ($attempt -eq 3) {
                Warn "Could not load $verifyUrl - $($_.Exception.Message)"
            } else {
                Start-Sleep -Seconds 2
            }
        }
    }
}

if (-not $SkipCheck -and (Test-Path $PhpExe) -and $mysqlUp) {
    Step "Running the application self-check"
    & $PhpExe (Join-Path $Root "deploy\check.php")
    if ($LASTEXITCODE -ne 0) {
        Warn "check.php reported problems - read the output above"
    }
}

Step "Setup complete"
Write-Host "  Local site : http://$HostName/"
Write-Host "  Admin      : http://$HostName/admin/"
Write-Host ""
Write-Host "  Start the public tunnel:"
Write-Host "    powershell -ExecutionPolicy Bypass -File `"$PSScriptRoot\tunnel-start.ps1`""
Write-Host ""
Write-Host "  Add -UpdateXendit to also rewrite config/xendit.php app_base_url."
