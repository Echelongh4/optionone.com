USE pos_system;

INSERT INTO companies (id, name, slug, email, phone, address, status, created_at, updated_at) VALUES
(1, 'NovaPOS Demo Store', 'novapos-demo-store', 'admin@novapos.test', '+3547001000', '12 Market Square, Reykjavik', 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO branches (id, company_id, name, code, address, phone, email, is_default, status, created_at, updated_at) VALUES
(1, 1, 'Main Branch', 'MAIN', '12 Market Square, Reykjavik', '+3547001000', 'main@novapos.test', 1, 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'Harbor Branch', 'HARBOR', '88 Ocean Drive, Reykjavik', '+3547001001', 'harbor@novapos.test', 0, 'active', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

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

INSERT INTO taxes (id, company_id, name, rate, inclusive, created_at, updated_at) VALUES
(1, 1, 'VAT 7.5%', 7.50, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'GST 15%', 15.00, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 1, 'Zero Rated', 0.00, 0, '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO categories (id, company_id, parent_id, name, slug, description, created_at, updated_at) VALUES
(1, 1, NULL, 'Beverages', 'beverages', 'Drinks and refreshments', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 1, 'Soft Drinks', 'soft-drinks', 'Carbonated beverages', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 1, NULL, 'Snacks', 'snacks', 'Packaged snacks', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, 1, NULL, 'Electronics', 'electronics', 'Accessories and devices', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(5, 1, 4, 'Mobile Accessories', 'mobile-accessories', 'Chargers, cables, and adapters', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO suppliers (id, branch_id, name, contact_person, email, phone, address, tax_number, created_at, updated_at) VALUES
(1, 1, 'FreshFlow Distributors', 'Amina Cole', 'orders@freshflow.test', '+3547002000', '45 Harbor Street', 'SUP-111', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'RetailHub Wholesale', 'Jon Eriksen', 'sales@retailhub.test', '+3547003000', '18 Tech Avenue', 'SUP-222', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO customer_groups (id, company_id, name, discount_type, discount_value, description, created_at, updated_at) VALUES
(1, 1, 'Retail', 'none', 0.00, 'Default customer tier', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'VIP', 'percentage', 5.00, 'Preferred customers', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO users (id, company_id, is_platform_admin, branch_id, role_id, first_name, last_name, username, email, email_verified_at, phone, password, status, remember_token, remember_expires_at, last_login_at, last_activity_at, failed_login_attempts, locked_until, created_at, updated_at) VALUES
(1, 1, 1, 1, 1, 'Nova', 'Owner', 'superadmin', 'superadmin@novapos.test', '2026-03-01 08:00:00', '+3547004001', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:00:00', '2026-03-18 08:30:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:30:00'),
(2, 1, 0, 1, 2, 'Leah', 'Admin', 'admin', 'admin@novapos.test', '2026-03-01 08:00:00', '+3547004002', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:10:00', '2026-03-18 08:35:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:35:00'),
(3, 1, 0, 1, 3, 'Mika', 'Manager', 'manager', 'manager@novapos.test', '2026-03-01 08:00:00', '+3547004003', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:12:00', '2026-03-18 08:38:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:38:00'),
(4, 1, 0, 1, 4, 'Kai', 'Cashier', 'cashier', 'cashier@novapos.test', '2026-03-01 08:00:00', '+3547004004', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:15:00', '2026-03-18 08:40:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:40:00');

INSERT INTO users (id, company_id, is_platform_admin, branch_id, role_id, first_name, last_name, username, email, email_verified_at, phone, password, status, remember_token, remember_expires_at, last_login_at, last_activity_at, failed_login_attempts, locked_until, created_at, updated_at) VALUES
(5, 1, 0, 2, 3, 'Nora', 'Harbor Manager', 'harbor.manager', 'harbor.manager@novapos.test', '2026-03-01 08:00:00', '+3547004005', '$2y$10$apDcTKnQx8WNS6LBzPDSuucMxeAjijPrl7bhOMyJMm.ZX9aNDJuhi', 'active', NULL, NULL, '2026-03-18 08:18:00', '2026-03-18 08:42:00', 0, NULL, '2026-03-01 08:00:00', '2026-03-18 08:42:00');

INSERT INTO settings (id, company_id, key_name, value_text, type, created_at, updated_at) VALUES
(1, 1, 'business_name', 'NovaPOS Demo Store', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'business_address', '12 Market Square, Reykjavik', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 1, 'business_phone', '+3547001000', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(4, 1, 'currency', 'USD', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(5, 1, 'receipt_header', 'Thank you for shopping with NovaPOS', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(6, 1, 'receipt_footer', 'Goods sold are subject to store policy.', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(7, 1, 'barcode_format', 'CODE128', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(8, 1, 'tax_default', 'VAT 7.5%', 'string', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(9, 1, 'multi_branch_enabled', 'true', 'boolean', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO customers (id, branch_id, customer_group_id, first_name, last_name, email, phone, address, credit_balance, loyalty_balance, special_pricing_type, special_pricing_value, created_at, updated_at) VALUES
(1, 1, 1, 'John', 'Doe', 'john.doe@testmail.com', '+3547005001', '101 Oak Street', 0.00, 1, 'none', 0.00, '2026-03-02 09:00:00', '2026-03-18 07:30:00'),
(2, 1, 2, 'Sarah', 'Lee', 'sarah.lee@testmail.com', '+3547005002', '22 Pine Street', 12.50, 3, 'percentage', 5.00, '2026-03-03 10:00:00', '2026-03-18 07:30:00');

INSERT INTO expense_categories (id, company_id, name, description, created_at, updated_at) VALUES
(1, 1, 'Utilities', 'Electricity, water, and services', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(2, 1, 'Transport', 'Fuel and travel', '2026-03-01 08:00:00', '2026-03-01 08:00:00'),
(3, 1, 'Internet', 'Connectivity costs', '2026-03-01 08:00:00', '2026-03-01 08:00:00');

INSERT INTO products (id, company_id, branch_id, category_id, supplier_id, tax_id, name, brand, slug, sku, barcode, description, image_path, unit, price, cost_price, low_stock_threshold, track_stock, status, inventory_method, created_at, updated_at) VALUES
(1, 1, 1, 2, 1, 1, 'Cola 500ml', 'FizzUp', 'cola-500ml-demo', 'SKU-COLA500', '260318100001', 'Chilled carbonated drink', NULL, 'pcs', 1.50, 0.80, 12.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(2, 1, 1, 1, 1, 1, 'Orange Juice 1L', 'SunFresh', 'orange-juice-1l-demo', 'SKU-OJ1000', '260318100002', 'Fresh orange juice', NULL, 'pcs', 3.80, 2.40, 6.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(3, 1, 1, 3, 1, 1, 'Chocolate Bar', 'Cocoa House', 'chocolate-bar-demo', 'SKU-CHOC100', '260318100003', 'Premium milk chocolate snack', NULL, 'pcs', 2.50, 1.20, 10.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(4, 1, 1, 5, 2, 2, 'FastCharge Adapter', 'VoltEdge', 'fastcharge-adapter-demo', 'SKU-FASTADP', '260318100004', 'USB fast charger adapter', NULL, 'pcs', 15.00, 8.50, 5.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00'),
(5, 1, 1, 5, 2, 2, 'USB-C Cable', 'VoltEdge', 'usb-c-cable-demo', 'SKU-USBC001', '260318100005', 'Durable USB-C cable', NULL, 'pcs', 6.00, 2.50, 5.00, 1, 'active', 'FIFO', '2026-03-05 08:00:00', '2026-03-05 08:00:00');

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
