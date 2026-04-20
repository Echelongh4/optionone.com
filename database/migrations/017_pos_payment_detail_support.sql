USE pos_system;

ALTER TABLE payments
    ADD COLUMN IF NOT EXISTS cheque_number VARCHAR(120) NULL AFTER reference,
    ADD COLUMN IF NOT EXISTS cheque_bank VARCHAR(150) NULL AFTER cheque_number,
    ADD COLUMN IF NOT EXISTS cheque_date DATE NULL AFTER cheque_bank;
