USE pos_system;

CREATE TABLE IF NOT EXISTS billing_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    billing_cycle ENUM('monthly', 'quarterly', 'yearly', 'custom') NOT NULL DEFAULT 'monthly',
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    trial_days INT UNSIGNED NOT NULL DEFAULT 0,
    max_branches INT UNSIGNED NULL,
    max_users INT UNSIGNED NULL,
    max_products INT UNSIGNED NULL,
    max_monthly_sales INT UNSIGNED NULL,
    features_json LONGTEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_billing_plans_status (status),
    INDEX idx_billing_plans_default (is_default)
) ENGINE=InnoDB;

INSERT INTO billing_plans (
    name,
    slug,
    description,
    billing_cycle,
    price,
    currency,
    trial_days,
    max_branches,
    max_users,
    max_products,
    max_monthly_sales,
    features_json,
    status,
    is_featured,
    is_default,
    sort_order,
    created_at,
    updated_at
) VALUES
    (
        'Starter',
        'starter',
        'Single-location launch plan for smaller operators.',
        'monthly',
        29.00,
        'USD',
        14,
        1,
        5,
        500,
        1000,
        '["Cloud POS workspace","Inventory tracking","Email verification","Tenant billing desk"]',
        'active',
        0,
        1,
        10,
        NOW(),
        NOW()
    ),
    (
        'Growth',
        'growth',
        'Multi-location plan with stronger capacity for scaling teams.',
        'monthly',
        79.00,
        'USD',
        14,
        5,
        25,
        5000,
        10000,
        '["Multi-branch operations","Advanced reporting","Priority support access","Invoice management"]',
        'active',
        1,
        0,
        20,
        NOW(),
        NOW()
    ),
    (
        'Scale',
        'scale',
        'High-capacity plan for complex retail and wholesale operations.',
        'monthly',
        199.00,
        'USD',
        14,
        NULL,
        NULL,
        NULL,
        NULL,
        '["Unlimited catalog growth","Unlimited users and branches","Platform support priority","Flexible billing overrides"]',
        'active',
        1,
        0,
        30,
        NOW(),
        NOW()
    )
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    billing_cycle = VALUES(billing_cycle),
    price = VALUES(price),
    currency = VALUES(currency),
    trial_days = VALUES(trial_days),
    max_branches = VALUES(max_branches),
    max_users = VALUES(max_users),
    max_products = VALUES(max_products),
    max_monthly_sales = VALUES(max_monthly_sales),
    features_json = VALUES(features_json),
    status = VALUES(status),
    is_featured = VALUES(is_featured),
    is_default = VALUES(is_default),
    sort_order = VALUES(sort_order),
    updated_at = VALUES(updated_at);

CREATE TABLE IF NOT EXISTS company_subscriptions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    billing_plan_id BIGINT UNSIGNED NOT NULL,
    plan_name_snapshot VARCHAR(120) NOT NULL,
    billing_cycle ENUM('monthly', 'quarterly', 'yearly', 'custom') NOT NULL DEFAULT 'monthly',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    status ENUM('trialing', 'active', 'past_due', 'suspended', 'cancelled') NOT NULL DEFAULT 'trialing',
    trial_ends_at DATETIME NULL,
    current_period_start DATETIME NULL,
    current_period_end DATETIME NULL,
    next_invoice_at DATETIME NULL,
    grace_ends_at DATETIME NULL,
    max_branches INT UNSIGNED NULL,
    max_users INT UNSIGNED NULL,
    max_products INT UNSIGNED NULL,
    max_monthly_sales INT UNSIGNED NULL,
    auto_renew TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_company_subscriptions_company (company_id),
    INDEX idx_company_subscriptions_status (status),
    INDEX idx_company_subscriptions_plan (billing_plan_id),
    INDEX idx_company_subscriptions_next_invoice (next_invoice_at),
    CONSTRAINT fk_company_subscriptions_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_company_subscriptions_plan FOREIGN KEY (billing_plan_id) REFERENCES billing_plans(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS billing_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL,
    company_subscription_id BIGINT UNSIGNED NULL,
    invoice_number VARCHAR(40) NOT NULL UNIQUE,
    status ENUM('draft', 'issued', 'paid', 'void', 'overdue') NOT NULL DEFAULT 'issued',
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    balance_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    period_start DATETIME NULL,
    period_end DATETIME NULL,
    issued_at DATETIME NULL,
    due_at DATETIME NULL,
    paid_at DATETIME NULL,
    payment_reference VARCHAR(150) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_billing_invoices_company (company_id),
    INDEX idx_billing_invoices_subscription (company_subscription_id),
    INDEX idx_billing_invoices_status (status),
    INDEX idx_billing_invoices_due (due_at),
    CONSTRAINT fk_billing_invoices_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_invoices_subscription FOREIGN KEY (company_subscription_id) REFERENCES company_subscriptions(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS billing_invoice_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    billing_invoice_id BIGINT UNSIGNED NOT NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    payment_method ENUM('bank_transfer', 'card', 'cash', 'mobile_money', 'other') NOT NULL DEFAULT 'bank_transfer',
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    reference VARCHAR(150) NULL,
    notes VARCHAR(255) NULL,
    paid_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_billing_invoice_payments_invoice (billing_invoice_id),
    INDEX idx_billing_invoice_payments_user (recorded_by_user_id),
    CONSTRAINT fk_billing_invoice_payments_invoice FOREIGN KEY (billing_invoice_id) REFERENCES billing_invoices(id) ON DELETE CASCADE,
    CONSTRAINT fk_billing_invoice_payments_user FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO company_subscriptions (
    company_id,
    billing_plan_id,
    plan_name_snapshot,
    billing_cycle,
    amount,
    currency,
    status,
    trial_ends_at,
    current_period_start,
    current_period_end,
    next_invoice_at,
    grace_ends_at,
    max_branches,
    max_users,
    max_products,
    max_monthly_sales,
    auto_renew,
    notes,
    created_at,
    updated_at
)
SELECT
    c.id,
    default_plan.id,
    default_plan.name,
    default_plan.billing_cycle,
    default_plan.price,
    default_plan.currency,
    'active',
    NULL,
    NOW(),
    CASE default_plan.billing_cycle
        WHEN 'quarterly' THEN DATE_ADD(NOW(), INTERVAL 3 MONTH)
        WHEN 'yearly' THEN DATE_ADD(NOW(), INTERVAL 1 YEAR)
        ELSE DATE_ADD(NOW(), INTERVAL 1 MONTH)
    END,
    CASE default_plan.billing_cycle
        WHEN 'quarterly' THEN DATE_ADD(NOW(), INTERVAL 3 MONTH)
        WHEN 'yearly' THEN DATE_ADD(NOW(), INTERVAL 1 YEAR)
        ELSE DATE_ADD(NOW(), INTERVAL 1 MONTH)
    END,
    NULL,
    default_plan.max_branches,
    default_plan.max_users,
    default_plan.max_products,
    default_plan.max_monthly_sales,
    1,
    'Imported from the pre-billing environment.',
    NOW(),
    NOW()
FROM companies c
INNER JOIN (
    SELECT *
    FROM billing_plans
    WHERE is_default = 1
    ORDER BY id ASC
    LIMIT 1
) default_plan ON 1 = 1
WHERE NOT EXISTS (
    SELECT 1
    FROM company_subscriptions cs
    WHERE cs.company_id = c.id
)
AND EXISTS (
    SELECT 1
    FROM users u
    WHERE u.company_id = c.id
      AND u.deleted_at IS NULL
      AND COALESCE(u.is_platform_admin, 0) = 0
);
