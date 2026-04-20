<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\ThermalPrinterService;
use Throwable;

class PosController extends Controller
{
    private const HOLD_SUBMISSION_SCOPE = 'pos_hold';
    private const CHECKOUT_SUBMISSION_SCOPE = 'pos_checkout';

    public function index(Request $request): void
    {
        $saleModel = new Sale();
        $recallSale = null;
        $heldId = (int) $request->query('held_id', 0);
        $receiptModal = Session::pullFlash('pos_receipt_modal');

        if ($heldId > 0) {
            $recallSale = $saleModel->findDetailed($heldId);
        }

        $seedCustomers = [];
        $recallCustomerId = isset($recallSale['customer_id']) && $recallSale['customer_id'] !== null
            ? (int) $recallSale['customer_id']
            : null;
        if ($recallCustomerId !== null && $recallCustomerId > 0) {
            $seedCustomers = (new Customer())->suggestForPos($this->branchId(), '', 1, $recallCustomerId);
        }
        $productModel = new Product();
        $catalogMeta = $productModel->catalogMetaForPos($this->branchId());
        $catalogPage = $productModel->catalogPageForPos($this->branchId(), [
            'page' => 1,
            'page_size' => 8,
        ]);
        if (is_array($recallSale) && !empty($recallSale['items'])) {
            $recallProductIds = array_values(array_unique(array_map(static fn (array $item): int => (int) ($item['product_id'] ?? 0), (array) $recallSale['items'])));
            $recallProducts = $productModel->catalogForPosByIds($recallProductIds, $this->branchId());
            $catalogMap = [];
            foreach ((array) ($catalogPage['items'] ?? []) as $item) {
                $catalogMap[(int) ($item['id'] ?? 0)] = $item;
            }
            foreach ($recallProducts as $item) {
                $catalogMap[(int) ($item['id'] ?? 0)] = $item;
            }
            $catalogPage['items'] = array_values($catalogMap);
        }

        $this->render('pos/index', [
            'title' => 'POS Terminal',
            'breadcrumbs' => ['Dashboard', 'POS Terminal'],
            'catalog' => $catalogPage['items'] ?? [],
            'catalogMeta' => $catalogMeta,
            'catalogFilteredTotal' => (int) ($catalogPage['filtered_total'] ?? 0),
            'customers' => $seedCustomers,
            'heldSales' => $saleModel->heldSales($this->branchId()),
            'recallSale' => $recallSale,
            'receiptModal' => is_array($receiptModal) ? $receiptModal : null,
            'holdSubmissionKey' => $this->issueSubmissionKey(self::HOLD_SUBMISSION_SCOPE),
            'checkoutSubmissionKey' => $this->issueSubmissionKey(self::CHECKOUT_SUBMISSION_SCOPE),
        ]);
    }

    public function catalog(Request $request): void
    {
        $recentIds = array_filter(array_map(static fn (string $value): int => (int) trim($value), explode(',', (string) $request->query('recent_ids', ''))), static fn (int $id): bool => $id > 0);
        $page = (new Product())->catalogPageForPos($this->branchId(), [
            'page' => (int) $request->query('page', 1),
            'page_size' => (int) $request->query('page_size', 8),
            'search' => (string) $request->query('q', ''),
            'brand' => (string) $request->query('brand', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'stock_filter' => (string) $request->query('stock_filter', 'all'),
            'quick_mode' => (string) $request->query('quick_mode', 'all'),
            'sort' => (string) $request->query('sort', 'relevance'),
            'recent_ids' => $recentIds,
        ]);

        header('Content-Type: application/json');
        echo json_encode([
            'items' => array_map([$this, 'serializePosCatalogItem'], $page['items'] ?? []),
            'filtered_total' => (int) ($page['filtered_total'] ?? 0),
            'page' => (int) ($page['page'] ?? 1),
            'page_size' => (int) ($page['page_size'] ?? 8),
        ]);
    }

    public function hold(Request $request): void
    {
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::HOLD_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            if (!$request->isAjax() && !empty($duplicateSubmission['receiptModal']) && is_array($duplicateSubmission['receiptModal'])) {
                Session::flash('pos_receipt_modal', $duplicateSubmission['receiptModal']);
            }
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey(self::HOLD_SUBMISSION_SCOPE, $submissionKey)) {
            $message = 'This hold request has expired. Reload the POS page and try again.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('pos');
        }

        $cart = $this->decodeJson($request->input('cart_payload'));

        try {
            $saleId = (new Sale())->hold(
                items: $cart,
                orderDiscount: [
                    'type' => (string) $request->input('order_discount_type', 'fixed'),
                    'value' => (float) $request->input('order_discount_value', 0),
                ],
                customerId: $this->nullableInt($request->input('customer_id')),
                userId: (int) Auth::id(),
                branchId: $this->branchId(),
                notes: trim((string) $request->input('notes', '')),
                redeemPoints: max(0, (int) $request->input('redeem_points', 0)),
                heldSaleId: $this->nullableInt($request->input('held_sale_id'))
            );
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('pos');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'hold',
            entityType: 'sale',
            entityId: $saleId,
            description: 'Held a POS transaction.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $receiptModal = [
            'href' => url('pos/receipt?id=' . $saleId),
            'title' => 'Held Sale Slip',
            'size' => 'lg',
        ];
        $redirect = 'pos';
        $payload = [
            'success' => true,
            'message' => 'Sale held successfully.',
            'flash_message' => 'Sale held successfully.',
            'pos_action' => 'hold',
            'sale_id' => $saleId,
            'held_sale' => $this->heldSaleSummary((new Sale())->findDetailed($saleId)),
            'receiptModal' => $receiptModal,
        ];
        if (!$request->isAjax()) {
            $payload['redirect'] = url($redirect);
            $payload['redirect_path'] = $redirect;
        }
        $this->rememberProcessedSubmission(self::HOLD_SUBMISSION_SCOPE, $submissionKey, $payload);

        Session::flash('success', 'Sale held successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($payload);
            return;
        }

        Session::flash('pos_receipt_modal', $receiptModal);
        $this->redirect($redirect);
    }

    public function checkout(Request $request): void
    {
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::CHECKOUT_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            if (!$request->isAjax() && !empty($duplicateSubmission['receiptModal']) && is_array($duplicateSubmission['receiptModal'])) {
                Session::flash('pos_receipt_modal', $duplicateSubmission['receiptModal']);
            }
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey(self::CHECKOUT_SUBMISSION_SCOPE, $submissionKey)) {
            $message = 'This checkout request has expired. Reload the POS page and try again.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('pos');
        }

        $cart = $this->decodeJson($request->input('cart_payload'));
        $payments = $this->decodeJson($request->input('payments_payload'));

        try {
            $saleId = (new Sale())->checkout(
                items: $cart,
                payments: $payments,
                orderDiscount: [
                    'type' => (string) $request->input('order_discount_type', 'fixed'),
                    'value' => (float) $request->input('order_discount_value', 0),
                ],
                customerId: $this->nullableInt($request->input('customer_id')),
                userId: (int) Auth::id(),
                branchId: $this->branchId(),
                notes: trim((string) $request->input('notes', '')),
                redeemPoints: max(0, (int) $request->input('redeem_points', 0)),
                heldSaleId: $this->nullableInt($request->input('held_sale_id'))
            );
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('pos');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'checkout',
            entityType: 'sale',
            entityId: $saleId,
            description: 'Completed a POS sale.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $receiptModal = [
            'href' => url('pos/receipt?id=' . $saleId),
            'title' => 'Sale Receipt',
            'size' => 'lg',
        ];
        $redirect = 'pos';
        $payload = [
            'success' => true,
            'message' => 'Sale completed successfully.',
            'flash_message' => 'Sale completed successfully.',
            'pos_action' => 'checkout',
            'sale_id' => $saleId,
            'completed_sale_id' => $saleId,
            'held_sale_id' => $this->nullableInt($request->input('held_sale_id')),
            'receiptModal' => $receiptModal,
        ];
        if (!$request->isAjax()) {
            $payload['redirect'] = url($redirect);
            $payload['redirect_path'] = $redirect;
        }
        $this->rememberProcessedSubmission(self::CHECKOUT_SUBMISSION_SCOPE, $submissionKey, $payload);

        Session::flash('success', 'Sale completed successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($payload);
            return;
        }

        Session::flash('pos_receipt_modal', $receiptModal);
        $this->redirect($redirect);
    }

    public function receipt(Request $request): void
    {
        $sale = (new Sale())->receipt((int) $request->query('id'));

        if ($sale === null) {
            throw new HttpException(404, 'Receipt not found.');
        }

        $this->render('pos/receipt', [
            'title' => 'Receipt',
            'breadcrumbs' => ['Dashboard', 'POS Terminal', 'Receipt'],
            'sale' => $sale,
            'embedded' => $request->isAjax(),
        ]);
    }

    public function printReceipt(Request $request): void
    {
        $saleId = (int) ($request->input('sale_id', $request->query('id', 0)));
        $sale = (new Sale())->receipt($saleId);

        if ($sale === null) {
            throw new HttpException(404, 'Receipt not found.');
        }

        try {
            (new ThermalPrinterService())->printSaleReceipt($sale);
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('pos/receipt?id=' . $saleId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'print',
            entityType: 'sale',
            entityId: $saleId,
            description: 'Printed a thermal receipt for sale ' . ($sale['sale_number'] ?? '#' . $saleId) . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = 'Receipt sent to the thermal printer.';
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('pos/receipt?id=' . $saleId);
    }

    private function decodeJson(mixed $payload): array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return [];
        }

        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function heldSaleSummary(?array $sale): ?array
    {
        if ($sale === null) {
            return null;
        }

        $createdAt = trim((string) ($sale['created_at'] ?? ''));

        return [
            'id' => (string) ($sale['id'] ?? ''),
            'sale_number' => (string) ($sale['sale_number'] ?? ''),
            'grand_total' => (float) ($sale['grand_total'] ?? 0),
            'customer_name' => (string) ($sale['customer_name'] ?? 'Walk-in customer'),
            'created_at' => $createdAt,
            'created_label' => $createdAt !== '' ? date('M d, H:i', strtotime($createdAt)) : 'Queued',
        ];
    }

    private function serializePosCatalogItem(array $product): array
    {
        $imagePath = trim((string) ($product['image_path'] ?? ''));
        $categoryName = trim((string) ($product['category_name'] ?? ''));
        $stockQuantity = (float) ($product['stock_quantity'] ?? 0);
        $lowStockThreshold = (float) ($product['low_stock_threshold'] ?? 0);

        return [
            'id' => (string) ($product['id'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'brand' => (string) ($product['brand'] ?? ''),
            'sku' => (string) ($product['sku'] ?? ''),
            'barcode' => (string) ($product['barcode'] ?? ''),
            'unit' => (string) ($product['unit'] ?? 'unit'),
            'price' => (float) ($product['price'] ?? 0),
            'tax_rate' => (float) ($product['tax_rate'] ?? 0),
            'category_id' => (string) ($product['category_id'] ?? ''),
            'category_name' => $categoryName !== '' ? $categoryName : 'Uncategorized',
            'track_stock' => (int) ($product['track_stock'] ?? 0),
            'stock_quantity' => $stockQuantity,
            'low_stock_threshold' => $lowStockThreshold,
            'is_low_stock' => (int) ($product['track_stock'] ?? 0) === 1 && $stockQuantity <= max($lowStockThreshold, 0),
            'image_url' => $imagePath !== '' ? url($imagePath) : '',
            'search_blob' => strtolower(trim(implode(' ', [
                (string) ($product['name'] ?? ''),
                (string) ($product['brand'] ?? ''),
                (string) ($product['sku'] ?? ''),
                (string) ($product['barcode'] ?? ''),
                $categoryName,
                (string) ($product['unit'] ?? ''),
            ]))),
        ];
    }
}
