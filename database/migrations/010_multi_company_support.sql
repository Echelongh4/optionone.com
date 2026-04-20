USE pos_system;

CREATE TABLE IF NOT EXISTS companies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_companies_status (status)
) ENGINE=InnoDB;

INSERT INTO companies (name, slug, email, phone, address, status, created_at, updated_at)
SELECT
    COALESCE(MAX(CASE WHEN key_name = 'business_name' THEN value_text END), 'Default Company') AS name,
    'default-company' AS slug,
    MAX(CASE WHEN key_name = 'business_email' THEN value_text END) AS email,
    MAX(CASE WHEN key_name = 'business_phone' THEN value_text END) AS phone,
    MAX(CASE WHEN key_name = 'business_address' THEN value_text END) AS address,
    'active' AS status,
    NOW(),
    NOW()
FROM settings
WHERE NOT EXISTS (SELECT 1 FROM companies);

SET @default_company_id = (SELECT id FROM companies ORDER BY id ASC LIMIT 1);

ALTER TABLE branches
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_branches_company (company_id);

UPDATE branches
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE branches
    DROP INDEX code,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_branches_company_code (company_id, code),
    ADD CONSTRAINT fk_branches_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE users
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_users_company (company_id);

UPDATE users u
LEFT JOIN branches b ON b.id = u.branch_id
SET u.company_id = COALESCE(b.company_id, @default_company_id)
WHERE u.company_id IS NULL;

ALTER TABLE users
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD CONSTRAINT fk_users_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE settings
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_settings_company (company_id);

UPDATE settings
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE settings
    DROP INDEX key_name,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_settings_company_key (company_id, key_name),
    ADD CONSTRAINT fk_settings_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE taxes
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_taxes_company (company_id);

UPDATE taxes
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE taxes
    DROP INDEX name,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_taxes_company_name (company_id, name),
    ADD CONSTRAINT fk_taxes_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE categories
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_categories_company (company_id);

UPDATE categories
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE categories
    DROP INDEX slug,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_categories_company_slug (company_id, slug),
    ADD CONSTRAINT fk_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE customer_groups
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_customer_groups_company (company_id);

UPDATE customer_groups
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE customer_groups
    DROP INDEX name,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_customer_groups_company_name (company_id, name),
    ADD CONSTRAINT fk_customer_groups_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE expense_categories
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_expense_categories_company (company_id);

UPDATE expense_categories
SET company_id = @default_company_id
WHERE company_id IS NULL;

ALTER TABLE expense_categories
    DROP INDEX name,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_expense_categories_company_name (company_id, name),
    ADD CONSTRAINT fk_expense_categories_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;

ALTER TABLE products
    ADD COLUMN company_id BIGINT UNSIGNED NULL AFTER id,
    ADD INDEX idx_products_company (company_id);

UPDATE products p
LEFT JOIN branches b ON b.id = p.branch_id
SET p.company_id = COALESCE(b.company_id, @default_company_id)
WHERE p.company_id IS NULL;

ALTER TABLE products
    DROP INDEX slug,
    DROP INDEX sku,
    DROP INDEX barcode,
    MODIFY company_id BIGINT UNSIGNED NOT NULL,
    ADD UNIQUE KEY uq_products_company_slug (company_id, slug),
    ADD UNIQUE KEY uq_products_company_sku (company_id, sku),
    ADD UNIQUE KEY uq_products_company_barcode (company_id, barcode),
    ADD CONSTRAINT fk_products_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE;
