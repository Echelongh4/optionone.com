<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Supplier extends Model
{
    protected string $table = 'suppliers';

    public function list(?int $branchId = null, string $search = ''): array
    {
        $sql = 'SELECT s.*,
                       (
                           SELECT COUNT(*)
                           FROM products p
                           WHERE p.supplier_id = s.id
                             AND p.deleted_at IS NULL
                       ) AS total_products,
                       (
                           SELECT COUNT(*)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS total_purchase_orders,
                       (
                           SELECT COALESCE(SUM(po.total), 0)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS total_purchase_value,
                       (
                           SELECT MAX(po.created_at)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS last_purchase_order_at
                FROM suppliers s
                WHERE s.deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND (s.branch_id = :branch_id OR s.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $search = trim($search);
        if ($search !== '') {
            $sql .= ' AND (
                s.name LIKE :search
                OR s.contact_person LIKE :search
                OR s.email LIKE :search
                OR s.phone LIKE :search
                OR s.tax_number LIKE :search
            )';
            $params['search'] = '%' . $search . '%';
        }

        $sql .= ' ORDER BY s.name ASC';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id, ?int $branchId = null): ?array
    {
        $sql = 'SELECT * FROM suppliers WHERE id = :id AND deleted_at IS NULL';
        $params = ['id' => $id];

        if ($branchId !== null) {
            $sql .= ' AND (branch_id = :branch_id OR branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params);
    }

    public function findDetailed(int $id, ?int $branchId = null): ?array
    {
        $sql = 'SELECT s.*,
                       (
                           SELECT COUNT(*)
                           FROM products p
                           WHERE p.supplier_id = s.id
                             AND p.deleted_at IS NULL
                       ) AS total_products,
                       (
                           SELECT COUNT(*)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS total_purchase_orders,
                       (
                           SELECT COALESCE(SUM(po.total), 0)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS total_purchase_value,
                       (
                           SELECT MAX(po.created_at)
                           FROM purchase_orders po
                           WHERE po.supplier_id = s.id
                             AND po.deleted_at IS NULL
                       ) AS last_purchase_order_at
                FROM suppliers s
                WHERE s.id = :id
                  AND s.deleted_at IS NULL';
        $params = ['id' => $id];

        if ($branchId !== null) {
            $sql .= ' AND (s.branch_id = :branch_id OR s.branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $supplier = $this->fetch($sql . ' LIMIT 1', $params);
        if ($supplier === null) {
            return null;
        }

        $supplier['products'] = $this->productsForSupplier($id, $branchId);
        $supplier['purchase_orders'] = $this->purchaseOrdersForSupplier($id, $branchId);

        return $supplier;
    }

    public function nameExists(string $name, ?int $branchId = null, ?int $exceptId = null): bool
    {
        $sql = 'SELECT id
                FROM suppliers
                WHERE deleted_at IS NULL
                  AND name = :name';
        $params = ['name' => trim($name)];

        if ($branchId !== null) {
            $sql .= ' AND (branch_id = :branch_id OR branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function createSupplier(array $payload): int
    {
        $payload['created_at'] = date('Y-m-d H:i:s');
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->insert($payload);
    }

    public function updateSupplier(int $id, array $payload): bool
    {
        $payload['updated_at'] = date('Y-m-d H:i:s');

        return $this->updateRecord($payload, 'id = :id', ['id' => $id]);
    }

    public function deleteSupplier(int $id): bool
    {
        return $this->softDelete($id);
    }

    public function productsForSupplier(int $supplierId, ?int $branchId = null, int $limit = 8): array
    {
        $sql = 'SELECT p.id, p.name, p.sku, p.status, p.price, p.cost_price, p.low_stock_threshold,
                       c.name AS category_name,
                       COALESCE(i.quantity_on_hand, 0) AS stock_quantity
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN inventory i ON i.product_id = p.id
                   AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
                WHERE p.supplier_id = :supplier_id
                  AND p.deleted_at IS NULL';
        $params = [
            'supplier_id' => $supplierId,
            'branch_id' => $branchId,
        ];

        if ($branchId !== null) {
            $sql .= ' AND (p.branch_id = :product_branch_id OR p.branch_id IS NULL)';
            $params['product_branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT ' . max($limit, 1);

        return $this->fetchAll($sql, $params);
    }

    public function purchaseOrdersForSupplier(int $supplierId, ?int $branchId = null, int $limit = 8): array
    {
        $sql = 'SELECT po.id, po.po_number, po.status, po.total, po.created_at, po.expected_at, po.received_at,
                       b.name AS branch_name,
                       CONCAT(u.first_name, " ", u.last_name) AS created_by_name
                FROM purchase_orders po
                INNER JOIN branches b ON b.id = po.branch_id
                INNER JOIN users u ON u.id = po.created_by
                WHERE po.supplier_id = :supplier_id
                  AND po.deleted_at IS NULL';
        $params = ['supplier_id' => $supplierId];

        if ($branchId !== null) {
            $sql .= ' AND po.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY po.created_at DESC LIMIT ' . max($limit, 1);

        return $this->fetchAll($sql, $params);
    }
}
