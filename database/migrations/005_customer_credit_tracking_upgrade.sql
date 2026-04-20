USE pos_system;

ALTER TABLE sales
    ADD COLUMN IF NOT EXISTS loyalty_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER order_discount_total,
    ADD COLUMN IF NOT EXISTS loyalty_points_redeemed INT NOT NULL DEFAULT 0 AFTER loyalty_discount_total;

UPDATE sales
SET loyalty_discount_total = COALESCE(loyalty_discount_total, 0),
    loyalty_points_redeemed = COALESCE(loyalty_points_redeemed, 0);

ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('cash', 'card', 'mobile_money', 'cheque', 'split', 'credit') NOT NULL;

CREATE TABLE IF NOT EXISTS customer_credit_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    return_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    transaction_type ENUM('charge', 'payment', 'return', 'void', 'adjustment') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_credit_customer (customer_id),
    INDEX idx_credit_sale (sale_id),
    INDEX idx_credit_return (return_id),
    INDEX idx_credit_user (user_id),
    CONSTRAINT fk_credit_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_credit_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
    CONSTRAINT fk_credit_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE SET NULL,
    CONSTRAINT fk_credit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO customer_credit_transactions (customer_id, sale_id, return_id, user_id, transaction_type, amount, balance_after, notes, created_at)
SELECT c.id, NULL, NULL, NULL, 'adjustment', c.credit_balance, c.credit_balance,
       'Opening balance from existing customer credit balance', NOW()
FROM customers c
WHERE c.credit_balance > 0
  AND NOT EXISTS (
      SELECT 1
      FROM customer_credit_transactions cct
      WHERE cct.customer_id = c.id
  );
