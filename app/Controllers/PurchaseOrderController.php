<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Throwable;

class PurchaseOrderController extends Controller
{
    private const STORE_SUBMISSION_SCOPE = 'purchase_order_store';

    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'supplier_id' => trim((string) $request->query('supplier_id', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        $this->render('purchase_orders/index', [
            'title' => 'Purchase Orders',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Purchase Orders'],
            'orders' => (new PurchaseOrder())->list($filters, $this->branchId()),
            'suppliers' => (new Supplier())->list($this->branchId()),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): void
    {
        $this->renderForm([], [], [], false);
    }

    public function store(Request $request): void
    {
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::STORE_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        $payload = [
            'supplier_id' => (int) $request->input('supplier_id', 0),
            'expected_at' => trim((string) $request->input('expected_at', '')),
            'notes' => trim((string) $request->input('notes', '')),
            'status' => (string) $request->input('submit_action', 'draft'),
            'branch_id' => $this->branchId(),
            'created_by' => (int) Auth::id(),
        ];
        $items = $this->items($request);
        $errors = Validator::validate($payload, [
            'supplier_id' => 'required|integer',
            'status' => 'required|in:draft,ordered',
            'expected_at' => 'nullable|max:10',
            'notes' => 'nullable|max:1000',
        ]);

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey(self::STORE_SUBMISSION_SCOPE, $submissionKey)) {
            $errors['submission_key'][] = 'This purchase order form expired. Reload it and try again.';
        }

        if ($items === []) {
            $errors['items'][] = 'Add at least one purchase order line.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the purchase order details and try again.', 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, false);
            return;
        }

        try {
            $orderId = (new PurchaseOrder())->createOrder($payload, $items);
        } catch (Throwable $exception) {
            $errors['general'][] = $exception->getMessage();
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage(), 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, false);
            return;
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'purchase_order',
            entityId: $orderId,
            description: 'Created purchase order ' . $orderId . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $redirectPath = 'inventory/purchase-orders/show?id=' . $orderId;
        $this->rememberProcessedSubmission(self::STORE_SUBMISSION_SCOPE, $submissionKey, [
            'success' => true,
            'message' => 'Purchase order created successfully.',
            'flash_message' => 'Purchase order created successfully.',
            'redirect_path' => $redirectPath,
        ]);
        Session::flash('success', 'Purchase order created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Purchase order created successfully.']);
            return;
        }

        $this->redirect($redirectPath);
    }

    public function edit(Request $request): void
    {
        $order = $this->findOrderInBranch((int) $request->query('id'));
        if (!$this->isEditable($order)) {
            Session::flash('error', 'Only draft purchase orders can be edited.');
            $this->redirect('inventory/purchase-orders/show?id=' . $order['id']);
        }

        $this->renderForm($this->formPayload($order), $this->formItems($order), [], true, $order);
    }

    public function update(Request $request): void
    {
        $orderId = (int) $request->input('id');
        $orderModel = new PurchaseOrder();
        $order = $this->findOrderInBranch($orderId);
        $submissionScope = $this->updateSubmissionScope($orderId);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission($submissionScope, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if (!$orderModel->isEditable($order)) {
            Session::flash('error', 'Only draft purchase orders can be edited.');
            $this->redirect('inventory/purchase-orders/show?id=' . $order['id']);
        }

        $payload = [
            'supplier_id' => (int) $request->input('supplier_id', 0),
            'expected_at' => trim((string) $request->input('expected_at', '')),
            'notes' => trim((string) $request->input('notes', '')),
            'status' => (string) $request->input('submit_action', 'draft'),
        ];
        $items = $this->items($request);
        $errors = Validator::validate($payload, [
            'supplier_id' => 'required|integer',
            'status' => 'required|in:draft,ordered',
            'expected_at' => 'nullable|max:10',
            'notes' => 'nullable|max:1000',
        ]);

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey($submissionScope, $submissionKey)) {
            $errors['submission_key'][] = 'This purchase order form expired. Reload it and try again.';
        }

        if ($items === []) {
            $errors['items'][] = 'Add at least one purchase order line.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the purchase order details and try again.', 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, true, $order);
            return;
        }

        try {
            $updatedOrder = $orderModel->updateOrder($orderId, $payload, $items);
        } catch (Throwable $exception) {
            $errors['general'][] = $exception->getMessage();
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage(), 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, true, $order);
            return;
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'purchase_order',
            entityId: $orderId,
            description: 'Updated purchase order ' . $updatedOrder['po_number'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $redirectPath = 'inventory/purchase-orders/show?id=' . $orderId;
        $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
            'success' => true,
            'message' => 'Purchase order updated successfully.',
            'flash_message' => 'Purchase order updated successfully.',
            'redirect_path' => $redirectPath,
        ]);
        Session::flash('success', 'Purchase order updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Purchase order updated successfully.']);
            return;
        }

        $this->redirect($redirectPath);
    }

    public function show(Request $request): void
    {
        $order = $this->findOrderInBranch((int) $request->query('id'));

        $this->render('purchase_orders/show', [
            'title' => 'Purchase Order',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Purchase Orders', $order['po_number']],
            'order' => $order,
            'canEdit' => $this->isEditable($order),
            'canReceive' => in_array($order['status'], ['draft', 'ordered', 'partial_received'], true),
            'canCancel' => in_array($order['status'], ['draft', 'ordered', 'partial_received'], true),
            'workflowSubmissionKey' => $this->issueSubmissionKey($this->workflowSubmissionScope((int) $order['id'])),
            'receiveSubmissionKey' => $this->issueSubmissionKey($this->receiveSubmissionScope((int) $order['id'])),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $orderId = (int) $request->input('id');
        $submissionScope = $this->workflowSubmissionScope($orderId);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission($submissionScope, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey($submissionScope, $submissionKey)) {
            $message = 'This purchase order action expired. Reload the page and try again.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
        }

        $status = (string) $request->input('status');
        $note = trim((string) $request->input('note', ''));
        $order = (new PurchaseOrder())->findDetailed($orderId);

        if ($order === null || (int) $order['branch_id'] !== $this->branchId()) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        try {
            (new PurchaseOrder())->updateStatus($orderId, $status, $note);
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_status',
            entityType: 'purchase_order',
            entityId: $orderId,
            description: 'Changed purchase order status to ' . $status . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = 'Purchase order status updated.';
        $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
            'success' => true,
            'message' => $message,
            'flash_message' => $message,
            'redirect_path' => 'inventory/purchase-orders/show?id=' . $orderId,
        ]);
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
    }

    public function duplicate(Request $request): void
    {
        $orderId = (int) $request->input('id');
        $order = $this->findOrderInBranch($orderId);

        try {
            $duplicateId = (new PurchaseOrder())->duplicateOrder($orderId, (int) Auth::id(), $this->branchId());
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'duplicate',
            entityType: 'purchase_order',
            entityId: $duplicateId,
            description: 'Duplicated purchase order ' . $order['po_number'] . ' into draft ' . $duplicateId . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Purchase order duplicated as a draft.');
        $this->redirect('inventory/purchase-orders/edit?id=' . $duplicateId);
    }

    public function receive(Request $request): void
    {
        $orderId = (int) $request->input('id');
        $submissionScope = $this->receiveSubmissionScope($orderId);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission($submissionScope, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey($submissionScope, $submissionKey)) {
            $message = 'This stock receipt expired. Reload the page and try again.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
        }

        $orderModel = new PurchaseOrder();
        $order = $this->findOrderInBranch($orderId);

        $receivedItems = [];
        foreach ((array) $request->input('received_quantity', []) as $itemId => $quantity) {
            $receivedItems[(int) $itemId] = (float) $quantity;
        }

        $note = trim((string) $request->input('note', ''));

        try {
            $updatedOrder = $orderModel->receiveOrder($orderId, (int) Auth::id(), $this->branchId(), $receivedItems, $note);
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'receive',
            entityType: 'purchase_order',
            entityId: $orderId,
            description: 'Received stock against purchase order ' . $updatedOrder['po_number'] . ' (' . $updatedOrder['status'] . ').',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $message = $updatedOrder['status'] === 'received'
            ? 'Purchase order fully received and inventory updated.'
            : 'Purchase order partially received and inventory updated.';

        $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
            'success' => true,
            'message' => $message,
            'flash_message' => $message,
            'redirect_path' => 'inventory/purchase-orders/show?id=' . $orderId,
        ]);
        Session::flash('success', $message);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $message]);
            return;
        }

        $this->redirect('inventory/purchase-orders/show?id=' . $orderId);
    }

    private function renderForm(array $payload, array $items, array $errors, bool $isEdit, ?array $order = null): void
    {
        $productCatalog = (new Product())->allWithRelations(null, $this->branchId());
        $view = $isEdit ? 'purchase_orders/edit' : 'purchase_orders/create';
        $title = $isEdit ? 'Edit Purchase Order' : 'Create Purchase Order';
        $breadcrumbs = $isEdit
            ? ['Dashboard', 'Inventory', 'Purchase Orders', $order['po_number'] ?? 'Purchase Order', 'Edit']
            : ['Dashboard', 'Inventory', 'Purchase Orders', 'Create Purchase Order'];

        $this->render($view, [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'suppliers' => (new Supplier())->list($this->branchId()),
            'products' => $productCatalog,
            'form' => array_merge([
                'id' => $order['id'] ?? '',
                'supplier_id' => '',
                'expected_at' => '',
                'notes' => '',
                'status' => 'draft',
            ], $payload),
            'items' => $items !== [] ? $items : [[
                'product_id' => '',
                'quantity' => 1,
                'unit_cost' => 0,
                'tax_rate' => 0,
            ]],
            'errors' => $errors,
            'order' => $order,
            'submissionKey' => $this->issueSubmissionKey($isEdit && $order !== null ? $this->updateSubmissionScope((int) $order['id']) : self::STORE_SUBMISSION_SCOPE),
        ]);
    }

    private function items(Request $request): array
    {
        $productIds = $request->input('product_id', []);
        $quantities = $request->input('quantity', []);
        $unitCosts = $request->input('unit_cost', []);
        $taxRates = $request->input('tax_rate', []);
        $items = [];

        foreach ($productIds as $index => $productId) {
            $items[] = [
                'product_id' => (int) $productId,
                'quantity' => (float) ($quantities[$index] ?? 0),
                'unit_cost' => (float) ($unitCosts[$index] ?? 0),
                'tax_rate' => (float) ($taxRates[$index] ?? 0),
            ];
        }

        return array_values(array_filter($items, static fn (array $item): bool => $item['product_id'] > 0));
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function findOrderInBranch(int $orderId): array
    {
        $order = (new PurchaseOrder())->findDetailed($orderId);
        if ($order === null || (int) $order['branch_id'] !== $this->branchId()) {
            throw new HttpException(404, 'Purchase order not found.');
        }

        return $order;
    }

    private function formPayload(array $order): array
    {
        return [
            'id' => (string) $order['id'],
            'supplier_id' => (string) $order['supplier_id'],
            'expected_at' => $this->dateValue((string) ($order['expected_at'] ?? '')),
            'notes' => (string) ($order['notes'] ?? ''),
            'status' => (string) ($order['status'] ?? 'draft'),
        ];
    }

    private function formItems(array $order): array
    {
        return array_map(static fn (array $item): array => [
            'product_id' => (string) ($item['product_id'] ?? ''),
            'quantity' => (float) ($item['quantity'] ?? 1),
            'unit_cost' => (float) ($item['unit_cost'] ?? 0),
            'tax_rate' => (float) ($item['tax_rate'] ?? 0),
        ], $order['items'] ?? []);
    }

    private function isEditable(array $order): bool
    {
        return (new PurchaseOrder())->isEditable($order);
    }

    private function dateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return substr($value, 0, 10);
    }

    private function updateSubmissionScope(int $orderId): string
    {
        return 'purchase_order_update_' . $orderId;
    }

    private function workflowSubmissionScope(int $orderId): string
    {
        return 'purchase_order_workflow_' . $orderId;
    }

    private function receiveSubmissionScope(int $orderId): string
    {
        return 'purchase_order_receive_' . $orderId;
    }
}
