<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\SaleReturn;
use App\Models\User;

class ReturnController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'status' => trim((string) $request->query('status', '')),
            'processed_by' => trim((string) $request->query('processed_by', '')),
            'date_from' => trim((string) $request->query('date_from', '')),
            'date_to' => trim((string) $request->query('date_to', '')),
        ];

        if ($filters['processed_by'] !== '' && !ctype_digit($filters['processed_by'])) {
            $filters['processed_by'] = '';
        }

        $returnModel = new SaleReturn();

        $this->render('returns/index', [
            'title' => 'Returns',
            'breadcrumbs' => ['Dashboard', 'Returns'],
            'filters' => $filters,
            'returns' => $returnModel->list($filters, $this->branchId()),
            'summary' => $returnModel->summary($filters, $this->branchId()),
            'processedByUsers' => $this->processedByUsers(),
        ]);
    }

    public function show(Request $request): void
    {
        $returnId = (int) $request->query('id');
        $return = (new SaleReturn())->findDetailed($returnId);

        if ($return === null) {
            throw new HttpException(404, 'Return not found.');
        }

        $this->guardBranchAccess((int) ($return['branch_id'] ?? 0));

        $this->render('returns/show', [
            'title' => 'Return Detail',
            'breadcrumbs' => ['Dashboard', 'Returns', 'Detail'],
            'return' => $return,
        ]);
    }

    private function processedByUsers(): array
    {
        $branchId = $this->branchId();

        return array_values(array_filter(
            (new User())->allActive(),
            static function (array $user) use ($branchId): bool {
                $matchesBranch = $user['branch_id'] === null || (int) $user['branch_id'] === $branchId;

                return $matchesBranch && in_array((string) ($user['role_name'] ?? ''), ['Super Admin', 'Admin', 'Manager', 'Cashier'], true);
            }
        ));
    }

    private function guardBranchAccess(int $branchId): void
    {
        if ($this->canManageAllBranches()) {
            return;
        }

        if ($branchId <= 0 || $branchId !== $this->branchId()) {
            throw new HttpException(404, 'Return not found.');
        }
    }

    private function canManageAllBranches(): bool
    {
        return in_array((string) (current_user()['role_name'] ?? ''), ['Super Admin', 'Admin'], true);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }
}
