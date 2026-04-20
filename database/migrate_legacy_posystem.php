<?php

declare(strict_types=1);

$config = [
    'host' => '127.0.0.1',
    'port' => 3306,
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
    'source_database' => 'posystem',
    'target_database' => 'pos_system',
];

$dsn = sprintf(
    'mysql:host=%s;port=%d;charset=%s',
    $config['host'],
    $config['port'],
    $config['charset']
);

$pdo = new PDO(
    $dsn,
    $config['username'],
    $config['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$sourceDatabase = quoteIdentifier($config['source_database']);
$targetDatabase = quoteIdentifier($config['target_database']);

$sourceCounts = [
    'categories' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$sourceDatabase}.categories"),
    'customers' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$sourceDatabase}.customers"),
    'products' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$sourceDatabase}.products"),
    'sales' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$sourceDatabase}.sales"),
    'users' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$sourceDatabase}.users"),
];

foreach ($sourceCounts as $table => $count) {
    if ($count === 0) {
        throw new RuntimeException("Legacy source table {$table} is empty.");
    }
}

$legacyCategories = fetchAll($pdo, "SELECT * FROM {$sourceDatabase}.categories ORDER BY id");
$legacyCustomers = fetchAll($pdo, "SELECT * FROM {$sourceDatabase}.customers ORDER BY id");
$legacyProducts = fetchAll($pdo, "SELECT * FROM {$sourceDatabase}.products ORDER BY id");
$legacyUsers = fetchAll($pdo, "SELECT * FROM {$sourceDatabase}.users ORDER BY id");
$legacySales = fetchAll($pdo, "SELECT * FROM {$sourceDatabase}.sales ORDER BY saledate, id");

$legacyProductsById = [];
foreach ($legacyProducts as $product) {
    $legacyProductsById[(int) $product['id']] = $product;
}

$legacyCustomersById = [];
foreach ($legacyCustomers as $customer) {
    $legacyCustomersById[(int) $customer['id']] = $customer;
}

$missingProductIds = [];
foreach ($legacySales as $sale) {
    foreach (decodeLegacySaleItems((string) $sale['products'], (int) $sale['id']) as $item) {
        $productId = (int) $item['id'];
        if ($productId > 0 && !isset($legacyProductsById[$productId])) {
            $missingProductIds[$productId] = [
                'id' => $productId,
                'description' => trim((string) ($item['description'] ?? 'Legacy Product')),
                'price' => (float) ($item['price'] ?? 0),
                'sale_date' => (string) $sale['saledate'],
            ];
        }
    }
}

$pdo->beginTransaction();

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    $tablesToClear = [
        'return_items',
        'returns',
        'customer_credit_transactions',
        'loyalty_points',
        'payments',
        'sale_items',
        'sale_void_requests',
        'sales',
        'purchase_order_items',
        'purchase_orders',
        'stock_transfer_items',
        'stock_transfers',
        'stock_movements',
        'inventory',
        'product_variants',
        'products',
        'categories',
        'customers',
        'expenses',
        'expense_categories',
        'suppliers',
        'notifications',
        'audit_logs',
        'password_reset_tokens',
        'login_attempts',
    ];

    foreach ($tablesToClear as $table) {
        $pdo->exec("DELETE FROM {$targetDatabase}." . quoteIdentifier($table));
    }

    $pdo->exec("DELETE FROM {$targetDatabase}.users WHERE id IN (1, 2, 3, 4, 5)");

    insertPlaceholderCustomerIfNeeded($pdo, $targetDatabase, $legacySales, $legacyCustomersById);
    importLegacyCustomers($pdo, $targetDatabase, $legacyCustomers);
    importLegacyCategories($pdo, $targetDatabase, $legacyCategories);
    importLegacyProducts($pdo, $targetDatabase, $legacyProducts, $missingProductIds);
    importLegacyUsers($pdo, $targetDatabase, $legacyUsers);
    importInventorySnapshots($pdo, $targetDatabase, $legacyProducts, $missingProductIds);
    importLegacySales($pdo, $targetDatabase, $legacySales, $legacyProductsById, $legacyCustomersById);
    recordMigrationAudit($pdo, $targetDatabase, $sourceCounts, count($missingProductIds));

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    throw $exception;
}

$summary = [
    'categories' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.categories"),
    'customers' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.customers"),
    'products' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.products"),
    'inventory' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.inventory"),
    'stock_movements' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.stock_movements"),
    'users' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.users"),
    'sales' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.sales"),
    'sale_items' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.sale_items"),
    'payments' => (int) scalar($pdo, "SELECT COUNT(*) FROM {$targetDatabase}.payments"),
];

foreach ($summary as $table => $count) {
    fwrite(STDOUT, sprintf("%s: %d\n", $table, $count));
}

function importLegacyCategories(PDO $pdo, string $targetDatabase, array $legacyCategories): void
{
    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.categories (
            id, parent_id, name, slug, description, created_at, updated_at, deleted_at
        ) VALUES (
            :id, NULL, :name, :slug, :description, :created_at, :updated_at, NULL
        )"
    );

    foreach ($legacyCategories as $category) {
        $id = (int) $category['id'];
        $name = trim((string) $category['Category']);
        $timestamp = normalizeDateTime((string) $category['Date']);

        $statement->execute([
            'id' => $id,
            'name' => truncate($name !== '' ? $name : "Legacy Category {$id}", 150),
            'slug' => buildSlug($name !== '' ? $name : "legacy-category-{$id}", $id, 190),
            'description' => 'Imported from legacy posystem categories.',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function insertPlaceholderCustomerIfNeeded(PDO $pdo, string $targetDatabase, array $legacySales, array $legacyCustomersById): void
{
    $missingCustomerIds = [];
    $earliestSaleDate = null;

    foreach ($legacySales as $sale) {
        $legacyCustomerId = (int) $sale['idCustomer'];
        $saleDate = normalizeDateTime((string) $sale['saledate']);
        if ($earliestSaleDate === null || $saleDate < $earliestSaleDate) {
            $earliestSaleDate = $saleDate;
        }

        if ($legacyCustomerId !== 0 && !isset($legacyCustomersById[$legacyCustomerId])) {
            $missingCustomerIds[$legacyCustomerId] = true;
        }
    }

    if ($missingCustomerIds === []) {
        return;
    }

    $timestamp = $earliestSaleDate ?? date('Y-m-d H:i:s');

    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.customers (
            id, branch_id, customer_group_id, first_name, last_name, email, phone, address,
            credit_balance, loyalty_balance, special_pricing_type, special_pricing_value,
            created_at, updated_at, deleted_at
        ) VALUES (
            :id, 1, 1, 'Legacy', :last_name, NULL, NULL, NULL,
            0, 0, 'none', 0,
            :created_at, :updated_at, NULL
        )"
    );

    foreach (array_keys($missingCustomerIds) as $customerId) {
        $statement->execute([
            'id' => (int) $customerId,
            'last_name' => truncate("Customer {$customerId}", 100),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function importLegacyCustomers(PDO $pdo, string $targetDatabase, array $legacyCustomers): void
{
    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.customers (
            id, branch_id, customer_group_id, first_name, last_name, email, phone, address,
            credit_balance, loyalty_balance, special_pricing_type, special_pricing_value,
            created_at, updated_at, deleted_at
        ) VALUES (
            :id, 1, 1, :first_name, :last_name, :email, :phone, :address,
            0, 0, 'none', 0,
            :created_at, :updated_at, NULL
        )"
    );

    foreach ($legacyCustomers as $customer) {
        $nameParts = splitName((string) $customer['name']);
        $email = normalizeNullableString((string) ($customer['email'] ?? ''));
        $phone = normalizeNullableString((string) ($customer['phone'] ?? ''));
        $address = normalizeNullableString((string) ($customer['address'] ?? ''));
        $timestamp = normalizeDateTime((string) $customer['registerDate']);

        $statement->execute([
            'id' => (int) $customer['id'],
            'first_name' => truncate($nameParts['first_name'], 100),
            'last_name' => truncate($nameParts['last_name'], 100),
            'email' => $email !== null ? truncate(strtolower($email), 150) : null,
            'phone' => $phone !== null ? truncate($phone, 50) : null,
            'address' => $address !== null ? truncate($address, 255) : null,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function importLegacyProducts(PDO $pdo, string $targetDatabase, array $legacyProducts, array $missingProductIds): void
{
    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.products (
            id, branch_id, category_id, supplier_id, tax_id, name, slug, sku, barcode,
            description, image_path, unit, price, cost_price, low_stock_threshold, track_stock,
            status, inventory_method, created_at, updated_at, deleted_at
        ) VALUES (
            :id, 1, :category_id, NULL, 3, :name, :slug, :sku, :barcode,
            :description, :image_path, 'pcs', :price, :cost_price, 0, 1,
            'active', 'FIFO', :created_at, :updated_at, NULL
        )"
    );

    foreach ($legacyProducts as $product) {
        $id = (int) $product['id'];
        $legacyCode = trim((string) $product['code']);
        $legacyDescription = trim((string) $product['description']);
        $name = $legacyCode !== '' ? $legacyCode : ($legacyDescription !== '' ? $legacyDescription : "Legacy Product {$id}");
        $description = normalizeNullableString($legacyDescription);
        $timestamp = normalizeDateTime((string) $product['date']);
        $categoryId = (int) $product['idCategory'];

        $statement->execute([
            'id' => $id,
            'category_id' => $categoryId > 0 ? $categoryId : null,
            'name' => truncate($name, 180),
            'slug' => buildSlug($name, $id, 190),
            'sku' => buildIdentifier('LEG-SKU', $id, 120),
            'barcode' => buildIdentifier('LEG-BC', $id, 120),
            'description' => $description !== null ? truncate($description, 65535) : null,
            'image_path' => normalizeNullableString((string) ($product['image'] ?? '')),
            'price' => normalizeDecimal((float) $product['sellingPrice']),
            'cost_price' => normalizeDecimal((float) $product['buyingPrice']),
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    foreach ($missingProductIds as $missingProduct) {
        $id = (int) $missingProduct['id'];
        $name = trim((string) $missingProduct['description']);
        $name = $name !== '' ? $name : "Legacy Product {$id}";
        $timestamp = normalizeDateTime((string) $missingProduct['sale_date']);

        $statement->execute([
            'id' => $id,
            'category_id' => null,
            'name' => truncate($name, 180),
            'slug' => buildSlug($name, $id, 190),
            'sku' => buildIdentifier('LEG-SKU', $id, 120),
            'barcode' => buildIdentifier('LEG-BC', $id, 120),
            'description' => 'Placeholder imported because this product exists in legacy sales but not in the legacy products table.',
            'image_path' => null,
            'price' => normalizeDecimal((float) $missingProduct['price']),
            'cost_price' => '0.00',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function importLegacyUsers(PDO $pdo, string $targetDatabase, array $legacyUsers): void
{
    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.users (
            id, branch_id, role_id, first_name, last_name, email, phone, password, status,
            remember_token, remember_expires_at, last_login_at, last_activity_at,
            failed_login_attempts, locked_until, created_at, updated_at, deleted_at
        ) VALUES (
            :id, 1, :role_id, :first_name, :last_name, :email, NULL, :password, :status,
            NULL, NULL, :last_login_at, :last_activity_at,
            0, NULL, :created_at, :updated_at, NULL
        )"
    );

    foreach ($legacyUsers as $user) {
        $id = (int) $user['id'];
        $nameParts = splitName((string) $user['name']);
        $timestamp = normalizeDateTime((string) $user['date']);
        $lastLogin = normalizeNullableDateTime((string) ($user['lastLogin'] ?? ''));
        $roleId = legacyRoleId((string) $user['profile'], $id);

        $statement->execute([
            'id' => $id,
            'role_id' => $roleId,
            'first_name' => truncate($nameParts['first_name'], 100),
            'last_name' => truncate($nameParts['last_name'], 100),
            'email' => buildLegacyUserEmail($user),
            'password' => (string) $user['password'],
            'status' => ((int) $user['status']) === 1 ? 'active' : 'inactive',
            'last_login_at' => $lastLogin,
            'last_activity_at' => $lastLogin,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function importInventorySnapshots(PDO $pdo, string $targetDatabase, array $legacyProducts, array $missingProductIds): void
{
    $inventoryStatement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.inventory (
            product_id, branch_id, quantity_on_hand, quantity_reserved, average_cost,
            valuation_method, last_restocked_at, created_at, updated_at
        ) VALUES (
            :product_id, 1, :quantity_on_hand, 0, :average_cost,
            'FIFO', :last_restocked_at, :created_at, :updated_at
        )"
    );

    $movementStatement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.stock_movements (
            product_id, branch_id, user_id, movement_type, reason, reference_type,
            reference_id, quantity_change, balance_after, unit_cost, created_at
        ) VALUES (
            :product_id, 1, NULL, 'opening', :reason, 'legacy_import',
            :reference_id, :quantity_change, :balance_after, :unit_cost, :created_at
        )"
    );

    foreach ($legacyProducts as $product) {
        $productId = (int) $product['id'];
        $stock = (float) $product['stock'];
        $cost = (float) $product['buyingPrice'];
        $timestamp = normalizeDateTime((string) $product['date']);

        $inventoryStatement->execute([
            'product_id' => $productId,
            'quantity_on_hand' => normalizeDecimal($stock),
            'average_cost' => normalizeDecimal($cost),
            'last_restocked_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        if (abs($stock) < 0.00001) {
            continue;
        }

        $movementStatement->execute([
            'product_id' => $productId,
            'reason' => 'Imported current stock snapshot from legacy posystem.',
            'reference_id' => $productId,
            'quantity_change' => normalizeDecimal($stock),
            'balance_after' => normalizeDecimal($stock),
            'unit_cost' => normalizeDecimal($cost),
            'created_at' => $timestamp,
        ]);
    }

    foreach ($missingProductIds as $missingProduct) {
        $productId = (int) $missingProduct['id'];
        $timestamp = normalizeDateTime((string) $missingProduct['sale_date']);

        $inventoryStatement->execute([
            'product_id' => $productId,
            'quantity_on_hand' => '0.00',
            'average_cost' => '0.00',
            'last_restocked_at' => $timestamp,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }
}

function importLegacySales(PDO $pdo, string $targetDatabase, array $legacySales, array $legacyProductsById, array $legacyCustomersById): void
{
    $saleStatement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.sales (
            id, branch_id, customer_id, user_id, sale_number, status, subtotal,
            item_discount_total, order_discount_total, loyalty_discount_total,
            loyalty_points_redeemed, tax_total, grand_total, amount_paid, change_due,
            notes, held_until, completed_at, void_reason, approved_by,
            created_at, updated_at, deleted_at
        ) VALUES (
            :id, 1, :customer_id, :user_id, :sale_number, 'completed', :subtotal,
            0, 0, 0,
            0, :tax_total, :grand_total, :amount_paid, 0,
            :notes, NULL, :completed_at, NULL, NULL,
            :created_at, :updated_at, NULL
        )"
    );

    $saleItemStatement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.sale_items (
            sale_id, product_id, variant_id, product_name, sku, barcode, quantity,
            unit_price, discount_type, discount_value, discount_total, tax_rate,
            tax_total, line_total, created_at, updated_at
        ) VALUES (
            :sale_id, :product_id, NULL, :product_name, :sku, :barcode, :quantity,
            :unit_price, 'fixed', 0, 0, 0,
            0, :line_total, :created_at, :updated_at
        )"
    );

    $paymentStatement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.payments (
            sale_id, payment_method, amount, reference, notes, paid_at, created_at
        ) VALUES (
            :sale_id, :payment_method, :amount, :reference, :notes, :paid_at, :created_at
        )"
    );

    foreach ($legacySales as $sale) {
        $saleId = (int) $sale['id'];
        $legacyCustomerId = (int) $sale['idCustomer'];
        $saleDate = normalizeDateTime((string) $sale['saledate']);
        $saleCode = (int) $sale['code'];
        $notes = [
            "Imported from legacy posystem sale {$saleCode}.",
        ];

        if ($legacyCustomerId !== 0 && !isset($legacyCustomersById[$legacyCustomerId])) {
            $notes[] = "Referenced legacy customer {$legacyCustomerId}, which no longer exists in the source customer table.";
        }

        $saleStatement->execute([
            'id' => $saleId,
            'customer_id' => $legacyCustomerId === 0 ? null : $legacyCustomerId,
            'user_id' => (int) $sale['idSeller'],
            'sale_number' => truncate(sprintf('LEG-%d-%d', $saleCode, $saleId), 120),
            'subtotal' => normalizeDecimal((float) $sale['netPrice']),
            'tax_total' => normalizeDecimal((float) $sale['tax']),
            'grand_total' => normalizeDecimal((float) $sale['totalPrice']),
            'amount_paid' => normalizeDecimal((float) $sale['totalPrice']),
            'notes' => truncate(implode(' ', $notes), 65535),
            'completed_at' => $saleDate,
            'created_at' => $saleDate,
            'updated_at' => $saleDate,
        ]);

        foreach (decodeLegacySaleItems((string) $sale['products'], $saleId) as $item) {
            $productId = (int) $item['id'];
            $sourceProduct = $legacyProductsById[$productId] ?? null;
            $fallbackName = trim((string) ($item['description'] ?? 'Legacy Product'));
            $productName = $sourceProduct !== null
                ? (trim((string) $sourceProduct['code']) !== '' ? trim((string) $sourceProduct['code']) : $fallbackName)
                : ($fallbackName !== '' ? $fallbackName : "Legacy Product {$productId}");

            $saleItemStatement->execute([
                'sale_id' => $saleId,
                'product_id' => $productId,
                'product_name' => truncate($productName, 180),
                'sku' => buildIdentifier('LEG-SKU', $productId, 120),
                'barcode' => buildIdentifier('LEG-BC', $productId, 120),
                'quantity' => normalizeDecimal((float) ($item['quantity'] ?? 0)),
                'unit_price' => normalizeDecimal((float) ($item['price'] ?? 0)),
                'line_total' => normalizeDecimal((float) ($item['totalPrice'] ?? 0)),
                'created_at' => $saleDate,
                'updated_at' => $saleDate,
            ]);

        }

        $payment = normalizeLegacyPayment((string) ($sale['paymentMethod'] ?? ''));
        $paymentStatement->execute([
            'sale_id' => $saleId,
            'payment_method' => $payment['method'],
            'amount' => normalizeDecimal((float) $sale['totalPrice']),
            'reference' => $payment['reference'],
            'notes' => $payment['notes'],
            'paid_at' => $saleDate,
            'created_at' => $saleDate,
        ]);
    }
}

function recordMigrationAudit(PDO $pdo, string $targetDatabase, array $sourceCounts, int $placeholderProducts): void
{
    $statement = $pdo->prepare(
        "INSERT INTO {$targetDatabase}.audit_logs (
            user_id, action, entity_type, entity_id, description, ip_address, user_agent, created_at
        ) VALUES (
            NULL, 'legacy_import', 'database', NULL, :description, '127.0.0.1', 'database/migrate_legacy_posystem.php', NOW()
        )"
    );

    $description = sprintf(
        'Imported legacy posystem data into pos_system. Categories: %d, customers: %d, products: %d, sales: %d, users: %d, placeholder products: %d.',
        $sourceCounts['categories'],
        $sourceCounts['customers'],
        $sourceCounts['products'],
        $sourceCounts['sales'],
        $sourceCounts['users'],
        $placeholderProducts
    );

    $statement->execute([
        'description' => $description,
    ]);
}

function fetchAll(PDO $pdo, string $sql): array
{
    return $pdo->query($sql)->fetchAll();
}

function scalar(PDO $pdo, string $sql): mixed
{
    return $pdo->query($sql)->fetchColumn();
}

function decodeLegacySaleItems(string $json, int $saleId): array
{
    $decoded = json_decode($json, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to decode sale items for legacy sale {$saleId}.");
    }

    return $decoded;
}

function splitName(string $name): array
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return ['first_name' => 'Legacy', 'last_name' => 'Record'];
    }

    $parts = preg_split('/\s+/', $name) ?: [];
    $firstName = array_shift($parts) ?: 'Legacy';
    $lastName = trim(implode(' ', $parts));

    if ($lastName === '') {
        $lastName = 'Legacy';
    }

    return [
        'first_name' => $firstName,
        'last_name' => $lastName,
    ];
}

function normalizeDateTime(string $value): string
{
    $value = trim($value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return date('Y-m-d H:i:s');
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
}

function normalizeNullableDateTime(string $value): ?string
{
    $value = trim($value);
    if ($value === '' || $value === '0000-00-00 00:00:00') {
        return null;
    }

    $timestamp = strtotime($value);

    return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
}

function normalizeNullableString(string $value): ?string
{
    $value = trim($value);

    return $value === '' ? null : $value;
}

function buildSlug(string $value, int $suffix, int $maxLength): string
{
    $base = strtolower(trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $value), '-'));
    if ($base === '') {
        $base = 'legacy-item';
    }

    $suffixText = (string) $suffix;
    $maxBaseLength = max(1, $maxLength - strlen($suffixText) - 1);
    $base = substr($base, 0, $maxBaseLength);

    return $base . '-' . $suffixText;
}

function buildIdentifier(string $prefix, int $id, int $maxLength): string
{
    return substr($prefix . '-' . $id, 0, $maxLength);
}

function truncate(string $value, int $maxLength): string
{
    return strlen($value) <= $maxLength ? $value : substr($value, 0, $maxLength);
}

function normalizeDecimal(float $value): string
{
    return number_format($value, 2, '.', '');
}

function buildLegacyUserEmail(array $user): string
{
    $source = trim((string) ($user['user'] ?? ''));
    if ($source === '') {
        $source = trim((string) ($user['name'] ?? 'legacy-user'));
    }

    $local = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $source));
    $local = trim($local, '-');
    if ($local === '') {
        $local = 'legacy-user';
    }

    $local = substr($local, 0, 48);

    return $local . '-' . (int) $user['id'] . '@legacy.optionone.local';
}

function legacyRoleId(string $profile, int $legacyUserId): int
{
    $profile = strtolower(trim($profile));
    if ($profile === 'administrator' && $legacyUserId === 13) {
        return 1;
    }

    return match ($profile) {
        'administrator' => 2,
        'seller' => 4,
        default => 4,
    };
}

function normalizeLegacyPayment(string $paymentMethod): array
{
    $paymentMethod = trim($paymentMethod);
    $lower = strtolower($paymentMethod);

    if ($paymentMethod === '' || $lower === 'cash') {
        return [
            'method' => 'cash',
            'reference' => null,
            'notes' => null,
        ];
    }

    if ($lower === 'card') {
        return [
            'method' => 'card',
            'reference' => null,
            'notes' => null,
        ];
    }

    if (str_starts_with(strtoupper($paymentMethod), 'MM-')) {
        return [
            'method' => 'mobile_money',
            'reference' => truncate($paymentMethod, 150),
            'notes' => null,
        ];
    }

    return [
        'method' => 'cash',
        'reference' => null,
        'notes' => truncate("Imported legacy payment method value: {$paymentMethod}", 255),
    ];
}

function quoteIdentifier(string $value): string
{
    return '`' . str_replace('`', '``', $value) . '`';
}
