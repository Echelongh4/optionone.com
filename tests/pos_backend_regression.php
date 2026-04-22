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

$tests['controller accepts empty payload as empty array'] = static function (): void {
    $controller = new PosController();
    $result = test_invoke_method($controller, 'decodeJson', ['', 'payments']);

    test_assert_true(is_array($result), 'Empty payload should still decode to an array.');
    test_assert_same([], $result, 'Empty payload should become an empty array.');
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

$tests['sale rejects underpayment after allocations'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'summarizePayments', [[
            ['method' => 'cash', 'amount' => 40],
            ['method' => 'card', 'amount' => 20],
        ], 100.0, null]),
        HttpException::class,
        'less than the balance due'
    );
};

$tests['sale rejects credit above sale total'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'summarizePayments', [[
            ['method' => 'credit', 'amount' => 120],
        ], 100.0, 55]),
        HttpException::class,
        'Credit assigned cannot exceed the sale total'
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

$tests['draft non-cheque payments drop cheque-only fields'] = static function (): void {
    $sale = new Sale();
    $payments = test_invoke_method($sale, 'sanitizeDraftPayments', [[
        [
            'method' => 'cash',
            'amount' => 35,
            'cheque_number' => 'CHK-999',
            'cheque_bank' => 'Legacy Bank',
            'cheque_date' => '2026-04-21',
        ],
    ], 77]);

    test_assert_same(null, $payments[0]['cheque_number'], 'Cash drafts should not keep cheque numbers.');
    test_assert_same(null, $payments[0]['cheque_bank'], 'Cash drafts should not keep cheque banks.');
    test_assert_same(null, $payments[0]['cheque_date'], 'Cash drafts should not keep cheque dates.');
};

$tests['draft cheque payments reject invalid dates'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'sanitizeDraftPayments', [[
            [
                'method' => 'cheque',
                'amount' => 50,
                'cheque_number' => 'CHK-001',
                'cheque_bank' => 'Bank A',
                'cheque_date' => 'not-a-date',
            ],
        ], 77]),
        HttpException::class,
        'cheque dates are invalid'
    );
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

$tests['return lines compile proportional refund totals'] = static function (): void {
    $sale = new Sale();
    $compiled = test_invoke_method($sale, 'compileReturnLines', [[
        [
            'id' => 10,
            'product_id' => 44,
            'product_name' => 'Blue Pen',
            'quantity' => 4,
            'returned_quantity' => 1,
            'unit_price' => 15,
            'tax_total' => 6,
            'line_total' => 66,
        ],
    ], [
        [
            'sale_item_id' => 10,
            'quantity' => 2,
            'reason' => '',
        ],
    ], 'Damaged item']);

    test_assert_same(1, count($compiled['lines']), 'One return line should be compiled.');
    test_assert_same(30.0, round((float) $compiled['subtotal'], 2), 'Subtotal should reflect returned quantity times unit price.');
    test_assert_same(3.0, round((float) $compiled['tax_total'], 2), 'Tax should scale proportionally to the returned quantity.');
    test_assert_same(33.0, round((float) $compiled['refund_total'], 2), 'Refund total should scale proportionally to the returned line total.');
    test_assert_same('Damaged item', $compiled['lines'][0]['reason'], 'Blank line reasons should inherit the default reason.');
};

$tests['return lines reject quantities above remaining balance'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'compileReturnLines', [[
            [
                'id' => 10,
                'product_id' => 44,
                'product_name' => 'Blue Pen',
                'quantity' => 4,
                'returned_quantity' => 3,
                'unit_price' => 15,
                'tax_total' => 6,
                'line_total' => 66,
            ],
        ], [
            [
                'sale_item_id' => 10,
                'quantity' => 2,
            ],
        ], 'Customer return']),
        HttpException::class,
        'exceeds the remaining quantity'
    );
};

$tests['return lines require at least one valid quantity'] = static function (): void {
    $sale = new Sale();

    test_assert_throws(
        static fn () => test_invoke_method($sale, 'compileReturnLines', [[
            [
                'id' => 10,
                'product_id' => 44,
                'product_name' => 'Blue Pen',
                'quantity' => 4,
                'returned_quantity' => 0,
                'unit_price' => 15,
                'tax_total' => 6,
                'line_total' => 66,
            ],
        ], [
            [
                'sale_item_id' => 10,
                'quantity' => 0,
            ],
        ], 'Customer return']),
        HttpException::class,
        'Add at least one return line'
    );
};

$tests['held sale resume token is deterministic for same snapshot'] = static function (): void {
    $sale = new Sale();
    $snapshot = [
        'id' => 88,
        'status' => 'held',
        'updated_at' => '2026-04-22 10:30:00',
        'held_until' => '2026-04-23 10:30:00',
        'grand_total' => 155.5,
    ];

    $tokenA = $sale->heldSaleResumeToken($snapshot);
    $tokenB = $sale->heldSaleResumeToken($snapshot);

    test_assert_true($tokenA !== '', 'Held sale token should not be empty for a valid sale snapshot.');
    test_assert_same($tokenA, $tokenB, 'Held sale token should remain stable for the same snapshot.');
};

$tests['held sale resume token changes when held sale snapshot changes'] = static function (): void {
    $sale = new Sale();
    $baseline = [
        'id' => 88,
        'status' => 'held',
        'updated_at' => '2026-04-22 10:30:00',
        'held_until' => '2026-04-23 10:30:00',
        'grand_total' => 155.5,
    ];
    $changed = $baseline;
    $changed['updated_at'] = '2026-04-22 10:45:00';

    test_assert_true(
        $sale->heldSaleResumeToken($baseline) !== $sale->heldSaleResumeToken($changed),
        'Held sale token should change when the held sale snapshot changes.'
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
