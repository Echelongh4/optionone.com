USE pos_system;

ALTER TABLE billing_payment_methods
    ADD COLUMN IF NOT EXISTS integration_driver VARCHAR(40) NOT NULL DEFAULT 'manual' AFTER type,
    ADD COLUMN IF NOT EXISTS integration_config_json LONGTEXT NULL AFTER checkout_url;

ALTER TABLE billing_invoice_payments
    ADD COLUMN IF NOT EXISTS gateway_provider VARCHAR(40) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS gateway_reference VARCHAR(150) NULL AFTER reference,
    ADD COLUMN IF NOT EXISTS gateway_payload_json LONGTEXT NULL AFTER notes;

CREATE TABLE IF NOT EXISTS billing_gateway_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    billing_invoice_id BIGINT UNSIGNED NOT NULL,
    billing_payment_method_id BIGINT UNSIGNED NOT NULL,
    billing_invoice_payment_id BIGINT UNSIGNED NULL,
    billing_payment_submission_id BIGINT UNSIGNED NULL,
    initiated_by_user_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    provider_reference VARCHAR(150) NOT NULL UNIQUE,
    provider_transaction_id VARCHAR(150) NULL,
    access_code VARCHAR(120) NULL,
    authorization_url VARCHAR(255) NULL,
    status ENUM('initialized', 'pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'initialized',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    payer_name VARCHAR(150) NULL,
    payer_email VARCHAR(150) NULL,
    payer_phone VARCHAR(50) NULL,
    metadata_json LONGTEXT NULL,
    verification_payload_json LONGTEXT NULL,
    failure_reason VARCHAR(255) NULL,
    last_checked_at DATETIME NULL,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_billing_gateway_transactions_company (company_id),
    INDEX idx_billing_gateway_transactions_invoice (billing_invoice_id),
    INDEX idx_billing_gateway_transactions_method (billing_payment_method_id),
    INDEX idx_billing_gateway_transactions_status (status),
    INDEX idx_billing_gateway_transactions_payment (billing_invoice_payment_id),
    CONSTRAINT fk_billing_gateway_transactions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_gateway_transactions_invoice FOREIGN KEY (billing_invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_gateway_transactions_method FOREIGN KEY (billing_payment_method_id) REFERENCES billing_payment_methods(id) ON DELETE RESTRICT,
    CONSTRAINT fk_billing_gateway_transactions_payment FOREIGN KEY (billing_invoice_payment_id) REFERENCES billing_invoice_payments(id) ON DELETE SET NULL,
    CONSTRAINT fk_billing_gateway_transactions_submission FOREIGN KEY (billing_payment_submission_id) REFERENCES billing_payment_submissions(id) ON DELETE SET NULL,
    CONSTRAINT fk_billing_gateway_transactions_user FOREIGN KEY (initiated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS platform_automation_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    automation_key VARCHAR(80) NOT NULL,
    status ENUM('running', 'succeeded', 'failed') NOT NULL DEFAULT 'running',
    trigger_mode ENUM('manual', 'scheduled', 'webhook') NOT NULL DEFAULT 'manual',
    company_id BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at DATETIME NULL,
    message VARCHAR(255) NULL,
    summary_json LONGTEXT NULL,
    created_by_user_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_platform_automation_runs_key (automation_key),
    INDEX idx_platform_automation_runs_status (status),
    INDEX idx_platform_automation_runs_company (company_id),
    INDEX idx_platform_automation_runs_started (started_at),
    CONSTRAINT fk_platform_automation_runs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_platform_automation_runs_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sms_message_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    provider VARCHAR(40) NOT NULL,
    recipient_phone VARCHAR(50) NOT NULL,
    sender_identity VARCHAR(120) NULL,
    message_body TEXT NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    external_message_id VARCHAR(120) NULL,
    error_message VARCHAR(255) NULL,
    payload_json LONGTEXT NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sms_message_logs_company (company_id),
    INDEX idx_sms_message_logs_user (user_id),
    INDEX idx_sms_message_logs_status (status),
    INDEX idx_sms_message_logs_provider (provider),
    CONSTRAINT fk_sms_message_logs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
    CONSTRAINT fk_sms_message_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
