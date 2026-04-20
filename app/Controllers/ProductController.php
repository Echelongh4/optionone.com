<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Tax;
use App\Services\ProductImportService;
use App\Services\UploadService;
use App\Core\Database;

class ProductController extends Controller
{
    private const PRODUCT_CREATE_SUBMISSION_SCOPE = 'product_create';
    private const PRODUCT_IMPORT_SUBMISSION_SCOPE = 'product_import';

    public function index(Request $request): void
    {
        $search = trim((string) $request->query('search', ''));
        $categoryId = $this->nullableInt($request->query('category_id'));
        $brand = trim((string) $request->query('brand', ''));

        $this->renderIndex($search, $categoryId, $brand);
    }

    public function categories(Request $request): void
    {
        $this->renderCategoryIndex();
    }

    public function show(Request $request): void
    {
        $product = (new Product())->findDetailed((int) $request->query('id'), $this->branchId());

        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $this->render('products/show', [
            'title' => 'Product Details',
            'breadcrumbs' => ['Dashboard', 'Products', $product['name']],
            'product' => $product,
        ]);
    }

    public function create(Request $request): void
    {
        $productModel = new Product();

        $this->render('products/create', [
            'title' => 'Add Product',
            'breadcrumbs' => ['Dashboard', 'Products', 'Add Product'],
            'categories' => $productModel->categories(),
            'suppliers' => $productModel->suppliers($this->branchId()),
            'taxes' => $productModel->taxes(),
            'errors' => [],
            'product' => [],
            'submissionKey' => $this->issueSubmissionKey(self::PRODUCT_CREATE_SUBMISSION_SCOPE),
        ]);
    }

    public function importForm(Request $request): void
    {
        $this->render('products/import', [
            'title' => 'Import Products',
            'breadcrumbs' => ['Dashboard', 'Products', 'Import'],
            'errors' => [],
            'form' => [
                'create_missing_categories' => 1,
                'update_existing_products' => 1,
            ],
            'submissionKey' => $this->issueSubmissionKey(self::PRODUCT_IMPORT_SUBMISSION_SCOPE),
        ]);
    }

    public function importTemplate(Request $request): void
    {
        (new ProductImportService())->streamTemplate();
        exit;
    }

    public function import(Request $request): void
    {
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::PRODUCT_IMPORT_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        $form = [
            'create_missing_categories' => $request->boolean('create_missing_categories', true) ? 1 : 0,
            'update_existing_products' => $request->boolean('update_existing_products', true) ? 1 : 0,
        ];
        $errors = [];
        $importFile = $request->file('import_file');

        if ($submissionKey !== '' && !$this->isIssuedSubmissionKey(self::PRODUCT_IMPORT_SUBMISSION_SCOPE, $submissionKey)) {
            $errors['submission_key'][] = 'This import form expired. Reload it and submit again.';
        }

        if ($importFile === null || (int) ($importFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors['import_file'][] = 'Choose an XLSX file to import.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Choose a valid import file and try again.',
                    'errors' => $errors,
                ]);
                return;
            }

            $this->render('products/import', [
                'title' => 'Import Products',
                'breadcrumbs' => ['Dashboard', 'Products', 'Import'],
                'errors' => $errors,
                'form' => $form,
                'submissionKey' => $this->reuseOrIssueSubmissionKey(self::PRODUCT_IMPORT_SUBMISSION_SCOPE, $submissionKey),
            ]);
            return;
        }

        try {
            $report = (new ProductImportService())->importFromUpload(
                $importFile ?? [],
                [
                    'create_missing_categories' => (bool) $form['create_missing_categories'],
                    'update_existing_products' => (bool) $form['update_existing_products'],
                ],
                $this->branchId(),
                (int) Auth::id()
            );
        } catch (\Throwable $exception) {
            $message = trim($exception->getMessage()) !== '' ? trim($exception->getMessage()) : 'The XLSX file could not be imported.';
            $errors['import_file'][] = $message;

            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => $message,
                    'errors' => $errors,
                ]);
                return;
            }

            $this->render('products/import', [
                'title' => 'Import Products',
                'breadcrumbs' => ['Dashboard', 'Products', 'Import'],
                'errors' => $errors,
                'form' => $form,
                'submissionKey' => $this->reuseOrIssueSubmissionKey(self::PRODUCT_IMPORT_SUBMISSION_SCOPE, $submissionKey),
            ]);
            return;
        }

        Session::flash('product_import_report', $report);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'import',
            entityType: 'product',
            entityId: null,
            description: sprintf(
                'Imported products from %s: %d created, %d updated, %d failed, %d categories auto-created.',
                (string) ($report['file_name'] ?? 'workbook'),
                (int) ($report['created_count'] ?? 0),
                (int) ($report['updated_count'] ?? 0),
                (int) ($report['failed_count'] ?? 0),
                (int) ($report['created_categories_count'] ?? 0)
            ),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $successCount = (int) ($report['created_count'] ?? 0) + (int) ($report['updated_count'] ?? 0);
        $message = $successCount > 0
            ? sprintf(
                'Import complete: %d created, %d updated, %d rows need review.',
                (int) ($report['created_count'] ?? 0),
                (int) ($report['updated_count'] ?? 0),
                (int) ($report['failed_count'] ?? 0)
            )
            : sprintf('Import complete: no products were imported. %d rows need review.', (int) ($report['failed_count'] ?? 0));

        $responsePayload = [
            'success' => true,
            'message' => $message,
            'refreshTarget' => '[data-refresh-region="products-catalog"]',
            'refreshUrl' => url('products'),
            'redirect_path' => 'products',
            'flash_message' => $message,
        ];

        $this->rememberProcessedSubmission(self::PRODUCT_IMPORT_SUBMISSION_SCOPE, $submissionKey, array_merge($responsePayload, [
            'stored_at' => time(),
        ]));

        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode($responsePayload);
            return;
        }

        Session::flash('success', $message);
        $this->redirect('products');
    }

    public function store(Request $request): void
    {
        $productModel = new Product();
        $submissionKey = trim((string) $request->input('submission_key', ''));
        $duplicateSubmission = $this->processedSubmission(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey);
        if ($duplicateSubmission !== null) {
            $this->respondWithStoredSubmission($request, $duplicateSubmission);
        }

        $payload = $this->payload($request);
        $errors = $this->validateForm($request, $payload);
        $variants = $this->variants($request);
        $existingProduct = $productModel->findExistingForRestock($payload, $this->branchId());

        if ($submissionKey !== '' && !$this->isIssuedSubmissionKey(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey)) {
            $errors['submission_key'][] = 'This product form expired. Reload it and submit again.';
        }

        if ($existingProduct !== null) {
            if ((float) ($payload['opening_stock'] ?? 0) <= 0) {
                $errors['opening_stock'][] = 'This product already exists. Enter stock to add, or use Edit/Inventory for other changes.';
            }

            if ($errors !== []) {
                if ($request->isAjax()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Please review the product details and try again.', 'errors' => $errors]);
                    return;
                }

                $this->render('products/create', [
                    'title' => 'Add Product',
                    'breadcrumbs' => ['Dashboard', 'Products', 'Add Product'],
                    'categories' => $productModel->categories(),
                    'suppliers' => $productModel->suppliers($this->branchId()),
                    'taxes' => $productModel->taxes(),
                    'errors' => $errors,
                    'product' => array_merge($payload, ['variants' => $variants]),
                    'submissionKey' => $this->reuseOrIssueSubmissionKey(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey),
                ]);
                return;
            }

            $upload = new UploadService();
            $payload['image_path'] = $upload->store($request->file('image'), 'products') ?? $existingProduct['image_path'];
            $productModel->restockExistingProduct((int) $existingProduct['id'], $payload, $this->branchId(), (int) Auth::id());

            (new AuditLog())->record(
                userId: Auth::id(),
                action: 'restock',
                entityType: 'product',
                entityId: (int) $existingProduct['id'],
                description: 'Added ' . number_format((float) $payload['opening_stock'], 2) . ' units to existing product ' . $existingProduct['name'] . ' from the create product form.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $this->rememberProcessedSubmission(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey, [
                'success' => true,
                'message' => 'Existing product matched and stock was added successfully.',
                'flash_message' => 'Existing product updated and stock added successfully.',
                'product_id' => (int) $existingProduct['id'],
                'restocked' => true,
                'redirect_path' => 'products/show?id=' . (int) $existingProduct['id'],
            ]);
            Session::flash('success', 'Existing product updated and stock added successfully.');
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Existing product matched and stock was added successfully.',
                    'product_id' => (int) $existingProduct['id'],
                    'restocked' => true,
                ]);
                return;
            }

            $this->redirect('products/show?id=' . (int) $existingProduct['id']);
        }

        if ($productModel->skuExists($payload['sku'], null, $this->branchId())) {
            $errors['sku'][] = 'That SKU is already in use.';
        }

        if ($productModel->barcodeExists($payload['barcode'], null, $this->branchId())) {
            $errors['barcode'][] = 'That barcode is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please review the product details and try again.', 'errors' => $errors]);
                return;
            }

            $this->render('products/create', [
                'title' => 'Add Product',
                'breadcrumbs' => ['Dashboard', 'Products', 'Add Product'],
                'categories' => $productModel->categories(),
                'suppliers' => $productModel->suppliers($this->branchId()),
                'taxes' => $productModel->taxes(),
                'errors' => $errors,
                'product' => array_merge($payload, ['variants' => $variants]),
                'submissionKey' => $this->reuseOrIssueSubmissionKey(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey),
            ]);
            return;
        }


        $upload = new UploadService();
        $payload['image_path'] = $upload->store($request->file('image'), 'products');

        // Wrap create in a transaction to ensure variants and inventory are consistent
        $productId = Database::transaction(function () use ($productModel, $payload, $variants) {
            return $productModel->createProduct($payload, $variants, $this->branchId());
        });

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'product',
            entityId: $productId,
            description: 'Created product ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->rememberProcessedSubmission(self::PRODUCT_CREATE_SUBMISSION_SCOPE, $submissionKey, [
            'success' => true,
            'message' => 'Product created successfully.',
            'flash_message' => 'Product created successfully.',
            'product_id' => $productId,
            'redirect_path' => 'products',
        ]);
        Session::flash('success', 'Product created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product created successfully.']);
            return;
        }

        $this->redirect('products');
    }

    public function edit(Request $request): void
    {
        $productModel = new Product();
        $product = $productModel->find((int) $request->query('id'), $this->branchId());

        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $this->render('products/edit', [
            'title' => 'Edit Product',
            'breadcrumbs' => ['Dashboard', 'Products', 'Edit Product'],
            'categories' => $productModel->categories(),
            'suppliers' => $productModel->suppliers($this->branchId()),
            'taxes' => $productModel->taxes(),
            'errors' => [],
            'product' => $product,
        ]);
    }

    public function update(Request $request): void
    {
        $productModel = new Product();
        $productId = (int) $request->input('id');
        $existing = $productModel->find($productId, $this->branchId());

        if ($existing === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $payload = $this->payload($request);
        $errors = $this->validateForm($request, $payload, false);
        $variants = $this->variants($request);

        if ($productModel->skuExists($payload['sku'], $productId, $this->branchId())) {
            $errors['sku'][] = 'That SKU is already in use.';
        }

        if ($productModel->barcodeExists($payload['barcode'], $productId, $this->branchId())) {
            $errors['barcode'][] = 'That barcode is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please review the product details and try again.', 'errors' => $errors]);
                return;
            }

            $this->render('products/edit', [
                'title' => 'Edit Product',
                'breadcrumbs' => ['Dashboard', 'Products', 'Edit Product'],
                'categories' => $productModel->categories(),
                'suppliers' => $productModel->suppliers($this->branchId()),
                'taxes' => $productModel->taxes(),
                'errors' => $errors,
                'product' => array_merge($existing, $payload, ['variants' => $variants]),
            ]);
            return;
        }


        $upload = new UploadService();
        $payload['image_path'] = $upload->store($request->file('image'), 'products') ?? $existing['image_path'];

        // Wrap update in a transaction for safety
        Database::transaction(function () use ($productModel, $productId, $payload, $variants) {
            $productModel->updateProduct($productId, $payload, $variants);
        });

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'product',
            entityId: $productId,
            description: 'Updated product ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Product updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product updated successfully.']);
            return;
        }

        $this->redirect('products');
    }

    public function suggest(Request $request): void
    {
        $q = trim((string) $request->query('q', ''));
        $productModel = new Product();
        $items = $productModel->allWithRelations($q, $this->branchId());
        $items = array_slice($items, 0, 12);
        $results = array_map(static function (array $p): array {
            return [
                'id' => (int) ($p['id'] ?? 0),
                'name' => (string) ($p['name'] ?? ''),
                'brand' => (string) ($p['brand'] ?? ''),
                'sku' => (string) ($p['sku'] ?? ''),
                'barcode' => (string) ($p['barcode'] ?? ''),
                'price' => (float) ($p['price'] ?? 0),
                'image' => (string) ($p['image_path'] ?? ''),
                'stock' => isset($p['stock_quantity']) ? (float) $p['stock_quantity'] : 0,
            ];
        }, $items);

        header('Content-Type: application/json');
        echo json_encode($results);
    }

    public function delete(Request $request): void
    {
        $productId = (int) $request->input('id');
        $productModel = new Product();
        $product = $productModel->find($productId, $this->branchId());

        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        // Soft-delete inside transaction and ensure branch ownership
        Database::transaction(function () use ($productModel, $productId, $product) {
            $productModel->deleteProduct($productId);
        });

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'product',
            entityId: $productId,
            description: 'Soft-deleted product ' . $product['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Product archived.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product archived successfully.']);
            return;
        }

        $this->redirect('products');
    }

    public function storeCategory(Request $request): void
    {
        $categoryModel = new ProductCategory();
        $payload = $this->categoryPayload($request);
        $errors = $this->validateCategory($payload);

        if ($categoryModel->nameExists($payload['name'], $payload['parent_id'])) {
            $errors['name'][] = 'That category name is already in use at the selected level.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please review the category details and try again.',
                    'errors' => $errors,
                ]);
                return;
            }

            $this->renderCategoryIndex([
                'categoryForm' => $payload,
                'categoryCreateErrors' => $errors,
            ]);
            return;
        }

        $categoryId = $categoryModel->createCategory($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'product_category',
            entityId: $categoryId,
            description: 'Created product category ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Product category created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product category created successfully.']);
            return;
        }

        $this->redirect('products/categories');
    }

    public function updateCategory(Request $request): void
    {
        $categoryModel = new ProductCategory();
        $categoryId = (int) $request->input('id');
        $existing = $categoryModel->find($categoryId);

        if ($existing === null) {
            throw new HttpException(404, 'Category not found.');
        }

        $payload = $this->categoryPayload($request);
        $errors = $this->validateCategory($payload, $categoryId);

        if ($categoryModel->nameExists($payload['name'], $payload['parent_id'], $categoryId)) {
            $errors['name'][] = 'That category name is already in use at the selected level.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please review the category details and try again.',
                    'errors' => $errors,
                ]);
                return;
            }

            $this->renderCategoryIndex([
                'editCategoryId' => $categoryId,
                'categoryEditErrors' => $errors,
            ]);
            return;
        }

        $categoryModel->updateCategory($categoryId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'product_category',
            entityId: $categoryId,
            description: 'Updated product category ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Product category updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product category updated successfully.']);
            return;
        }

        $this->redirect('products/categories');
    }

    public function deleteCategory(Request $request): void
    {
        $categoryModel = new ProductCategory();
        $categoryId = (int) $request->input('id');
        $blockedMessage = null;
        $ipAddress = $request->ip();
        $userAgent = $request->userAgent();

        // Wrap delete in a transaction and re-check counts to avoid race conditions
        try {
            Database::transaction(function () use ($categoryModel, $categoryId, $ipAddress, $userAgent, &$blockedMessage) {
                $category = $categoryModel->find($categoryId);

                if ($category === null) {
                    throw new HttpException(404, 'Category not found.');
                }

                // re-check counts inside transaction
                $productCount = (int) ($category['product_count'] ?? 0);
                $childCount = (int) ($category['child_count'] ?? 0);

                if ($productCount > 0 || $childCount > 0) {
                    $blockedMessage = 'This category has products or subcategories and cannot be deleted yet.';
                    return;
                }

                $categoryModel->deleteCategory($categoryId);

                (new AuditLog())->record(
                    userId: Auth::id(),
                    action: 'delete',
                    entityType: 'product_category',
                    entityId: $categoryId,
                    description: 'Deleted product category ' . $category['name'] . '.',
                    ipAddress: $ipAddress,
                    userAgent: $userAgent
                );
            });
        } catch (HttpException $e) {
            throw $e;
        }

        if ($blockedMessage !== null) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $blockedMessage]);
                return;
            }

            Session::flash('error', $blockedMessage);
            $this->redirect('products/categories');
        }

        Session::flash('success', 'Product category deleted successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Product category deleted successfully.']);
            return;
        }

        $this->redirect('products/categories');
    }

    public function bulkArchive(Request $request): void
    {
        $ids = $request->input('ids', []);
        if (!is_array($ids) || count($ids) === 0) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No products selected.']);
                return;
            }

            Session::flash('error', 'No products selected for archival.');
            $this->redirect('products');
        }

        $productModel = new Product();
        $archivedCount = 0;

        try {
            Database::transaction(function () use ($productModel, $ids, &$archivedCount) {
                foreach ($ids as $id) {
                    $id = (int) $id;
                    $product = $productModel->find($id, $this->branchId());
                    if ($product === null) {
                        continue;
                    }

                    $productModel->deleteProduct($id);
                    $archivedCount++;
                    (new AuditLog())->record(
                        userId: Auth::id(),
                        action: 'delete',
                        entityType: 'product',
                        entityId: $id,
                        description: 'Bulk archived product ' . $product['name'] . '.',
                        ipAddress: '',
                        userAgent: ''
                    );
                }
            });
        } catch (\Throwable $e) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Failed to archive products.']);
                return;
            }

            Session::flash('error', 'Failed to archive products.');
            $this->redirect('products');
        }

        if ($archivedCount === 0) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'No matching products were found for archival.']);
                return;
            }

            Session::flash('error', 'No matching products were found for archival.');
            $this->redirect('products');
        }

        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $archivedCount === 1 ? '1 product archived.' : $archivedCount . ' products archived.',
            ]);
            return;
        }

        Session::flash('success', $archivedCount === 1 ? '1 product archived.' : $archivedCount . ' products archived.');
        $this->redirect('products');
    }

    private function validateForm(Request $request, array $payload, bool $creating = true): array
    {
        $errors = Validator::validate($request->all(), [
            'name' => 'required|min:2|max:150',
            'brand' => 'nullable|max:120',
            'unit' => 'required|in:pcs,kg,litre,box',
            'price' => 'required|numeric',
            'cost_price' => 'required|numeric',
            'low_stock_threshold' => 'required|numeric',
            'inventory_method' => 'required|in:FIFO,LIFO',
            'status' => 'required|in:active,inactive',
        ]);

        if ($payload['price'] < 0) {
            $errors['price'][] = 'Selling price cannot be negative.';
        }

        if ($payload['cost_price'] < 0) {
            $errors['cost_price'][] = 'Cost price cannot be negative.';
        }

        if ($payload['low_stock_threshold'] < 0) {
            $errors['low_stock_threshold'][] = 'Low stock threshold cannot be negative.';
        }

        if ($creating && $payload['opening_stock'] < 0) {
            $errors['opening_stock'][] = 'Opening stock cannot be negative.';
        }

        if ($payload['category_id'] !== null && (new ProductCategory())->find((int) $payload['category_id']) === null) {
            $errors['category_id'][] = 'Select a valid category.';
        }

        if ($payload['supplier_id'] !== null && (new Supplier())->find((int) $payload['supplier_id'], $this->branchId()) === null) {
            $errors['supplier_id'][] = 'Select a valid supplier.';
        }

        if ($payload['tax_id'] !== null && (new Tax())->find((int) $payload['tax_id']) === null) {
            $errors['tax_id'][] = 'Select a valid tax profile.';
        }

        if ($request->file('image') !== null && ($request->file('image')['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $tmpName = (string) ($request->file('image')['tmp_name'] ?? '');
            if ($tmpName !== '' && !is_file($tmpName)) {
                $errors['image'][] = 'Uploaded image is invalid.';
            }
        }

        return $errors;
    }

    private function payload(Request $request): array
    {
        return [
            'branch_id' => $this->branchId(),
            'category_id' => $this->nullableInt($request->input('category_id')),
            'supplier_id' => $this->nullableInt($request->input('supplier_id')),
            'tax_id' => $this->nullableInt($request->input('tax_id')),
            'name' => trim((string) $request->input('name')),
            'brand' => trim((string) $request->input('brand', '')),
            'sku' => trim((string) $request->input('sku', '')),
            'barcode' => trim((string) $request->input('barcode', '')),
            'description' => trim((string) $request->input('description', '')),
            'unit' => (string) $request->input('unit', 'pcs'),
            'price' => (float) $request->input('price', 0),
            'cost_price' => (float) $request->input('cost_price', 0),
            'low_stock_threshold' => (float) $request->input('low_stock_threshold', 0),
            'track_stock' => $request->boolean('track_stock') ? 1 : 0,
            'status' => (string) $request->input('status', 'active'),
            'inventory_method' => (string) $request->input('inventory_method', 'FIFO'),
            'opening_stock' => (float) $request->input('opening_stock', 0),
        ];
    }

    private function variants(Request $request): array
    {
        $names = $request->input('variant_name', []);
        $values = $request->input('variant_value', []);
        $skus = $request->input('variant_sku', []);
        $barcodes = $request->input('variant_barcode', []);
        $priceAdjustments = $request->input('variant_price_adjustment', []);
        $stocks = $request->input('variant_stock_quantity', []);
        $variants = [];

        foreach ($names as $index => $name) {
            $variants[] = [
                'variant_name' => trim((string) $name),
                'variant_value' => trim((string) ($values[$index] ?? '')),
                'sku' => trim((string) ($skus[$index] ?? '')),
                'barcode' => trim((string) ($barcodes[$index] ?? '')),
                'price_adjustment' => (float) ($priceAdjustments[$index] ?? 0),
                'stock_quantity' => (float) ($stocks[$index] ?? 0),
            ];
        }

        return $variants;
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function renderIndex(string $search = '', ?int $categoryId = null, string $brand = ''): void
    {
        $productModel = new Product();
        $categoryModel = new ProductCategory();
        $productCategories = $categoryModel->allWithUsage();
        $selectedCategory = $categoryId !== null ? $categoryModel->find($categoryId) : null;

        if ($categoryId !== null && $selectedCategory === null) {
            $categoryId = null;
        }

        $this->render('products/index', [
            'title' => 'Products',
            'breadcrumbs' => ['Dashboard', 'Products'],
            'products' => $productModel->allWithRelations($search, $this->branchId(), $categoryId, $brand),
            'search' => $search,
            'selectedCategoryId' => $categoryId !== null ? (string) $categoryId : '',
            'selectedBrand' => $brand,
            'selectedCategory' => $selectedCategory,
            'productCategories' => $productModel->categories(),
            'productBrands' => $productModel->brands($this->branchId()),
            'importReport' => Session::pullFlash('product_import_report'),
            'categorySummary' => [
                'total_categories' => count($productCategories),
                'top_level_categories' => count(array_filter($productCategories, static fn (array $category): bool => empty($category['parent_id']))),
                'subcategories' => count(array_filter($productCategories, static fn (array $category): bool => !empty($category['parent_id']))),
            ],
        ]);
    }

    private function renderCategoryIndex(array $overrides = []): void
    {
        $categoryModel = new ProductCategory();
        $productCategories = $categoryModel->allWithUsage();
        $assignedProducts = array_sum(array_map(static fn (array $category): int => (int) ($category['product_count'] ?? 0), $productCategories));

        $this->render('products/categories', [
            'title' => 'Product Categories',
            'breadcrumbs' => ['Dashboard', 'Products', 'Categories'],
            'productCategories' => $productCategories,
            'parentCategoryOptions' => $categoryModel->parentOptions(),
            'categoryCreateErrors' => $overrides['categoryCreateErrors'] ?? [],
            'categoryEditErrors' => $overrides['categoryEditErrors'] ?? [],
            'editCategoryId' => $overrides['editCategoryId'] ?? null,
            'categoryForm' => $overrides['categoryForm'] ?? [
                'name' => '',
                'parent_id' => '',
                'description' => '',
            ],
            'categorySummary' => [
                'total_categories' => count($productCategories),
                'top_level_categories' => count(array_filter($productCategories, static fn (array $category): bool => empty($category['parent_id']))),
                'subcategories' => count(array_filter($productCategories, static fn (array $category): bool => !empty($category['parent_id']))),
                'assigned_products' => $assignedProducts,
            ],
        ]);
    }

    private function categoryPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'parent_id' => $this->nullableInt($request->input('parent_id')),
            'description' => trim((string) $request->input('description', '')),
        ];
    }

    private function validateCategory(array $payload, ?int $categoryId = null): array
    {
        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:150',
            'description' => 'nullable|max:255',
        ]);

        if ($categoryId !== null && $payload['parent_id'] !== null && (int) $payload['parent_id'] === $categoryId) {
            $errors['parent_id'][] = 'A category cannot be its own parent.';
        }

        if ($categoryId !== null && $payload['parent_id'] !== null) {
            $descendants = (new ProductCategory())->descendantIds($categoryId);
            if (in_array((int) $payload['parent_id'], $descendants, true)) {
                $errors['parent_id'][] = 'A category cannot be moved under one of its own subcategories.';
            }
        }

        // If a parent_id is provided, ensure it exists and is not deleted
        if ($payload['parent_id'] !== null) {
            $parent = (new ProductCategory())->find((int) $payload['parent_id']);
            if ($parent === null) {
                $errors['parent_id'][] = 'Selected parent category does not exist.';
            }
        }

        return $errors;
    }
}
