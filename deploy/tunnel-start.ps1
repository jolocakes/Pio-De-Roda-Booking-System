#Requires -Version 5.1
<#
.SYNOPSIS
    Start a Cloudflare Quick Tunnel for the PDR app and print the public URL.

.DESCRIPTION
    Opens a free *.trycloudflare.com HTTPS URL that forwards to the local
    Apache vhost (http://pdrsystem.local or http://127.0.0.1:80).

    The URL is written to deploy\tunnel-url.txt and printed with the admin and
    Xendit webhook paths. With -UpdateXendit, config/xendit.php app_base_url is
    rewritten to the new URL automatically.

    The tunnel lives only while this window is open. Press Ctrl+C to stop it.

.PARAMETER UpdateXendit
    Rewrite 'app_base_url' in config/xendit.php to the new public URL.

.PARAMETER NoBrowser
    Do not open the public URL in the default browser once it is ready.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\tunnel-start.ps1 -UpdateXendit
#>
[CmdletBinding()]
param(
    [string]$HostName = "pdrsystem.local",
    [switch]$UpdateXendit,
    [switch]$NoBrowser
)

$ErrorActionPreference = "Stop"

$Root    = Split-Path -Parent $PSScriptRoot
$UrlFile = Join-Path $PSScriptRoot "tunnel-url.txt"

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

function Update-XenditConfig([string]$Url) {
    $config = Join-Path $Root "config\xendit.php"
    if (-not (Test-Path $config)) {
        Write-Host "  [!] config/xendit.php not found - payments keep the old app_base_url." -ForegroundColor Yellow
        return
    }

    $content  = Get-Content $config -Raw
    $pattern  = "('app_base_url'\s*=>\s*)'[^']*'"
    $replace  = "`$1'$Url'"
    $updated  = [regex]::Replace($content, $pattern, $replace)

    if ($updated -eq $content) {
        Write-Host "  [!] app_base_url not found in config/xendit.php - update it manually." -ForegroundColor Yellow
        return
    }

    $backup = Join-Path $env:TEMP ("pdr-xendit-" + (Get-Date -Format "yyyyMMdd-HHmmss") + ".php")
    Copy-Item $config $backup -Force
    [IO.File]::WriteAllText($config, $updated, (New-Object System.Text.UTF8Encoding($false)))
    Write-Host "  [OK] config/xendit.php app_base_url updated to $Url" -ForegroundColor Green
    Write-Host "       (backup: $backup)" -ForegroundColor DarkGray
}

$cloudflared = Resolve-Cloudflared
if (-not $cloudflared) {
    Write-Host "cloudflared is not installed. Run deploy\tunnel-setup.ps1 first." -ForegroundColor Red
    exit 1
}

if (-not (Test-LocalPort 80)) {
    Write-Host "Apache is not answering on http://127.0.0.1:80" -ForegroundColor Red
    Write-Host "Start Apache and MySQL in the XAMPP Control Panel, then run this again."
    Start-Process "C:\xampp\xampp-control.exe"
    exit 1
}

# Refuse to publish the XAMPP dashboard by accident.
try {
    $probe = Invoke-WebRequest -Uri "http://127.0.0.1/" -UseBasicParsing -TimeoutSec 10
    if ($probe.Content -match 'XAMPP' -and $probe.Content -notmatch 'Pio De Roda') {
        Write-Host "Apache is serving the XAMPP dashboard, not the app." -ForegroundColor Red
        Write-Host "Restart Apache in the XAMPP Control Panel (Stop > Start) so the"
        Write-Host "pdrsystem.local vhost loads, then run this script again."
        exit 1
    }
} catch {
    # Probe failed for an unrelated reason - let the tunnel start anyway.
}

$origin = "http://127.0.0.1:80"
try {
    $addresses = [System.Net.Dns]::GetHostAddresses($HostName) | ForEach-Object { $_.IPAddressToString }
    if ($addresses -contains "127.0.0.1") { $origin = "http://$HostName" }
} catch {
    # Hosts entry missing: 127.0.0.1 still hits the only vhost.
}

Write-Host "Opening a Cloudflare Quick Tunnel to $origin" -ForegroundColor Cyan
Write-Host "The public URL appears below in a few seconds." -ForegroundColor DarkGray
Write-Host "Keep this window open while the site is in use. Ctrl+C stops it." -ForegroundColor DarkGray
Write-Host ""

$script:publicUrl = $null
$script:handled   = $false

# cloudflared logs everything to stderr. Merging that stream under the default
# Stop preference would abort this script on the first log line, so relax it.
$ErrorActionPreference = "Continue"

& $cloudflared tunnel --no-autoupdate --url $origin 2>&1 | ForEach-Object {
    $line = "$_"
    Write-Host $line

    if (-not $script:publicUrl -and $line -match 'https://[a-z0-9-]+\.trycloudflare\.com') {
        $script:publicUrl = $Matches[0]
    }

    if ($script:publicUrl -and -not $script:handled) {
        $script:handled = $true
        Set-Content -Path $UrlFile -Value $script:publicUrl -Encoding ASCII

        Write-Host ""
        Write-Host ("=" * 72) -ForegroundColor Green
        Write-Host ("  PUBLIC URL : " + $script:publicUrl + "/") -ForegroundColor Green
        Write-Host ("  ADMIN      : " + $script:publicUrl + "/admin/") -ForegroundColor Green
        Write-Host ("  WEBHOOK    : " + $script:publicUrl + "/api/payments/webhook.php") -ForegroundColor Green
        Write-Host ("=" * 72) -ForegroundColor Green
        Write-Host "  Saved to deploy\tunnel-url.txt" -ForegroundColor DarkGray

        if ($UpdateXendit) { Update-XenditConfig $script:publicUrl }

        Write-Host "  For this session, set the Xendit webhook to the WEBHOOK URL above." -ForegroundColor Yellow
        Write-Host ""

        if (-not $NoBrowser) { Start-Process ($script:publicUrl + "/") }
    }
}
