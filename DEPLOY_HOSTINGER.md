# Deploying NutriDeq to Hostinger (Shared / Premium / Business)

> **Target:** Hostinger shared-style hosting (hPanel, LiteSpeed web server, MySQL via phpMyAdmin).
> **App:** the plain-PHP application in `legacy-php/` (no Node build step required).

## What is already prepared in this repo

| File | Purpose |
|---|---|
| `deploy/nutrideq-deploy.zip` (~4.3 MB) | Production package — `legacy-php/` with dev files stripped and `vendor/` included. Unzips **directly into `public_html/`**. |
| `deploy/nutrideq-dump.sql` (~2 MB) | Full export of your local XAMPP `nutrideq` DB (38 tables, migrations applied, DEFINER clauses stripped, no `CREATE DATABASE`/`USE` lines — imports into any Hostinger DB name). |
| `deploy/build-package.ps1` | Rebuilds the ZIP from `legacy-php/` at any time. |
| `legacy-php/.env.example` | Template for server secrets. |
| `legacy-php/config/env.php` | Loads `legacy-php/.env` into `getenv()`/`$_ENV` on every request (Hostinger can't set machine-level env vars). Real env vars always win, so XAMPP/Render behavior is unchanged. |
| `legacy-php/.htaccess` | Hardened: blocks web access to `.env`, `config/`, `migrations/`, `dev-tools/`, `api/sessions/`, `*.log`, `*.sql`; PHP overrides (upload size, timezone, display_errors off). |

---

## Step 0 — Fill in your secrets (locally, once)

1. Copy `legacy-php/.env.example` to `legacy-php/.env`.
2. You will fill in the **database values in Step 1** (after Hostinger creates them).
3. Optional now, required later for those features:
   - `GROQ_API_KEY` — AI recommendation endpoints.
   - `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` — "Sign in with Google" button.
   - `NUTRIDEQ_ENC_KEY` — app-level encryption (`libs/Encryption.php`). Generate:
     ```
     C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"
     ```
4. Keep `legacy-php/.env` **out of git** (already ignored) and out of the ZIP
   (`build-package.ps1` excludes it — it is uploaded separately in Step 5).

## Step 1 — Create the MySQL database in hPanel

1. hPanel → **Websites** → your site → **Dashboard** → sidebar **Databases → Management** (older plans: **MySQL Databases**).
2. **Create a new database**:
   - Database name: `nutrideq` → actual name becomes `uXXXXXXXX_nutrideq` (prefix = your account ID).
   - Username: `nutrideq` → actual username `uXXXXXXXX_nutrideq`.
   - Set a strong password and **save it** (hPanel shows it masked; you can reset it there).
3. Note these three values — they go into `.env`:
   ```
   MYSQLDATABASE=uXXXXXXXX_nutrideq
   MYSQLUSER=uXXXXXXXX_nutrideq
   MYSQLPASSWORD=<your password>
   ```
   Host stays `localhost` (already correct in the template).

## Step 2 — Import the database dump

1. In the same Databases page, open **phpMyAdmin** (or "Enter phpMyAdmin" next to the DB).
2. In the left tree, **click your database** (`uXXXXXXXX_nutrideq`) — selecting the DB first is required.
3. Tab **Import** → Choose file → `deploy/nutrideq-dump.sql` → **Go**.
   - ~2 MB / 38 tables — imports in seconds. No timeout expected.
4. Verify: `users`, `clients`, `food_logs`, `nutrient_standards` exist and are non-empty.
5. All migrations (`nutrient_standards`, NLP eval tables) are **already inside the dump** — nothing else to run.

> To reset later: the dump contains `DROP TABLE IF EXISTS` before each `CREATE TABLE`, so re-importing the same file cleanly replaces all data.

## Step 3 — Set the PHP version

hPanel → **Websites → Manage → PHP Configuration**:
- PHP version: **8.2** (app requires ≥ 8.1; 8.3 also works).
- Enable **OPcache** if it's not on (usually on by default).
- `display_errors` Off, `upload_max_filesize` 64M, timezone `Asia/Manila` are already enforced by `public_html/.htaccess`.

## Step 4 — Upload the app package

1. hPanel → **Files → File Manager** → open `public_html/`.
2. Delete Hostinger's default placeholder files (e.g. `default.php`) if present.
3. **Upload** `deploy/nutrideq-deploy.zip`.
4. Right-click the ZIP → **Extract** → path `/public_html` → confirm **overwrite**.
5. After extraction you must see `index.php`, `.htaccess`, `vendor/`, `api/`, `css/`… **directly inside `public_html/`** (not inside a subfolder). If they ended up in `public_html/nutrideq-deploy/`, move the contents up one level and delete the empty folder.

## Step 5 — Upload `.env` (secrets)

1. File Manager → `public_html` → enable **"Show hidden files"** (⋮ menu).
2. **New File** → name it exactly `.env` → paste your filled-in `.env` contents → Save.
   - Tip: to avoid copy/paste mangling, you can also upload `legacy-php/.env` and rename it to `.env` in place.
3. Confirm it sits at `/public_html/.env` — the same level as `index.php`.
4. Web access to it is blocked by `.htaccess` (visit `https://yourdomain/.env` — must be **403**).

## Step 6 — SSL / force HTTPS

1. hPanel → **Security → SSL** → your domain: install the free Let's Encrypt certificate (most plans auto-provision).
2. Enable **Force HTTPS** in the same panel.
3. The app reads `$_SERVER['HTTPS']` for cookie flags — forcing HTTPS makes session cookies `Secure` automatically.

## Step 7 — Google sign-in (only if you use it)

In Google Cloud Console → **APIs & Services → Credentials → OAuth client**:
- Add/replace the **Authorized redirect URI** with:
  ```
  https://YOURDOMAIN.com/login-logout/google-callback.php
  ```
- The redirect is built from the live host in `google-callback.php`, so no code change is needed.
- Only `@gmail.com` accounts are accepted by design.

## Step 8 — Smoke tests

| Check | Expected |
|---|---|
| `https://yourdomain/` | NutriDeq landing page renders (splash → hero). |
| `https://yourdomain/login-logout/NutriDeqN-Login.php` | Login page renders; log in with an existing account from the imported DB. |
| Dashboard | Widgets load (no eternal spinner). |
| AI features | Work only if `GROQ_API_KEY` is set; otherwise buttons show the graceful fallback. |
| `https://yourdomain/.env` and `/config/env.php` | **403 Forbidden**. |
| `https://yourdomain/dev-tools/` | **403 Forbidden** (folder shouldn't even exist in the ZIP). |
| `https://yourdomain/manifest.json` | 200 JSON (PWA installability). |
| http:// (not https) | Redirects to https. |

## Troubleshooting

- **Blank page / 500** — hPanel → **Websites → Manage → Logs** (or `/home/uXXXXXXXX/logs/`): read the PHP error log. `display_errors` is intentionally off in production.
- **"Database connection failed"** — 99% a `.env` typo. Host must be `localhost`; database and username must be the **full prefixed** names (`uXXXXXXXX_...`); password must match exactly (reset it in hPanel if unsure).
- **Dashboard widgets stuck "loading"** — that was the old `admin_stats` JSON-corruption bug (fixed by commit `b41e916`); it recurs only if something re-enables `display_errors`. Keep it Off.
- **File uploads fail** — confirm `public_html/uploads/reports/` exists (it ships with a `.gitkeep`). Permissions 755 are fine on Hostinger.
- **Sessions lost between pages** — confirm `/public_html/api/sessions/` exists and is writable (auto-created on first request otherwise).
- **phpMyAdmin import error on triggers** — shouldn't happen: all DEFINER clauses were stripped from the dump. If you ever re-export manually, strip `DEFINER=` clauses again.

## Redeploying after code changes

1. Make/test changes locally with XAMPP.
2. Rebuild the package (re-run `composer install` first if `composer.json`/`composer.lock` changed):
   ```powershell
   Set-Location legacy-php
   C:\xampp\php\php.exe ..\tools\composer.phar install --no-dev --optimize-autoloader
   Set-Location ..
   powershell -ExecutionPolicy Bypass -File deploy\build-package.ps1
   ```
3. Upload the new ZIP → Extract → overwrite (File Manager replaces existing files).
4. Schema changed? Re-export locally:
   ```powershell
   & C:\xampp\mysql\bin\mysqldump.exe -u root --databases nutrideq --single-transaction --routines --triggers --default-character-set=utf8mb4 --no-tablespaces | Out-File deploy\nutrideq-dump.sql -Encoding utf8
   ```
   then strip `DEFINER` clauses and the `CREATE DATABASE`/`USE` lines (same as the original export), and re-import in phpMyAdmin.
5. Hard-refresh the site (Ctrl+F5); the service worker caches assets, so bump its version if you changed static files.

## Security posture on shared hosting

- `.env` secrets are served only to PHP (blocked from HTTP by `.htaccess`; LiteSpeed honors it).
- Sessions live under `api/sessions/` (not the shared /tmp) and are blocked from HTTP.
- Dev tools (`dev-tools/`) are never uploaded by the package builder.
- Composer audit is clean as of the last build (`google/apiclient`, `guzzle`, `phpseclib` updated to patched releases; unused Google service classes removed — `vendor/` shrank from 132 MB to ~5 MB).

