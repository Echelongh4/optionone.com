<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Inventory;
use App\Models\Product;
use Throwable;

class InventoryController extends Controller
{
    private const ADJUST_SUBMISSION_SCOPE = 'inventory_adjust';

    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'stock_state' => trim((string) $request->query('stock_state', '')),
            'product_id' => trim((string) $request->query('product_id', '')),
            'sort' => trim((string) $request->query('sort', 'priority')),
            'movement_type' => trim((string) $request->query('movement_type', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $this->renderIndex($filters);
    }

    public function show(Request $request): void
    {
        $productId = (int) $request->query('id', 0);
        if ($productId <= 0) {
            Session::flash('error', 'Select a valid inventory item.');
            $this->redirect('inventory');
        }

        $inventoryModel = new Inventory();
        $item = $inventoryModel->findProduct($productId, $this->branchId());

        if ($item === null) {
            Session::flash('error', 'Inventory item not found for this branch.');
            $this->redirect('inventory');
        }

        $this->render('inventory/show', [
            'title' => 'Inventory Detail',
            'breadcrumbs' => ['Dashboard', 'Inventory', (string) $item['name']],
            'item' => $item,
            'movements' => $inventoryModel->movementFilters([
                'product_id' => (string) $productId,
            ], $this->branchId(), 60),
            'purchaseOrders' => $inventoryModel->recentPurchaseOrders($productId, $this->branchId()),
        ]);
    }

    public function adjust(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'stock_state' => trim((string) $request->query('stock_state', '')),
            'product_id' => trim((string) $request->query('product_id', '')),
            'sort' => trim((string) $request->query('sort', 'priority')),
            'movement_type' => trim((string) $request->query('movement_type', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
        $form = [
            'product_id' => (string) $request->input('product_id', ''),
            'direction' => (string) $request->input('direction', 'increase'),
            'quantity' => (string) $request->input('quantity', ''),
            'unit_cost' => (string) $request->input('unit_cost', ''),
            'reason' => trim((string) $request->input('reason', '')),
            'reason_code' => trim((string) $request->input('reason_code', '')),
        ];
        $respondAjaxError = static function (array $errors, string $message): void {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ]);
        };
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::ADJUST_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        $errors = Validator::validate($request->all(), [
            'product_id' => 'required|integer',
            'direction' => 'required|in:increase,decrease',
            'quantity' => 'required|numeric',
            'unit_cost' => 'nullable|numeric',
            'reason' => 'required|min:3|max:255',
        ]);

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey(self::ADJUST_SUBMISSION_SCOPE, $submissionKey)) {
            $errors['submission_key'][] = 'This inventory form expired. Reload the page and try again.';
        }

        if ((float) $form['quantity'] <= 0) {
            $errors['quantity'][] = 'Adjustment quantity must be greater than zero.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                $respondAjaxError($errors, 'Please review the inventory adjustment and try again.');
                return;
            }

            $this->renderIndex($filters, $errors, $form);
            return;
        }

        $product = (new Product())->find((int) $form['product_id'], $this->branchId());
        if ($product === null) {
            $errors['product_id'][] = 'Select a valid product.';
            if ($request->isAjax()) {
                $respondAjaxError($errors, 'Please review the inventory adjustment and try again.');
                return;
            }

            $this->renderIndex($filters, $errors, $form);
            return;
        }

        $quantityChange = (float) $form['quantity'] * ($form['direction'] === 'decrease' ? -1 : 1);
        $unitCost = $form['direction'] === 'increase'
            ? max((float) ($form['unit_cost'] !== '' ? $form['unit_cost'] : $product['cost_price']), 0)
            : 0.0;

        // Load allowed presets and threshold from config
        $allowedPresets = (array) config('app.inventory.presets', []);
        $largeThreshold = (int) config('app.inventory.large_adjustment_threshold', 1000);

        $reason = $form['reason'];
        if ($form['reason_code'] !== '' && in_array($form['reason_code'], $allowedPresets, true)) {
            $reason = '[' . $form['reason_code'] . '] ' . $reason;
        }

        // Require explicit confirmation for large increases
        if ($form['direction'] === 'increase' && abs($quantityChange) > $largeThreshold && $request->input('confirm_large') !== '1') {
            $errors['quantity'][] = 'This is a large stock increase (' . number_format($quantityChange, 2) . '). Please confirm to proceed.';
            if ($request->isAjax()) {
                $respondAjaxError($errors, 'Please confirm the large adjustment before continuing.');
                return;
            }

            $this->renderIndex($filters, $errors, $form);
            return;
        }

        try {
            (new Product())->adjustInventory(
                productId: (int) $product['id'],
                branchId: $this->branchId(),
                quantityChange: $quantityChange,
                movementType: 'adjustment',
                reason: $reason,
                userId: (int) Auth::id(),
                referenceType: 'manual_adjustment',
                referenceId: (int) $product['id'],
                unitCost: $unitCost
            );
        } catch (Throwable $exception) {
            $errors['general'][] = $exception->getMessage();
            if ($request->isAjax()) {
                $respondAjaxError($errors, 'Inventory adjustment failed.');
                return;
            }

            $this->renderIndex($filters, $errors, $form);
            return;
        }

        $auditDesc = 'Adjusted stock for ' . $product['name'] . ' by ' . $quantityChange . ' units.';
        if ($form['reason_code'] !== '' && in_array($form['reason_code'], $allowedPresets, true)) {
            $auditDesc .= ' Reason code: ' . $form['reason_code'] . '.';
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'adjust',
            entityType: 'inventory',
            entityId: (int) $product['id'],
            description: $auditDesc,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Inventory adjusted successfully.');
        $redirectPath = 'inventory';
        $redirectFilters = $filters;
        if ((int) $form['product_id'] > 0) {
            $redirectFilters['product_id'] = (string) (int) $form['product_id'];
        }

        $redirectFilters = array_filter(
            $redirectFilters,
            static fn ($value): bool => $value !== '' && $value !== null
        );

        if ($redirectFilters !== []) {
            $redirectPath .= '?' . http_build_query($redirectFilters);
        }

        $this->rememberProcessedSubmission(self::ADJUST_SUBMISSION_SCOPE, $submissionKey, [
            'success' => true,
            'message' => 'Inventory adjusted successfully.',
            'flash_message' => 'Inventory adjusted successfully.',
            'redirect_path' => $redirectPath,
        ]);

        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Inventory adjusted successfully.',
            ]);
            return;
        }

        $this->redirect($redirectPath);
    }

    private function renderIndex(array $filters, array $adjustErrors = [], array $adjustForm = []): void
    {
        $inventoryModel = new Inventory();
        $branchId = $this->branchId();
        $selectedProductId = (int) ($filters['product_id'] ?? 0);
        $selectedProduct = $selectedProductId > 0
            ? $inventoryModel->findProduct($selectedProductId, $branchId)
            : null;
        $summary = $inventoryModel->summary($branchId);
        $items = $inventoryModel->overview($filters, $branchId);
        $movementLimit = $selectedProduct !== null ? 80 : 40;
        $movements = $inventoryModel->movementFilters($filters, $branchId, $movementLimit);
        $products = $inventoryModel->adjustmentProducts($branchId);

        $this->render('inventory/index', [
            'title' => 'Inventory',
            'breadcrumbs' => ['Dashboard', 'Inventory'],
            'summary' => $summary,
            'items' => $items,
            'movements' => $movements,
            'movementLimit' => $movementLimit,
            'products' => $products,
            'selectedProduct' => $selectedProduct,
            'attentionItems' => $this->buildAttentionItems($items),
            'filterMeta' => [
                'active_count' => $this->activeFilterCount($filters),
                'has_advanced' => ($filters['movement_type'] ?? '') !== ''
                    || ($filters['date_from'] ?? '') !== ''
                    || ($filters['date_to'] ?? '') !== '',
            ],
            'filters' => $filters,
            'adjustErrors' => $adjustErrors,
            'adjustForm' => array_merge([
                'product_id' => $selectedProduct !== null ? (string) $selectedProduct['id'] : '',
                'direction' => 'increase',
                'quantity' => '',
                'unit_cost' => $selectedProduct !== null ? (string) $selectedProduct['average_cost'] : '',
                'reason' => '',
            ], $adjustForm),
            'adjustSubmissionKey' => $this->issueSubmissionKey(self::ADJUST_SUBMISSION_SCOPE),
        ]);
    }

    private function activeFilterCount(array $filters): int
    {
        $active = 0;

        foreach ($filters as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if ($key === 'sort' && $value === 'priority') {
                continue;
            }

            $active++;
        }

        return $active;
    }

    private function buildAttentionItems(array $items): array
    {
        $attention = [];

        foreach ($items as $item) {
            $stockState = (string) ($item['stock_state'] ?? 'normal');
            $shortfall = (float) ($item['shortfall_quantity'] ?? 0);
            $reorderQuantity = (float) ($item['reorder_quantity'] ?? 0);
            $reserved = (float) ($item['quantity_reserved'] ?? 0);
            $available = (float) ($item['available_quantity'] ?? 0);
            $openPurchase = (float) ($item['open_purchase_quantity'] ?? 0);
            $sold30 = (float) ($item['units_sold_30d'] ?? 0);

            $score = 0;
            $label = 'Monitor';
            $class = 'status-pill';
            $message = 'Healthy stock position.';

            if ($stockState === 'out_of_stock') {
                $score += 4000;
                $label = 'Out of Stock';
                $class = 'status-pill status-pill--danger';
                $message = 'No sellable stock is available.';
            } elseif ($stockState === 'low') {
                $score += 2500;
                $label = 'Low Stock';
                $class = 'status-pill status-pill--warning';
                $message = 'Below threshold by ' . number_format($shortfall, 2) . ' units.';
            }

            if ($reorderQuantity > 0) {
                $score += 1500 + (int) round($reorderQuantity * 10);
                $message = 'Reorder ' . number_format($reorderQuantity, 2) . ' units to cover threshold.';
            }

            if ($reserved > 0 && $reserved >= max($available, 0.01)) {
                $score += 900;
                $label = $stockState === 'normal' ? 'Reserved Pressure' : $label;
                $class = $stockState === 'normal' ? 'status-pill status-pill--info' : $class;
                $message = 'Reservations are consuming most of the available stock.';
            }

            if ($openPurchase > 0) {
                $message .= ' ' . number_format($openPurchase, 2) . ' units are already on order.';
                $score -= min(500, (int) round($openPurchase * 5));
            }

            if ($sold30 > 0) {
                $score += min(700, (int) round($sold30 * 5));
            }

            if ($score <= 0) {
                continue;
            }

            $item['attention_label'] = $label;
            $item['attention_class'] = $class;
            $item['attention_message'] = $message;
            $item['attention_score'] = $score;
            $attention[] = $item;
        }

        usort($attention, static function (array $left, array $right): int {
            $scoreComparison = ((int) ($right['attention_score'] ?? 0)) <=> ((int) ($left['attention_score'] ?? 0));
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return strcasecmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return array_slice($attention, 0, 6);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }
}
