# Deploying PDR to Oracle Cloud Always Free

Step-by-step runbook for putting the Pio De Roda booking system on a real,
public, HTTPS server at **$0/month**.

- **Time:** 60–90 minutes, mostly waiting for package installs.
- **You need:** an Oracle Cloud account (credit card for identity verification,
  it is not charged on Always Free), SSH access, and optionally a free
  subdomain from DuckDNS.
- **Result:** `https://your-name.duckdns.org` serving the site, the admin
  console, and the Xendit webhook endpoint.

Everything below was written against this repo (PHP 8.3 / MariaDB 10.11 on
Ubuntu 24.04). Replace `shop.duckdns.org` with your own domain everywhere.

---

## Phase 0 — Prepare the deploy kit (5 min, on your PC)

The deployment files live in `deploy/` and are blocked from HTTP by
`deploy/.htaccess`.

1. Review what was added:

   ```powershell
   git status
   ```

2. Commit and push so the server can clone them:

   ```powershell
   git add deploy .gitattributes .htaccess
   git commit -m "Add Oracle Cloud deployment kit"
   git push
   ```

3. Export your live local database (do this even if you plan a fresh install —
   it is your only copy of real bookings):

   ```powershell
   C:\xampp\mysql\bin\mysqldump.exe -u root --single-transaction --default-character-set=utf8mb4 pdrsystem > C:\Users\Julo\pdrsystem-export.sql
   ```

   Check the file is not empty: `Get-Item C:\Users\Julo\pdrsystem-export.sql`

> **Skipping git?** Create an archive that excludes secrets instead:
> ```powershell
> tar -czf pdrsystem-deploy.tar.gz admin api config customer database deploy lib index.php .htaccess .gitattributes favicon.svg robots.txt
> ```
> Then upload it with `scp` in Phase 3.

---

## Phase 1 — Create the server (10 min)

1. Sign in to <https://cloud.oracle.com> → **Compute → Instances → Create instance**.
2. Name: `pdr-web`. Compartment: your default.
3. **Image and shape → Edit**
   - Image: **Canonical Ubuntu 24.04**
   - Shape: **Ampere → VM.Standard.A1.Flex**, 1 OCPU / 6 GB is plenty
     (the free allowance is up to 4 OCPU / 24 GB).
   - If ARM is unavailable ("out of capacity"), use
     **VM.Standard.E2.1.Micro** (Always Free, x86).
4. **Networking:** keep the default VCN, and make sure
   **Assign a public IPv4 address** is set to **Yes**.
5. **Add SSH keys:** choose *Generate a key pair for me* and **download the
   private key** (`ssh-key-....key`). You cannot download it later.
6. **Boot volume:** leave the defaults (Always Free includes 200 GB total).
7. Click **Create**. Wait for the state to become **Running**.
8. Copy the **Public IP address** from the instance page.

Optional but recommended: **Instance details → Resources → Attached block
volumes / Public IP → Edit → Reserved public IP**. An ephemeral IP can change
if the instance is stopped and started, which breaks DNS.

---

## Phase 2 — Open ports 80 and 443 (5 min)

Two firewalls exist; both must allow the traffic.

### 2a. Oracle VCN (cloud firewall)

**Networking → Virtual Cloud Networks → your VCN → Security Lists → Default
Security List → Add Ingress Rules** (twice):

| Stateless | Source Type | Source CIDR | IP Protocol | Destination Port Range |
|---|---|---|---|---|
| No | CIDR | `0.0.0.0/0` | TCP | `80` |
| No | CIDR | `0.0.0.0/0` | TCP | `443` |

### 2b. Server firewall (handled by the setup script)

`deploy/oracle-setup.sh` inserts the iptables rules and saves them. If you
install things manually instead, run:

```bash
sudo iptables -I INPUT 1 -p tcp --dport 80 -m state --state NEW -j ACCEPT
sudo iptables -I INPUT 1 -p tcp --dport 443 -m state --state NEW -j ACCEPT
sudo mkdir -p /etc/iptables && sudo iptables-save | sudo tee /etc/iptables/rules.v4
```

### 2c. Test SSH from Windows

```powershell
ssh -i C:\Users\Julo\Downloads\ssh-key-2026-11-18.key ubuntu@<PUBLIC_IP>
```

If SSH complains about key permissions:

```powershell
icacls C:\Users\Julo\Downloads\ssh-key-2026-11-18.key /inheritance:r /grant:r "$($env:USERNAME):(R)"
```

---

## Phase 3 — Get the code onto the server (5 min)

SSH into the instance (all remaining commands run there, as `ubuntu`).

### Option A — git clone (recommended)

```bash
sudo mkdir -p /var/www/pdrsystem
sudo chown ubuntu:ubuntu /var/www/pdrsystem
git clone https://github.com/jolocakes/Pio-De-Roda-Booking-System.git /var/www/pdrsystem
```

Private repo? Use a fine-grained PAT in the URL or upload via Option B.

### Option B — archive upload

```powershell
# on Windows
scp -i C:\Users\Julo\Downloads\ssh-key-2026-11-18.key C:\Users\Julo\pdrsystem-deploy.tar.gz ubuntu@<PUBLIC_IP>:~/
scp -i C:\Users\Julo\Downloads\ssh-key-2026-11-18.key C:\Users\Julo\pdrsystem-export.sql ubuntu@<PUBLIC_IP>:~/
```

```bash
# on the server
sudo mkdir -p /var/www/pdrsystem
sudo tar -xzf ~/pdrsystem-deploy.tar.gz -C /var/www/pdrsystem
sudo mv ~/pdrsystem-export.sql /root/pdrsystem-export.sql
sudo chown -R ubuntu:ubuntu /var/www/pdrsystem
```

### Verify the shell script has LF line endings

If you uploaded a ZIP or a git checkout with CRLF, bash fails with
`$'\r': command not found`. Fix it with:

```bash
sudo sed -i 's/\r$//' /var/www/pdrsystem/deploy/*.sh
```

---

## Phase 4 — Run the bootstrap script (10 min)

```bash
cd /var/www/pdrsystem
sudo bash deploy/oracle-setup.sh shop.duckdns.org
```

The script is idempotent and does all of this:

| Step | Detail |
|---|---|
| Packages | Apache, MariaDB, PHP 8.3 + `pdo_mysql/curl/mbstring/xml/zip`, Certbot |
| Apache | enables `rewrite`, `headers`, `deflate`, `expires`, `mpm_prefork`, `php` |
| Database | creates `pdrsystem` + user `pdr`, random password, stored in `/root/pdr-credentials.txt` |
| `.env` | copies `.env.example`, fills DB credentials, generates `PDR_LOOKUP_SECRET` |
| Import | imports `/root/pdrsystem-export.sql` if present, otherwise `schema.sql` + `seed_catalog.php` |
| vhost | writes `/etc/apache2/sites-available/pdrsystem.conf` for your domain |
| PHP | `display_errors=Off`, `expose_php=Off`, secure session cookie flags |
| Permissions | `www-data` ownership, `.env` 640, outbox writable |
| Firewall | opens 80/443 in iptables and saves the rules |

It prints the domain, DB credentials, and the remaining steps. **Do not copy
the database password anywhere public** — it is in `/root/pdr-credentials.txt`
(root-only, 600).

Sanity check from your Windows machine:

```powershell
curl.exe -I http://shop.duckdns.org
```

A `200` or `302` is good. A timeout means Phase 2 is incomplete.

---

## Phase 5 — Domain and HTTPS (10 min)

### If you do not own a domain: DuckDNS (free)

1. Go to <https://www.duckdns.org>, sign in with GitHub/Google.
2. Create a subdomain, e.g. `pdr-cafe`, and set **current ip** to your Oracle
   public IP. Click **update ip**.
3. Verify from Windows: `nslookup pdr-cafe.duckdns.org` → your server IP.

> Instant alternative: `https://<PUBLIC-IP>.nip.io` requires no signup and
> resolves to the IP, but DuckDNS is more stable for a real site.

### Issue the certificate and force HTTPS

```bash
sudo certbot --apache -d shop.duckdns.org --redirect
```

Answer the prompts (email for expiry notices, agree to terms). Certbot rewrites
the vhost, adds the `:443` block, and installs an auto-renew timer.

### Finish HTTPS hardening

Once `https://shop.duckdns.org` loads without warnings, enable secure cookies:

```bash
PHPV=$(php -r 'echo PHP_VERSION;' | cut -d. -f1,2)
sudo sed -i 's/^; session.cookie_secure.*/session.cookie_secure = 1/' /etc/php/$PHPV/apache2/conf.d/99-pdr.ini
echo 'session.cookie_secure = 1' | sudo tee -a /etc/php/$PHPV/apache2/conf.d/99-pdr.ini
sudo systemctl reload apache2
```

Check renewal works:

```bash
sudo certbot renew --dry-run
```

---

## Phase 6 — Configure the application (15 min)

### 6a. SMTP (confirmation emails)

```bash
sudo nano /var/www/pdrsystem/.env
```

Fill these (Gmail needs 2-Step Verification and an **App Password** from
<https://myaccount.google.com/apppasswords> — a normal password will fail):

```ini
PDR_MAIL_ENABLED=true
PDR_MAIL_HOST=smtp.gmail.com
PDR_MAIL_PORT=587
PDR_MAIL_ENCRYPTION=tls
PDR_MAIL_USERNAME=yourname@gmail.com
PDR_MAIL_PASSWORD=abcd efgh ijkl mnop
PDR_MAIL_FROM_EMAIL=yourname@gmail.com
PDR_MAIL_REPLY_TO=yourname@gmail.com
PDR_CONTACT_RECIPIENT=yourname@gmail.com
PDR_MAIL_TEST_RECIPIENT=yourname@gmail.com
```

Then:

```bash
sudo -u www-data php /var/www/pdrsystem/api/mail_test.php
sudo -u www-data php /var/www/pdrsystem/api/mail_test.php --send
```

> Oracle blocks outbound **port 25**; 587 (TLS) works. If Gmail rejects the
> login, use Brevo (300 free emails/day, `smtp-relay.brevo.com:587`) or
> Resend. Leave `PDR_MAIL_PASSWORD` blank only if you accept outbox mode
> (emails written to `api/mail/outbox/` instead of sent).

### 6b. Cloudflare Turnstile (optional bot protection)

Leave both blank to disable. To enable, create a widget at
<https://dash.cloudflare.com> → Turnstile, then set:

```ini
PDR_TURNSTILE_SITE_KEY=0x4AAAAAAA...
PDR_TURNSTILE_SECRET=0x4AAAAAAA...
```

### 6c. Online payments (Xendit)

```bash
cd /var/www/pdrsystem
sudo cp config/xendit.example.php config/xendit.php
sudo nano config/xendit.php
```

```php
'secret_key'     => 'xnd_development_...',   // test key first
'callback_token' => '...',                    // Settings > Webhooks
'app_base_url'   => 'https://shop.duckdns.org', // NO trailing slash
```

In the Xendit dashboard → **Settings → Webhooks**, set the Invoice callback to:

```
https://shop.duckdns.org/api/payments/webhook.php
```

### 6d. Create your admin account

```bash
cd /var/www/pdrsystem
sudo -u www-data php create_admin.php admin 'A-Long-Password-12+' "Pio De Roda Administrator"
```

Password must be **12+ characters**. A leading space before the command keeps
it out of bash history: ` sudo -u www-data php ...`

---

## Phase 7 — Verify (10 min)

```bash
cd /var/www/pdrsystem
sudo -u www-data php deploy/check.php
```

Expect `0 failure(s)`. Then check the public surface:

```bash
curl -o /dev/null -s -w 'home   %{http_code}\n' https://shop.duckdns.org/
curl -o /dev/null -s -w 'admin  %{http_code}\n' https://shop.duckdns.org/admin/
curl -o /dev/null -s -w '.env   %{http_code}\n' https://shop.duckdns.org/.env
curl -o /dev/null -s -w 'schema %{http_code}\n' https://shop.duckdns.org/database/schema.sql
curl -o /dev/null -s -w 'setup  %{http_code}\n' https://shop.duckdns.org/create_admin.php
curl -sI https://shop.duckdns.org/ | grep -iE 'strict-transport|x-frame|content-security'
```

Expected: home/admin `200`, `.env`/`schema.sql`/`create_admin.php` **`403`**,
and the three security headers present.

Manual test pass (in a browser, on your phone ideally):

- [ ] Homepage, service pages, images all load
- [ ] Availability calendar fetches dates (AJAX works)
- [ ] Submit a booking → confirmation email arrives → admin sees it
- [ ] Booking lookup by reference + PIN works
- [ ] Contact form delivers to `PDR_CONTACT_RECIPIENT`
- [ ] Admin login, logout, and edit a booking without a CSRF error
- [ ] Cancel a reservation from the customer link
- [ ] Xendit test invoice → payment row updates → redirect lands on
      `customer/payment/result.html`

---

## Phase 8 — Backups and monitoring (5 min)

```bash
sudo bash /var/www/pdrsystem/deploy/backup.sh
ls -lh /var/backups/pdrsystem/
```

Schedule it daily at 02:00:

```bash
sudo crontab -e
```

```cron
0 2 * * * /usr/bin/env bash /var/www/pdrsystem/deploy/backup.sh >> /var/log/pdr-backup.log 2>&1
```

Copy backups off the server occasionally (they are useless if the instance
dies):

```powershell
scp -i C:\Users\Julo\Downloads\ssh-key-2026-11-18.key ubuntu@<PUBLIC_IP>:/var/backups/pdrsystem/*.gz C:\Users\Julo\Backups\
```

Useful log files:

```bash
sudo tail -f /var/log/apache2/pdrsystem-error.log
sudo tail -f /var/log/apache2/pdrsystem-access.log
sudo tail -f /var/log/pdr-backup.log
```

---

## Phase 9 — Updating the live site

```bash
cd /var/www/pdrsystem
sudo -u www-data git pull
sudo -u www-data php database/migrate.php --status
sudo -u www-data php database/migrate.php      # apply pending migrations
sudo -u www-data php deploy/check.php
```

Uploaded via tar instead of git? Re-upload and extract over the existing
folder, never overwrite `.env` or `config/xendit.php`:

```powershell
tar -czf pdrsystem-update.tar.gz admin api config customer database deploy lib index.php .htaccess favicon.svg robots.txt
scp -i C:\Users\Julo\Downloads\ssh-key-2026-11-18.key pdrsystem-update.tar.gz ubuntu@<PUBLIC_IP>:~/
```

```bash
sudo tar -xzf ~/pdrsystem-update.tar.gz -C /var/www/pdrsystem
sudo chown -R www-data:www-data /var/www/pdrsystem
sudo rm ~/pdrsystem-update.tar.gz
```

---

## Phase 10 — Going live with Xendit

1. Complete Xendit account activation (business documents).
2. Dashboard → **Settings → Developers → API Keys** → copy the **live** key.
3. Update `config/xendit.php`: `secret_key` → live key, keep the same
   `callback_token` (or rotate it in both places at once).
4. Remove `'payment_methods' => ['EWALLET','BANK_TRANSFER']` restrictions if
   you want all methods your account enables.
5. Run one real small-value transaction end to end and refund it.
6. Watch `https://shop.duckdns.org/api/payments/webhook.php` in the Xendit
   webhook log for `200` responses.

---

## Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Browser times out | VCN ingress rules or iptables missing | Redo Phase 2a and 2b |
| `403 Forbidden` on `/` | default site still active / no DirectoryIndex | `sudo a2dissite 000-default`, `sudo apache2ctl -S`, reload Apache |
| `Database connection failed` | wrong `.env` credentials | `sudo -u www-data php deploy/check.php`; re-run setup script |
| Blank page / 500 | PHP fatal | `sudo tail -50 /var/log/apache2/pdrsystem-error.log` |
| Login works, then bounces to login | `session.cookie_secure=1` while browsing over http | Use https, or remove that line temporarily |
| Images/CSS 404 only on the server | Windows ignores filename case, Linux does not | Rename files to match the exact case used in HTML (`CB.php` vs `cb.php`) |
| Emails never arrive | outbox mode or bad app password | `php api/mail_test.php`, check Gmail App Password, confirm port 587 |
| Xendit webhook `401` | `callback_token` mismatch | Copy the token from the dashboard again |
| `certbot` fails | DNS not pointing at the server, or port 80 closed | `nslookup your.domain`, recheck Phase 2 |
| `$'\r': command not found` | CRLF shell script | `sudo sed -i 's/\r$//' deploy/*.sh` |
| Site unreachable after a reboot | ephemeral public IP changed | Reserve the public IP (Phase 1) and update DNS |
| Oracle emails about idle resources | Always Free reclamation of unused instances | Log in monthly, keep the instance serving traffic |

---

## Restore from backup

```bash
sudo gunzip -c /var/backups/pdrsystem/pdrsystem-2026-11-18_0200.sql.gz | mysql -u pdr -p pdrsystem
```

The matching `env-....bak` contains the `.env` that belonged to that dump —
copy it back if you lost credentials.

---

## Security checklist before announcing the site

- [ ] `https://` works and `http://` redirects to it
- [ ] `/.env`, `/database/schema.sql`, `/deploy/*`, `/create_admin.php` return 403
- [ ] `.env` permissions are `640` and owned by `www-data`
- [ ] `PDR_LOOKUP_SECRET` is a random 64-char hex value (script generates it)
- [ ] Admin password is unique, 12+ characters, not reused anywhere
- [ ] `config/xendit.php` contains live keys only on the server, never in git
- [ ] `display_errors=Off` (`php -i | grep display_errors`)
- [ ] Nightly backup ran at least once and the file is not empty
- [ ] Certbot renewal dry-run passes
- [ ] The GitHub repo has no `.env`, `*.sql` dump, or `config/xendit.php`
      (`git log --all --full-history -- .env config/xendit.php`)
