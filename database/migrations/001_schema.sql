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
