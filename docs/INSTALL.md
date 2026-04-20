# Install Guide

## 1. Copy Environment File

Create `.env` from [`.env.example`](/c:/xampp/htdocs/optionone.com/.env.example).

## 2. Configure Environment

Set these values for your machine:

```env
APP_ENV=local
APP_DEBUG=true
APP_URL="http://optionone.com"
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

## 7. Start Using the App

- `/login`
- `/register`
- `/platform/setup`
