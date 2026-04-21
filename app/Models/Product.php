<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\Model;
use App\Services\OperationsEmailService;

class Product extends Model
{
    protected string $table = 'products';

    public function allWithRelations(?string $search = null, ?int $branchId = null, ?int $categoryId = null, ?string $brand = null): array
    {
        $sql = 'SELECT p.*, c.name AS category_name, pc.name AS parent_category_name, t.name AS tax_name, t.rate AS tax_rate,
                       s.name AS supplier_name, COALESCE(i.quantity_on_hand, 0) AS stock_quantity
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                LEFT JOIN categories pc ON pc.id = c.parent_id
                LEFT JOIN taxes t ON t.id = p.tax_id
                LEFT JOIN suppliers s ON s.id = p.supplier_id
                LEFT JOIN inventory i ON i.product_id = p.id
                    AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
                WHERE p.deleted_at IS NULL';
        $params = ['branch_id' => $branchId];
        $sql = $this->appendCompanyScope($sql, $params);
        $sql = $this->appendBranchScope($sql, $params, $branchId);

        if ($search !== null && $search !== '') {
            $searchTerm = '%' . $search . '%';
            $sql .= ' AND (p.name LIKE :search_name OR p.brand LIKE :search_brand OR p.sku LIKE :search_sku OR p.barcode LIKE :search_barcode)';
            $params['search_name'] = $searchTerm;
            $params['search_brand'] = $searchTerm;
            $params['search_sku'] = $searchTerm;
            $params['search_barcode'] = $searchTerm;
        }

        if ($categoryId !== null && $categoryId > 0) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        $brand = trim((string) $brand);
        if ($brand !== '') {
            if ($brand === '__unbranded__') {
                $sql .= ' AND TRIM(COALESCE(p.brand, "")) = ""';
            } else {
                $sql .= ' AND p.brand = :brand';
                $params['brand'] = $brand;
            }
        }

        $sql .= ' ORDER BY p.created_at DESC';

        return $this->fetchAll($sql, $params);
    }

    public function brands(?int $branchId = null): array
    {
        $sql = 'SELECT
                    CASE
                        WHEN TRIM(COALESCE(brand, "")) = "" THEN "__unbranded__"
                        ELSE brand
                    END AS brand_key,
                    CASE
                        WHEN TRIM(COALESCE(brand, "")) = "" THEN "Unbranded"
                        ELSE brand
                    END AS brand_label,
                    COUNT(*) AS product_count
                FROM products p
                WHERE p.deleted_at IS NULL';
        $params = [];
        $sql = $this->appendCompanyScope($sql, $params);
        $sql = $this->appendBranchScope($sql, $params, $branchId);
        $sql .= '
                GROUP BY
                    CASE
                        WHEN TRIM(COALESCE(brand, "")) = "" THEN "__unbranded__"
                        ELSE brand
                    END,
                    CASE
                        WHEN TRIM(COALESCE(brand, "")) = "" THEN "Unbranded"
                        ELSE brand
                    END
                ORDER BY brand_label ASC';

        return $this->fetchAll($sql, $params);
    }

    public function find(int $id, ?int $branchId = null): ?array
    {
        $params = ['id' => $id, 'branch_id' => $branchId];
        $sql = 'SELECT p.*, COALESCE(i.quantity_on_hand, 0) AS stock_quantity
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
             WHERE p.id = :id AND p.deleted_at IS NULL';
        $sql = $this->appendCompanyScope($sql, $params);
        $product = $this->fetch(
            $sql,
            $params
        );

        if ($product === null) {
            return null;
        }

        if ($branchId !== null && !$this->matchesBranchScope($product, $branchId)) {
            return null;
        }

        $product['variants'] = $this->variantsFor($id);

        return $product;
    }

    public function findDetailed(int $id, ?int $branchId = null): ?array
    {
        $params = ['id' => $id, 'branch_id' => $branchId];
        $sql = 'SELECT p.*,
                    c.name AS category_name,
                    pc.name AS parent_category_name,
                    s.name AS supplier_name,
                    s.contact_person AS supplier_contact_person,
                    s.email AS supplier_email,
                    s.phone AS supplier_phone,
                    t.name AS tax_name,
                    t.rate AS tax_rate,
                    COALESCE(i.quantity_on_hand, 0) AS stock_quantity,
                    COALESCE(i.average_cost, p.cost_price, 0) AS average_cost
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories pc ON pc.id = c.parent_id
             LEFT JOIN suppliers s ON s.id = p.supplier_id
             LEFT JOIN taxes t ON t.id = p.tax_id
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
             WHERE p.id = :id
               AND p.deleted_at IS NULL';
        $sql = $this->appendCompanyScope($sql, $params);
        $product = $this->fetch(
            $sql . ' LIMIT 1',
            $params
        );

        if ($product === null) {
            return null;
        }

        if ($branchId !== null && !$this->matchesBranchScope($product, $branchId)) {
            return null;
        }

        $product['variants'] = $this->variantsFor($id);
        $product['movements'] = $this->recentMovements($id, $branchId);

        return $product;
    }

    public function categories(): array
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT c.id, c.name, c.parent_id, p.name AS parent_name
             FROM categories c
             LEFT JOIN categories p ON p.id = c.parent_id
             WHERE c.company_id = :company_id
               AND c.deleted_at IS NULL
             ORDER BY COALESCE(p.name, c.name), c.name',
            ['company_id' => $companyId]
        );
    }

    public function suppliers(?int $branchId = null): array
    {
        $sql = 'SELECT id, name
                FROM suppliers
                WHERE deleted_at IS NULL';
        $params = [];

        if ($branchId !== null) {
            $sql .= ' AND (branch_id = :branch_id OR branch_id IS NULL)';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY name';

        return $this->fetchAll($sql, $params);
    }

    public function taxes(): array
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId === null) {
            return [];
        }

        return $this->fetchAll(
            'SELECT id, name, rate, inclusive
             FROM taxes
             WHERE company_id = :company_id
             ORDER BY name',
            ['company_id' => $companyId]
        );
    }

    public function skuExists(string $sku, ?int $exceptId = null, ?int $branchId = null): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return false;
        }

        $sql = 'SELECT id FROM products WHERE sku = :sku AND deleted_at IS NULL';
        $params = ['sku' => $sku];
        $sql = $this->appendCompanyScope($sql, $params, 'company_id');
        $sql = $this->appendBranchScope($sql, $params, $branchId, 'branch_id', 'sku_branch_id');

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function barcodeExists(string $barcode, ?int $exceptId = null, ?int $branchId = null): bool
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return false;
        }

        $sql = 'SELECT id FROM products WHERE barcode = :barcode AND deleted_at IS NULL';
        $params = ['barcode' => $barcode];
        $sql = $this->appendCompanyScope($sql, $params, 'company_id');
        $sql = $this->appendBranchScope($sql, $params, $branchId, 'branch_id', 'barcode_branch_id');

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $exceptId;
        }

        return $this->fetch($sql . ' LIMIT 1', $params) !== null;
    }

    public function findExistingForRestock(array $payload, ?int $branchId = null): ?array
    {
        $sku = trim((string) ($payload['sku'] ?? ''));
        if ($sku !== '') {
            $params = ['sku' => $sku];
            $sql = 'SELECT * FROM products WHERE sku = :sku AND deleted_at IS NULL';
            $sql = $this->appendCompanyScope($sql, $params, 'company_id');
            $sql = $this->appendBranchScope($sql, $params, $branchId, 'branch_id', 'restock_branch_id');
            $product = $this->fetch(
                $sql . ' LIMIT 1',
                $params
            );

            if ($product !== null) {
                return $product;
            }
        }

        $barcode = trim((string) ($payload['barcode'] ?? ''));
        if ($barcode !== '') {
            $params = ['barcode' => $barcode];
            $sql = 'SELECT * FROM products WHERE barcode = :barcode AND deleted_at IS NULL';
            $sql = $this->appendCompanyScope($sql, $params, 'company_id');
            $sql = $this->appendBranchScope($sql, $params, $branchId, 'branch_id', 'restock_barcode_branch_id');
            $product = $this->fetch(
                $sql . ' LIMIT 1',
                $params
            );

            if ($product !== null) {
                return $product;
            }
        }

        $name = trim((string) ($payload['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $brand = trim((string) ($payload['brand'] ?? ''));
        $normalizedBrand = strtolower($brand);
        $categoryId = $payload['category_id'] ?? null;
        $modelCode = $this->extractModelCode($sku, $name);

        if ($modelCode !== null) {
            $sql = 'SELECT p.*, COALESCE((SELECT SUM(quantity_on_hand) FROM inventory WHERE product_id = p.id), 0) AS stock_quantity
                    FROM products p
                    WHERE p.deleted_at IS NULL';
            $params = [];
            $sql = $this->appendCompanyScope($sql, $params, 'p.company_id');
            $sql = $this->appendBranchScope($sql, $params, $branchId);

            if ($categoryId !== null) {
                $sql .= ' AND p.category_id = :category_id';
                $params['category_id'] = (int) $categoryId;
            }

            $sql .= ' ORDER BY stock_quantity DESC, p.created_at ASC, p.id ASC';

            foreach ($this->fetchAll($sql, $params) as $candidate) {
                if ($normalizedBrand !== '' && strtolower(trim((string) ($candidate['brand'] ?? ''))) !== $normalizedBrand) {
                    continue;
                }

                $candidateModelCode = $this->extractModelCode((string) ($candidate['sku'] ?? ''), (string) ($candidate['name'] ?? ''));
                if ($candidateModelCode === $modelCode) {
                    return $candidate;
                }
            }
        }

        if ($categoryId !== null) {
            $params = [
                'name' => strtolower($name),
                'category_id' => (int) $categoryId,
            ];
            $sql = 'SELECT p.*, COALESCE((SELECT SUM(quantity_on_hand) FROM inventory WHERE product_id = p.id), 0) AS stock_quantity
                 FROM products p
                 WHERE LOWER(TRIM(p.name)) = :name
                   AND p.category_id = :category_id
                   AND p.deleted_at IS NULL';
            $sql = $this->appendCompanyScope($sql, $params, 'p.company_id');
            if ($normalizedBrand !== '') {
                $sql .= ' AND LOWER(TRIM(COALESCE(p.brand, ""))) = :brand';
                $params['brand'] = $normalizedBrand;
            }
            $sql = $this->appendBranchScope($sql, $params, $branchId);
            $product = $this->fetch(
                $sql . ' ORDER BY stock_quantity DESC, p.created_at ASC, p.id ASC LIMIT 1',
                $params
            );

            if ($product !== null) {
                return $product;
            }
        }

        $params = ['name' => strtolower($name)];
        $sql = 'SELECT p.*, COALESCE((SELECT SUM(quantity_on_hand) FROM inventory WHERE product_id = p.id), 0) AS stock_quantity
             FROM products p
             WHERE LOWER(TRIM(p.name)) = :name
               AND p.deleted_at IS NULL';
        $sql = $this->appendCompanyScope($sql, $params, 'p.company_id');
        if ($normalizedBrand !== '') {
            $sql .= ' AND LOWER(TRIM(COALESCE(p.brand, ""))) = :brand';
            $params['brand'] = $normalizedBrand;
        }
        $sql = $this->appendBranchScope($sql, $params, $branchId);

        return $this->fetch(
            $sql . ' ORDER BY stock_quantity DESC, p.created_at ASC, p.id ASC LIMIT 1',
            $params
        );
    }

    public function createProduct(array $payload, array $variants, int $branchId): int
    {
        $now = date('Y-m-d H:i:s');
        $openingStock = (float) ($payload['opening_stock'] ?? 0);
        unset($payload['opening_stock']);

        $payload['company_id'] = (int) ($payload['company_id'] ?? $this->resolveCompanyId() ?? 0);
        if ($payload['company_id'] <= 0) {
            throw new HttpException(500, 'Company context is required to create a product.');
        }
        $payload['slug'] = $this->slugify($payload['name']);
        $payload['sku'] = $this->resolveSku($payload, null, (int) ($payload['branch_id'] ?? $branchId));
        $payload['barcode'] = $this->resolveBarcode($payload, null, (int) ($payload['branch_id'] ?? $branchId));
        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        $productId = $this->insert($payload);
        $this->createInventory($productId, $branchId, $openingStock, (float) $payload['cost_price'], (string) $payload['inventory_method']);
        $this->saveVariants($productId, $variants);

        return $productId;
    }

    public function restockExistingProduct(int $id, array $payload, int $branchId, int $userId): void
    {
        $openingStock = (float) ($payload['opening_stock'] ?? 0);
        unset($payload['opening_stock']);

        Database::transaction(function () use ($id, $payload, $branchId, $userId, $openingStock): void {
            $this->restockExistingProductWithinTransaction($id, $payload, $branchId, $userId, $openingStock);
        });
    }

    public function restockExistingProductWithinTransaction(int $id, array $payload, int $branchId, int $userId, ?float $openingStock = null): void
    {
        $openingStock ??= (float) ($payload['opening_stock'] ?? 0);
        unset($payload['opening_stock']);

        $params = ['id' => $id];
        $sql = 'SELECT * FROM products WHERE id = :id AND deleted_at IS NULL';
        $sql = $this->appendCompanyScope($sql, $params, 'company_id');
        $existing = $this->fetch($sql . ' LIMIT 1', $params);
        if ($existing === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $updatePayload = [
            'company_id' => $existing['company_id'],
            'branch_id' => $existing['branch_id'],
            'category_id' => $payload['category_id'],
            'supplier_id' => $payload['supplier_id'],
            'tax_id' => $payload['tax_id'],
            'name' => $payload['name'],
            'brand' => $payload['brand'],
            'slug' => $this->slugify((string) $payload['name']),
            'sku' => $this->resolveSku($payload, (string) ($existing['sku'] ?? ''), (int) ($existing['branch_id'] ?? $branchId), $id),
            'barcode' => $this->resolveBarcode($payload, (string) ($existing['barcode'] ?? ''), (int) ($existing['branch_id'] ?? $branchId), $id),
            'description' => $payload['description'],
            'unit' => $payload['unit'],
            'price' => $payload['price'],
            'cost_price' => $payload['cost_price'],
            'low_stock_threshold' => $payload['low_stock_threshold'],
            'track_stock' => $payload['track_stock'],
            'status' => $payload['status'],
            'inventory_method' => $payload['inventory_method'],
            'image_path' => $payload['image_path'] ?? $existing['image_path'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $this->updateRecord($updatePayload, 'id = :id', ['id' => $id]);

        if ($openingStock > 0) {
            $this->adjustInventory(
                productId: $id,
                branchId: $branchId,
                quantityChange: $openingStock,
                movementType: 'adjustment',
                reason: 'Stock added from the product creation form.',
                userId: $userId,
                referenceType: 'product_restock',
                referenceId: $id,
                unitCost: (float) $payload['cost_price']
            );
        }
    }

    public function updateProduct(int $id, array $payload, array $variants): bool
    {
        unset($payload['opening_stock']);
        $params = ['id' => $id];
        $sql = 'SELECT sku, barcode, branch_id, company_id FROM products WHERE id = :id AND deleted_at IS NULL';
        $sql = $this->appendCompanyScope($sql, $params, 'company_id');
        $existing = $this->fetch($sql . ' LIMIT 1', $params);
        if ($existing === null) {
            return false;
        }

        $payload['company_id'] = (int) ($existing['company_id'] ?? $this->resolveCompanyId() ?? 0);
        $payload['slug'] = $this->slugify($payload['name']);
        $payload['sku'] = $this->resolveSku($payload, (string) ($existing['sku'] ?? ''), (int) ($existing['branch_id'] ?? ($payload['branch_id'] ?? 0)), $id);
        $payload['barcode'] = $this->resolveBarcode($payload, (string) ($existing['barcode'] ?? ''), (int) ($existing['branch_id'] ?? ($payload['branch_id'] ?? 0)), $id);
        $payload['updated_at'] = date('Y-m-d H:i:s');

        $updated = $this->updateRecord($payload, 'id = :id AND company_id = :company_id', ['id' => $id, 'company_id' => $payload['company_id']]);
        $this->saveVariants($id, $variants);

        return $updated;
    }

    public function deleteProduct(int $id): bool
    {
        return $this->softDelete($id);
    }

    public function variantsFor(int $productId): array
    {
        return $this->fetchAll(
            'SELECT * FROM product_variants WHERE product_id = :product_id AND deleted_at IS NULL ORDER BY id',
            ['product_id' => $productId]
        );
    }

    public function catalogForPos(?int $branchId = null): array
    {
        $sql = 'SELECT p.id, p.name, p.brand, p.sku, p.barcode, p.unit, p.price, p.image_path, p.track_stock,
                    p.category_id, p.low_stock_threshold,
                    COALESCE(i.quantity_on_hand, 0) AS stock_quantity,
                    COALESCE(t.rate, 0) AS tax_rate,
                    t.name AS tax_name,
                    c.name AS category_name
             FROM products p
             LEFT JOIN inventory i ON i.product_id = p.id
                AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
             LEFT JOIN taxes t ON t.id = p.tax_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL AND p.status = "active"';
        $params = ['branch_id' => $branchId];
        $sql = $this->appendCompanyScope($sql, $params);
        $sql = $this->appendBranchScope($sql, $params, $branchId);
        $sql .= ' ORDER BY p.name';

        return $this->fetchAll($sql, $params);
    }

    public function catalogMetaForPos(?int $branchId = null): array
    {
        $summarySql = 'SELECT COUNT(*) AS total_count,
                              SUM(CASE WHEN p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold THEN 1 ELSE 0 END) AS low_stock_count,
                              SUM(CASE WHEN p.track_stock = 0 OR COALESCE(i.quantity_on_hand, 0) > 0 THEN 1 ELSE 0 END) AS available_count
                       FROM products p
                       LEFT JOIN inventory i ON i.product_id = p.id
                          AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
                       WHERE p.deleted_at IS NULL
                         AND p.status = "active"';
        $summaryParams = ['branch_id' => $branchId];
        $summarySql = $this->appendCompanyScope($summarySql, $summaryParams);
        $summarySql = $this->appendBranchScope($summarySql, $summaryParams, $branchId);
        $summary = $this->fetch($summarySql, $summaryParams) ?? [];

        $brandSql = 'SELECT
                        CASE WHEN TRIM(COALESCE(p.brand, "")) = "" THEN "__unbranded__" ELSE p.brand END AS brand_key,
                        CASE WHEN TRIM(COALESCE(p.brand, "")) = "" THEN "Unbranded" ELSE p.brand END AS brand_label,
                        COUNT(*) AS product_count
                     FROM products p
                     WHERE p.deleted_at IS NULL
                       AND p.status = "active"';
        $brandParams = [];
        $brandSql = $this->appendCompanyScope($brandSql, $brandParams);
        $brandSql = $this->appendBranchScope($brandSql, $brandParams, $branchId);
        $brandSql .= ' GROUP BY brand_key, brand_label ORDER BY brand_label ASC';

        $categorySql = 'SELECT
                            COALESCE(CAST(c.id AS CHAR), "") AS category_id,
                            COALESCE(c.name, "Uncategorized") AS category_name,
                            COUNT(*) AS product_count
                        FROM products p
                        LEFT JOIN categories c ON c.id = p.category_id
                        WHERE p.deleted_at IS NULL
                          AND p.status = "active"';
        $categoryParams = [];
        $categorySql = $this->appendCompanyScope($categorySql, $categoryParams);
        $categorySql = $this->appendBranchScope($categorySql, $categoryParams, $branchId);
        $categorySql .= ' GROUP BY category_id, category_name ORDER BY category_name ASC';

        return [
            'total_count' => (int) ($summary['total_count'] ?? 0),
            'low_stock_count' => (int) ($summary['low_stock_count'] ?? 0),
            'available_count' => (int) ($summary['available_count'] ?? 0),
            'brand_options' => array_map(static fn (array $row): array => [
                'key' => (string) ($row['brand_key'] ?? ''),
                'label' => (string) ($row['brand_label'] ?? ''),
                'count' => (int) ($row['product_count'] ?? 0),
            ], $this->fetchAll($brandSql, $brandParams)),
            'category_options' => array_map(static fn (array $row): array => [
                'id' => (string) ($row['category_id'] ?? ''),
                'name' => (string) ($row['category_name'] ?? 'Uncategorized'),
                'count' => (int) ($row['product_count'] ?? 0),
            ], $this->fetchAll($categorySql, $categoryParams)),
        ];
    }

    public function catalogPageForPos(?int $branchId = null, array $filters = []): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pageSize = max(1, min((int) ($filters['page_size'] ?? 8), 40));
        $offset = ($page - 1) * $pageSize;
        $query = trim((string) ($filters['search'] ?? ''));
        $brand = trim((string) ($filters['brand'] ?? ''));
        $categoryId = trim((string) ($filters['category_id'] ?? ''));
        $stockFilter = trim((string) ($filters['stock_filter'] ?? 'all'));
        $quickMode = trim((string) ($filters['quick_mode'] ?? 'all'));
        $sort = trim((string) ($filters['sort'] ?? 'relevance'));
        $recentIds = array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, (array) ($filters['recent_ids'] ?? [])), static fn (int $id): bool => $id > 0));

        $baseSql = ' FROM products p
                     LEFT JOIN inventory i ON i.product_id = p.id
                        AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
                     LEFT JOIN taxes t ON t.id = p.tax_id
                     LEFT JOIN categories c ON c.id = p.category_id
                     WHERE p.deleted_at IS NULL
                       AND p.status = "active"';
        $params = ['branch_id' => $branchId];
        $baseSql = $this->appendCompanyScope($baseSql, $params);
        $baseSql = $this->appendBranchScope($baseSql, $params, $branchId);

        if ($query !== '') {
            $params['search_name'] = '%' . $query . '%';
            $params['search_brand'] = '%' . $query . '%';
            $params['search_sku'] = '%' . $query . '%';
            $params['search_barcode'] = '%' . $query . '%';
            $params['search_category'] = '%' . $query . '%';
            $baseSql .= ' AND (
                p.name LIKE :search_name
                OR COALESCE(p.brand, "") LIKE :search_brand
                OR COALESCE(p.sku, "") LIKE :search_sku
                OR COALESCE(p.barcode, "") LIKE :search_barcode
                OR COALESCE(c.name, "") LIKE :search_category
            )';
        }

        if ($brand !== '') {
            if ($brand === '__unbranded__') {
                $baseSql .= ' AND TRIM(COALESCE(p.brand, "")) = ""';
            } else {
                $baseSql .= ' AND p.brand = :brand';
                $params['brand'] = $brand;
            }
        }

        if ($categoryId !== '') {
            if ($categoryId === '__uncategorized__') {
                $baseSql .= ' AND p.category_id IS NULL';
            } else {
                $baseSql .= ' AND p.category_id = :category_id';
                $params['category_id'] = (int) $categoryId;
            }
        }

        if ($quickMode === 'recent') {
            if ($recentIds === []) {
                return [
                    'items' => [],
                    'filtered_total' => 0,
                    'page' => $page,
                    'page_size' => $pageSize,
                ];
            }

            $recentPlaceholders = [];
            foreach ($recentIds as $index => $recentId) {
                $key = 'recent_id_' . $index;
                $recentPlaceholders[] = ':' . $key;
                $params[$key] = $recentId;
            }
            $baseSql .= ' AND p.id IN (' . implode(', ', $recentPlaceholders) . ')';
        } elseif ($quickMode === 'in_stock') {
            $baseSql .= ' AND (p.track_stock = 0 OR COALESCE(i.quantity_on_hand, 0) > 0)';
        } elseif ($quickMode === 'low_stock') {
            $baseSql .= ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold';
        }

        if ($stockFilter === 'available') {
            $baseSql .= ' AND (p.track_stock = 0 OR COALESCE(i.quantity_on_hand, 0) > 0)';
        } elseif ($stockFilter === 'low_stock') {
            $baseSql .= ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) > 0 AND COALESCE(i.quantity_on_hand, 0) <= p.low_stock_threshold';
        } elseif ($stockFilter === 'out_of_stock') {
            $baseSql .= ' AND p.track_stock = 1 AND COALESCE(i.quantity_on_hand, 0) <= 0';
        } elseif ($stockFilter === 'open') {
            $baseSql .= ' AND p.track_stock = 0';
        }

        $countSql = 'SELECT COUNT(*) AS filtered_total' . $baseSql;
        $countRow = $this->fetch($countSql, $params) ?? [];

        $selectSql = 'SELECT p.id, p.name, p.brand, p.sku, p.barcode, p.unit, p.price, p.image_path, p.track_stock,
                             p.category_id, p.low_stock_threshold,
                             COALESCE(i.quantity_on_hand, 0) AS stock_quantity,
                             COALESCE(t.rate, 0) AS tax_rate,
                             t.name AS tax_name,
                             c.name AS category_name' . $baseSql;

        if ($query !== '' && $sort === 'relevance') {
            $selectSql .= ' ORDER BY
                CASE
                    WHEN COALESCE(p.barcode, "") = :exact_barcode_query THEN 0
                    WHEN COALESCE(p.sku, "") = :exact_sku_query THEN 1
                    WHEN p.name = :exact_name_query THEN 2
                    WHEN p.name LIKE :starts_name_query THEN 3
                    WHEN COALESCE(p.brand, "") LIKE :starts_brand_query THEN 4
                    ELSE 5
                END,
                p.name ASC';
            $params['exact_barcode_query'] = $query;
            $params['exact_sku_query'] = $query;
            $params['exact_name_query'] = $query;
            $params['starts_name_query'] = $query . '%';
            $params['starts_brand_query'] = $query . '%';
        } elseif ($sort === 'price') {
            $selectSql .= ' ORDER BY p.price ASC, p.name ASC';
        } elseif ($sort === 'stock') {
            $selectSql .= ' ORDER BY COALESCE(i.quantity_on_hand, 0) DESC, p.name ASC';
        } else {
            $selectSql .= ' ORDER BY p.name ASC';
        }

        $selectSql .= ' LIMIT ' . $pageSize . ' OFFSET ' . $offset;

        return [
            'items' => $this->fetchAll($selectSql, $params),
            'filtered_total' => (int) ($countRow['filtered_total'] ?? 0),
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function catalogForPosByIds(array $productIds, ?int $branchId = null): array
    {
        $productIds = array_values(array_filter(array_map(static fn (mixed $id): int => (int) $id, $productIds), static fn (int $id): bool => $id > 0));
        if ($productIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['branch_id' => $branchId];
        foreach ($productIds as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $sql = 'SELECT p.id, p.name, p.brand, p.sku, p.barcode, p.unit, p.price, p.image_path, p.track_stock,
                       p.category_id, p.low_stock_threshold,
                       COALESCE(i.quantity_on_hand, 0) AS stock_quantity,
                       COALESCE(t.rate, 0) AS tax_rate,
                       t.name AS tax_name,
                       c.name AS category_name
                FROM products p
                LEFT JOIN inventory i ON i.product_id = p.id
                   AND i.branch_id = COALESCE(:branch_id, (SELECT id FROM branches WHERE is_default = 1 LIMIT 1))
                LEFT JOIN taxes t ON t.id = p.tax_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.deleted_at IS NULL
                  AND p.status = "active"
                  AND p.id IN (' . implode(', ', $placeholders) . ')';
        $sql = $this->appendCompanyScope($sql, $params);
        $sql = $this->appendBranchScope($sql, $params, $branchId);
        $sql .= ' ORDER BY p.name';

        return $this->fetchAll($sql, $params);
    }

    public function adjustInventory(int $productId, int $branchId, float $quantityChange, string $movementType, string $reason, int $userId, string $referenceType, int $referenceId, float $unitCost = 0): void
    {
        $inventory = $this->fetch(
            'SELECT * FROM inventory WHERE product_id = :product_id AND branch_id = :branch_id LIMIT 1',
            ['product_id' => $productId, 'branch_id' => $branchId]
        );

        if ($inventory === null) {
            $this->createInventory($productId, $branchId, 0, $unitCost, 'FIFO');
            $inventory = $this->fetch(
                'SELECT * FROM inventory WHERE product_id = :product_id AND branch_id = :branch_id LIMIT 1',
                ['product_id' => $productId, 'branch_id' => $branchId]
            );
        }

        $currentQuantity = (float) $inventory['quantity_on_hand'];
        $newBalance = $currentQuantity + $quantityChange;

        if ($newBalance < -0.0001) {
            throw new HttpException(500, 'Stock level cannot go negative for this product.');
        }

        $averageCost = (float) $inventory['average_cost'];
        $newAverageCost = $averageCost;
        $lastRestockedAt = $inventory['last_restocked_at'];

        if ($quantityChange > 0 && $unitCost > 0) {
            $newAverageCost = (($currentQuantity * $averageCost) + ($quantityChange * $unitCost)) / max($newBalance, 1);
            $lastRestockedAt = date('Y-m-d H:i:s');
        }

        $this->db->prepare(
            'UPDATE inventory SET quantity_on_hand = :quantity, average_cost = :average_cost, last_restocked_at = :last_restocked_at, updated_at = NOW() WHERE id = :id'
        )->execute([
            'quantity' => $newBalance,
            'average_cost' => $newAverageCost,
            'last_restocked_at' => $lastRestockedAt,
            'id' => $inventory['id'],
        ]);

        $this->db->prepare(
            'INSERT INTO stock_movements (product_id, branch_id, user_id, movement_type, reason, reference_type, reference_id, quantity_change, balance_after, unit_cost, created_at)
             VALUES (:product_id, :branch_id, :user_id, :movement_type, :reason, :reference_type, :reference_id, :quantity_change, :balance_after, :unit_cost, NOW())'
        )->execute([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'user_id' => $userId,
            'movement_type' => $movementType,
            'reason' => $reason,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'quantity_change' => $quantityChange,
            'balance_after' => $newBalance,
            'unit_cost' => $unitCost,
        ]);

        $product = $this->fetch(
            'SELECT p.id, p.name, p.sku, p.track_stock, p.low_stock_threshold, b.name AS branch_name
             FROM products p
             LEFT JOIN branches b ON b.id = :branch_id
             WHERE p.id = :product_id
             LIMIT 1',
            [
                'product_id' => $productId,
                'branch_id' => $branchId,
            ]
        );

        if (
            $product !== null
            && (int) ($product['track_stock'] ?? 0) === 1
            && $currentQuantity > (float) ($product['low_stock_threshold'] ?? 0)
            && $newBalance <= (float) ($product['low_stock_threshold'] ?? 0)
        ) {
            $message = $product['name'] . ' reached ' . number_format($newBalance, 2) . ' units, at or below the threshold of ' . number_format((float) $product['low_stock_threshold'], 2) . '.';
            (new Notification())->createBranchNotification(
                branchId: $branchId,
                type: 'low_stock',
                title: 'Low stock alert',
                message: $message,
                linkUrl: url('inventory/show?id=' . $productId),
                sendEmail: true
            );

            (new OperationsEmailService())->sendLowStockAlert([
                'product_id' => $productId,
                'product_name' => (string) $product['name'],
                'sku' => (string) ($product['sku'] ?? ''),
                'quantity_on_hand' => $newBalance,
                'threshold' => (float) ($product['low_stock_threshold'] ?? 0),
                'branch_name' => (string) ($product['branch_name'] ?? 'Primary branch'),
            ], $branchId);
        }
    }

    public function saveVariants(int $productId, array $variants): void
    {
        $this->db->prepare('UPDATE product_variants SET deleted_at = NOW() WHERE product_id = :product_id AND deleted_at IS NULL')
            ->execute(['product_id' => $productId]);

        foreach ($variants as $variant) {
            if (($variant['variant_name'] ?? '') === '' || ($variant['variant_value'] ?? '') === '') {
                continue;
            }

            $this->db->prepare(
                'INSERT INTO product_variants (product_id, variant_name, variant_value, sku, barcode, price_adjustment, stock_quantity, created_at, updated_at)
                 VALUES (:product_id, :variant_name, :variant_value, :sku, :barcode, :price_adjustment, :stock_quantity, NOW(), NOW())'
            )->execute([
                'product_id' => $productId,
                'variant_name' => $variant['variant_name'],
                'variant_value' => $variant['variant_value'],
                'sku' => $variant['sku'] !== '' ? $variant['sku'] : null,
                'barcode' => $variant['barcode'] !== '' ? $variant['barcode'] : null,
                'price_adjustment' => (float) ($variant['price_adjustment'] ?? 0),
                'stock_quantity' => (float) ($variant['stock_quantity'] ?? 0),
            ]);
        }
    }

    public function recentMovements(int $productId, ?int $branchId = null, int $limit = 12): array
    {
        $sql = 'SELECT sm.*, b.name AS branch_name,
                       CONCAT(u.first_name, " ", u.last_name) AS user_name
                FROM stock_movements sm
                LEFT JOIN branches b ON b.id = sm.branch_id
                LEFT JOIN users u ON u.id = sm.user_id
                WHERE sm.product_id = :product_id';
        $params = ['product_id' => $productId];

        if ($branchId !== null) {
            $sql .= ' AND sm.branch_id = :branch_id';
            $params['branch_id'] = $branchId;
        }

        $sql .= ' ORDER BY sm.created_at DESC LIMIT ' . max($limit, 1);

        return $this->fetchAll($sql, $params);
    }

    private function createInventory(int $productId, int $branchId, float $openingStock, float $averageCost, string $valuationMethod): void
    {
        $this->db->prepare(
            'INSERT INTO inventory (product_id, branch_id, quantity_on_hand, quantity_reserved, average_cost, valuation_method, last_restocked_at, created_at, updated_at)
             VALUES (:product_id, :branch_id, :quantity_on_hand, 0, :average_cost, :valuation_method, NOW(), NOW(), NOW())'
        )->execute([
            'product_id' => $productId,
            'branch_id' => $branchId,
            'quantity_on_hand' => $openingStock,
            'average_cost' => $averageCost,
            'valuation_method' => $valuationMethod,
        ]);

        if ($openingStock > 0) {
            $this->db->prepare(
                'INSERT INTO stock_movements (product_id, branch_id, user_id, movement_type, reason, reference_type, reference_id, quantity_change, balance_after, unit_cost, created_at)
                 VALUES (:product_id, :branch_id, NULL, "opening", "Initial stock", "product", :reference_id, :quantity_change, :balance_after, :unit_cost, NOW())'
            )->execute([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'reference_id' => $productId,
                'quantity_change' => $openingStock,
                'balance_after' => $openingStock,
                'unit_cost' => $averageCost,
            ]);
        }
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $value) ?? 'product', '-'));

        return $slug !== '' ? $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 6) : 'product-' . substr(bin2hex(random_bytes(4)), 0, 6);
    }

    private function resolveSku(array $payload, ?string $fallback = null, ?int $branchId = null, ?int $exceptId = null): string
    {
        $sku = strtoupper(trim((string) ($payload['sku'] ?? '')));
        if ($sku !== '') {
            return $sku;
        }

        $modelCode = $this->extractModelCode('', (string) ($payload['name'] ?? ''));
        if ($modelCode !== null) {
            if (!$this->skuExists($modelCode, $exceptId, $branchId)) {
                return $modelCode;
            }
        }

        $fallback = strtoupper(trim((string) $fallback));
        if ($fallback !== '') {
            if (!$this->skuExists($fallback, $exceptId, $branchId)) {
                return $fallback;
            }
        }

        do {
            $generated = $this->generateCode('SKU');
        } while ($this->skuExists($generated, $exceptId, $branchId));

        return $generated;
    }

    private function resolveBarcode(array $payload, ?string $fallback = null, ?int $branchId = null, ?int $exceptId = null): string
    {
        $barcode = trim((string) ($payload['barcode'] ?? ''));
        if ($barcode !== '') {
            return $barcode;
        }

        $fallback = trim((string) $fallback);
        if ($fallback !== '') {
            if (!$this->barcodeExists($fallback, $exceptId, $branchId)) {
                return $fallback;
            }
        }

        do {
            $generated = $this->generateBarcode();
        } while ($this->barcodeExists($generated, $exceptId, $branchId));

        return $generated;
    }

    private function extractModelCode(string $sku, string $name): ?string
    {
        $skuCode = $this->extractModelCodeFromText($sku);
        if ($skuCode !== null) {
            return $skuCode;
        }

        return $this->extractModelCodeFromText($name);
    }

    private function extractModelCodeFromText(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if ($value === '') {
            return null;
        }

        preg_match_all('/[A-Z0-9-]+/', $value, $matches);
        $tokens = array_reverse($matches[0] ?? []);

        foreach ($tokens as $token) {
            $candidate = trim($token, '-');

            if ($candidate === '' || strlen($candidate) < 5) {
                continue;
            }

            if (str_starts_with($candidate, 'SKU-') || str_starts_with($candidate, 'LEG-SKU-') || str_starts_with($candidate, 'LEG-BC-')) {
                continue;
            }

            if (preg_match('/[A-Z]/', $candidate) !== 1 || preg_match('/\d/', $candidate) !== 1) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    private function generateCode(string $prefix): string
    {
        return $prefix . '-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
    }

    private function generateBarcode(): string
    {
        return date('ymd') . random_int(100000, 999999);
    }

    private function appendBranchScope(
        string $sql,
        array &$params,
        ?int $branchId,
        string $column = 'p.branch_id',
        string $paramName = 'product_branch_id'
    ): string {
        if ($branchId === null || $branchId <= 0) {
            return $sql;
        }

        $params[$paramName] = $branchId;

        return $sql . ' AND (' . $column . ' = :' . $paramName . ' OR ' . $column . ' IS NULL)';
    }

    private function appendCompanyScope(string $sql, array &$params, string $column = 'p.company_id', string $paramName = 'company_id'): string
    {
        $companyId = $this->resolveCompanyId();
        if ($companyId === null) {
            return $sql;
        }

        $params[$paramName] = $companyId;

        return $sql . ' AND ' . $column . ' = :' . $paramName;
    }

    private function matchesBranchScope(array $product, int $branchId): bool
    {
        $productBranchId = $product['branch_id'] ?? null;

        return $productBranchId === null || (int) $productBranchId === $branchId;
    }

    private function resolveCompanyId(): ?int
    {
        $companyId = current_company_id();

        return $companyId !== null && $companyId > 0 ? $companyId : null;
    }
}
