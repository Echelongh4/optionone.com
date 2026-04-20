<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class BillingUsageService
{
    public function snapshot(int $companyId): array
    {
        $statement = Database::connection()->prepare(
            'SELECT
                (SELECT COUNT(*)
                 FROM branches b
                 WHERE b.company_id = :company_id_branches
                   AND b.deleted_at IS NULL) AS branch_count,
                (SELECT COUNT(*)
                 FROM branches b
                 WHERE b.company_id = :company_id_active_branches
                   AND b.status = "active"
                   AND b.deleted_at IS NULL) AS active_branch_count,
                (SELECT COUNT(*)
                 FROM users u
                 WHERE u.company_id = :company_id_users
                   AND u.deleted_at IS NULL) AS user_count,
                (SELECT COUNT(*)
                 FROM users u
                 WHERE u.company_id = :company_id_active_users
                   AND u.status = "active"
                   AND u.deleted_at IS NULL) AS active_user_count,
                (SELECT COUNT(*)
                 FROM products p
                 WHERE p.company_id = :company_id_products
                   AND p.deleted_at IS NULL) AS product_count,
                (SELECT COUNT(*)
                 FROM products p
                 WHERE p.company_id = :company_id_active_products
                   AND p.status = "active"
                   AND p.deleted_at IS NULL) AS active_product_count,
                (SELECT COUNT(*)
                 FROM sales s
                 INNER JOIN branches b ON b.id = s.branch_id
                 WHERE b.company_id = :company_id_sales_count
                   AND s.deleted_at IS NULL
                   AND s.status IN ("completed", "partial_return")
                   AND COALESCE(s.completed_at, s.created_at) >= DATE_FORMAT(NOW(), "%Y-%m-01")) AS monthly_sale_count,
                (SELECT COALESCE(SUM(s.grand_total), 0)
                 FROM sales s
                 INNER JOIN branches b ON b.id = s.branch_id
                 WHERE b.company_id = :company_id_sales_revenue
                   AND s.deleted_at IS NULL
                   AND s.status IN ("completed", "partial_return")
                   AND COALESCE(s.completed_at, s.created_at) >= DATE_FORMAT(NOW(), "%Y-%m-01")) AS monthly_revenue,
                (SELECT MAX(COALESCE(s.completed_at, s.created_at))
                 FROM sales s
                 INNER JOIN branches b ON b.id = s.branch_id
                 WHERE b.company_id = :company_id_last_sale
                   AND s.deleted_at IS NULL
                   AND s.status IN ("completed", "partial_return")) AS last_sale_at'
        );
        $statement->execute([
            'company_id_branches' => $companyId,
            'company_id_active_branches' => $companyId,
            'company_id_users' => $companyId,
            'company_id_active_users' => $companyId,
            'company_id_products' => $companyId,
            'company_id_active_products' => $companyId,
            'company_id_sales_count' => $companyId,
            'company_id_sales_revenue' => $companyId,
            'company_id_last_sale' => $companyId,
        ]);

        return $statement->fetch() ?: [
            'branch_count' => 0,
            'active_branch_count' => 0,
            'user_count' => 0,
            'active_user_count' => 0,
            'product_count' => 0,
            'active_product_count' => 0,
            'monthly_sale_count' => 0,
            'monthly_revenue' => 0,
            'last_sale_at' => null,
        ];
    }
}
