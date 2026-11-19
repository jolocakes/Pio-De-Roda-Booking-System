<#
.SYNOPSIS
    Public HTTPS tunnel for Xendit webhooks, using localhost.run (no account).

.DESCRIPTION
    Xendit refuses webhook URLs on *.trycloudflare.com and *.ngrok-free.app with
    "This webhook URL can not be saved because the URL may contain private or
    local IPs" (the API says URL_NOT_VALID_ERROR). Their validator accepts
    *.lhr.life, which localhost.run provides over plain SSH - no account, no
    installer, no credit card.

    This starts a reverse SSH tunnel to the local Apache site, prints the public
    webhook URL, and registers it with Xendit through the API using
    config/xendit.php. Run it next to deploy/tunnel-start.ps1 and keep both
    windows open.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\webhook-tunnel.ps1

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File deploy\webhook-tunnel.ps1 -NoXendit
#>
[CmdletBinding()]
param(
    [string]$HostName = "pdrsystem.local",
    [int]$LocalPort = 80,
    [switch]$NoXendit
)

$ErrorActionPreference = "Stop"
$ProgressPreference    = "SilentlyContinue"
$root     = Split-Path -Parent $PSScriptRoot
$xendit   = Join-Path $root "config\xendit.php"

function Step([string]$text) {
    Write-Host ""
    Write-Host "==> $text" -ForegroundColor Cyan
}
function Ok([string]$text)   { Write-Host "  [OK] $text" -ForegroundColor Green }
function Warn([string]$text) { Write-Host "  [!] $text"  -ForegroundColor Yellow }
function Err([string]$text)  { Write-Host "  [X] $text"  -ForegroundColor Red }

function Get-XenditKey {
    if (-not (Test-Path $xendit)) { return $null }
    $raw = Get-Content $xendit -Raw
    $m = [regex]::Match($raw, "'secret_key'\s*=>\s*'([^']*)'")
    if (-not $m.Success) { return $null }
    $key = $m.Groups[1].Value
    if ([string]::IsNullOrWhiteSpace($key) -or $key -like '*REPLACE*') { return $null }
    return $key
}

function Get-XenditToken {
    if (-not (Test-Path $xendit)) { return "" }
    $raw = Get-Content $xendit -Raw
    $m = [regex]::Match($raw, "'callback_token'\s*=>\s*'([^']*)'")
    if ($m.Success) { return $m.Groups[1].Value }
    return ""
}

function Set-XenditWebhook([string]$url, [string]$key) {
    $body = @{ url = $url } | ConvertTo-Json -Compress
    $auth = [Convert]::ToBase64String([Text.Encoding]::ASCII.GetBytes($key + ":"))
    try {
        return Invoke-RestMethod -Uri "https://api.xendit.co/callback_urls/invoice" `
            -Method Post -Headers @{ Authorization = "Basic $auth" } `
            -ContentType "application/json" -Body $body -TimeoutSec 40
    } catch {
        $detail = $_.Exception.Message
        if ($_.Exception.Response) {
            try {
                $reader = New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())
                $detail = $reader.ReadToEnd()
            } catch { }
        }
        throw "Xendit rejected the webhook registration: $detail"
    }
}

Step "Checking the SSH client"
$ssh = (Get-Command ssh.exe -ErrorAction SilentlyContinue).Source
if (-not $ssh) {
    Err "ssh.exe was not found. Install the Windows 'OpenSSH Client' optional feature."
    exit 1
}
Ok $ssh

Step "Checking Apache on port $LocalPort"
# A raw socket probe: Test-NetConnection renders "Attempting TCP connect /
# Waiting for response" progress output on Windows PowerShell 5.1, which looks
# like a hang even though it finishes instantly on localhost.
$listening = $false
$probe = New-Object System.Net.Sockets.TcpClient
try {
    $pending = $probe.BeginConnect("127.0.0.1", $LocalPort, $null, $null)
    if ($pending.AsyncWaitHandle.WaitOne(1500)) {
        $probe.EndConnect($pending)
        $listening = $true
    }
} catch {
    $listening = $false
} finally {
    $probe.Close()
}
if (-not $listening) {
    Err "Nothing is listening on 127.0.0.1:$LocalPort. Start Apache in the XAMPP Control Panel."
    exit 1
}
Ok "Apache is up"

$xenditKey = $null
if (-not $NoXendit) {
    Step "Reading Xendit credentials"
    $xenditKey = Get-XenditKey
    if (-not $xenditKey) {
        Warn "config/xendit.php is missing or has no secret key - the tunnel will start but Xendit will not be updated."
        Write-Host "    Copy config\xendit.example.php to config\xendit.php and add your TEST secret key."
    } elseif ($xenditKey -like 'xnd_public_*') {
        Warn "secret_key holds a PUBLIC key (xnd_public_...). Use the TEST secret key instead."
        $xenditKey = $null
    } else {
        Ok "secret key loaded from $xendit"
    }
}

Write-Host ""
Write-Host "Opening a localhost.run tunnel to http://127.0.0.1:$LocalPort" -ForegroundColor White
Write-Host "Keep this window open while the site is in use. Ctrl+C stops it." -ForegroundColor White

$sshArgs = @(
    "-T",
    "-o", "StrictHostKeyChecking=accept-new",
    "-o", "ServerAliveInterval=30",
    "-o", "ExitOnForwardFailure=yes",
    "-R", "$LocalPort`:127.0.0.1:$LocalPort",
    "nokey@localhost.run"
)

$script:webhookDone = $false

# cmd.exe merges the stderr stream into plain text. Piping `2>&1` inside
# PowerShell would turn every ssh log line into an ErrorRecord and print
# "System.Management.Automation.RemoteException" noise.
$cmdLine = "`"$ssh`" " + ($sshArgs -join " ") + " 2>&1"

& cmd /c $cmdLine | ForEach-Object {
    $line = ([string]$_ -replace "\x1B\[[0-9;?]*[a-zA-Z]", "").TrimEnd()

    if (-not $script:webhookDone -and $line -match 'https://([a-z0-9.-]+\.lhr\.life)') {
        $script:webhookDone = $true
        $publicUrl = "https://" + $Matches[1]
        $webhookUrl = "$publicUrl/api/payments/webhook.php"

        Write-Host ""
        Write-Host "========================================================================" -ForegroundColor Green
        Write-Host "  WEBHOOK URL : $webhookUrl" -ForegroundColor Green
        Write-Host "========================================================================" -ForegroundColor Green

        if ($xenditKey) {
            try {
                $result = Set-XenditWebhook $webhookUrl $xenditKey
                if ($result.status -eq "SUCCESSFUL") {
                    Ok "Registered with Xendit ($($result.environment))"
                    $cfgToken = Get-XenditToken
                    $apiToken = [string]$result.callback_token
                    if ($cfgToken -and $apiToken -eq $cfgToken) {
                        Ok "Callback token matches config/xendit.php"
                    } elseif ($cfgToken) {
                        Warn "Xendit's callback token differs from config/xendit.php."
                        Write-Host "    Copy this into callback_token: $apiToken" -ForegroundColor Yellow
                    }
                } else {
                    Warn "Unexpected response: $($result | ConvertTo-Json -Compress)"
                }
            } catch {
                Warn $_.Exception.Message
                Write-Host "    Set it by hand in the Xendit dashboard: $webhookUrl" -ForegroundColor Yellow
            }
        }

        Write-Host ""
        Write-Host "  This hostname rotates when localhost.run rotates it. If webhooks" -ForegroundColor Yellow
        Write-Host "  stop arriving, restart this script - it re-registers the new URL." -ForegroundColor Yellow
        Write-Host ""
    }

    # Skip the QR block (whitespace only) but keep real output.
    if ($line -match '\S') { Write-Host $line }
}
