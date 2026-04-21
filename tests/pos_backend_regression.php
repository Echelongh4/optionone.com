<?php

declare(strict_types=1);

use App\Controllers\PosController;
use App\Core\HttpException;
use App\Models\Sale;

require __DIR__ . '/bootstrap.php';

$tests = [];

$tests['controller decodes valid cart json'] = static function (): void {
    $controller = new PosController();
    $result = test_invoke_method($controller, 'decodeJson', ['[{"product_id":1,"quantity":2}]', 'cart']);

    test_assert_true(is_array($result), 'Decoded cart payload should be an array.');
    test_assert_same(1, (int) $result[0]['product_id'], 'Decoded cart payload should preserve product_id.');
    test_assert_same(2, (int) $result[0]['quantity'], 'Decoded cart payload should preserve quantity.');
};

$tests['controller rejects malformed json payload'] = static function (): void {
    $controller = new PosController();

    test_assert_throws(
        static fn () => test_invoke_method($controller, 'decodeJson', ['{"bad"', 'payments']),
        HttpException::class,
        'payments data is invalid'
    );
};

$tests['controller rejects non-array json payload'] = static function (): void {
    $controller = new PosController();

    test_assert_throws(
        static fn () => test_invoke_method($controller, 'decodeJson', ['"scalar"', 'cart']),
        HttpException::class,
        'cart data is invalid'
    );
};

$tests['sale summarizes exact cash payment'] = static function (): void {
    $sale = new Sale();
    $summary = test_invoke_method($sale, 'summarizePayments', [[
        ['method' => 'cash', 'amount' => 100],
    ], 100.0, null]);

    test_assert_same(100.0, $summary['collected_amount'], 'Collected amount should equal the cash amount.');
    test_assert_same(0.0, $summary['change_due'], 'Exact cash should not create change.');
    test_assert_same(0.0, $summary['credit_amount'], 'Exact cash should not create credit.');
};

$tests['sale summarizes cash overpayment with change'] = static function (): void {
    $sale = new Sale();
    $summary = test_invoke_method($sale, 'summarizePayments', [[
        ['method' => 'cash', 'amount' => 200],
    ], 125.0, null]);

    test_assert_same(200.0, $summary['collected_amount'], 'Collected amount should include all cash tendered.');
    test_assert_same(75.0, $summary['change_due'], 'Cash overpayment should compute change due.');
};

$tests['sale rejects credit allocation without customer'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'summarizePayments', [[
            ['method' => 'credit', 'amount' => 20],
        ], 20.0, null]),
        HttpException::class,
        'Select a customer before assigning part of the sale to credit'
    );
};

$tests['sale rejects non-cash over-collection'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'summarizePayments', [[
            ['method' => 'card', 'amount' => 90],
            ['method' => 'mobile_money', 'amount' => 20],
        ], 100.0, null]),
        HttpException::class,
        'cannot exceed the balance due'
    );
};

$tests['sale supports mixed credit and cash settlement'] = static function (): void {
    $sale = new Sale();
    $summary = test_invoke_method($sale, 'summarizePayments', [[
        ['method' => 'credit', 'amount' => 40],
        ['method' => 'cash', 'amount' => 60],
    ], 100.0, 55]);

    test_assert_same(60.0, $summary['collected_amount'], 'Collected amount should include only non-credit payments.');
    test_assert_same(40.0, $summary['credit_amount'], 'Credit amount should be preserved separately.');
    test_assert_same(0.0, $summary['change_due'], 'Exact mixed settlement should not create change.');
};

$tests['draft cheque payments normalize date and reference'] = static function (): void {
    $sale = new Sale();
    $payments = test_invoke_method($sale, 'sanitizeDraftPayments', [[
        [
            'method' => 'cheque',
            'amount' => 50,
            'cheque_number' => 'CHK-001',
            'cheque_bank' => 'Bank A',
            'cheque_date' => '2026-04-21',
            'reference' => '',
        ],
    ], 77]);

    test_assert_same('CHK-001', $payments[0]['reference'], 'Cheque reference should default to the cheque number.');
    test_assert_same('2026-04-21', $payments[0]['cheque_date'], 'Cheque date should be normalized.');
};

$tests['draft credit payments require customer'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'sanitizeDraftPayments', [[
            ['method' => 'credit', 'amount' => 15],
        ], null]),
        HttpException::class,
        'Select a customer before saving part of the sale to credit'
    );
};

$failures = [];

foreach ($tests as $label => $callback) {
    try {
        $callback();
        echo "[PASS] {$label}" . PHP_EOL;
    } catch (Throwable $throwable) {
        $failures[] = [
            'label' => $label,
            'message' => $throwable->getMessage(),
            'class' => $throwable::class,
        ];
        echo "[FAIL] {$label} :: {$throwable->getMessage()}" . PHP_EOL;
    }
}

if ($failures !== []) {
    echo PHP_EOL . 'Failures: ' . count($failures) . PHP_EOL;
    exit(1);
}

echo PHP_EOL . 'POS backend regression checks passed: ' . count($tests) . PHP_EOL;
