# Install Guide

## 1. Copy Environment File

Create `.env` from [`.env.example`](/c:/xampp/htdocs/optionone.com/.env.example).

## 2. Configure Environment

Set these values for your machine:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL="http://optionone.test"
APP_BASE_PATH=""

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos_system
DB_USERNAME=root
DB_PASSWORD=
```

## 3. Install PHP Dependencies

```powershell
composer install
```

## 4. Create Database

Create `pos_system` in MySQL or phpMyAdmin.

## 5. Import Database Migrations

Import these SQL files in order:

1. `001_schema.sql`
2. `003_purchase_order_receiving_upgrade.sql`
3. `004_loyalty_redemption_upgrade.sql`
4. `005_customer_credit_tracking_upgrade.sql`
5. `006_sale_void_requests_upgrade.sql`
6. `007_password_reset_tokens_upgrade.sql`
7. `008_product_brand_upgrade.sql`
8. `009_username_login_upgrade.sql`
9. `010_multi_company_support.sql`
10. `011_email_verification_support.sql`
11. `012_platform_admin_support.sql`
12. `013_billing_management_support.sql`
13. `014_billing_payment_methods_support.sql`
14. `015_automation_gateway_sms_support.sql`
15. `016_pos_cheque_payment_support.sql`
16. `017_pos_payment_detail_support.sql`

Optional demo data:

17. `002_seed_demo.sql`

## 6. Configure Apache

- Point the vhost document root to [`public/`](/c:/xampp/htdocs/optionone.com/public)
- Alias `/assets/` to [`assets/`](/c:/xampp/htdocs/optionone.com/assets)
- Alias `/storage/uploads/` to [`storage/uploads/`](/c:/xampp/htdocs/optionone.com/storage/uploads)
- Prefer a local-only domain such as `optionone.test` instead of `optionone.com`

Example local `hosts` file entry on the PC running XAMPP:

```txt
127.0.0.1 optionone.test
```

If another device on the same network must access the app, add this on that device:

```txt
172.20.10.5 optionone.test
```

Do not use `optionone.com` on client devices unless you intentionally want to override the real public domain.

## 7. Start Using the App

- `/login`
- `/register`
- `/platform/setup`

## 8. Offline Installation For Low-Internet Devices

If the destination computer cannot reliably access the internet, prepare the app on another machine first.

Do this on your development machine:

1. Run:

```powershell
composer install
```

2. Confirm [`vendor/`](/c:/xampp/htdocs/optionone.com/vendor) exists.
3. Set up `.env` for the target machine.
4. Copy the full project to the destination PC.

Include these items in the copied package:

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

Then on the destination PC:

1. Put the project in `C:\xampp\htdocs\`
2. Configure Apache vhost and `hosts` entries
3. Create the database
4. Import the migrations
5. Start Apache
6. Open `/login`

Rule:
- `vendor/` should stay out of GitHub
- `vendor/` should be included in offline deployment copies
