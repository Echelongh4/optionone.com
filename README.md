# ECHELONGH TECHNOLOGY POS

PHP 8 point-of-sale application with multi-company support, inventory, billing, reporting, and a local asset bundle.

## Requirements

- PHP 8.1+
- MySQL 8+ or MariaDB 10.5+
- Apache with `mod_rewrite`
- Composer
- XAMPP is fine for local hosting on Windows

## Local Installation

1. Copy [`.env.example`](/c:/xampp/htdocs/optionone.com/.env.example) to `.env`.
2. Update database credentials and app URL in `.env`.
3. Create the database.
4. Import the SQL migrations in numeric order:
   - `database/migrations/001_schema.sql`
   - `database/migrations/003_purchase_order_receiving_upgrade.sql`
   - `database/migrations/004_loyalty_redemption_upgrade.sql`
   - `database/migrations/005_customer_credit_tracking_upgrade.sql`
   - `database/migrations/006_sale_void_requests_upgrade.sql`
   - `database/migrations/007_password_reset_tokens_upgrade.sql`
   - `database/migrations/008_product_brand_upgrade.sql`
   - `database/migrations/009_username_login_upgrade.sql`
   - `database/migrations/010_multi_company_support.sql`
   - `database/migrations/011_email_verification_support.sql`
   - `database/migrations/012_platform_admin_support.sql`
   - `database/migrations/013_billing_management_support.sql`
   - `database/migrations/014_billing_payment_methods_support.sql`
   - `database/migrations/015_automation_gateway_sms_support.sql`
   - `database/migrations/016_pos_cheque_payment_support.sql`
   - `database/migrations/017_pos_payment_detail_support.sql`
5. Optional: import `database/migrations/002_seed_demo.sql` only after the schema migrations if you want demo data.
6. Run `composer install`.
7. Configure Apache so:
   - `DocumentRoot` points to [`public/`](/c:/xampp/htdocs/optionone.com/public)
   - `/assets/` is aliased to [`assets/`](/c:/xampp/htdocs/optionone.com/assets)
   - `/storage/uploads/` is aliased to [`storage/uploads/`](/c:/xampp/htdocs/optionone.com/storage/uploads)
8. Restart Apache and open the configured host in the browser.

## Local Apache Example

```apache
<VirtualHost *:80>
    ServerName optionone.test
    DocumentRoot "C:/xampp/htdocs/optionone.com/public"

    <Directory "C:/xampp/htdocs/optionone.com/public">
        AllowOverride All
        Require all granted
        Options FollowSymLinks
    </Directory>

    Alias /assets/ "C:/xampp/htdocs/optionone.com/assets/"
    <Directory "C:/xampp/htdocs/optionone.com/assets">
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>

    Alias /storage/uploads/ "C:/xampp/htdocs/optionone.com/storage/uploads/"
    <Directory "C:/xampp/htdocs/optionone.com/storage/uploads">
        AllowOverride None
        Require all granted
        Options FollowSymLinks
    </Directory>
</VirtualHost>
```

## Local Domain Notes

Do not use `optionone.com` on other devices unless you intentionally override the real public domain in that device's `hosts` file.

Why:
- your own machine can map `optionone.com` to localhost in its `hosts` file
- another device will normally resolve `optionone.com` through public DNS
- that sends the browser to the real website on the internet, not your local XAMPP server

Recommended local-network setup:
- use `optionone.test` or `optionone.local` instead of `optionone.com`
- on the hosting PC, map the local domain to `127.0.0.1`
- on the client device, map the same local domain to your host PC LAN IP, for example `172.20.10.5`

Example `hosts` file entries:

Host PC:
```txt
127.0.0.1 optionone.test
```

Client device:
```txt
172.20.10.5 optionone.test
```

Then set `.env` like this:

```env
APP_URL="http://optionone.test"
APP_BASE_PATH=""
```

## Daily Use

- `http://optionone.test/login` opens the sign-in page.
- `http://optionone.test/register` creates a new company workspace.
- `http://optionone.test/platform/setup` bootstraps the first platform admin when none exists.
- Uploaded files are served from `/storage/uploads/...`.
- Local CSS and JS are served from `/assets/...`.

## Offline Installation

If the target machine has weak or no internet access, do not run Composer there.

Correct approach:
- run `composer install` on your own machine first
- confirm [`vendor/`](/c:/xampp/htdocs/optionone.com/vendor) exists
- copy the full project to the target machine, including `vendor/`

Minimum folders and files to copy for an offline install:
- `app/`
- `assets/`
- `config/`
- `database/`
- `helpers/`
- `public/`
- `storage/`
- `vendor/`
- `.env`
- `composer.json`
- `composer.lock`
- `index.php`
- `.htaccess`

Suggested offline deployment flow:
1. Prepare the app fully on your development machine.
2. Run `composer install`.
3. Configure `.env` for the target machine.
4. Zip the project folder or copy it with a flash drive.
5. Move it to the target PC.
6. Configure XAMPP/Apache there.
7. Create the database and import the SQL migrations.
8. Start Apache and test `/login`.

Important:
- do not commit `vendor/` to GitHub
- do include `vendor/` in your offline deployment package
- do not expect the client machine to download dependencies if internet access is unreliable

## GitHub Workflow

1. Initialize Git once:
   - `git init`
2. Add the remote:
   - `git remote add origin <your-github-repo-url>`
3. Commit your work:
   - `git add .`
   - `git commit -m "Describe your change"`
4. Push:
   - `git branch -M main`
   - `git push -u origin main`
5. After that, your normal cycle is:
   - `git add .`
   - `git commit -m "Describe your change"`
   - `git push`

## Notes

- Do not commit `.env`.
- Do not commit `vendor/` or runtime data under `storage/`.
- If you change the host name, keep `.env` `APP_URL` in sync with Apache and the `hosts` file entries.
