<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Sale;
use App\Models\SaleVoidRequest;
use App\Models\User;
use Throwable;

class SalesController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => (string) $request->query('status', ''),
            'cashier_id' => (string) $request->query('cashier_id', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $this->render('sales/index', [
            'title' => 'Sales History',
            'breadcrumbs' => ['Dashboard', 'Sales'],
            'sales' => (new Sale())->history($filters, $this->branchId()),
            'cashiers' => (new User())->allActive(),
            'filters' => $filters,
        ]);
    }

    public function suggest(Request $request): void
    {
        $q = trim((string) $request->query('q', ''));
        $sales = (new Sale())->history(['search' => $q], $this->branchId());
        $results = array_map(static function (array $s): array {
            return [
                'id' => (int) ($s['id'] ?? 0),
                'sale_number' => (string) ($s['sale_number'] ?? ''),
                'customer_name' => (string) ($s['customer_name'] ?? 'Walk-in customer'),
                'grand_total' => (float) ($s['grand_total'] ?? 0),
                'created_at' => (string) ($s['created_at'] ?? ''),
            ];
        }, array_slice($sales, 0, 10));

        header('Content-Type: application/json');
        echo json_encode($results);
    }

    public function show(Request $request): void
    {
        $saleId = (int) $request->query('id');
        $sale = (new Sale())->findDetailed($saleId);

        if ($sale === null) {
            throw new HttpException(404, 'Sale not found.');
        }

        $this->guardBranchAccess((int) ($sale['branch_id'] ?? 0));

        $voidRequestModel = new SaleVoidRequest();

        $this->render('sales/show', [
            'title' => 'Sale Detail',
            'breadcrumbs' => ['Dashboard', 'Sales', 'Detail'],
            'sale' => $sale,
            'activeVoidRequest' => $voidRequestModel->activeForSale($saleId),
            'voidRequestHistory' => $voidRequestModel->historyForSale($saleId),
        ]);
    }

    public function processReturn(Request $request): void
    {
        $saleId = (int) $request->input('sale_id');
        $quantities = $request->input('return_quantity', []);
        $reasons = $request->input('return_reason', []);
        $lines = [];
        $redirectPath = 'sales/show?id=' . $saleId;

        foreach ($quantities as $saleItemId => $quantity) {
            if ((float) $quantity <= 0) {
                continue;
            }

            $lines[] = [
                'sale_item_id' => (int) $saleItemId,
                'quantity' => (float) $quantity,
                'reason' => trim((string) ($reasons[$saleItemId] ?? '')),
            ];
        }

        try {
            $sale = (new Sale())->findDetailed($saleId);
            if ($sale === null) {
                throw new HttpException(404, 'Sale not found.');
            }

            $this->guardBranchAccess((int) ($sale['branch_id'] ?? 0));

            if ((new SaleVoidRequest())->activeForSale($saleId) !== null) {
                throw new HttpException(409, 'Returns are unavailable while a void request is pending for this sale.');
            }

            $returnId = (new Sale())->processReturn(
                saleId: $saleId,
                returnLines: $lines,
                reason: trim((string) $request->input('reason', 'Customer return')),
                userId: (int) Auth::id(),
                branchId: $this->branchId()
            );

            $redirectPath = 'returns/show?id=' . $returnId;

            (new AuditLog())->record(
                userId: Auth::id(),
                action: 'return',
                entityType: 'sale',
                entityId: $saleId,
                description: 'Processed return #' . $returnId . ' for sale #' . $saleId . '.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', 'Return processed successfully.');
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Return processed successfully.',
                    'return_id' => $returnId,
                ]);
                return;
            }
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
        }

        $this->redirect($redirectPath);
    }

    public function requestVoid(Request $request): void
    {
        $saleId = (int) $request->input('sale_id');
        $reason = trim((string) $request->input('void_reason', ''));

        try {
            if (mb_strlen($reason) < 5) {
                throw new HttpException(422, 'Provide a clearer reason for the void request.');
            }

            $saleModel = new Sale();
            $sale = $saleModel->findDetailed($saleId);
            if ($sale === null) {
                throw new HttpException(404, 'Sale not found.');
            }

            $this->guardBranchAccess((int) ($sale['branch_id'] ?? 0));

            if ((string) ($sale['status'] ?? '') !== 'completed') {
                throw new HttpException(409, 'Only completed sales can be submitted for void approval.');
            }

            $requestId = (new SaleVoidRequest())->createRequest($saleId, (int) Auth::id(), $reason);

            (new AuditLog())->record(
                userId: Auth::id(),
                action: 'request_void',
                entityType: 'sale',
                entityId: $saleId,
                description: 'Submitted void approval request #' . $requestId . ' for sale ' . ($sale['sale_number'] ?? ('#' . $saleId)) . '.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $this->notifyVoidApprovers($sale, $reason);

            Session::flash('success', 'Void approval request submitted.');
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Void approval request submitted.']);
                return;
            }
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
        }

        $this->redirect('sales/show?id=' . $saleId);
    }

    public function reviewVoidRequest(Request $request): void
    {
        $requestId = (int) $request->input('request_id');
        $decision = strtolower(trim((string) $request->input('decision', '')));
        $reviewNotes = trim((string) $request->input('review_notes', ''));
        $saleId = (int) $request->input('sale_id', 0);

        try {
            if (!in_array($decision, ['approved', 'rejected'], true)) {
                throw new HttpException(422, 'Choose approve or reject for this void request.');
            }

            $voidRequestModel = new SaleVoidRequest();
            $voidRequest = $voidRequestModel->findWithContext($requestId);
            if ($voidRequest === null) {
                throw new HttpException(404, 'Void request not found.');
            }

            $saleId = (int) $voidRequest['sale_id'];
            $this->guardBranchAccess((int) ($voidRequest['branch_id'] ?? 0));

            if ((string) ($voidRequest['status'] ?? '') !== 'pending') {
                throw new HttpException(409, 'This void request has already been reviewed.');
            }

            if ((int) ($voidRequest['requested_by'] ?? 0) === (int) Auth::id()) {
                throw new HttpException(403, 'A different supervisor must review this void request.');
            }

            if ($decision === 'approved') {
                Database::transaction(function () use ($voidRequestModel, $voidRequest, $requestId, $reviewNotes): void {
                    (new Sale())->voidSale(
                        saleId: (int) $voidRequest['sale_id'],
                        reason: trim((string) $voidRequest['reason']),
                        approvedBy: (int) Auth::id(),
                        branchId: $this->branchId()
                    );

                    $voidRequestModel->review($requestId, 'approved', (int) Auth::id(), $reviewNotes);
                });
            } else {
                $voidRequestModel->review($requestId, 'rejected', (int) Auth::id(), $reviewNotes);
            }

            (new AuditLog())->record(
                userId: Auth::id(),
                action: $decision === 'approved' ? 'approve_void_request' : 'reject_void_request',
                entityType: 'sale',
                entityId: $saleId,
                description: ucfirst($decision) . ' void approval request #' . $requestId . ' for sale #' . $saleId . '.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $updatedRequest = $voidRequestModel->findWithContext($requestId) ?? $voidRequest;
            $this->notifyVoidRequester($updatedRequest, $decision, $reviewNotes);

            Session::flash('success', $decision === 'approved' ? 'Void request approved and sale voided.' : 'Void request rejected.');
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $decision === 'approved' ? 'Void request approved and sale voided.' : 'Void request rejected.',
                ]);
                return;
            }
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
        }

        $this->redirect($saleId > 0 ? 'sales/show?id=' . $saleId : 'sales');
    }

    public function void(Request $request): void
    {
        $saleId = (int) $request->input('sale_id');
        Session::flash('error', 'Direct voiding is disabled. Submit or review a void approval request instead.');
        $this->redirect($saleId > 0 ? 'sales/show?id=' . $saleId : 'sales');
    }

    private function notifyVoidApprovers(array $sale, string $reason): void
    {
        $branchId = (int) ($sale['branch_id'] ?? $this->branchId());
        $saleId = (int) ($sale['id'] ?? 0);
        $saleNumber = (string) ($sale['sale_number'] ?? ('Sale #' . $saleId));
        $requesterId = (int) Auth::id();
        $requesterName = (string) ((current_user()['full_name'] ?? null) ?: 'Staff user');
        $link = '/sales/show?id=' . $saleId;
        $message = $saleNumber . ' was submitted for void approval by ' . $requesterName . '. Reason: ' . $reason;

        $notified = 0;
        foreach ((new User())->allActive() as $user) {
            $roleName = (string) ($user['role_name'] ?? '');
            $userBranchId = isset($user['branch_id']) ? (int) $user['branch_id'] : null;
            $sameScope = $roleName === 'Super Admin' || $userBranchId === null || $userBranchId === $branchId;

            if (!$sameScope || !in_array($roleName, ['Super Admin', 'Admin', 'Manager'], true) || (int) $user['id'] === $requesterId) {
                continue;
            }

            (new Notification())->createUserNotification((int) $user['id'], $branchId, 'void_request', 'Void approval requested', $message, $link);
            $notified++;
        }

        if ($notified === 0) {
            (new Notification())->createBranchNotification($branchId, 'void_request', 'Void approval requested', $message, $link);
        }
    }

    private function notifyVoidRequester(array $voidRequest, string $decision, string $reviewNotes = ''): void
    {
        $requesterId = (int) ($voidRequest['requested_by'] ?? 0);
        if ($requesterId <= 0) {
            return;
        }

        $saleId = (int) ($voidRequest['sale_id'] ?? 0);
        $saleNumber = (string) ($voidRequest['sale_number'] ?? ('Sale #' . $saleId));
        $reviewerName = (string) (($voidRequest['reviewed_by_name'] ?? null) ?: 'Supervisor');
        $title = $decision === 'approved' ? 'Void request approved' : 'Void request rejected';
        $message = $saleNumber . ' void request was ' . $decision . ' by ' . $reviewerName . '.';

        if ($reviewNotes !== '') {
            $message .= ' Notes: ' . $reviewNotes;
        }

        (new Notification())->createUserNotification(
            $requesterId,
            isset($voidRequest['branch_id']) ? (int) $voidRequest['branch_id'] : null,
            $decision === 'approved' ? 'void_request_approved' : 'void_request_rejected',
            $title,
            $message,
            '/sales/show?id=' . $saleId
        );
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function guardBranchAccess(int $branchId): void
    {
        if ($this->canManageAllBranches()) {
            return;
        }

        if ($branchId <= 0 || $branchId !== $this->branchId()) {
            throw new HttpException(404, 'Sale not found.');
        }
    }

    private function canManageAllBranches(): bool
    {
        return in_array((string) (current_user()['role_name'] ?? ''), ['Super Admin', 'Admin'], true);
    }
}
