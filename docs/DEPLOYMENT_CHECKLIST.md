# Client Deployment Checklist

Use this checklist each time you install the application for a client.

## Before Leaving Your Office

- [ ] Run `composer install` on your preparation machine
- [ ] Confirm [`vendor/`](/c:/xampp/htdocs/optionone.com/vendor) exists
- [ ] Confirm `.env` is prepared for the target device
- [ ] Confirm the database name, username, and password are ready
- [ ] Confirm the SQL migrations are available
- [ ] Confirm the project folder includes:
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
- [ ] Copy the package to flash drive or other offline transport

## On The Client Machine

- [ ] Install or confirm XAMPP is available
- [ ] Copy the project to `C:\xampp\htdocs\optionone.com`
- [ ] Create the MySQL database
- [ ] Import SQL migrations in order
- [ ] Confirm Apache `mod_rewrite` is enabled
- [ ] Confirm `httpd-vhosts.conf` is included in Apache config
- [ ] Create the Apache vhost
- [ ] Set `DocumentRoot` to [`public/`](/c:/xampp/htdocs/optionone.com/public)
- [ ] Add `/assets/` alias to [`assets/`](/c:/xampp/htdocs/optionone.com/assets)
- [ ] Add `/storage/uploads/` alias to [`storage/uploads/`](/c:/xampp/htdocs/optionone.com/storage/uploads)
- [ ] Update Windows `hosts` file
- [ ] Restart Apache

## Recommended Local Domain

- [ ] Use `optionone.test` instead of `optionone.com`
- [ ] On the host PC, map:

```txt
127.0.0.1 optionone.test
```

- [ ] On other LAN devices, map:

```txt
<host-pc-lan-ip> optionone.test
```

## Environment File Check

- [ ] `APP_URL` matches the chosen local domain
- [ ] `APP_BASE_PATH=""`
- [ ] `APP_ENV=local` for local install
- [ ] `APP_DEBUG=true` only for local troubleshooting
- [ ] Database credentials match the client machine
- [ ] Mail settings are configured if email features are needed

## Final Verification

- [ ] `/login` loads
- [ ] CSS loads correctly
- [ ] Sign-in works
- [ ] `/register` works if self-registration is required
- [ ] `/platform/setup` works if first platform admin must be created
- [ ] Product images and uploads load
- [ ] Reports open
- [ ] Backups path is writable if backups are enabled

## Optional LAN Access Check

- [ ] Apache is reachable from another device using `http://<host-pc-lan-ip>/login`
- [ ] Windows Firewall allows Apache on the private network
- [ ] Client device `hosts` file points the local domain to the host PC LAN IP

## After Installation

- [ ] Show the client how to open the local URL
- [ ] Record the database credentials securely
- [ ] Record the local domain used
- [ ] Keep a backup of the prepared package
- [ ] If needed, configure Windows auto-start for Apache and MySQL using [AUTO_START_WINDOWS.md](/c:/xampp/htdocs/optionone.com/docs/AUTO_START_WINDOWS.md)
