<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;

class Customer extends Model
{
    protected string $table = 'customers';

    public function allActive(?int $branchId = null, array $filters = []): array
    {
        $sql = 'SELECT c.*, cg.name AS customer_group_name,
                       CONCAT(c.first_name, " ", c.last_name) AS full_name,
                       COALESCE(stats.total_spent, 0) AS total_spent,
                       COALESCE(stats.total_orders, 0) AS total_orders,
                       stats.last_purchase_at
                FROM customers c
                LEFT JOIN customer_groups cg ON cg.id = c.customer_group_id
                LEFT JOIN (
                    SELECT customer_id,
                           COUNT(*) AS total_orders,
                           SUM(grand_total) AS total_spent,
                           MAX(completed_at) AS last_purchase_at
                    FROM sales
                    WHERE status IN ("completed", "partial_return", "refunded")
                    GROUP BY customer_id
                ) stats ON stats.customer_id = c.id
                WHERE c.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND (c.branch_id = :branch_id OR c.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $sql .= ' AND (
                c.first_name LIKE :search
                OR c.last_name LIKE :search
                OR CONCAT(c.first_name, " ", c.last_name) LIKE :search
                OR c.phone LIKE :search
                OR c.email LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        if (($filters['group_id'] ?? '') !== '') {
            $sql .= ' AND c.customer_group_id = :group_id';
            $params['group_id'] = (int) $filters['group_id'];
        }

        $creditStatus = trim((string) ($filters['credit_status'] ?? ''));
        if ($creditStatus === 'with_balance') {
            $sql .= ' AND c.credit_balance > 0';
        } elseif ($creditStatus === 'clear') {
            $sql .= ' AND c.credit_balance <= 0';
        }

        $sql .= ' ORDER BY c.first_name, c.last_name';

        return $this->fetchAll($sql, $params);
    }

    public function suggestForPos(?int $branchId = null, string $search = '', int $limit = 20, ?int $customerId = null): array
    {
        $limit = max(1, min($limit, 50));
        $search = trim($search);

        $sql = 'SELECT c.id,
                       c.phone,
                       c.email,
                       c.credit_balance,
                       c.loyalty_balance,
                       c.special_pricing_type,
                       c.special_pricing_value,
                       c.updated_at,
                       c.created_at,
                       cg.name AS customer_group_name,
                       CONCAT(c.first_name, " ", c.last_name) AS full_name,
                       (
                           SELECT COUNT(*)
                           FROM sales s
                           WHERE s.customer_id = c.id
                             AND s.status IN ("completed", "partial_return", "refunded")
                       ) AS total_orders,
                       (
                           SELECT COALESCE(SUM(s.grand_total), 0)
                           FROM sales s
                           WHERE s.customer_id = c.id
                             AND s.status IN ("completed", "partial_return", "refunded")
                       ) AS total_spent,
                       (
                           SELECT MAX(s.completed_at)
                           FROM sales s
                           WHERE s.customer_id = c.id
                             AND s.status IN ("completed", "partial_return", "refunded")
                       ) AS last_purchase_at
                FROM customers c
                LEFT JOIN customer_groups cg ON cg.id = c.customer_group_id
                WHERE c.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND (c.branch_id = :branch_id OR c.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        if ($customerId !== null && $customerId > 0) {
            $sql .= ' AND c.id = :customer_id';
            $params['customer_id'] = $customerId;
        } elseif ($search !== '') {
            $sql .= ' AND (
                c.first_name LIKE :search
                OR c.last_name LIKE :search
                OR CONCAT(c.first_name, " ", c.last_name) LIKE :search
                OR c.phone LIKE :search
                OR c.email LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        if ($search !== '' && $customerId === null) {
            $sql .= ' ORDER BY
                CASE
                    WHEN c.phone = :exact_match THEN 0
                    WHEN c.email = :exact_match THEN 1
                    WHEN CONCAT(c.first_name, " ", c.last_name) = :exact_match THEN 2
                    WHEN CONCAT(c.first_name, " ", c.last_name) LIKE :starts_with THEN 3
                    WHEN c.phone LIKE :starts_with THEN 4
                    WHEN c.email LIKE :starts_with THEN 5
                    ELSE 6
                END,
                total_orders DESC,
                full_name ASC';
            $params['exact_match'] = $search;
            $params['starts_with'] = $search . '%';
        } else {
            $sql .= ' ORDER BY
                COALESCE(last_purchase_at, c.updated_at, c.created_at) DESC,
                full_name ASC';
        }

        $sql .= ' LIMIT ' . $limit;

        return $this->fetchAll($sql, $params);
    }

    public function groups(?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT *
             FROM customer_groups
             WHERE company_id = :company_id
             ORDER BY name',
            ['company_id' => $companyId]
        );
    }

    public function groupsWithUsage(?int $branchId = null, ?int $companyId = null): array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return [];
        }

        $sql = 'SELECT cg.*,
                       (
                           SELECT COUNT(*)
                           FROM customers c
                           WHERE c.customer_group_id = cg.id
                             AND c.deleted_at IS NULL';
        $params = ['company_id' => $companyId];

        if ($branchId !== null) {
            $sql .= ' AND (c.branch_id = :branch_id OR c.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $sql .= '
                       ) AS customer_count
                FROM customer_groups cg
                WHERE cg.company_id = :company_id
                ORDER BY cg.name';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id, ?int $branchId = null): ?array
    {
        $sql = 'SELECT c.*, cg.name AS customer_group_name, CONCAT(c.first_name, " ", c.last_name) AS full_name
                FROM customers c
                LEFT JOIN customer_groups cg ON cg.id = c.customer_group_id
                WHERE c.id = :id
                  AND c.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($branchId !== null) {
            $sql .= ' AND (c.branch_id = :branch_id OR c.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params);
    }

    public function purchaseHistory(int $customerId): array
    {
        return $this->fetchAll(
            'SELECT s.id, s.sale_number, s.status, s.grand_total, s.amount_paid, s.completed_at,
                    CONCAT(u.first_name, " ", u.last_name) AS cashier_name,
                    COALESCE(cp.credit_amount, 0) AS credit_amount
             FROM sales s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN (
                 SELECT sale_id, SUM(amount) AS credit_amount
                 FROM payments
                 WHERE payment_method = "credit"
                 GROUP BY sale_id
             ) cp ON cp.sale_id = s.id
             WHERE s.customer_id = :customer_id
             ORDER BY s.created_at DESC',
            ['customer_id' => $customerId]
        );
    }

    public function loyaltyHistory(int $customerId): array
    {
        return $this->fetchAll(
            'SELECT lp.*, s.sale_number
             FROM loyalty_points lp
             LEFT JOIN sales s ON s.id = lp.sale_id
             WHERE lp.customer_id = :customer_id
             ORDER BY lp.created_at DESC',
            ['customer_id' => $customerId]
        );
    }

    public function creditHistory(int $customerId): array
    {
        return $this->fetchAll(
            'SELECT cct.*, s.sale_number, r.return_number,
                    CONCAT(u.first_name, " ", u.last_name) AS user_name
             FROM customer_credit_transactions cct
             LEFT JOIN sales s ON s.id = cct.sale_id
             LEFT JOIN returns r ON r.id = cct.return_id
             LEFT JOIN users u ON u.id = cct.user_id
             WHERE cct.customer_id = :customer_id
             ORDER BY cct.created_at DESC, cct.id DESC',
            ['customer_id' => $customerId]
        );
    }

    public function createCustomer(array $payload): int
    {
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateCustomer(int $id, array $payload): bool
    {
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($payload, 'id = :id', ['id' => $id]);
    }

    public function deleteCustomer(int $id): bool
    {
        return $this->softDelete($id);
    }

    public function findGroup(int $id, ?int $companyId = null): ?array
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return null;
        }

        return $this->fetch(
            'SELECT cg.*,
                    (
                        SELECT COUNT(*)
                        FROM customers c
                        WHERE c.customer_group_id = cg.id
                          AND c.deleted_at IS NULL
                    ) AS customer_count
             FROM customer_groups cg
             WHERE cg.id = :id
               AND cg.company_id = :company_id
             LIMIT 1',
            ['id' => $id, 'company_id' => $companyId]
        );
    }

    public function groupNameExists(string $name, ?int $exceptId = null, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        $sql = 'SELECT id FROM customer_groups WHERE company_id = :company_id AND name = :name';
        $params = ['company_id' => $companyId, 'name' => trim($name)];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createGroup(array $payload): int
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            throw new HttpException(500, 'Company context is required to create a customer group.');
        }

        return $this->db->prepare(
            'INSERT INTO customer_groups (company_id, name, discount_type, discount_value, description, created_at, updated_at)
             VALUES (:company_id, :name, :discount_type, :discount_value, :description, NOW(), NOW())'
        )->execute([
            'company_id' => $companyId,
            'name' => trim((string) $payload['name']),
            'discount_type' => $payload['discount_type'],
            'discount_value' => (float) $payload['discount_value'],
            'description' => trim((string) ($payload['description'] ?? '')),
        ]) ? (int) $this->db->lastInsertId() : 0;
    }

    public function updateGroup(int $id, array $payload): bool
    {
        $companyId = $this->resolveCompanyId(isset($payload['company_id']) ? (int) $payload['company_id'] : null);
        if ($companyId === null) {
            return false;
        }

        return $this->db->prepare(
            'UPDATE customer_groups
             SET company_id = :company_id,
                 name = :name,
                 discount_type = :discount_type,
                 discount_value = :discount_value,
                 description = :description,
                 updated_at = NOW()
             WHERE id = :id
               AND company_id = :company_id'
        )->execute([
            'id' => $id,
            'company_id' => $companyId,
            'name' => trim((string) $payload['name']),
            'discount_type' => $payload['discount_type'],
            'discount_value' => (float) $payload['discount_value'],
            'description' => trim((string) ($payload['description'] ?? '')),
        ]);
    }

    public function deleteGroup(int $id, ?int $companyId = null): bool
    {
        $companyId = $this->resolveCompanyId($companyId);
        if ($companyId === null) {
            return false;
        }

        return $this->db->prepare(
            'DELETE FROM customer_groups WHERE id = :id AND company_id = :company_id'
        )->execute([
            'id' => $id,
            'company_id' => $companyId,
        ]);
    }

    public function adjustCreditBalance(int $customerId, float $amount, string $transactionType, ?int $saleId = null, ?int $returnId = null, ?int $userId = null, string $notes = ''): array
    {
        $amount = round($amount, 2);
        if (abs($amount) < 0.0001) {
            $customer = $this->find($customerId);
            if ($customer === null) {
                throw new HttpException(404, 'Customer not found.');
            }

            return [
                'id' => null,
                'amount' => 0.0,
                'balance' => (float) $customer['credit_balance'],
            ];
        }

        return Database::transaction(function () use ($customerId, $amount, $transactionType, $saleId, $returnId, $userId, $notes): array {
            $customer = $this->fetch(
                'SELECT id, credit_balance FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $customerId]
            );

            if ($customer === null) {
                throw new HttpException(404, 'Customer not found.');
            }

            $currentBalance = (float) $customer['credit_balance'];
            $newBalance = round($currentBalance + $amount, 2);

            if ($newBalance < -0.0001) {
                throw new HttpException(500, 'Credit settlement exceeds the customer outstanding balance.');
            }

            if (abs($newBalance) < 0.0001) {
                $newBalance = 0.0;
            }

            $this->db->prepare('UPDATE customers SET credit_balance = :balance, updated_at = NOW() WHERE id = :id')
                ->execute([
                    'balance' => $newBalance,
                    'id' => $customerId,
                ]);

            $this->db->prepare(
                'INSERT INTO customer_credit_transactions (customer_id, sale_id, return_id, user_id, transaction_type, amount, balance_after, notes, created_at)
                 VALUES (:customer_id, :sale_id, :return_id, :user_id, :transaction_type, :amount, :balance_after, :notes, NOW())'
            )->execute([
                'customer_id' => $customerId,
                'sale_id' => $saleId,
                'return_id' => $returnId,
                'user_id' => $userId,
                'transaction_type' => $transactionType,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'notes' => $notes !== '' ? $notes : null,
            ]);

            return [
                'id' => (int) $this->db->lastInsertId(),
                'amount' => $amount,
                'balance' => $newBalance,
            ];
        });
    }

    public function syncCreditBalance(int $customerId, float $targetBalance, ?int $userId = null, string $notes = 'Customer profile credit balance updated.'): array
    {
        $customer = $this->fetch(
            'SELECT id, credit_balance FROM customers WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $customerId]
        );

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        $difference = round($targetBalance - (float) $customer['credit_balance'], 2);

        if (abs($difference) < 0.0001) {
            return [
                'id' => null,
                'amount' => 0.0,
                'balance' => (float) $customer['credit_balance'],
            ];
        }

        return $this->adjustCreditBalance(
            customerId: $customerId,
            amount: $difference,
            transactionType: 'adjustment',
            userId: $userId,
            notes: $notes
        );
    }

    private function resolveCompanyId(?int $companyId = null): ?int
    {
        $companyId ??= current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
