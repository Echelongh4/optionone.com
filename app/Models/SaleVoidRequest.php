<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\HttpException;
use App\Core\Model;

class SaleVoidRequest extends Model
{
    protected string $table = 'sale_void_requests';

    private function selectBase(): string
    {
        return 'SELECT svr.*, s.sale_number, s.branch_id, s.status AS sale_status,
                       CONCAT(req.first_name, " ", req.last_name) AS requested_by_name,
                       CONCAT(rev.first_name, " ", rev.last_name) AS reviewed_by_name
                FROM sale_void_requests svr
                INNER JOIN sales s ON s.id = svr.sale_id
                INNER JOIN users req ON req.id = svr.requested_by
                LEFT JOIN users rev ON rev.id = svr.reviewed_by';
    }

    public function activeForSale(int $saleId): ?array
    {
        return $this->fetch(
            $this->selectBase() . ' WHERE svr.sale_id = :sale_id AND svr.status = "pending" ORDER BY svr.id DESC LIMIT 1',
            ['sale_id' => $saleId]
        );
    }

    public function historyForSale(int $saleId): array
    {
        return $this->fetchAll(
            $this->selectBase() . ' WHERE svr.sale_id = :sale_id ORDER BY svr.created_at DESC, svr.id DESC',
            ['sale_id' => $saleId]
        );
    }

    public function findWithContext(int $requestId): ?array
    {
        return $this->fetch(
            $this->selectBase() . ' WHERE svr.id = :id LIMIT 1',
            ['id' => $requestId]
        );
    }

    public function createRequest(int $saleId, int $requestedBy, string $reason): int
    {
        if ($this->activeForSale($saleId) !== null) {
            throw new HttpException(409, 'A void approval request is already pending for this sale.');
        }

        return $this->insert([
            'sale_id' => $saleId,
            'requested_by' => $requestedBy,
            'status' => 'pending',
            'reason' => trim($reason),
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function review(int $requestId, string $status, int $reviewedBy, ?string $reviewNotes = null): void
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new HttpException(400, 'Invalid void request decision.');
        }

        $statement = $this->db->prepare(
            'UPDATE sale_void_requests
             SET status = :status,
                 review_notes = :review_notes,
                 reviewed_by = :reviewed_by,
                 reviewed_at = NOW(),
                 updated_at = NOW()
             WHERE id = :id
               AND status = "pending"'
        );

        $statement->execute([
            'status' => $status,
            'review_notes' => trim((string) $reviewNotes) !== '' ? trim((string) $reviewNotes) : null,
            'reviewed_by' => $reviewedBy,
            'id' => $requestId,
        ]);

        if ($statement->rowCount() === 0) {
            throw new HttpException(409, 'This void request has already been reviewed.');
        }
    }
}