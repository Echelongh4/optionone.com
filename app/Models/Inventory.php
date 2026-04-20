<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Inventory extends Model
{
    protected string $table = 'inventory';

    public function summary(?int $branchId = null): array
    {
        $branchId = $this->resolvedBranchId($branchId);

        $sql = 'SELECT COUNT(*) AS total_products,
                       COALESCE(SUM(CASE WHEN p.track_stock = 1 THEN 1 ELSE 0 END), 0) AS tracked_products,
                       COALESCE(SUM(CASE WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold AND COALESCE(i.quantity_on_hand, 0) > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
                       COALESCE(SUM(CASE WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count,
                       COALESCE(SUM(COALESCE(i.quantity_on_hand, 0)), 0) AS total_units_on_hand,
                       COALESCE(SUM(COALESCE(i.quantity_reserved, 0)), 0) AS total_reserved_units,
                       COALESCE(SUM(GREATEST(COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0)), 0) AS total_available_units,
                       COALESCE(SUM(COALESCE(po_open.open_purchase_quantity, 0)), 0) AS total_open_purchase_units,
                       COALESCE(SUM(CASE WHEN COALESCE(po_open.open_purchase_quantity, 0) > 0 THEN 1 ELSE 0 END), 0) AS items_on_order,
                       COALESCE(SUM(COALESCE(i.quantity_on_hand, 0) * COALESCE(i.average_cost, p.cost_price, 0)), 0) AS total_inventory_value
                FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id
                    AND i.branch_id = :branch_id
                LEFT JOIN (
                    SELECT poi.product_id,
                           COALESCE(SUM(GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0)), 0) AS open_purchase_quantity
                    FROM purchase_order_items poi
                    INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                    WHERE po.branch_id = :open_po_branch_id
                      AND po.status IN ("ordered", "partial_received")
                    GROUP BY poi.product_id
                ) po_open ON po_open.product_id = p.id
                WHERE p.deleted_at IS NULL';
        $params = [
            'branch_id' => $branchId,
            'open_po_branch_id' => $branchId,
        ];
        $sql = $this->appendProductBranchScope($sql, $params, $branchId);

        return $this->fetch($sql, $params) ?? [
            'total_products' => 0,
            'tracked_products' => 0,
            'low_stock_count' => 0,
            'out_of_stock_count' => 0,
            'total_units_on_hand' => 0,
            'total_reserved_units' => 0,
            'total_available_units' => 0,
            'total_open_purchase_units' => 0,
            'items_on_order' => 0,
            'total_inventory_value' => 0,
        ];
    }

    public function overview(array $filters = [], ?int $branchId = null): array
    {
        $branchId = $this->resolvedBranchId($branchId);
        $search = trim((string) ($filters['search'] ?? ''));
        $stockState = trim((string) ($filters['stock_state'] ?? ''));
        $sort = trim((string) ($filters['sort'] ?? 'priority'));
        $productId = (int) ($filters['product_id'] ?? 0);
        $availableQuantitySql = $this->availableQuantitySql();
        $stockStateSql = $this->stockStateSql();
        $shortfallSql = $this->shortfallQuantitySql();
        $reorderQuantitySql = $this->reorderQuantitySql($availableQuantitySql);

        $sql = 'SELECT p.id, p.name, p.brand, p.sku, p.barcode, p.unit, p.low_stock_threshold, p.inventory_method,
                       p.track_stock, p.status,
                       c.name AS category_name,
                       s.name AS supplier_name,
                       COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
                       COALESCE(i.quantity_reserved, 0) AS quantity_reserved,
                       ' . $availableQuantitySql . ' AS available_quantity,
                       COALESCE(i.average_cost, p.cost_price, 0) AS average_cost,
                       COALESCE(i.quantity_on_hand, 0) * COALESCE(i.average_cost, p.cost_price, 0) AS inventory_value,
                       ' . $shortfallSql . ' AS shortfall_quantity,
                       ' . $stockStateSql . ' AS stock_state,
                       COALESCE(po_open.open_purchase_quantity, 0) AS open_purchase_quantity,
                       COALESCE(sales_30.units_sold_30d, 0) AS units_sold_30d,
                       last_move.last_movement_at,
                       ' . $reorderQuantitySql . ' AS reorder_quantity
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN inventory i ON i.product_id = p.id
                    AND i.branch_id = :branch_id
                LEFT JOIN (
                    SELECT poi.product_id,
                           COALESCE(SUM(GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0)), 0) AS open_purchase_quantity
                    FROM purchase_order_items poi
                    INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                    WHERE po.branch_id = :open_po_branch_id
                      AND po.status IN ("ordered", "partial_received")
                    GROUP BY poi.product_id
                ) po_open ON po_open.product_id = p.id
                LEFT JOIN (
                    SELECT sm.product_id,
                           MAX(sm.created_at) AS last_movement_at
                    FROM stock_movements sm
                    WHERE sm.branch_id = :last_move_branch_id
                    GROUP BY sm.product_id
                ) last_move ON last_move.product_id = p.id
                LEFT JOIN (
                    SELECT sm.product_id,
                           COALESCE(SUM(ABS(sm.quantity_change)), 0) AS units_sold_30d
                    FROM stock_movements sm
                    WHERE sm.branch_id = :sales_branch_id
                      AND sm.movement_type = "sale"
                      AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY sm.product_id
                ) sales_30 ON sales_30.product_id = p.id
                WHERE p.deleted_at IS NULL';
        $params = [
            'branch_id' => $branchId,
            'open_po_branch_id' => $branchId,
            'last_move_branch_id' => $branchId,
            'sales_branch_id' => $branchId,
        ];
        $sql = $this->appendProductBranchScope($sql, $params, $branchId);

        if ($search !== '') {
            $searchTerm = '%' . $search . '%';
            $sql .= ' AND (p.name LIKE :search_name OR p.brand LIKE :search_brand OR p.sku LIKE :search_sku OR p.barcode LIKE :search_barcode)';
            $params['search_name'] = $searchTerm;
            $params['search_brand'] = $searchTerm;
            $params['search_sku'] = $searchTerm;
            $params['search_barcode'] = $searchTerm;
        }

        if ($productId > 0) {
            $sql .= ' AND p.id = :product_id';
            $params['product_id'] = $productId;
        }

        $sql .= match ($stockState) {
            'out_of_stock' => ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= 0',
            'low' => ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) > 0 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold',
            'normal' => ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) > p.low_stock_threshold',
            'not_tracked' => ' AND p.track_stock = 0',
            default => '',
        };

        $sql .= match ($sort) {
            'value_desc' => ' ORDER BY inventory_value DESC, p.name ASC',
            'movement_desc' => ' ORDER BY COALESCE(last_movement_at, "1970-01-01 00:00:00") DESC, p.name ASC',
            'sales_desc' => ' ORDER BY units_sold_30d DESC, p.name ASC',
            'available_asc' => ' ORDER BY available_quantity ASC, p.name ASC',
            default => ' ORDER BY CASE
                                WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= 0 THEN -1
                                WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN 0
                                ELSE 1
                             END,
                             reorder_quantity DESC,
                             quantity_reserved DESC,
                             units_sold_30d DESC,
                             p.name ASC',
        };

        return $this->fetchAll($sql, $params);
    }

    public function movementFilters(array $filters = [], ?int $branchId = null, int $limit = 150): array
    {
        $branchId = $this->resolvedBranchId($branchId);
        $sql = 'SELECT sm.*, p.name AS product_name, p.brand, p.sku,
                       CONCAT(u.first_name, " ", u.last_name) AS user_name
                FROM stock_movements sm
                INNER JOIN products p ON p.id = sm.product_id
                LEFT JOIN users u ON u.id = sm.user_id
                WHERE 1 = 1';
        $params = ['branch_id' => $branchId];

        $sql .= ' AND sm.branch_id = :branch_id';

        if (($filters['search'] ?? '') !== '') {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $sql .= ' AND (p.name LIKE :search_name OR p.brand LIKE :search_brand OR p.sku LIKE :search_sku OR p.barcode LIKE :search_barcode)';
            $params['search_name'] = $searchTerm;
            $params['search_brand'] = $searchTerm;
            $params['search_sku'] = $searchTerm;
            $params['search_barcode'] = $searchTerm;
        }

        if ((int) ($filters['product_id'] ?? 0) > 0) {
            $sql .= ' AND sm.product_id = :product_id';
            $params['product_id'] = (int) $filters['product_id'];
        }

        if (($filters['movement_type'] ?? '') !== '') {
            $sql .= ' AND sm.movement_type = :movement_type';
            $params['movement_type'] = $filters['movement_type'];
        }

        if (($filters['date_from'] ?? '') !== '') {
            $sql .= ' AND DATE(sm.created_at) >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (($filters['date_to'] ?? '') !== '') {
            $sql .= ' AND DATE(sm.created_at) <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $sql .= ' ORDER BY sm.created_at DESC LIMIT ' . max(1, (int) $limit);

        return $this->fetchAll($sql, $params);
    }

    public function adjustmentProducts(?int $branchId = null): array
    {
        $branchId = $this->resolvedBranchId($branchId);

        return $this->fetchAll(
            'SELECT p.id, p.name, p.sku, COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
                    p.brand,
                    COALESCE(i.quantity_reserved, 0) AS quantity_reserved,
                    COALESCE(i.average_cost, p.cost_price, 0) AS average_cost
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = :branch_id
             WHERE p.deleted_at IS NULL AND p.track_stock = 1
               AND (p.branch_id = :product_branch_id OR p.branch_id IS NULL)
             ORDER BY p.name ASC',
            ['branch_id' => $branchId, 'product_branch_id' => $branchId]
        );
    }

    public function findProduct(int $productId, ?int $branchId = null): ?array
    {
        $branchId = $this->resolvedBranchId($branchId);
        $availableQuantitySql = $this->availableQuantitySql();
        $stockStateSql = $this->stockStateSql();
        $shortfallSql = $this->shortfallQuantitySql();

        return $this->fetch(
            'SELECT p.id, p.name, p.brand, p.sku, p.barcode, p.unit, p.price, p.cost_price, p.low_stock_threshold,
                    p.track_stock, p.inventory_method, p.status, p.description,
                    c.name AS category_name,
                    s.id AS supplier_id,
                    s.name AS supplier_name,
                    t.name AS tax_name,
                    t.rate AS tax_rate,
                    COALESCE(i.quantity_on_hand, 0) AS quantity_on_hand,
                    COALESCE(i.quantity_reserved, 0) AS quantity_reserved,
                    ' . $availableQuantitySql . ' AS available_quantity,
                    COALESCE(i.average_cost, p.cost_price, 0) AS average_cost,
                    COALESCE(i.quantity_on_hand, 0) * COALESCE(i.average_cost, p.cost_price, 0) AS inventory_value,
                    ' . $shortfallSql . ' AS shortfall_quantity,
                    ' . $stockStateSql . ' AS stock_state,
                    i.last_restocked_at,
                    (
                        SELECT MAX(sm.created_at)
                        FROM stock_movements sm
                        WHERE sm.product_id = p.id
                          AND sm.branch_id = :branch_id_last
                    ) AS last_movement_at,
                    (
                        SELECT COALESCE(SUM(ABS(sm.quantity_change)), 0)
                        FROM stock_movements sm
                        WHERE sm.product_id = p.id
                          AND sm.branch_id = :branch_id_sold
                          AND sm.movement_type = "sale"
                          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) AS units_sold_30d,
                    (
                        SELECT COALESCE(SUM(sm.quantity_change), 0)
                        FROM stock_movements sm
                        WHERE sm.product_id = p.id
                          AND sm.branch_id = :branch_id_inbound
                          AND sm.quantity_change > 0
                          AND sm.movement_type IN ("purchase", "transfer_in", "return", "opening", "adjustment")
                          AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    ) AS inbound_units_30d,
                    (
                        SELECT COALESCE(SUM(GREATEST(poi.quantity - COALESCE(poi.received_quantity, 0), 0)), 0)
                        FROM purchase_order_items poi
                        INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
                        WHERE poi.product_id = p.id
                          AND po.branch_id = :branch_id_open_po
                          AND po.status IN ("ordered", "partial_received")
                    ) AS open_purchase_quantity
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN taxes t ON t.id = p.tax_id
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = :branch_id
             WHERE p.id = :product_id
               AND p.deleted_at IS NULL
               AND (p.branch_id = :product_branch_id OR p.branch_id IS NULL)',
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
                'product_branch_id' => $branchId,
                'branch_id_last' => $branchId,
                'branch_id_sold' => $branchId,
                'branch_id_inbound' => $branchId,
                'branch_id_open_po' => $branchId,
            ]
        );
    }

    public function recentPurchaseOrders(int $productId, ?int $branchId = null, int $limit = 6): array
    {
        $branchId = $this->resolvedBranchId($branchId);

        return $this->fetchAll(
            'SELECT po.id, po.po_number, po.status, po.ordered_at, po.expected_at, po.received_at,
                    poi.quantity, COALESCE(poi.received_quantity, 0) AS received_quantity, poi.unit_cost,
                    s.name AS supplier_name
             FROM purchase_order_items poi
             INNER JOIN purchase_orders po ON po.id = poi.purchase_order_id
             LEFT JOIN suppliers s ON s.id = po.supplier_id
             WHERE poi.product_id = :product_id
               AND po.branch_id = :branch_id
             ORDER BY COALESCE(po.ordered_at, po.created_at) DESC
             LIMIT ' . max(1, (int) $limit),
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
            ]
        );
    }

    private function resolvedBranchId(?int $branchId): int
    {
        if ($branchId !== null && $branchId > 0) {
            return $branchId;
        }

        $companyId = current_company_id();
        if ($companyId !== null) {
            $branch = $this->fetch(
                'SELECT id
                 FROM branches
                 WHERE company_id = :company_id
                   AND is_default = 1
                 LIMIT 1',
                ['company_id' => $companyId]
            );

            return (int) ($branch['id'] ?? 0);
        }

        $branch = $this->fetch('SELECT id FROM branches WHERE is_default = 1 LIMIT 1');

        return (int) ($branch['id'] ?? 0);
    }

    private function availableQuantitySql(): string
    {
        return 'GREATEST(COALESCE(i.quantity_on_hand, 0) - COALESCE(i.quantity_reserved, 0), 0)';
    }

    private function stockStateSql(): string
    {
        return 'CASE
                    WHEN p.track_stock = 0 THEN "not_tracked"
                    WHEN COALESCE(i.quantity_on_hand, 0) <= 0 THEN "out_of_stock"
                    WHEN COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN "low"
                    ELSE "normal"
                END';
    }

    private function shortfallQuantitySql(): string
    {
        return 'CASE
                    WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) < p.low_stock_threshold
                        THEN p.low_stock_threshold - COALESCE(i.quantity_on_hand, 0)
                    ELSE 0
                END';
    }

    private function reorderQuantitySql(string $availableQuantitySql): string
    {
        return 'CASE
                    WHEN p.track_stock = 1
                        THEN GREATEST(p.low_stock_threshold - (' . $availableQuantitySql . ' + COALESCE(po_open.open_purchase_quantity, 0)), 0)
                    ELSE 0
                END';
    }

    private function appendProductBranchScope(string $sql, array &$params, int $branchId): string
    {
        $params['product_branch_id'] = $branchId;

        return $sql . ' AND (p.branch_id = :product_branch_id OR p.branch_id IS NULL)';
    }
}
