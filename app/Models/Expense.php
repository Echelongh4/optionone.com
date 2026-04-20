<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Expense extends Model
{
    protected string $table = 'expenses';

    public function categories(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT *
             FROM expense_categories
             WHERE company_id = :company_id
               AND deleted_at IS NULL
             ORDER BY name',
            ['company_id' => $companyId]
        );
    }

    public function list(array $filters = [], ?int $branchId = null): array
    {
        $sql = 'SELECT e.*, ec.name AS category_name, CONCAT(u.first_name, " ", u.last_name) AS created_by_name
                FROM expenses e
                INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
                INNER JOIN users u ON u.id = e.user_id
                WHERE e.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND e.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        if (($filters['category_id'] ?? '') !== '') {
            $sql .= ' AND e.expense_category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND e.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND (e.description LIKE :search OR ec.name LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND e.expense_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND e.expense_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY e.expense_date DESC, e.created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id, ?int $branchId = null): ?array
    {
        $sql = 'SELECT e.*, ec.name AS category_name, CONCAT(u.first_name, " ", u.last_name) AS created_by_name
                FROM expenses e
                INNER JOIN expense_categories ec ON ec.id = e.expense_category_id
                INNER JOIN users u ON u.id = e.user_id
                WHERE e.id = :id
                  AND e.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($branchId !== null) {
            $sql .= ' AND e.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params);
    }

    public function summary(array $filters = [], ?int $branchId = null): array
    {
        $sql = 'SELECT COUNT(*) AS total_entries,
                       COALESCE(SUM(amount), 0) AS total_amount,
                       COALESCE(SUM(CASE WHEN expense_date = CURDATE() THEN amount ELSE 0 END), 0) AS today_amount,
                       COALESCE(SUM(CASE WHEN status = "approved" THEN amount ELSE 0 END), 0) AS approved_amount
                FROM expenses
                WHERE deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        if (($filters['category_id'] ?? '') !== '') {
            $sql .= ' AND expense_category_id = :category_id';
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (($filters['status'] ?? '') !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['search'] ?? '') !== '') {
            $sql .= ' AND description LIKE :search';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND expense_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND expense_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        return $this->fetch($sql, $params) ?? ['total_entries' => 0, 'total_amount' => 0, 'today_amount' => 0, 'approved_amount' => 0];
    }

    public function createExpense(array $payload): int
    {
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateExpense(int $id, array $payload): bool
    {
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($payload, 'id = :id', ['id' => $id]);
    }

    public function deleteExpense(int $id): bool
    {
        return $this->softDelete($id);
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
