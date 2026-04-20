USE pos_system;

ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS loyalty_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER order_discount_total,
    ADD COLUMN IF NOT EXISTS loyalty_points_redeemed INT NOT NULL DEFAULT 0 AFTER loyalty_discount_total;

UPDATE sales
SET loyalty_discount_total = COALESCE(loyalty_discount_total, 0),
    loyalty_points_redeemed = COALESCE(loyalty_points_redeemed, 0);