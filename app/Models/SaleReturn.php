<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class SaleReturn extends Model
{
    protected string $table = 'returns';

    public function summary(array $filters = [], ?int $branchId = null): array
    {
        $scope = $this->scope($filters, $branchId, 'r', 's', 'c');

        $summary = $this->fetch(
            'SELECT COUNT(DISTINCT r.id) AS total_returns,
                    COUNT(DISTINCT r.sale_id) AS linked_sales,
                    COUNT(DISTINCT r.customer_id) AS customers_impacted,
                    COALESCE(SUM(r.total_refund), 0) AS total_refund
             FROM returns r
             INNER JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             WHERE ' . $scope['where'],
            $scope['params']
        ) ?? [
            'total_returns' => 0,
            'linked_sales' => 0,
            'customers_impacted' => 0,
            'total_refund' => 0,
        ];

        $items = $this->fetch(
            'SELECT COALESCE(SUM(ri.quantity), 0) AS items_returned
             FROM return_items ri
             INNER JOIN returns r ON r.id = ri.return_id
             INNER JOIN sales s ON s.id = r.sale_id
             LEFT JOIN customers c ON c.id = r.customer_id
             WHERE ' . $scope['where'],
            $scope['params']
        ) ?? ['items_returned' => 0];

        return [
            'total_returns' => (int) ($summary['total_returns'] ?? 0),
            'linked_sales' => (int) ($summary['linked_sales'] ?? 0),
            'customers_impacted' => (int) ($summary['customers_impacted'] ?? 0),
            'total_refund' => (float) ($summary['total_refund'] ?? 0),
            'items_returned' => (float) ($items['items_returned'] ?? 0),
        ];
    }

    public function list(array $filters = [], ?int $branchId = null): array
    {
        $scope = $this->scope($filters, $branchId, 'r', 's', 'c');

        return $this->fetchAll(
            'SELECT r.*,
                    s.sale_number,
                    s.status AS sale_status,
                    s.branch_id,
                    b.name AS branch_name,
                    COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name,
                    CONCAT(u.first_name, " ", u.last_name) AS processed_by_name,
                    CONCAT(ap.first_name, " ", ap.last_name) AS approved_by_name,
                    COUNT(ri.id) AS line_count,
                    COALESCE(SUM(ri.quantity), 0) AS items_returned
             FROM returns r
             INNER JOIN sales s ON s.id = r.sale_id
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN customers c ON c.id = r.customer_id
             INNER JOIN users u ON u.id = r.user_id
             LEFT JOIN users ap ON ap.id = r.approved_by
             LEFT JOIN return_items ri ON ri.return_id = r.id
             WHERE ' . $scope['where'] . '
             GROUP BY r.id, r.sale_id, r.user_id, r.customer_id, r.return_number, r.reason, r.status,
                      r.subtotal, r.tax_total, r.total_refund, r.approved_by, r.created_at, r.updated_at,
                      s.sale_number, s.status, s.branch_id, b.name, c.first_name, c.last_name,
                      u.first_name, u.last_name, ap.first_name, ap.last_name
             ORDER BY r.created_at DESC, r.id DESC',
            $scope['params']
        );
    }

    public function findDetailed(int $returnId): ?array
    {
        $return = $this->fetch(
            'SELECT r.*,
                    s.sale_number,
                    s.status AS sale_status,
                    s.grand_total AS sale_grand_total,
                    s.completed_at AS sale_completed_at,
                    s.branch_id,
                    b.name AS branch_name,
                    COALESCE(CONCAT(c.first_name, " ", c.last_name), "Walk-in Customer") AS customer_name,
                    c.email AS customer_email,
                    c.phone AS customer_phone,
                    CONCAT(u.first_name, " ", u.last_name) AS processed_by_name,
                    CONCAT(ap.first_name, " ", ap.last_name) AS approved_by_name
             FROM returns r
             INNER JOIN sales s ON s.id = r.sale_id
             LEFT JOIN branches b ON b.id = s.branch_id
             LEFT JOIN customers c ON c.id = r.customer_id
             INNER JOIN users u ON u.id = r.user_id
             LEFT JOIN users ap ON ap.id = r.approved_by
             WHERE r.id = :id
             LIMIT 1',
            ['id' => $returnId]
        );

        if ($return === null) {
            return null;
        }

        $return['items'] = $this->fetchAll(
            'SELECT ri.*,
                    si.product_name,
                    si.sku,
                    si.barcode,
                    si.quantity AS sold_quantity,
                    si.tax_rate
             FROM return_items ri
             INNER JOIN sale_items si ON si.id = ri.sale_item_id
             WHERE ri.return_id = :return_id
             ORDER BY ri.id ASC',
            ['return_id' => $returnId]
        );

        $return['credit_transactions'] = $this->fetchAll(
            'SELECT cct.*,
                    CONCAT(u.first_name, " ", u.last_name) AS user_name
             FROM customer_credit_transactions cct
             LEFT JOIN users u ON u.id = cct.user_id
             WHERE cct.return_id = :return_id
             ORDER BY cct.created_at ASC, cct.id ASC',
            ['return_id' => $returnId]
        );

        return $return;
    }

    private function scope(array $filters, ?int $branchId, string $returnAlias, string $saleAlias, string $customerAlias): array
    {
        $clauses = [$saleAlias . '.deleted_at IS NULL'];
        $params = [];

        if ($branchId !== null) {
            $clauses[] = $saleAlias . '.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        if (($filters['status'] ?? '') !== '') {
            $clauses[] = $returnAlias . '.status = :status';
            $params['status'] = $filters['status'];
        }

        if (($filters['processed_by'] ?? '') !== '') {
            $clauses[] = $returnAlias . '.user_id = :processed_by';
            $params['processed_by'] = (int) $filters['processed_by'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = 'DATE(' . $returnAlias . '.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = 'DATE(' . $returnAlias . '.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (($filters['search'] ?? '') !== '') {
            $clauses[] = '(' . $returnAlias . '.return_number LIKE :search
                OR ' . $saleAlias . '.sale_number LIKE :search
                OR CONCAT(COALESCE(' . $customerAlias . '.first_name, ""), " ", COALESCE(' . $customerAlias . '.last_name, "")) LIKE :search
                OR COALESCE(' . $customerAlias . '.phone, "") LIKE :search)';
            $params['search'] = '%' . trim((string) $filters['search']) . '%';
        }

        return [
            'where' => implode(' AND ', $clauses),
            'params' => $params,
        ];
    }
}
