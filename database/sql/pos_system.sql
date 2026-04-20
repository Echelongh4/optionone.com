CREATE DATABASE IF NOT EXISTS pos_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pos_system;

CREATE TABLE IF NOT EXISTS branches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    code VARCHAR(40) NOT NULL UNIQUE,
    address VARCHAR(255) NULL,
    phone VARCHAR(50) NULL,
    email VARCHAR(150) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_branches_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    module VARCHAR(100) NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_role_permissions_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_role_permissions_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(50) NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    remember_token VARCHAR(64) NULL,
    remember_expires_at DATETIME NULL,
    last_login_at DATETIME NULL,
    last_activity_at DATETIME NULL,
    failed_login_attempts INT NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_users_role (role_id),
    INDEX idx_users_branch (branch_id),
    CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    ip_address VARCHAR(64) NOT NULL,
    attempted_at DATETIME NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    INDEX idx_login_attempts_email_ip (email, ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_password_reset_token_hash (token_hash),
    INDEX idx_password_reset_user (user_id),
    INDEX idx_password_reset_email (email),
    INDEX idx_password_reset_expires (expires_at),
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(150) NOT NULL UNIQUE,
    value_text TEXT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS taxes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    rate DECIMAL(8,2) NOT NULL DEFAULT 0,
    inclusive TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    parent_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_categories_parent (parent_id),
    CONSTRAINT fk_categories_parent FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS suppliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    contact_person VARCHAR(150) NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    tax_number VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_suppliers_branch (branch_id),
    CONSTRAINT fk_suppliers_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NULL,
    supplier_id BIGINT UNSIGNED NULL,
    tax_id BIGINT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    brand VARCHAR(120) NULL,
    slug VARCHAR(190) NOT NULL UNIQUE,
    sku VARCHAR(120) NOT NULL UNIQUE,
    barcode VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    image_path VARCHAR(255) NULL,
    unit ENUM('pcs', 'kg', 'litre', 'box') NOT NULL DEFAULT 'pcs',
    price DECIMAL(12,2) NOT NULL DEFAULT 0,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    low_stock_threshold DECIMAL(12,2) NOT NULL DEFAULT 0,
    track_stock TINYINT(1) NOT NULL DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    inventory_method ENUM('FIFO', 'LIFO') NOT NULL DEFAULT 'FIFO',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_products_branch (branch_id),
    INDEX idx_products_category (category_id),
    INDEX idx_products_supplier (supplier_id),
    INDEX idx_products_tax (tax_id),
    CONSTRAINT fk_products_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    CONSTRAINT fk_products_tax FOREIGN KEY (tax_id) REFERENCES taxes(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS product_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    variant_value VARCHAR(100) NOT NULL,
    sku VARCHAR(120) NULL,
    barcode VARCHAR(120) NULL,
    price_adjustment DECIMAL(12,2) NOT NULL DEFAULT 0,
    stock_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_product_variants_product (product_id),
    CONSTRAINT fk_product_variants_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    quantity_on_hand DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity_reserved DECIMAL(12,2) NOT NULL DEFAULT 0,
    average_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    valuation_method ENUM('FIFO', 'LIFO') NOT NULL DEFAULT 'FIFO',
    last_restocked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_inventory_product_branch (product_id, branch_id),
    INDEX idx_inventory_branch (branch_id),
    CONSTRAINT fk_inventory_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventory_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    movement_type ENUM('purchase', 'sale', 'return', 'adjustment', 'transfer_in', 'transfer_out', 'void', 'opening') NOT NULL,
    reason VARCHAR(255) NULL,
    reference_type VARCHAR(80) NULL,
    reference_id BIGINT UNSIGNED NULL,
    quantity_change DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_stock_movements_product (product_id),
    INDEX idx_stock_movements_branch (branch_id),
    INDEX idx_stock_movements_user (user_id),
    CONSTRAINT fk_stock_movements_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_movements_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NOT NULL,
    supplier_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    po_number VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('draft', 'ordered', 'partial_received', 'received', 'cancelled') NOT NULL DEFAULT 'draft',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    ordered_at DATETIME NULL,
    expected_at DATETIME NULL,
    received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_purchase_orders_branch (branch_id),
    INDEX idx_purchase_orders_supplier (supplier_id),
    CONSTRAINT fk_purchase_orders_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_purchase_orders_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    CONSTRAINT fk_purchase_orders_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS purchase_order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    purchase_order_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    received_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL,
    received_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    last_received_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_po_items_order FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_po_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customer_groups (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    discount_type ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NULL,
    customer_group_id BIGINT UNSIGNED NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NULL,
    phone VARCHAR(50) NULL,
    address VARCHAR(255) NULL,
    credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
    loyalty_balance INT NOT NULL DEFAULT 0,
    special_pricing_type ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none',
    special_pricing_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_customers_branch (branch_id),
    INDEX idx_customers_group (customer_group_id),
    CONSTRAINT fk_customers_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL,
    CONSTRAINT fk_customers_group FOREIGN KEY (customer_group_id) REFERENCES customer_groups(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loyalty_points (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id BIGINT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NULL,
    points INT NOT NULL,
    transaction_type ENUM('earn', 'redeem', 'adjustment') NOT NULL,
    balance_after INT NOT NULL,
    notes VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_loyalty_customer (customer_id),
    CONSTRAINT fk_loyalty_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    sale_number VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('held', 'completed', 'voided', 'refunded', 'partial_return') NOT NULL DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    item_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    order_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    loyalty_discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    loyalty_points_redeemed INT NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    grand_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount_paid DECIMAL(12,2) NOT NULL DEFAULT 0,
    change_due DECIMAL(12,2) NOT NULL DEFAULT 0,
    notes TEXT NULL,
    held_until DATETIME NULL,
    completed_at DATETIME NULL,
    void_reason VARCHAR(255) NULL,
    approved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_sales_branch (branch_id),
    INDEX idx_sales_customer (customer_id),
    INDEX idx_sales_user (user_id),
    CONSTRAINT fk_sales_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_sales_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_sales_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS sale_void_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reason VARCHAR(255) NOT NULL,
    review_notes VARCHAR(255) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_void_requests_sale (sale_id),
    INDEX idx_void_requests_status (status),
    INDEX idx_void_requests_requested_by (requested_by),
    CONSTRAINT fk_void_requests_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_void_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_void_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS sale_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    variant_id BIGINT UNSIGNED NULL,
    product_name VARCHAR(180) NOT NULL,
    sku VARCHAR(120) NULL,
    barcode VARCHAR(120) NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount_type ENUM('fixed', 'percent') NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_rate DECIMAL(8,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sale_items_sale (sale_id),
    INDEX idx_sale_items_product (product_id),
    CONSTRAINT fk_sale_items_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_sale_items_product FOREIGN KEY (product_id) REFERENCES products(id),
    CONSTRAINT fk_sale_items_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE loyalty_points
    ADD CONSTRAINT fk_loyalty_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('cash', 'card', 'mobile_money', 'cheque', 'split', 'credit') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    reference VARCHAR(150) NULL,
    cheque_number VARCHAR(120) NULL,
    cheque_bank VARCHAR(150) NULL,
    cheque_date DATE NULL,
    notes VARCHAR(255) NULL,
    paid_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_payments_sale (sale_id),
    CONSTRAINT fk_payments_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NULL,
    return_number VARCHAR(120) NOT NULL UNIQUE,
    reason VARCHAR(255) NULL,
    status ENUM('pending', 'completed', 'rejected') NOT NULL DEFAULT 'completed',
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_refund DECIMAL(12,2) NOT NULL DEFAULT 0,
    approved_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_returns_sale (sale_id),
    CONSTRAINT fk_returns_sale FOREIGN KEY (sale_id) REFERENCES sales(id),
    CONSTRAINT fk_returns_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_returns_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    CONSTRAINT fk_returns_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS return_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    return_id BIGINT UNSIGNED NOT NULL,
    sale_item_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    tax_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    line_total DECIMAL(12,2) NOT NULL,
    reason VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_return_items_return FOREIGN KEY (return_id) REFERENCES returns(id) ON DELETE CASCADE,
    CONSTRAINT fk_return_items_sale_item FOREIGN KEY (sale_item_id) REFERENCES sale_items(id),
    CONSTRAINT fk_return_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS expense_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    branch_id BIGINT UNSIGNED NOT NULL,
    expense_category_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    description VARCHAR(255) NULL,
    receipt_path VARCHAR(255) NULL,
    status ENUM('draft', 'approved', 'rejected') NOT NULL DEFAULT 'approved',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    INDEX idx_expenses_branch (branch_id),
    INDEX idx_expenses_category (expense_category_id),
    CONSTRAINT fk_expenses_branch FOREIGN KEY (branch_id) REFERENCES branches(id),
    CONSTRAINT fk_expenses_category FOREIGN KEY (expense_category_id) REFERENCES expense_categories(id),
    CONSTRAINT fk_expenses_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    branch_id BIGINT UNSIGNED NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    link_url VARCHAR(255) NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    send_email TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user (user_id),
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_notifications_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_logs_user (user_id),
    INDEX idx_audit_logs_entity (entity_type, entity_id),
    CONSTRAINT fk_audit_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transfers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_branch_id BIGINT UNSIGNED NOT NULL,
    destination_branch_id BIGINT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    reference_number VARCHAR(120) NOT NULL UNIQUE,
    status ENUM('draft', 'in_transit', 'completed', 'cancelled') NOT NULL DEFAULT 'draft',
    notes VARCHAR(255) NULL,
    transfer_date DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_transfers_source FOREIGN KEY (source_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_stock_transfers_destination FOREIGN KEY (destination_branch_id) REFERENCES branches(id),
    CONSTRAINT fk_stock_transfers_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stock_transfer_id BIGINT UNSIGNED NOT NULL,
    product_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_stock_transfer_items_transfer FOREIGN KEY (stock_transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
    CONSTRAINT fk_stock_transfer_items_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

USE pos_system;

INSERT INTO branches (id, name, code, address, phone, email, is_default, status, created_at, updated_at) VALUES
(1, 'Main Branch', 'MAIN', '12 Market Square, Reykjavik', '+3547001000', 'main@novapos.test', 1, 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'Harbor Branch', 'HARBOR', '88 Ocean Drive, Reykjavik', '+3547001001', 'harbor@novapos.test', 0, 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO roles (id, name, description, created_at, updated_at) VALUES
(1, 'Super Admin', 'Platform owner with unrestricted access.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'Admin', 'Operational administrator with wide permissions.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 'Manager', 'Branch manager handling reports and oversight.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, 'Cashier', 'Frontline operator for sales transactions.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO permissions (id, name, module, description, created_at, updated_at) VALUES
(1, 'view_dashboard', 'dashboard', 'View the analytics dashboard.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'manage_products', 'products', 'Create and update products.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 'access_pos', 'pos', 'Access the POS terminal.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, 'manage_inventory', 'inventory', 'Manage stock and inventory.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(5, 'manage_reports', 'reports', 'Access reporting.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(6, 'manage_users', 'users', 'Manage user accounts.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(7, 'manage_settings', 'settings', 'Manage business settings.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(8, 'manage_expenses', 'expenses', 'Log and review expenses.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(9, 'manage_customers', 'customers', 'Manage customer profiles.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(10, 'manage_sales', 'sales', 'View and operate on sales.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(11, 'approve_voids', 'sales', 'Approve transaction voids.', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(12, 'manage_branches', 'settings', 'Manage branches and transfers.', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES
(1,1,'2026-03-01 08:00:00'),(1,2,'2026-03-01 08:00:00'),(1,3,'2026-03-01 08:00:00'),(1,4,'2026-03-01 08:00:00'),(1,5,'2026-03-01 08:00:00'),(1,6,'2026-03-01 08:00:00'),(1,7,'2026-03-01 08:00:00'),(1,8,'2026-03-01 08:00:00'),(1,9,'2026-03-01 08:00:00'),(1,10,'2026-03-01 08:00:00'),(1,11,'2026-03-01 08:00:00'),(1,12,'2026-03-01 08:00:00'),
(2,1,'2026-03-01 08:00:00'),(2,2,'2026-03-01 08:00:00'),(2,3,'2026-03-01 08:00:00'),(2,4,'2026-03-01 08:00:00'),(2,5,'2026-03-01 08:00:00'),(2,6,'2026-03-01 08:00:00'),(2,7,'2026-03-01 08:00:00'),(2,8,'2026-03-01 08:00:00'),(2,9,'2026-03-01 08:00:00'),(2,10,'2026-03-01 08:00:00'),(2,11,'2026-03-01 08:00:00'),(2,12,'2026-03-01 08:00:00'),
(3,1,'2026-03-01 08:00:00'),(3,2,'2026-03-01 08:00:00'),(3,3,'2026-03-01 08:00:00'),(3,4,'2026-03-01 08:00:00'),(3,5,'2026-03-01 08:00:00'),(3,8,'2026-03-01 08:00:00'),(3,9,'2026-03-01 08:00:00'),(3,10,'2026-03-01 08:00:00'),(3,11,'2026-03-01 08:00:00'),
(4,1,'2026-03-01 08:00:00'),(4,3,'2026-03-01 08:00:00'),(4,9,'2026-03-01 08:00:00'),(4,10,'2026-03-01 08:00:00');

INSERT INTO taxes (id, name, rate, inclusive, created_at, updated_at) VALUES
(1, 'VAT 7.5%', 7.50, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'GST 15%', 15.00, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 'Zero Rated', 0.00, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO categories (id, parent_id, name, slug, description, created_at, updated_at) VALUES
(1, NULL, 'Beverages', 'beverages', 'Drinks and refreshments', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'Soft Drinks', 'soft-drinks', 'Carbonated beverages', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, NULL, 'Snacks', 'snacks', 'Packaged snacks', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, NULL, 'Electronics', 'electronics', 'Accessories and devices', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(5, 4, 'Mobile Accessories', 'mobile-accessories', 'Chargers, cables, and adapters', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO suppliers (id, branch_id, name, contact_person, email, phone, address, tax_number, created_at, updated_at) VALUES
(1, 1, 'FreshFlow Distributors', 'Amina Cole', 'orders@freshflow.test', '+3547002000', '45 Harbor Street', 'SUP-111', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'RetailHub Wholesale', 'Jon Eriksen', 'sales@retailhub.test', '+3547003000', '18 Tech Avenue', 'SUP-222', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO customer_groups (id, name, discount_type, discount_value, description, created_at, updated_at) VALUES
(1, 'Retail', 'none', 0.00, 'Default customer tier', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'VIP', 'percentage', 5.00, 'Preferred customers', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO users (id, branch_id, role_id, first_name, last_name, username, email, phone, password, status, remember_token, remember_expires_at, last_login_at, last_activity_at, failed_login_attempts, locked_until, created_at, updated_at) VALUES
(1, 1, 1, 'Nova', 'Owner', 'superadmin', 'superadmin@novapos.test', '+3547004001', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:00:00', '2026-03-18 08:30:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:30:00'),
(2, 1, 2, 'Leah', 'Admin', 'admin', 'admin@novapos.test', '+3547004002', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:35:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:35:00'),
(3, 1, 3, 'Mika', 'Manager', 'manager', 'manager@novapos.test', '+3547004003', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:12:00', '2026-03-18 08:38:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:38:00'),
(4, 1, 4, 'Kai', 'Cashier', 'cashier', 'cashier@novapos.test', '+3547004004', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:15:00', '2026-03-18 08:40:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:40:00');

INSERT INTO users (id, branch_id, role_id, first_name, last_name, username, email, phone, password, status, remember_token, remember_expires_at, last_login_at, last_activity_at, failed_login_attempts, locked_until, created_at, updated_at) VALUES
(5, 2, 3, 'Nora', 'Harbor Manager', 'harbor.manager', 'harbor.manager@novapos.test', '+3547004005', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:18:00', '2026-03-18 08:42:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:42:00');

INSERT INTO settings (id, key_name, value_text, type, created_at, updated_at) VALUES
(1, 'business_name', 'NovaPOS Demo Store', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'business_address', '12 Market Square, Reykjavik', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 'business_phone', '+3547001000', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, 'currency', 'USD', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(5, 'receipt_header', 'Thank you for shopping with NovaPOS', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(6, 'receipt_footer', 'Goods sold are subject to store policy.', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(7, 'barcode_format', 'CODE128', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(8, 'tax_default', 'VAT 7.5%', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(9, 'multi_branch_enabled', 'true', 'boolean', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO customers (id, branch_id, customer_group_id, first_name, last_name, email, phone, address, credit_balance, loyalty_balance, special_pricing_type, special_pricing_value, created_at, updated_at) VALUES
(1, 1, 1, 'John', 'Doe', 'john.doe@testmail.com', '+3547005001', '101 Oak Street', 0.00, 1, 'none', 0.00, '2026-03-02 09:00:00', '2026-03-18 07:30:00'),
(2, 1, 2, 'Sarah', 'Lee', 'sarah.lee@testmail.com', '+3547005002', '22 Pine Street', 12.50, 3, 'percentage', 5.00, '2026-03-03 10:00:00', '2026-03-18 07:30:00');

INSERT INTO expense_categories (id, name, description, created_at, updated_at) VALUES
(1, 'Utilities', 'Electricity, water, and services', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 'Transport', 'Fuel and travel', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 'Internet', 'Connectivity costs', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO products (id, branch_id, category_id, supplier_id, tax_id, name, brand, slug, sku, barcode, description, image_path, unit, price, cost_price, low_stock_threshold, track_stock, status, inventory_method, created_at, updated_at) VALUES
(1, 1, 2, 1, 1, 'Cola 500ml', 'FizzUp', 'cola-500ml-demo', 'SKU-COLA500', '260318100001', 'Chilled carbonated drink', NULL, 'pcs', 1.50, 0.80, 12.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(2, 1, 1, 1, 1, 'Orange Juice 1L', 'SunFresh', 'orange-juice-1l-demo', 'SKU-OJ1000', '260318100002', 'Fresh orange juice', NULL, 'pcs', 3.80, 2.40, 6.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(3, 1, 3, 1, 1, 'Chocolate Bar', 'Cocoa House', 'chocolate-bar-demo', 'SKU-CHOC100', '260318100003', 'Premium milk chocolate snack', NULL, 'pcs', 2.50, 1.20, 10.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(4, 1, 5, 2, 2, 'FastCharge Adapter', 'VoltEdge', 'fastcharge-adapter-demo', 'SKU-FASTADP', '260318100004', 'USB fast charger adapter', NULL, 'pcs', 15.00, 8.50, 5.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(5, 1, 5, 2, 2, 'USB-C Cable', 'VoltEdge', 'usb-c-cable-demo', 'SKU-USBC001', '260318100005', 'Durable USB-C cable', NULL, 'pcs', 6.00, 2.50, 5.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00');

INSERT INTO product_variants (id, product_id, variant_name, variant_value, sku, barcode, price_adjustment, stock_quantity, created_at, updated_at) VALUES
(1, 4, 'Color', 'Black', 'SKU-FASTADP-BLK', '260318200001', 0.00, 2.00, '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(2, 4, 'Color', 'White', 'SKU-FASTADP-WHT', '260318200002', 0.00, 3.00, '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(3, 5, 'Length', '1m', 'SKU-USBC001-1M', '260318200003', 0.00, 2.00, '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(4, 5, 'Length', '2m', 'SKU-USBC001-2M', '260318200004', 1.50, 1.00, '2026-03-05 08:00:00', '2026-03-05 08:00:00');

INSERT INTO inventory (id, product_id, branch_id, quantity_on_hand, quantity_reserved, average_cost, valuation_method, last_restocked_at, created_at, updated_at) VALUES
(1, 1, 1, 48.00, 0.00, 0.80, 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00'),
(2, 2, 1, 16.00, 0.00, 2.40, 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00'),
(3, 3, 1, 8.00, 0.00, 1.20, 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00'),
(4, 4, 1, 5.00, 0.00, 8.50, 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00'),
(5, 5, 1, 3.00, 0.00, 2.50, 'FIFO', '2026-03-16 10:00:00', '2026-03-05 08:00:00', '2026-03-18 08:00:00');

INSERT INTO stock_movements (id, product_id, branch_id, user_id, movement_type, reason, reference_type, reference_id, quantity_change, balance_after, unit_cost, created_at) VALUES
(1, 1, 1, NULL, 'opening', 'Initial stock', 'product', 1, 48.00, 48.00, 0.80, '2026-03-05 08:00:00'),
(2, 2, 1, NULL, 'opening', 'Initial stock', 'product', 2, 16.00, 16.00, 2.40, '2026-03-05 08:00:00'),
(3, 3, 1, NULL, 'opening', 'Initial stock', 'product', 3, 8.00, 8.00, 1.20, '2026-03-05 08:00:00'),
(4, 4, 1, NULL, 'opening', 'Initial stock', 'product', 4, 5.00, 5.00, 8.50, '2026-03-05 08:00:00'),
(5, 5, 1, NULL, 'opening', 'Initial stock', 'product', 5, 3.00, 3.00, 2.50, '2026-03-05 08:00:00');

INSERT INTO expenses (id, branch_id, expense_category_id, user_id, amount, expense_date, description, receipt_path, status, created_at, updated_at) VALUES
(1, 1, 1, 2, 85.00, '2026-03-12', 'Electricity bill', NULL, 'approved', '2026-03-12 17:00:00', '2026-03-12 17:00:00'),
(2, 1, 2, 3, 36.00, '2026-03-15', 'Delivery fuel top-up', NULL, 'approved', '2026-03-15 17:00:00', '2026-03-15 17:00:00'),
(3, 1, 3, 2, 24.00, '2026-03-17', 'Internet subscription', NULL, 'approved', '2026-03-17 17:00:00', '2026-03-17 17:00:00');

INSERT INTO purchase_orders (id, branch_id, supplier_id, created_by, po_number, status, subtotal, tax_total, total, notes, ordered_at, expected_at, received_at, created_at, updated_at) VALUES
(1, 1, 1, 2, 'PO-20260310-001', 'ordered', 120.00, 9.00, 129.00, 'Replenishment for drinks and snacks', '2026-03-10 09:00:00', '2026-03-20 12:00:00', NULL, '2026-03-10 09:00:00', '2026-03-10 09:00:00');

INSERT INTO purchase_order_items (id, purchase_order_id, product_id, quantity, unit_cost, tax_rate, total, created_at, updated_at) VALUES
(1, 1, 1, 40.00, 0.80, 7.50, 34.40, '2026-03-10 09:00:00', '2026-03-10 09:00:00'),
(2, 1, 2, 20.00, 2.40, 7.50, 51.60, '2026-03-10 09:00:00', '2026-03-10 09:00:00'),
(3, 1, 3, 30.00, 1.20, 7.50, 38.70, '2026-03-10 09:00:00', '2026-03-10 09:00:00');

INSERT INTO sales (id, branch_id, customer_id, user_id, sale_number, status, subtotal, item_discount_total, order_discount_total, tax_total, grand_total, amount_paid, change_due, notes, held_until, completed_at, void_reason, approved_by, created_at, updated_at) VALUES
(1, 1, 1, 4, 'SAL-202603170901-101', 'completed', 11.00, 0.00, 0.00, 0.83, 11.83, 12.00, 0.17, 'Morning beverage sale', NULL, '2026-03-17 09:01:00', NULL, NULL, '2026-03-17 09:01:00', '2026-03-17 09:01:00'),
(2, 1, 2, 4, 'SAL-202603171405-102', 'completed', 27.00, 0.00, 0.00, 4.05, 31.05, 35.00, 3.95, 'Accessory bundle', NULL, '2026-03-17 14:05:00', NULL, NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00'),
(3, 1, NULL, 4, 'SAL-202603180810-103', 'completed', 7.60, 0.60, 0.00, 0.53, 7.53, 10.00, 2.47, 'Discounted juice sale', NULL, '2026-03-18 08:10:00', NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:10:00'),
(4, 1, 1, 4, 'HLD-202603181030-104', 'held', 9.00, 0.00, 0.00, 1.13, 10.13, 0.00, 0.00, 'Customer is browsing more items', '2026-03-19 10:30:00', NULL, NULL, NULL, '2026-03-18 10:30:00', '2026-03-18 10:30:00');

INSERT INTO sale_items (id, sale_id, product_id, variant_id, product_name, sku, barcode, quantity, unit_price, discount_type, discount_value, discount_total, tax_rate, tax_total, line_total, created_at, updated_at) VALUES
(1, 1, 1, NULL, 'Cola 500ml', 'SKU-COLA500', '260318100001', 4.00, 1.50, 'fixed', 0.00, 0.00, 7.50, 0.45, 6.45, '2026-03-17 09:01:00', '2026-03-17 09:01:00'),
(2, 1, 3, NULL, 'Chocolate Bar', 'SKU-CHOC100', '260318100003', 2.00, 2.50, 'fixed', 0.00, 0.00, 7.50, 0.38, 5.38, '2026-03-17 09:01:00', '2026-03-17 09:01:00'),
(3, 2, 4, NULL, 'FastCharge Adapter', 'SKU-FASTADP', '260318100004', 1.00, 15.00, 'fixed', 0.00, 0.00, 15.00, 2.25, 17.25, '2026-03-17 14:05:00', '2026-03-17 14:05:00'),
(4, 2, 5, NULL, 'USB-C Cable', 'SKU-USBC001', '260318100005', 2.00, 6.00, 'fixed', 0.00, 0.00, 15.00, 1.80, 13.80, '2026-03-17 14:05:00', '2026-03-17 14:05:00'),
(5, 3, 2, NULL, 'Orange Juice 1L', 'SKU-OJ1000', '260318100002', 2.00, 3.80, 'fixed', 0.60, 0.60, 7.50, 0.53, 7.53, '2026-03-18 08:10:00', '2026-03-18 08:10:00'),
(6, 4, 1, NULL, 'Cola 500ml', 'SKU-COLA500', '260318100001', 2.00, 1.50, 'fixed', 0.00, 0.00, 7.50, 0.23, 3.23, '2026-03-18 10:30:00', '2026-03-18 10:30:00'),
(7, 4, 5, NULL, 'USB-C Cable', 'SKU-USBC001', '260318100005', 1.00, 6.00, 'fixed', 0.00, 0.00, 15.00, 0.90, 6.90, '2026-03-18 10:30:00', '2026-03-18 10:30:00');

INSERT INTO payments (id, sale_id, payment_method, amount, reference, notes, paid_at, created_at) VALUES
(1, 1, 'cash', 12.00, NULL, NULL, '2026-03-17 09:01:00', '2026-03-17 09:01:00'),
(2, 2, 'card', 20.00, 'CARD-7788', NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00'),
(3, 2, 'mobile_money', 15.00, 'MM-9123', NULL, '2026-03-17 14:05:00', '2026-03-17 14:05:00'),
(4, 3, 'cash', 10.00, NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:10:00');

INSERT INTO loyalty_points (id, customer_id, sale_id, points, transaction_type, balance_after, notes, created_at) VALUES
(1, 1, 1, 1, 'earn', 1, 'Auto-earned from POS sale', '2026-03-17 09:01:00'),
(2, 2, 2, 3, 'earn', 3, 'Auto-earned from POS sale', '2026-03-17 14:05:00');

INSERT INTO customer_credit_transactions (id, customer_id, sale_id, return_id, user_id, transaction_type, amount, balance_after, notes, created_at) VALUES
(1, 2, NULL, NULL, 2, 'adjustment', 12.50, 12.50, 'Opening balance from seeded demo credit account.', '2026-03-18 08:20:00');

INSERT INTO notifications (id, user_id, branch_id, type, title, message, link_url, is_read, send_email, created_at) VALUES
(1, 2, 1, 'low_stock', 'Low stock alert', 'FastCharge Adapter reached its low stock threshold.', '/products', 0, 0, '2026-03-18 07:45:00'),
(2, 2, 1, 'low_stock', 'Low stock alert', 'USB-C Cable is below the configured threshold.', '/products', 0, 0, '2026-03-18 07:46:00');

INSERT INTO audit_logs (id, user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at) VALUES
(1, 2, 'login', 'user', 2, 'Admin logged into the POS system.', '127.0.0.1', 'Seeded browser', '2026-03-18 08:00:00'),
(2, 4, 'checkout', 'sale', 3, 'Completed a POS sale.', '127.0.0.1', 'Seeded browser', '2026-03-18 08:10:00'),
(3, 2, 'create', 'product', 5, 'Created product USB-C Cable.', '127.0.0.1', 'Seeded browser', '2026-03-05 08:05:00');

INSERT INTO sale_void_requests (id, sale_id, requested_by, status, reason, review_notes, reviewed_by, reviewed_at, created_at, updated_at) VALUES
(1, 2, 4, 'pending', 'Customer reported a duplicate charge and requested cancellation.', NULL, NULL, NULL, '2026-03-18 15:10:00', '2026-03-18 15:10:00');
INSERT INTO stock_transfers (id, source_branch_id, destination_branch_id, created_by, reference_number, status, notes, transfer_date, created_at, updated_at) VALUES
(1, 1, 2, 2, 'TRF-20260318-001', 'draft', 'Demo transfer placeholder for multi-branch scaffolding.', NULL, '2026-03-18 12:00:00', '2026-03-18 12:00:00');

INSERT INTO stock_transfer_items (id, stock_transfer_id, product_id, quantity, created_at) VALUES
(1, 1, 5, 2.00, '2026-03-18 12:00:00');
