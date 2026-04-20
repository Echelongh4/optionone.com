USE pos_system;

ALTER TABLE payments
    MODIFY COLUMN payment_method ENUM('cash', 'card', 'mobile_money', 'cheque', 'split', 'credit') NOT NULL;
