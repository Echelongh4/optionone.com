USE pos_system;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS is_platform_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER company_id;

UPDATE users
SET is_platform_admin = 1
WHERE email = 'baafisamuel888@gmail.com';
