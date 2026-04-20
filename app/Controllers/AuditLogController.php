<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\ReportExportService;
use DateTimeImmutable;

class AuditLogController extends Controller
{
    public function index(Request $request): void
    {
        $filters = $this->normalizedFilters($request);
        $auditLog = new AuditLog();

        $this->render('audit_logs/index', [
            'title' => 'Audit Trail',
            'breadcrumbs' => ['Dashboard', 'Audit Trail'],
            'filters' => $filters,
            'summary' => $auditLog->summary($filters),
            'logs' => $auditLog->search($filters, 300),
            'actions' => $auditLog->actions(),
            'entityTypes' => $auditLog->entityTypes(),
            'users' => (new User())->listUsers(),
        ]);
    }

    public function export(Request $request): void
    {
        $filters = $this->normalizedFilters($request);
        $format = $this->normalizedFormat((string) $request->query('format', 'csv'));
        $dataset = (new AuditLog())->exportDataset($filters);

        (new ReportExportService())->download($dataset, $format, [
            'title' => 'Audit Trail',
            'filters' => [
                'date_from' => $filters['date_from'],
                'date_to' => $filters['date_to'],
            ],
        ]);
    }

    private function normalizedFilters(Request $request): array
    {
        $today = new DateTimeImmutable('today');
        $defaultFrom = $today->modify('-29 days')->format('Y-m-d');
        $defaultTo = $today->format('Y-m-d');
        $dateFrom = trim((string) $request->query('date_from', $defaultFrom));
        $dateTo = trim((string) $request->query('date_to', $defaultTo));
        $userId = trim((string) $request->query('user_id', ''));
        $action = trim((string) $request->query('action', ''));
        $entityType = trim((string) $request->query('entity_type', ''));
        $term = trim((string) $request->query('term', ''));

        if (!$this->isValidDate($dateFrom)) {
            $dateFrom = $defaultFrom;
        }

        if (!$this->isValidDate($dateTo)) {
            $dateTo = $defaultTo;
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        if ($userId !== '' && !ctype_digit($userId)) {
            $userId = '';
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 50),
            'entity_type' => mb_substr($entityType, 0, 50),
            'term' => mb_substr($term, 0, 100),
        ];
    }

    private function normalizedFormat(string $value): string
    {
        $format = strtolower(trim($value));

        return match ($format) {
            'excel' => 'xlsx',
            'csv', 'xlsx', 'pdf' => $format,
            default => throw new HttpException(404, 'Unknown audit export format.'),
        };
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}
