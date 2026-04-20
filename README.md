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
    ServerName optionone.com
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

## Daily Use

- `http://optionone.com/login` opens the sign-in page.
- `http://optionone.com/register` creates a new company workspace.
- `http://optionone.com/platform/setup` bootstraps the first platform admin when none exists.
- Uploaded files are served from `/storage/uploads/...`.
- Local CSS and JS are served from `/assets/...`.

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
- If you change the host name, keep `.env` `APP_URL` in sync with Apache.
