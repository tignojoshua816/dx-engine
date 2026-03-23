# DX-Engine — XAMPP Local Setup Guide

Complete fix reference for all four issues reported on a Windows/XAMPP environment.

---

## Issue 1 — Bootstrap SRI Integrity Block

### Root Cause

Subresource Integrity (SRI) is a browser security feature. When a `<link>` or `<script>` tag
carries an `integrity=` attribute, the browser downloads the resource **and** expects the CDN
response to include an `Access-Control-Allow-Origin` CORS header so the hash can be verified.

On `localhost` the browser enforces this as a same-origin check. jsDelivr's CDN does send the
CORS header on real deployments, but XAMPP's loopback network path sometimes strips it or the
browser treats the response as opaque — causing:

```
Failed to find a valid digest in the 'integrity' attribute for resource
'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'.
The resource has been blocked.
```

### Fix Applied (already in the repo)

All `integrity=` and `crossorigin=` attributes have been removed from:
- `public/admission.php`
- `examples/index.php`
- `examples/legacy-embed.php`

The correct SHA-384 hashes for Bootstrap **5.3.3** are preserved in comments for when
you deploy to a public server:

```html
<!-- Production — re-add these attributes on any publicly accessible server -->
<link rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFU0NGgm/c1Bs7qAGRa0f0BBML6"
        crossorigin="anonymous"></script>
```

---

## Issue 2 — Localhost / XAMPP Subfolder Path

### Root Cause

The original code hardcoded the API endpoint as `/dx-engine/public/api/dx.php` in three places:

| File | Location |
|---|---|
| `config/app.php` | `dx_api_endpoint` default value |
| `app/DX/AdmissionDX.php` | `post_endpoint` key in `getFlow()` return array |
| `public/admission.php` | `endpoint:` option in the JS initialiser |

If the app lives at `localhost/` (document root) instead of `localhost/dx-engine/`, every fetch
returns a 404 because the subfolder path is wrong.

### Fix Applied (already in the repo)

**`config/app.php`** now auto-detects the base URL at runtime:

```
http://localhost/dx-engine/   →  http://localhost/dx-engine/public/api/dx.php
http://localhost/             →  http://localhost/public/api/dx.php
https://my-hospital.com/      →  https://my-hospital.com/public/api/dx.php
```

**`src/Core/Router.php`** injects `dx_api_endpoint` into every controller's `$context` array.

**`app/DX/AdmissionDX.php`** reads `$context['dx_api_endpoint']` instead of hardcoding the path.

**`public/admission.php`** echoes the PHP-resolved endpoint directly into the JS initialiser:

```php
// PHP resolves the correct URL server-side:
$apiEndpoint = htmlspecialchars($config['dx_api_endpoint'], ENT_QUOTES, 'UTF-8');
```

```js
// JS receives the correct URL for the current deployment location:
endpoint: '<?= $apiEndpoint ?>',
```

**Override:** Set the `DX_ENDPOINT` environment variable to skip auto-detection entirely:

```
DX_ENDPOINT=https://my-hospital.com/dx/api/dx.php
```

---

## Issue 3 — `.htaccess` Files

### Root Cause

Apache ignores `.htaccess` files by default (`AllowOverride None` in the XAMPP default
`httpd.conf`). Without `AllowOverride All`, the `Options -Indexes` and `RewriteRule`
directives in `.htaccess` are silently skipped.

### Step A — Enable AllowOverride in httpd.conf

1. Open **XAMPP Control Panel**.
2. Next to Apache, click **Config → httpd.conf**.
3. Find the `<Directory>` block for your htdocs folder. There are two to change:

```apache
# Block 1 — global default (around line 230)
<Directory />
    AllowOverride none        ← change to: AllowOverride All
    Require all denied
</Directory>

# Block 2 — htdocs directory (around line 252)
<Directory "C:/xampp/htdocs">
    Options Indexes FollowSymLinks Includes ExecCGI
    AllowOverride None        ← change to: AllowOverride All
    Require all granted
</Directory>
```

4. **Save** `httpd.conf`.
5. In the XAMPP Control Panel, click **Stop** then **Start** next to Apache.

### Step B — Enable mod_rewrite and mod_headers

In the same `httpd.conf`, find the `LoadModule` section and make sure these two lines
are **not** commented out (remove the leading `#` if present):

```apache
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule headers_module modules/mod_headers.so
```

### Step C — .htaccess files in the repo

Two `.htaccess` files have been created in the repo:

| File | Purpose |
|---|---|
| `dx-engine/.htaccess` | Blocks direct HTTP access to `/config`, `/src`, `/app`, `/database` |
| `dx-engine/public/.htaccess` | Sets CORS headers, handles OPTIONS preflight, ensures PHP execution |

These files are already in the repository — no manual creation needed.

---

## Issue 4 — Autoloader Namespace Resolution on Windows

### Root Cause

Windows uses `\` as the directory separator; PHP's `DIRECTORY_SEPARATOR` constant reflects this.
If a namespace resolver concatenates paths with `/`, `file_exists()` fails silently on Windows
when the path contains mixed slashes.

The DX-Engine `Autoloader::load()` already handles this correctly:

```php
$file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relClass) . '.php';
```

It converts every `\\` in the namespace to the OS-native separator before calling `is_file()`.

### Diagnostic: check_env.php

Run the included diagnostic script to verify every namespace resolves to a real file:

```
http://localhost/dx-engine/check_env.php
```

It checks:

- PHP version (≥ 8.0 required)
- Required extensions: `pdo`, `pdo_mysql`, `mbstring`, `json`, `session`
- Every required file exists at the expected absolute path
- Every namespace (`DXEngine\Core\*`, `App\DX\*`, `App\Models\*`) resolves correctly
- PDO can connect to MySQL with the credentials in `config/database.php`
- All four DB tables exist (`dx_patients`, `dx_admissions`, `dx_insurance`, `dx_departments`)
- The detected base URL and API endpoint are correct for the current installation path
- `mod_rewrite` and `mod_headers` are loaded

**Security:** Delete or rename `check_env.php` before deploying to any publicly accessible server.

---

## Common XAMPP Error → Fix Table

| Error | Cause | Fix |
|---|---|---|
| Bootstrap stylesheet blocked (SRI) | `integrity=` on localhost | Remove `integrity=` and `crossorigin=` attributes (done) |
| `fetch` returns 404 on API endpoint | Hardcoded `/dx-engine/` subfolder path | Auto-detection in `config/app.php` (done) |
| Blank page / no `.htaccess` effect | `AllowOverride None` in `httpd.conf` | Change to `AllowOverride All`, restart Apache |
| `Class not found` on Windows | Mixed path separators | `Autoloader::load()` uses `DIRECTORY_SEPARATOR` (already correct) |
| `PDO connection failed` | MySQL not started, or wrong credentials | Start MySQL in XAMPP Control Panel; check `config/database.php` |
| `Unknown database dx_engine` | DB not created | Run `CREATE DATABASE dx_engine;` in phpMyAdmin, then run the migration SQL |
| `Table dx_patients doesn't exist` | Migration not run | Import `database/migrations/001_create_tables.sql` via phpMyAdmin |
| `mod_rewrite` rules ignored | Module not loaded | Uncomment `LoadModule rewrite_module` in `httpd.conf` |

---

## Verified Working URL Pattern (XAMPP Subfolder)

```
XAMPP htdocs layout:
  C:\xampp\htdocs\
    dx-engine\
      app\
      config\
      database\
      docs\
      examples\
      public\
        api\
          dx.php          ← API endpoint
        css\
          dx-engine.css
        js\
          dx-interpreter.js
        admission.php     ← Main page
      src\
      check_env.php       ← DELETE before production

URLs:
  Main form:   http://localhost/dx-engine/public/admission.php
  API (GET):   http://localhost/dx-engine/public/api/dx.php?dx=admission_case
  API (POST):  http://localhost/dx-engine/public/api/dx.php  (body: { _step, dx_id, ... })
  Diagnostics: http://localhost/dx-engine/check_env.php
```
