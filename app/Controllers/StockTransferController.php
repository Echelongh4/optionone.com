<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Notification;
use App\Models\StockTransfer;
use Throwable;

class StockTransferController extends Controller
{
    private const STORE_SUBMISSION_SCOPE = 'stock_transfer_store';

    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'direction' => trim((string) $request->query('direction', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];
        $transferModel = new StockTransfer();

        $this->render('stock_transfers/index', [
            'title' => 'Stock Transfers',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Stock Transfers'],
            'summary' => $transferModel->summary($this->branchId(), $this->canManageAllBranches()),
            'transfers' => $transferModel->list($filters, $this->branchId(), $this->canManageAllBranches()),
            'filters' => $filters,
            'currentBranchId' => $this->branchId(),
            'canManageAllBranches' => $this->canManageAllBranches(),
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
            'source_branch_id' => $this->branchId(),
            'destination_branch_id' => (int) $request->input('destination_branch_id', 0),
            'notes' => trim((string) $request->input('notes', '')),
            'status' => (string) $request->input('submit_action', 'draft'),
            'created_by' => (int) Auth::id(),
        ];
        $items = $this->items($request);
        $errors = Validator::validate($payload, [
            'destination_branch_id' => 'required|integer',
            'notes' => 'nullable|max:255',
            'status' => 'required|in:draft,in_transit',
        ]);

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey(self::STORE_SUBMISSION_SCOPE, $submissionKey)) {
            $errors['submission_key'][] = 'This stock transfer form expired. Reload it and try again.';
        }

        if ($payload['destination_branch_id'] === $payload['source_branch_id']) {
            $errors['destination_branch_id'][] = 'Destination branch must be different from the source branch.';
        }

        if ($items === []) {
            $errors['items'][] = 'Add at least one transfer item.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the stock transfer details and try again.', 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, false);
            return;
        }

        $transferModel = new StockTransfer();

        try {
            $transferId = $transferModel->createTransfer($payload, $items);
            $transfer = $transferModel->findDetailed($transferId);
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

        if ($transfer === null) {
            throw new HttpException(500, 'Unable to load the saved stock transfer.');
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'stock_transfer',
            entityId: $transferId,
            description: 'Created stock transfer ' . $transfer['reference_number'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($transfer['status'] === 'in_transit') {
            $this->notifyBranch(
                branchId: (int) $transfer['destination_branch_id'],
                title: 'Incoming stock transfer',
                message: $transfer['reference_number'] . ' is on the way from ' . $transfer['source_branch_name'] . '.',
                link: 'inventory/transfers/show?id=' . $transferId
            );
        }

        $successMessage = $transfer['status'] === 'in_transit'
            ? 'Stock transfer created and dispatched.'
            : 'Stock transfer saved as draft.';
        $this->rememberProcessedSubmission(self::STORE_SUBMISSION_SCOPE, $submissionKey, [
            'success' => true,
            'message' => $successMessage,
            'flash_message' => $successMessage,
            'redirect_path' => 'inventory/transfers/show?id=' . $transferId,
        ]);
        Session::flash('success', $successMessage);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMessage]);
            return;
        }

        $this->redirect('inventory/transfers/show?id=' . $transferId);
    }

    public function edit(Request $request): void
    {
        $transfer = $this->findTransfer((int) $request->query('id'));
        if (!$this->canEditTransfer($transfer)) {
            Session::flash('error', 'Only draft stock transfers can be edited.');
            $this->redirect('inventory/transfers/show?id=' . $transfer['id']);
        }

        $this->renderForm($this->formPayload($transfer), $this->formItems($transfer), [], true, $transfer);
    }

    public function update(Request $request): void
    {
        $transferId = (int) $request->input('id');
        $transferModel = new StockTransfer();
        $transfer = $this->findTransfer($transferId);
        $submissionScope = $this->updateSubmissionScope($transferId);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission($submissionScope, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if (!$this->canEditTransfer($transfer)) {
            Session::flash('error', 'Only draft stock transfers can be edited.');
            $this->redirect('inventory/transfers/show?id=' . $transfer['id']);
        }

        $payload = [
            'destination_branch_id' => (int) $request->input('destination_branch_id', 0),
            'notes' => trim((string) $request->input('notes', '')),
            'status' => (string) $request->input('submit_action', 'draft'),
            'updated_by' => (int) Auth::id(),
        ];
        $items = $this->items($request);
        $errors = Validator::validate($payload, [
            'destination_branch_id' => 'required|integer',
            'notes' => 'nullable|max:255',
            'status' => 'required|in:draft,in_transit',
        ]);

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey($submissionScope, $submissionKey)) {
            $errors['submission_key'][] = 'This stock transfer form expired. Reload it and try again.';
        }

        if ($payload['destination_branch_id'] === (int) $transfer['source_branch_id']) {
            $errors['destination_branch_id'][] = 'Destination branch must be different from the source branch.';
        }

        if ($items === []) {
            $errors['items'][] = 'Add at least one transfer item.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the stock transfer details and try again.', 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, true, $transfer);
            return;
        }

        try {
            $updatedTransfer = $transferModel->updateTransfer($transferId, $payload, $items);
        } catch (Throwable $exception) {
            $errors['general'][] = $exception->getMessage();
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage(), 'errors' => $errors]);
                return;
            }

            $this->renderForm($payload, $items, $errors, true, $transfer);
            return;
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'stock_transfer',
            entityId: $transferId,
            description: 'Updated stock transfer ' . $updatedTransfer['reference_number'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        if ($updatedTransfer['status'] === 'in_transit') {
            $this->notifyBranch(
                branchId: (int) $updatedTransfer['destination_branch_id'],
                title: 'Incoming stock transfer',
                message: $updatedTransfer['reference_number'] . ' is on the way from ' . $updatedTransfer['source_branch_name'] . '.',
                link: 'inventory/transfers/show?id=' . $transferId
            );
        }

        $successMessage = $updatedTransfer['status'] === 'in_transit'
            ? 'Stock transfer updated and dispatched.'
            : 'Stock transfer draft updated successfully.';
        $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
            'success' => true,
            'message' => $successMessage,
            'flash_message' => $successMessage,
            'redirect_path' => 'inventory/transfers/show?id=' . $transferId,
        ]);
        Session::flash('success', $successMessage);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMessage]);
            return;
        }

        $this->redirect('inventory/transfers/show?id=' . $transferId);
    }

    public function show(Request $request): void
    {
        $transfer = $this->findTransfer((int) $request->query('id'));

        $this->render('stock_transfers/show', [
            'title' => 'Stock Transfer',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Stock Transfers', $transfer['reference_number']],
            'transfer' => $transfer,
            'canEdit' => $this->canEditTransfer($transfer),
            'canSend' => $this->canSendTransfer($transfer),
            'canReceive' => $this->canReceiveTransfer($transfer),
            'canCancel' => $this->canCancelTransfer($transfer),
            'currentBranchId' => $this->branchId(),
            'workflowSubmissionKey' => $this->issueSubmissionKey($this->workflowSubmissionScope((int) $transfer['id'])),
            'receiveSubmissionKey' => $this->issueSubmissionKey($this->receiveSubmissionScope((int) $transfer['id'])),
        ]);
    }

    public function updateStatus(Request $request): void
    {
        $transferId = (int) $request->input('id');
        $submissionScope = $this->workflowSubmissionScope($transferId);
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission($submissionScope, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        if ($submissionKey === '' || !$this->isIssuedSubmissionKey($submissionScope, $submissionKey)) {
            $message = 'This transfer action expired. Reload the page and try again.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('inventory/transfers/show?id=' . $transferId);
        }

        $action = (string) $request->input('action');
        $transferModel = new StockTransfer();
        $transfer = $transferModel->findDetailed($transferId);

        if ($transfer === null || !$this->canAccessTransfer($transfer)) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        $successMessage = null;

        try {
            if ($action === 'send') {
                if (!$this->canSendTransfer($transfer)) {
                    throw new HttpException(403, 'You cannot dispatch this transfer.');
                }

                $transferModel->send($transferId, (int) Auth::id());

                (new AuditLog())->record(
                    userId: Auth::id(),
                    action: 'dispatch',
                    entityType: 'stock_transfer',
                    entityId: $transferId,
                    description: 'Dispatched stock transfer ' . $transfer['reference_number'] . '.',
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                $this->notifyBranch(
                    branchId: (int) $transfer['destination_branch_id'],
                    title: 'Incoming stock transfer',
                    message: $transfer['reference_number'] . ' is on the way from ' . $transfer['source_branch_name'] . '.',
                    link: 'inventory/transfers/show?id=' . $transferId
                );

                $successMessage = 'Stock transfer dispatched successfully.';
                Session::flash('success', $successMessage);
            } elseif ($action === 'cancel') {
                if (!$this->canCancelTransfer($transfer)) {
                    throw new HttpException(403, 'You cannot cancel this transfer.');
                }

                $transferModel->cancel($transferId, (int) Auth::id());

                (new AuditLog())->record(
                    userId: Auth::id(),
                    action: 'cancel',
                    entityType: 'stock_transfer',
                    entityId: $transferId,
                    description: 'Cancelled stock transfer ' . $transfer['reference_number'] . '.',
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                $successMessage = 'Stock transfer cancelled.';
                Session::flash('success', $successMessage);
            } else {
                throw new HttpException(404, 'Transfer action not found.');
            }
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
        }

        if ($request->isAjax() && $successMessage !== null) {
            $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
                'success' => true,
                'message' => $successMessage,
                'flash_message' => $successMessage,
                'redirect_path' => 'inventory/transfers/show?id=' . $transferId,
            ]);
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $successMessage]);
            return;
        }

        if ($successMessage !== null) {
            $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
                'success' => true,
                'message' => $successMessage,
                'flash_message' => $successMessage,
                'redirect_path' => 'inventory/transfers/show?id=' . $transferId,
            ]);
        }

        $this->redirect('inventory/transfers/show?id=' . $transferId);
    }

    public function duplicate(Request $request): void
    {
        $transferId = (int) $request->input('id');
        $transfer = $this->findTransfer($transferId);

        try {
            $duplicateId = (new StockTransfer())->duplicateTransfer($transferId, (int) Auth::id());
        } catch (Throwable $exception) {
            Session::flash('error', $exception->getMessage());
            $this->redirect('inventory/transfers/show?id=' . $transferId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'duplicate',
            entityType: 'stock_transfer',
            entityId: $duplicateId,
            description: 'Duplicated stock transfer ' . $transfer['reference_number'] . ' into draft ' . $duplicateId . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Stock transfer duplicated as a draft.');
        $this->redirect('inventory/transfers/edit?id=' . $duplicateId);
    }

    public function receive(Request $request): void
    {
        $transferId = (int) $request->input('id');
        $submissionScope = $this->receiveSubmissionScope($transferId);
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
            $this->redirect('inventory/transfers/show?id=' . $transferId);
        }

        $transferModel = new StockTransfer();
        $transfer = $this->findTransfer($transferId);

        if (!$this->canReceiveTransfer($transfer)) {
            throw new HttpException(403, 'You cannot receive this transfer.');
        }

        try {
            $transferModel->receive($transferId, (int) Auth::id());
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
            $this->redirect('inventory/transfers/show?id=' . $transferId);
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'receive',
            entityType: 'stock_transfer',
            entityId: $transferId,
            description: 'Received stock transfer ' . $transfer['reference_number'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->notifyBranch(
            branchId: (int) $transfer['source_branch_id'],
            title: 'Transfer completed',
            message: $transfer['reference_number'] . ' was received by ' . $transfer['destination_branch_name'] . '.',
            link: 'inventory/transfers/show?id=' . $transferId
        );

        Session::flash('success', 'Stock transfer received and inventory updated.');
        $this->rememberProcessedSubmission($submissionScope, $submissionKey, [
            'success' => true,
            'message' => 'Stock transfer received and inventory updated.',
            'flash_message' => 'Stock transfer received and inventory updated.',
            'redirect_path' => 'inventory/transfers/show?id=' . $transferId,
        ]);
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Stock transfer received and inventory updated.']);
            return;
        }

        $this->redirect('inventory/transfers/show?id=' . $transferId);
    }

    private function renderForm(array $payload, array $items, array $errors, bool $isEdit, ?array $transfer = null): void
    {
        $sourceBranchId = (int) ($transfer['source_branch_id'] ?? $this->branchId());
        $branches = array_values(array_filter(
            (new Branch())->active(),
            fn (array $branch): bool => (int) $branch['id'] !== $sourceBranchId
        ));
        $view = $isEdit ? 'stock_transfers/edit' : 'stock_transfers/create';
        $title = $isEdit ? 'Edit Stock Transfer' : 'Create Stock Transfer';
        $breadcrumbs = $isEdit
            ? ['Dashboard', 'Inventory', 'Stock Transfers', $transfer['reference_number'] ?? 'Transfer', 'Edit']
            : ['Dashboard', 'Inventory', 'Stock Transfers', 'Create Stock Transfer'];

        $this->render($view, [
            'title' => $title,
            'breadcrumbs' => $breadcrumbs,
            'branches' => $branches,
            'products' => (new Inventory())->adjustmentProducts($sourceBranchId),
            'form' => array_merge([
                'id' => $transfer['id'] ?? '',
                'destination_branch_id' => '',
                'notes' => '',
                'status' => 'draft',
            ], $payload),
            'items' => $items !== [] ? $items : [[
                'product_id' => '',
                'quantity' => 1,
            ]],
            'errors' => $errors,
            'transfer' => $transfer,
            'sourceBranchId' => $sourceBranchId,
            'sourceBranchName' => $this->branchName($sourceBranchId),
            'submissionKey' => $this->issueSubmissionKey($isEdit && $transfer !== null ? $this->updateSubmissionScope((int) $transfer['id']) : self::STORE_SUBMISSION_SCOPE),
        ]);
    }

    private function items(Request $request): array
    {
        $productIds = $request->input('product_id', []);
        $quantities = $request->input('quantity', []);
        $items = [];

        foreach ($productIds as $index => $productId) {
            $items[] = [
                'product_id' => (int) $productId,
                'quantity' => (float) ($quantities[$index] ?? 0),
            ];
        }

        return array_values(array_filter($items, static fn (array $item): bool => $item['product_id'] > 0));
    }

    private function canAccessTransfer(array $transfer): bool
    {
        if ($this->canManageAllBranches()) {
            return true;
        }

        return in_array($this->branchId(), [
            (int) $transfer['source_branch_id'],
            (int) $transfer['destination_branch_id'],
        ], true);
    }

    private function canSendTransfer(array $transfer): bool
    {
        if ($transfer['status'] !== 'draft') {
            return false;
        }

        return $this->canManageAllBranches() || (int) $transfer['source_branch_id'] === $this->branchId();
    }

    private function updateSubmissionScope(int $transferId): string
    {
        return 'stock_transfer_update_' . $transferId;
    }

    private function workflowSubmissionScope(int $transferId): string
    {
        return 'stock_transfer_workflow_' . $transferId;
    }

    private function receiveSubmissionScope(int $transferId): string
    {
        return 'stock_transfer_receive_' . $transferId;
    }

    private function canReceiveTransfer(array $transfer): bool
    {
        if ($transfer['status'] !== 'in_transit') {
            return false;
        }

        return $this->canManageAllBranches() || (int) $transfer['destination_branch_id'] === $this->branchId();
    }

    private function canCancelTransfer(array $transfer): bool
    {
        if (!in_array($transfer['status'], ['draft', 'in_transit'], true)) {
            return false;
        }

        return $this->canManageAllBranches() || (int) $transfer['source_branch_id'] === $this->branchId();
    }

    private function canEditTransfer(array $transfer): bool
    {
        if (!(new StockTransfer())->isEditable($transfer)) {
            return false;
        }

        return $this->canManageAllBranches() || (int) $transfer['source_branch_id'] === $this->branchId();
    }

    private function canManageAllBranches(): bool
    {
        return in_array((string) (current_user()['role_name'] ?? ''), [Role::SuperAdmin->value, Role::Admin->value], true);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function branchName(?int $branchId = null): string
    {
        $branch = (new Branch())->find($branchId ?? $this->branchId());

        return $branch['name'] ?? 'Current Branch';
    }

    private function findTransfer(int $transferId): array
    {
        $transfer = (new StockTransfer())->findDetailed($transferId);
        if ($transfer === null || !$this->canAccessTransfer($transfer)) {
            throw new HttpException(404, 'Stock transfer not found.');
        }

        return $transfer;
    }

    private function formPayload(array $transfer): array
    {
        return [
            'id' => (string) $transfer['id'],
            'destination_branch_id' => (string) $transfer['destination_branch_id'],
            'notes' => (string) ($transfer['notes'] ?? ''),
            'status' => (string) ($transfer['status'] ?? 'draft'),
        ];
    }

    private function formItems(array $transfer): array
    {
        return array_map(static fn (array $item): array => [
            'product_id' => (string) ($item['product_id'] ?? ''),
            'quantity' => (float) ($item['quantity'] ?? 1),
        ], $transfer['items'] ?? []);
    }

    private function notifyBranch(int $branchId, string $title, string $message, string $link): void
    {
        (new Notification())->createBranchNotification(
            branchId: $branchId,
            type: 'stock_transfer',
            title: $title,
            message: $message,
            linkUrl: url($link)
        );
    }
}
