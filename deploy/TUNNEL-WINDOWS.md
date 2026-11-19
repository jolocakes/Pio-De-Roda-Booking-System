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

## Every session (1 minute)

1. Open **XAMPP Control Panel** → **Start Apache** and **Start MySQL**.
2. In a **new** PowerShell window:

```powershell
cd C:\xampp\htdocs\pdrsystem
powershell -ExecutionPolicy Bypass -File deploy\tunnel-start.ps1 -UpdateXendit
```

3. Wait for the green banner:

```
 PUBLIC URL : https://quiet-river-1234.trycloudflare.com/
 ADMIN      : https://quiet-river-1234.trycloudflare.com/admin/
 WEBHOOK    : https://quiet-river-1234.trycloudflare.com/api/payments/webhook.php
```

4. Leave that window open. **Ctrl+C** stops the tunnel (and the public URL).

The URL is also saved to `deploy\tunnel-url.txt` (git-ignored) so you can copy
it later.

### If the PC sleeps, the site dies

Settings → System → Power → **Screen and sleep** → set *Sleep* to **Never**
while plugged in. Closing a laptop lid also suspends it.

---

## Xendit (payments)

`-UpdateXendit` rewrites `app_base_url` in `config/xendit.php` to the new URL
and keeps a backup in `%TEMP%`. **The webhook URL in the Xendit dashboard must
be updated by hand each session** because the hostname changes:

1. Xendit Dashboard → **Settings → Webhooks** → Invoice callback:
   `https://<the-url-from-the-banner>/api/payments/webhook.php`
2. Click **Send test** and confirm a `200`.
3. Run one test invoice end to end.

If `config/xendit.php` does not exist yet:

```powershell
copy config\xendit.example.php config\xendit.php
notepad config\xendit.php
```

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
| Xendit webhook `401` | `callback_token` mismatch | Re-copy it into `config/xendit.php` |
| Xendit webhook not arriving | Dashboard still has the old URL | Update it to this session's WEBHOOK URL |

Local (non-tunnel) checks:

```powershell
C:\xampp\php\php.exe deploy\check.php
C:\xampp\apache\bin\httpd.exe -S
```
