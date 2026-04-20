<?php

declare(strict_types=1);

// Usage: php database/clear_products_and_sales.php
// This script truncates product- and sale-related tables. Back up your database first.

require __DIR__ . '/../config/bootstrap.php';

use App\Core\Database;

$pdo = Database::connection();

$tables = [
    // Sales-related
    'payments',
    'sale_items',
    'sale_void_requests',
    'customer_credit_transactions',
    'loyalty_points',
    'return_items',
    'returns',
    'sales',

    // Product/inventory-related
    'stock_movements',
    'inventory',
    'product_variants',
    'products',
];

echo "This will TRUNCATE the following tables:\n" . implode(', ', $tables) . "\n";

// Quick safety check when run interactively
if (php_sapi_name() === 'cli') {
    $handle = fopen('php://stdin', 'r');
    echo "Type 'YES' to proceed: ";
    $line = trim(fgets($handle));
    if ($line !== 'YES') {
        echo "Aborted by user. No changes made.\n";
        exit(1);
    }
}

try {
    $pdo->beginTransaction();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    foreach ($tables as $table) {
        $pdo->exec("TRUNCATE TABLE `{$table}`");
        echo "Truncated {$table}\n";
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    $pdo->commit();

    echo "Done. All listed tables truncated successfully.\n";
    echo "Run the application and add new products/sales to verify behavior.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo "Error: " . $e->getMessage() . "\n";
    echo "No changes were applied (transaction rolled back).\n";
    exit(1);
}
