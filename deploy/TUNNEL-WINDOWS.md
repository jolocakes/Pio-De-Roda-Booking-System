# Public demo on Windows + XAMPP (Cloudflare Quick Tunnel)

Exposes your existing local install at a free HTTPS URL, with **no account, no
card, and no Oracle signup**. Everything that works locally keeps working:
Gmail SMTP, Xendit webhooks, sessions, the admin console.

```
Visitors ──HTTPS──> Cloudflare edge ──tunnel──> cloudflared.exe (your PC)
                                                  │
                                     Apache vhost http://pdrsystem.local
                                                  │
                                          C:\xampp\htdocs\pdrsystem
```

**Trade-off:** the site is online only while this PC is on and the tunnel
window is open. That is fine for a defense, a client demo, or a short pilot —
not for a 24/7 cafe.

---

## What you get vs. limits

| | Detail |
|---|---|
| URL | `https://<random-words>.trycloudflare.com` (changes every start) |
| Cost | $0, no Cloudflare account needed |
| Uptime | Only while PC is awake + tunnel window open; no SLA |
| Limits | 200 in-flight requests; no Server-Sent Events (not used by this app) |
| Speed | Your home upload bandwidth |
| Security | Your real visitor IP is restored by `mod_remoteip` (rate limits / audit logs stay correct) |

---

## One-time setup (5 minutes)

Open **PowerShell as Administrator** (needed for the `hosts` entry), then:

```powershell
cd C:\xampp\htdocs\pdrsystem
powershell -ExecutionPolicy Bypass -File deploy\tunnel-setup.ps1
```

The script:

1. Installs `cloudflared` with winget if missing.
2. Adds an Apache vhost so the app is served at `http://pdrsystem.local/`
   (root, exactly like production).
3. Uncomments `mod_remoteip` in `httpd.conf` and trusts `127.0.0.1`, so
   Cloudflare's `CF-Connecting-IP` becomes `REMOTE_ADDR`.
4. Adds `127.0.0.1 pdrsystem.local` to `hosts`.
5. Runs `httpd -t`, restarts Apache, starts the XAMPP Control Panel if needed.
6. Runs `deploy/check.php`.

Backups are written next to the edited files
(`httpd-vhosts.conf.bak-YYYYMMDD-HHMMSS`, `httpd.conf.bak-...`), so this is
reversible.

If the hosts edit fails, add this line manually as Administrator:

```
127.0.0.1    pdrsystem.local
```

Then open `http://pdrsystem.local/` — you should see the homepage, and
`http://pdrsystem.local/admin/` the login.

The hosts entry is **optional**: without it the tunnel uses `127.0.0.1`, which
still reaches the vhost. It only makes local browsing prettier.

> Prefer not to use a vhost? `http://localhost/pdrsystem/` still works. The
> tunnel origin just has to be changed to that path, and the 404 page will not
> resolve (the `.htaccess` path is root-based now).

---

## Every session (2 minutes)

1. Open **XAMPP Control Panel** → **Start Apache** and **Start MySQL**.

2. **Window 1 — the website:**

```powershell
cd C:\xampp\htdocs\pdrsystem
powershell -ExecutionPolicy Bypass -File deploy\tunnel-start.ps1 -UpdateXendit
```

Wait for the green banner:

```
 PUBLIC URL : https://quiet-river-1234.trycloudflare.com/
 ADMIN      : https://quiet-river-1234.trycloudflare.com/admin/
```

3. **Window 2 — the Xendit webhook** (only if you are demoing payments):

```powershell
cd C:\xampp\htdocs\pdrsystem
powershell -ExecutionPolicy Bypass -File deploy\webhook-tunnel.ps1
```

```
========================================================================
  WEBHOOK URL : https://586decdc957d9e.app.lhr.life/api/payments/webhook.php
========================================================================
  [OK] Registered with Xendit (TEST)
  [OK] Callback token matches config/xendit.php
```

4. Leave **both** windows open. **Ctrl+C** in either one stops that tunnel.

The site URL is also saved to `deploy\tunnel-url.txt` (git-ignored).

### Why two tunnels

Xendit refuses `*.trycloudflare.com` (and `*.ngrok-free.app`, `*.loca.lt`)
webhook URLs — the dashboard says *"This webhook URL can not be saved because
the URL may contain private or local IPs"*, and the API returns
`URL_NOT_VALID_ERROR`. It accepts `*.lhr.life`, which
[localhost.run](https://localhost.run) hands out over plain SSH: no account, no
installer, no card.

So the site stays on the fast, stable Cloudflare hostname, and only the webhook
callback travels over localhost.run. Both point at the same Apache site.

### If the PC sleeps, the site dies

Settings → System → Power → **Screen and sleep** → set *Sleep* to **Never**
while plugged in. Closing a laptop lid also suspends it.

---

## Xendit (payments)

`config/xendit.php` needs the **test secret key** (`xnd_development_...`, 78
characters) — not the public key (`xnd_public_development_...`), which is only
for browser components. With the public key in `secret_key`, every call fails
with `401 INVALID_API_KEY` and no invoice can be created.

```powershell
copy config\xendit.example.php config\xendit.php
notepad config\xendit.php
```

Quick auth check (expects `HTTP 200`):

```powershell
C:\xampp\php\php.exe -r "$c = require 'config/xendit.php'; $ch = curl_init('https://api.xendit.co/v2/invoices?limit=1'); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_USERPWD => $c['secret_key'] . ':']); curl_exec($ch); echo PHP_EOL . 'HTTP ' . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;"
```

> Run that from **cmd.exe**, not PowerShell: PowerShell interpolates `$c`
> inside double quotes and PHP receives a broken script.

`deploy/webhook-tunnel.ps1` registers
`https://<id>.app.lhr.life/api/payments/webhook.php` with Xendit automatically
through `POST /callback_urls/invoice`, and warns if the account's callback token
does not match `config/xendit.php`.

Verify the endpoint by hand (a browser GET returns `403` on purpose — the
endpoint requires Xendit's `x-callback-token` header):

```powershell
$tok = (Select-String -Path config\xendit.php -Pattern "'callback_token'\s*=>\s*'([^']+)'").Matches.Groups[1].Value
curl.exe -s -w " [HTTP %{http_code}]`n" -X POST -H "Content-Type: application/json" -H "x-callback-token: $tok" -d "{\"id\":\"test-nonexistent\"}" https://YOUR-ID.app.lhr.life/api/payments/webhook.php
```

Expect `ok [HTTP 200]`; remove the header and it must be `403`.

> Never commit `config/xendit.php` — it is already git-ignored.

---

## Email

Gmail SMTP works through the tunnel because mail is sent from your PC, not from
a hosting provider:

```powershell
C:\xampp\php\php.exe api\mail_test.php
C:\xampp\php\php.exe api\mail_test.php --send
```

If Gmail rejects the login, use a 16-character **App Password**
(<https://myaccount.google.com/apppasswords>), not your normal password.

---

## Turnstile (optional)

The widget validates the requesting hostname, and the tunnel host changes each
session. Either leave the keys blank (challenge disabled) or add the new
`*.trycloudflare.com` host to the widget's allowed domains each time. For demos,
leaving it blank is simpler.

---

## If the browser says `DNS_PROBE_FINISHED_NXDOMAIN`

The tunnel is usually fine — your router or ISP resolver has failed to resolve
the brand-new `*.trycloudflare.com` hostname (or cached a negative answer from
the moment the URL was printed). Check which side is broken:

```powershell
Resolve-DnsName YOUR-URL.trycloudflare.com                      # uses your router
Resolve-DnsName YOUR-URL.trycloudflare.com -Server 1.1.1.1      # public DNS
```

If the second command returns Cloudflare IPs (`104.16.x.x`) and the first says
"DNS name does not exist", the site is publicly reachable and only this PC's
resolver is wrong. Fix it in an **elevated** PowerShell:

```powershell
Set-DnsClientServerAddress -InterfaceAlias 'Ethernet' -ServerAddresses 1.1.1.1,1.0.0.1
Clear-DnsClientCache
Resolve-DnsName YOUR-URL.trycloudflare.com
```

No admin? Enable **Secure DNS** in your browser (Chrome/Edge → Settings →
Privacy → Security → Use secure DNS → Cloudflare). That bypasses the router.
Phones on mobile data and any other network resolve it normally.

> This recurs with every new tunnel URL until the router/ISP DNS is bypassed,
> which is why setting `1.1.1.1` once is worth it.

## Verify the public URL

```powershell
curl.exe -o NUL -s -w 'home   %{http_code}\n' https://YOUR-URL.trycloudflare.com/
curl.exe -o NUL -s -w 'admin  %{http_code}\n' https://YOUR-URL.trycloudflare.com/admin/
curl.exe -o NUL -s -w '.env   %{http_code}\n' https://YOUR-URL.trycloudflare.com/.env
curl.exe -o NUL -s -w 'schema %{http_code}\n' https://YOUR-URL.trycloudflare.com/database/schema.sql
curl.exe -o NUL -s -w 'setup  %{http_code}\n' https://YOUR-URL.trycloudflare.com/create_admin.php
```

Expected: `200`, `200`, `403`, `403`, `403`.

If `.env` returns `200`, **stop the tunnel immediately** and check that
`AllowOverride All` is set for the vhost (the setup script does this) — your
secrets would be public.

---

## Security rules for a tunnel demo

- Use a strong admin password (12+ characters); the whole admin console is now
  on the public internet.
- `.env`, `database/`, `deploy/`, and PHP scripts in those folders are denied by
  `.htaccess`. Verify with the curls above.
- phpMyAdmin is **not** exposed: the vhost's DocumentRoot is the project folder,
  not `C:\xampp\htdocs`.
- No Windows Firewall or router changes are needed — `cloudflared` makes only
  outbound connections. Do not port-forward 80.
- Stop the tunnel when you are done: `Ctrl+C`, or
  ```powershell
  Get-Process cloudflared -ErrorAction SilentlyContinue | Stop-Process
  ```
- For a private demo, restrict access to specific emails (this disables
  automated webhooks):
  ```powershell
  cloudflared tunnel --url http://pdrsystem.local --allowed-mail you@example.com
  ```

---

## Want a stable URL?

**Option 1 — ngrok free static domain.** One free static hostname per account:
`https://yourname.ngrok-free.app`. Browsers first show a one-click warning page.
```powershell
ngrok http 80 --host-header="pdrsystem.local"
```

**Option 2 — a real domain + a named Cloudflare Tunnel** (~$10/year for the
domain, the tunnel itself is free and permanent):
```powershell
cloudflared tunnel login
cloudflared tunnel create pdr
cloudflared tunnel route dns pdr pdr.yourdomain.com
cloudflared tunnel run --url http://127.0.0.1:80 pdr
```
Then install it as a service so it starts with Windows:
```powershell
cloudflared service install
```

**Option 3 — move to a VPS** when the cafe needs genuine 24/7 uptime:
`deploy/DEPLOY-ORACLE.md` works unchanged on any Ubuntu 22.04/24.04 server
(DigitalOcean, GCP e2-micro, Vultr, etc.).

---

## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Cloudflare `502 Bad Gateway` | Apache not running | Start Apache in XAMPP, re-run `tunnel-start.ps1` |
| Cloudflare `1033` | Tunnel process died | Restart `tunnel-start.ps1` |
| XAMPP dashboard instead of the app | vhost not applied | `httpd -t`, then restart Apache; check `httpd -S` lists `pdrsystem.local` |
| `404` on every path | Wrong DocumentRoot | Re-run `tunnel-setup.ps1` |
| `cloudflared` not recognized | PATH not refreshed | Open a new terminal, or use `C:\cloudflared\cloudflared.exe` |
| Script blocked by execution policy | Default policy is Restricted | Prefix with `powershell -ExecutionPolicy Bypass -File` |
| hosts edit failed | Not elevated, or Controlled folder access | Optional; run PowerShell as Administrator if you want the pretty hostname |
| Setup stops right after `Syntax OK` | Old script version treated Apache's stderr as fatal | Pull the latest `deploy/tunnel-setup.ps1` (fixed) |
| MySQL will not start | Port 3306 in use | Stop the other MySQL/Skype service, retry |
| `500` on a page | PHP fatal | `Get-Content C:\xampp\apache\logs\pdrsystem-error.log -Tail 40` |
| Login works but bounces | Stale cookie from `localhost` | Clear cookies for the tunnel host and retry |
| Slow first load | Quick-tunnel cold start | Reload once |
| Browser shows `DNS_PROBE_FINISHED_NXDOMAIN` | Router/ISP DNS cannot resolve new `*.trycloudflare.com` names | Set the PC DNS to `1.1.1.1`, flush, reload (see section above) |
| Xendit webhook `401` | `callback_token` mismatch | Re-copy it into `config/xendit.php` |
| Xendit webhook not arriving | webhook tunnel closed, or localhost.run rotated the hostname | Restart `deploy\webhook-tunnel.ps1` — it re-registers the new URL |
| Xendit rejects the webhook URL ("private or local IPs") | `*.trycloudflare.com` / `*.ngrok-free.app` / `*.loca.lt` are blocked by Xendit | Use `deploy\webhook-tunnel.ps1` (`*.lhr.life`) |
| Xendit returns `401 INVALID_API_KEY` | `secret_key` holds the public key | Use the test **secret** key (`xnd_development_...`) |

Local (non-tunnel) checks:

```powershell
C:\xampp\php\php.exe deploy\check.php
C:\xampp\apache\bin\httpd.exe -S
```
