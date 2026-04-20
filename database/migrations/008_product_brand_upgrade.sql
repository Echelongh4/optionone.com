USE pos_system;

ALTER TABLE products
    ADD COLUMN IF NOT EXISTS brand VARCHAR(120) NULL AFTER name;

UPDATE products
SET brand = NULL
WHERE TRIM(COALESCE(brand, '')) = '';
