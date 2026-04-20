<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Models\Product;
use App\Models\ProductCategory;

class ProductImportService
{
    private const TEMPLATE_HEADERS = [
        'name',
        'brand',
        'category_path',
        'supplier_name',
        'tax_name',
        'sku',
        'barcode',
        'description',
        'unit',
        'price',
        'cost_price',
        'opening_stock',
        'low_stock_threshold',
        'track_stock',
        'status',
        'inventory_method',
    ];

    private const REQUIRED_HEADERS = ['name', 'price'];

    private Product $products;
    private ProductCategory $categories;
    private array $categoryRows = [];
    private array $categoriesById = [];
    private array $categoriesByName = [];
    private array $categoriesByParent = [];
    private array $supplierRows = [];
    private array $supplierMap = [];
    private array $taxRows = [];
    private array $taxMap = [];
    private array $createdCategoryIds = [];

    public function __construct()
    {
        $this->products = new Product();
        $this->categories = new ProductCategory();
    }

    public function importFromUpload(array $file, array $options, int $branchId, int $userId): array
    {
        $this->assertSpreadsheetSupport();
        $this->assertValidUpload($file);
        $this->bootLookups($branchId);
        $this->createdCategoryIds = [];

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load((string) ($file['tmp_name'] ?? ''));
        $sheet = $spreadsheet->getSheet(0);
        $rows = $sheet->toArray(null, true, true, true);

        try {
            if ($rows === [] || count($rows) < 2) {
                throw new HttpException(422, 'The uploaded workbook does not contain any product rows.');
            }

            $headerMap = $this->resolveHeaders((array) reset($rows));
            $report = [
                'file_name' => (string) ($file['name'] ?? 'products.xlsx'),
                'processed_rows' => 0,
                'created_count' => 0,
                'updated_count' => 0,
                'failed_count' => 0,
                'created_categories_count' => 0,
                'issue_count' => 0,
                'issues' => [],
                'completed_at' => date('Y-m-d H:i:s'),
            ];

            $allowCreateCategories = !empty($options['create_missing_categories']);
            $updateExistingProducts = !empty($options['update_existing_products']);

            foreach ($rows as $rowNumber => $row) {
                if ($rowNumber === array_key_first($rows)) {
                    continue;
                }

                if ($this->isBlankRow($row)) {
                    continue;
                }

                $report['processed_rows']++;
                $mapped = $this->mapRow($row, $headerMap);
                $rowLabel = trim((string) ($mapped['name'] ?? '')) !== '' ? (string) $mapped['name'] : 'Row ' . $rowNumber;
                $errors = $this->validateRow($mapped, $allowCreateCategories);

                if ($errors !== []) {
                    $report['issues'][] = [
                        'row' => $rowNumber,
                        'product' => $rowLabel,
                        'message' => implode(' ', $errors),
                    ];
                    continue;
                }

                try {
                    $lookupPayload = $this->lookupPayload($mapped, $branchId, $allowCreateCategories);
                    $hasRequestedCategory = $mapped['category'] !== '' || $mapped['category_path'] !== '';
                    $existingProduct = null;

                    if (
                        !$hasRequestedCategory
                        || $lookupPayload['category_id'] !== null
                        || trim((string) ($lookupPayload['sku'] ?? '')) !== ''
                        || trim((string) ($lookupPayload['barcode'] ?? '')) !== ''
                    ) {
                        $existingProduct = $this->products->findExistingForRestock($lookupPayload, $branchId);
                    }

                    if ($existingProduct !== null) {
                        if (!$updateExistingProducts) {
                            $report['issues'][] = [
                                'row' => $rowNumber,
                                'product' => $rowLabel,
                                'message' => 'This row matches existing product ' . (string) ($existingProduct['name'] ?? 'catalog item') . '. Enable update existing products to apply the import.',
                            ];
                            continue;
                        }

                        Database::transaction(function () use ($existingProduct, $mapped, $branchId, $userId, $allowCreateCategories): void {
                            $payload = $this->buildPayload($mapped, $branchId, $allowCreateCategories);
                            $this->products->restockExistingProductWithinTransaction((int) ($existingProduct['id'] ?? 0), $payload, $branchId, $userId);
                        });

                        $report['updated_count']++;
                        continue;
                    }

                    Database::transaction(function () use ($mapped, $branchId, $allowCreateCategories): void {
                        $payload = $this->buildPayload($mapped, $branchId, $allowCreateCategories);
                        $this->products->createProduct($payload, [], $branchId);
                    });

                    $report['created_count']++;
                } catch (\Throwable $exception) {
                    $report['issues'][] = [
                        'row' => $rowNumber,
                        'product' => $rowLabel,
                        'message' => $this->friendlyFailureMessage($exception),
                    ];
                }
            }

            if ($report['processed_rows'] === 0) {
                $report['issues'][] = [
                    'row' => 0,
                    'product' => 'Workbook',
                    'message' => 'No product rows were found beneath the header row.',
                ];
            }

            $report['created_categories_count'] = count($this->createdCategoryIds);
            $report['failed_count'] = count($report['issues']);
            $report['issue_count'] = $report['failed_count'];

            return $report;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function streamTemplate(): void
    {
        $this->assertSpreadsheetSupport();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Products Import');
        $sheet->fromArray(self::TEMPLATE_HEADERS, null, 'A1');
        $sheet->fromArray([
            ['Classic Cola 500ml', 'Nova Drinks', 'Beverages / Soft Drinks', '', 'VAT 15%', 'COLA-500', '240420260001', 'Chilled retail bottle', 'pcs', 8.50, 5.10, 48, 10, 'yes', 'active', 'FIFO'],
            ['Matte Emulsion White', 'ColorPro', 'Paint / Interior', 'Prime Suppliers', '', 'PAINT-WHT-20L', '240420260002', 'Interior wall paint 20L', 'box', 320.00, 245.00, 12, 3, 'yes', 'active', 'FIFO'],
        ], null, 'A2');

        $guidanceSheet = $spreadsheet->createSheet();
        $guidanceSheet->setTitle('Guidance');
        $guidanceSheet->fromArray(['Column', 'Required', 'Example', 'Notes'], null, 'A1');
        $guidanceSheet->fromArray([
            ['name', 'Yes', 'Classic Cola 500ml', 'Product name.'],
            ['category_path', 'No', 'Beverages / Soft Drinks', 'Use one root category or a full path. The importer resolves this to category_id automatically.'],
            ['supplier_name', 'No', 'Prime Suppliers', 'Must match an existing supplier name exactly if provided.'],
            ['tax_name', 'No', 'VAT 15%', 'Must match an existing tax profile exactly if provided.'],
            ['unit', 'No', 'pcs', 'Accepted values: pcs, kg, litre, box. Blank defaults to pcs.'],
            ['price', 'Yes', '8.50', 'Selling price.'],
            ['cost_price', 'No', '5.10', 'Optional. Blank defaults to 0.00.'],
            ['opening_stock', 'No', '48', 'Creates opening stock for new items and adds stock when updating existing items.'],
            ['track_stock', 'No', 'yes', 'Accepted values: yes, no, true, false, 1, 0. Blank defaults to yes.'],
            ['status', 'No', 'active', 'Accepted values: active or inactive. Blank defaults to active.'],
            ['inventory_method', 'No', 'FIFO', 'Accepted values: FIFO or LIFO. Blank defaults to FIFO.'],
        ], null, 'A2');

        foreach ([$sheet, $guidanceSheet] as $worksheet) {
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($worksheet->getHighestColumn());
            $highestRow = $worksheet->getHighestRow();

            for ($index = 1; $index <= $highestColumnIndex; $index++) {
                $column = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index);
                $worksheet->getColumnDimension($column)->setAutoSize(true);
            }

            $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColumnIndex) . '1';
            $fullRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColumnIndex) . $highestRow;
            $worksheet->freezePane('A2');
            $worksheet->setAutoFilter($fullRange);
            $worksheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '246BDB'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'D7E5F6'],
                    ],
                ],
            ]);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="products-import-template.xlsx"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }

    private function assertSpreadsheetSupport(): void
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class) || !class_exists(\PhpOffice\PhpSpreadsheet\Writer\Xlsx::class)) {
            throw new HttpException(500, 'Product XLSX import requires PhpSpreadsheet. Run composer install first.');
        }
    }

    private function assertValidUpload(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw new HttpException(422, $error === UPLOAD_ERR_NO_FILE ? 'Choose an XLSX file to import.' : 'The product import upload failed.');
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new HttpException(422, 'The uploaded XLSX file is invalid or unavailable.');
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== 'xlsx') {
            throw new HttpException(422, 'Upload an Excel workbook in .xlsx format.');
        }
    }

    private function bootLookups(int $branchId): void
    {
        $this->categoryRows = $this->categories->parentOptions();
        $this->categoriesById = [];
        $this->categoriesByName = [];
        $this->categoriesByParent = [];

        foreach ($this->categoryRows as $row) {
            $categoryId = (int) ($row['id'] ?? 0);
            $parentId = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : null;
            $normalizedName = $this->normalizeKey((string) ($row['name'] ?? ''));

            $this->categoriesById[$categoryId] = $row;
            $this->categoriesByName[$normalizedName][] = $row;
            $this->categoriesByParent[$this->categoryParentKey($parentId)][$normalizedName][] = $row;
        }

        $this->supplierRows = $this->products->suppliers($branchId);
        $this->supplierMap = [];
        foreach ($this->supplierRows as $row) {
            $this->supplierMap[$this->normalizeKey((string) ($row['name'] ?? ''))][] = $row;
        }

        $this->taxRows = $this->products->taxes();
        $this->taxMap = [];
        foreach ($this->taxRows as $row) {
            $this->taxMap[$this->normalizeKey((string) ($row['name'] ?? ''))][] = $row;
        }
    }

    private function resolveHeaders(array $headerRow): array
    {
        $aliases = [
            'product_name' => 'name',
            'name' => 'name',
            'brand' => 'brand',
            'category' => 'category',
            'category_name' => 'category',
            'category_path' => 'category_path',
            'supplier' => 'supplier_name',
            'supplier_name' => 'supplier_name',
            'tax' => 'tax_name',
            'tax_name' => 'tax_name',
            'sku' => 'sku',
            'barcode' => 'barcode',
            'description' => 'description',
            'unit' => 'unit',
            'price' => 'price',
            'selling_price' => 'price',
            'sale_price' => 'price',
            'cost' => 'cost_price',
            'cost_price' => 'cost_price',
            'opening_stock' => 'opening_stock',
            'opening_qty' => 'opening_stock',
            'stock' => 'opening_stock',
            'low_stock_threshold' => 'low_stock_threshold',
            'low_stock' => 'low_stock_threshold',
            'threshold' => 'low_stock_threshold',
            'track_stock' => 'track_stock',
            'status' => 'status',
            'inventory_method' => 'inventory_method',
            'valuation_method' => 'inventory_method',
        ];

        $resolved = [];
        foreach ($headerRow as $column => $value) {
            $normalized = $this->normalizeHeader((string) $value);
            if ($normalized === '') {
                continue;
            }

            $resolved[$column] = $aliases[$normalized] ?? $normalized;
        }

        $missing = array_values(array_filter(self::REQUIRED_HEADERS, static fn (string $header): bool => !in_array($header, $resolved, true)));
        if ($missing !== []) {
            throw new HttpException(422, 'The workbook is missing required columns: ' . implode(', ', $missing) . '.');
        }

        return $resolved;
    }

    private function mapRow(array $row, array $headerMap): array
    {
        $mapped = [];
        foreach ($headerMap as $column => $field) {
            $mapped[$field] = $row[$column] ?? null;
        }

        return [
            'name' => trim((string) ($mapped['name'] ?? '')),
            'brand' => trim((string) ($mapped['brand'] ?? '')),
            'category' => trim((string) ($mapped['category'] ?? '')),
            'category_path' => trim((string) ($mapped['category_path'] ?? '')),
            'supplier_name' => trim((string) ($mapped['supplier_name'] ?? '')),
            'tax_name' => trim((string) ($mapped['tax_name'] ?? '')),
            'sku' => trim((string) ($mapped['sku'] ?? '')),
            'barcode' => trim((string) ($mapped['barcode'] ?? '')),
            'description' => trim((string) ($mapped['description'] ?? '')),
            'unit' => $this->resolveUnit((string) ($mapped['unit'] ?? '')),
            'price' => $this->parseDecimal($mapped['price'] ?? null),
            'cost_price' => $this->parseDecimal($mapped['cost_price'] ?? null),
            'opening_stock' => $this->parseDecimal($mapped['opening_stock'] ?? 0, 0.0),
            'low_stock_threshold' => $this->parseDecimal($mapped['low_stock_threshold'] ?? 0, 0.0),
            'track_stock' => $this->resolveBoolean($mapped['track_stock'] ?? null, true) ? 1 : 0,
            'status' => $this->resolveStatus((string) ($mapped['status'] ?? '')),
            'inventory_method' => $this->resolveInventoryMethod((string) ($mapped['inventory_method'] ?? '')),
        ];
    }

    private function validateRow(array $row, bool $allowCreateCategories): array
    {
        $errors = [];

        if ($row['name'] === '') {
            $errors[] = 'Product name is required.';
        }

        if ($row['price'] === null) {
            $errors[] = 'Selling price is required.';
        } elseif ((float) $row['price'] < 0) {
            $errors[] = 'Selling price cannot be negative.';
        }

        if ($row['cost_price'] !== null && (float) $row['cost_price'] < 0) {
            $errors[] = 'Cost price cannot be negative.';
        }

        if ((float) $row['opening_stock'] < 0) {
            $errors[] = 'Opening stock cannot be negative.';
        }

        if ((float) $row['low_stock_threshold'] < 0) {
            $errors[] = 'Low stock threshold cannot be negative.';
        }

        if (!in_array($row['unit'], ['pcs', 'kg', 'litre', 'box'], true)) {
            $errors[] = 'Unit must be one of pcs, kg, litre, or box.';
        }

        if (!in_array($row['status'], ['active', 'inactive'], true)) {
            $errors[] = 'Status must be active or inactive.';
        }

        if (!in_array($row['inventory_method'], ['FIFO', 'LIFO'], true)) {
            $errors[] = 'Inventory method must be FIFO or LIFO.';
        }

        if ($row['category'] !== '' || $row['category_path'] !== '') {
            try {
                $this->resolveCategoryId($row['category_path'], $row['category'], $allowCreateCategories, false);
            } catch (\RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($row['supplier_name'] !== '') {
            $supplierMatches = $this->supplierMap[$this->normalizeKey($row['supplier_name'])] ?? [];
            if ($supplierMatches === []) {
                $errors[] = 'Supplier "' . $row['supplier_name'] . '" was not found in this workspace.';
            } elseif (count($supplierMatches) > 1) {
                $errors[] = 'Supplier "' . $row['supplier_name'] . '" is ambiguous. Keep one supplier name per branch before importing.';
            }
        }

        if ($row['tax_name'] !== '') {
            $taxMatches = $this->taxMap[$this->normalizeKey($row['tax_name'])] ?? [];
            if ($taxMatches === []) {
                $errors[] = 'Tax profile "' . $row['tax_name'] . '" was not found.';
            } elseif (count($taxMatches) > 1) {
                $errors[] = 'Tax profile "' . $row['tax_name'] . '" is ambiguous. Rename duplicate tax profiles before importing.';
            }
        }

        return $errors;
    }

    private function buildPayload(array $row, int $branchId, bool $allowCreateCategories, bool $resolveRelations = true): array
    {
        $categoryId = null;
        $supplierId = null;
        $taxId = null;

        if ($resolveRelations) {
            $categoryId = $this->resolveCategoryId($row['category_path'], $row['category'], $allowCreateCategories);
            $supplierId = $this->resolveSupplierId($row['supplier_name']);
            $taxId = $this->resolveTaxId($row['tax_name']);
        }

        return [
            'branch_id' => $branchId,
            'category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'tax_id' => $taxId,
            'name' => $row['name'],
            'brand' => $row['brand'],
            'sku' => strtoupper($row['sku']),
            'barcode' => $row['barcode'],
            'description' => $row['description'],
            'unit' => $row['unit'],
            'price' => (float) ($row['price'] ?? 0),
            'cost_price' => (float) ($row['cost_price'] ?? 0),
            'low_stock_threshold' => (float) ($row['low_stock_threshold'] ?? 0),
            'track_stock' => (int) ($row['track_stock'] ?? 1),
            'status' => $row['status'],
            'inventory_method' => $row['inventory_method'],
            'opening_stock' => (float) ($row['opening_stock'] ?? 0),
        ];
    }

    private function lookupPayload(array $row, int $branchId, bool $allowCreateCategories): array
    {
        $payload = $this->buildPayload($row, $branchId, false, false);
        $payload['category_id'] = $this->resolveCategoryId($row['category_path'], $row['category'], $allowCreateCategories, false);

        return $payload;
    }

    private function resolveCategoryId(string $categoryPath, string $categoryName, bool $allowCreateCategories, bool $createRecords = true): ?int
    {
        $value = $categoryPath !== '' ? $categoryPath : $categoryName;
        if (trim($value) === '') {
            return null;
        }

        $segments = $this->categorySegments($value);
        if ($segments === []) {
            return null;
        }

        if (count($segments) === 1) {
            $matches = $this->categoriesByName[$this->normalizeKey($segments[0])] ?? [];
            if (count($matches) === 1) {
                return (int) ($matches[0]['id'] ?? 0);
            }

            if (count($matches) > 1) {
                throw new \RuntimeException('Category "' . $segments[0] . '" matches multiple categories. Use a category path like Parent / Child.');
            }

            if (!$allowCreateCategories) {
                throw new \RuntimeException('Category "' . $segments[0] . '" does not exist. Enable "create missing categories" or create it first.');
            }

            return $createRecords ? $this->createCategory($segments[0], null) : null;
        }

        $parentId = null;
        foreach ($segments as $segment) {
            $matches = $this->categoriesByParent[$this->categoryParentKey($parentId)][$this->normalizeKey($segment)] ?? [];
            if ($matches === []) {
                if (!$allowCreateCategories) {
                    throw new \RuntimeException('Category path "' . implode(' / ', $segments) . '" was not found. Enable "create missing categories" or create the full path first.');
                }

                if (!$createRecords) {
                    return null;
                }

                $parentId = $this->createCategory($segment, $parentId);
                continue;
            }

            $parentId = (int) ($matches[0]['id'] ?? 0);
        }

        return $parentId;
    }

    private function createCategory(string $name, ?int $parentId): int
    {
        $categoryId = $this->categories->createCategory([
            'name' => $name,
            'parent_id' => $parentId,
            'description' => 'Created automatically during product XLSX import.',
        ]);

        $row = [
            'id' => $categoryId,
            'name' => trim($name),
            'parent_id' => $parentId,
        ];
        $normalizedName = $this->normalizeKey($row['name']);
        $this->categoryRows[] = $row;
        $this->categoriesById[$categoryId] = $row;
        $this->categoriesByName[$normalizedName][] = $row;
        $this->categoriesByParent[$this->categoryParentKey($parentId)][$normalizedName][] = $row;
        $this->createdCategoryIds[$categoryId] = true;

        return $categoryId;
    }

    private function resolveSupplierId(string $supplierName): ?int
    {
        if ($supplierName === '') {
            return null;
        }

        $matches = $this->supplierMap[$this->normalizeKey($supplierName)] ?? [];

        return $matches === [] ? null : (int) ($matches[0]['id'] ?? 0);
    }

    private function resolveTaxId(string $taxName): ?int
    {
        if ($taxName === '') {
            return null;
        }

        $matches = $this->taxMap[$this->normalizeKey($taxName)] ?? [];

        return $matches === [] ? null : (int) ($matches[0]['id'] ?? 0);
    }

    private function resolveUnit(string $value): string
    {
        $normalized = $this->normalizeKey($value);
        if ($normalized === '') {
            return 'pcs';
        }

        return match ($normalized) {
            'pc', 'pcs', 'piece', 'pieces' => 'pcs',
            'kg', 'kilogram', 'kilograms' => 'kg',
            'litre', 'liter', 'litres', 'liters', 'ltr' => 'litre',
            'box', 'boxes', 'bx' => 'box',
            default => trim($value),
        };
    }

    private function resolveStatus(string $value): string
    {
        $normalized = $this->normalizeKey($value);

        return $normalized === 'inactive' ? 'inactive' : 'active';
    }

    private function resolveInventoryMethod(string $value): string
    {
        $normalized = strtoupper(trim($value));

        return $normalized === 'LIFO' ? 'LIFO' : 'FIFO';
    }

    private function resolveBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        $normalized = $this->normalizeKey((string) $value);
        if (in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'n', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function parseDecimal(mixed $value, ?float $default = null): ?float
    {
        if ($value === null) {
            return $default;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return $default;
        }

        $normalized = preg_replace('/[^0-9,\.\-]/', '', $normalized) ?? '';
        if ($normalized === '') {
            return $default;
        }

        if (substr_count($normalized, ',') > 0 && substr_count($normalized, '.') === 0) {
            $normalized = str_replace(',', '.', $normalized);
        } else {
            $normalized = str_replace(',', '', $normalized);
        }

        return is_numeric($normalized) ? round((float) $normalized, 2) : $default;
    }

    private function categorySegments(string $value): array
    {
        $normalized = trim(str_replace(['\\', '>', '|'], '/', $value));
        $segments = preg_split('/\s*\/\s*/', $normalized) ?: [];

        return array_values(array_filter(array_map(static fn (string $segment): string => trim($segment), $segments), static fn (string $segment): bool => $segment !== ''));
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';

        return trim($value, '_');
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function categoryParentKey(?int $parentId): string
    {
        return $parentId === null ? 'root' : 'parent_' . $parentId;
    }

    private function isBlankRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function friendlyFailureMessage(\Throwable $exception): string
    {
        $message = trim($exception->getMessage());

        return $message !== '' ? $message : 'The product row could not be imported.';
    }
}
