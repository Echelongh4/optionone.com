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
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\UploadService;

class ExpenseController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => (string) $request->query('search', ''),
            'category_id' => (string) $request->query('category_id', ''),
            'status' => (string) $request->query('status', ''),
            'date_from' => (string) $request->query('date_from', ''),
            'date_to' => (string) $request->query('date_to', ''),
        ];

        $this->renderIndex($filters);
    }

    public function create(Request $request): void
    {
        $expenseModel = new Expense();

        $this->render('expenses/create', [
            'title' => 'Log Expense',
            'breadcrumbs' => ['Dashboard', 'Expenses', 'Log Expense'],
            'categories' => $expenseModel->categories(),
            'expense' => ['status' => 'approved'],
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        $expenseModel = new Expense();
        $payload = $this->expensePayload($request);
        $errors = $this->validateExpense($payload);

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please provide a valid expense entry.', 'errors' => $errors]);
                return;
            }

            $this->render('expenses/create', [
                'title' => 'Log Expense',
                'breadcrumbs' => ['Dashboard', 'Expenses', 'Log Expense'],
                'categories' => $expenseModel->categories(),
                'expense' => $payload,
                'errors' => $errors,
            ]);
            return;
        }

        $payload['receipt_path'] = (new UploadService())->store($request->file('receipt'), 'expenses');
        $expenseId = $expenseModel->createExpense($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'expense',
            entityId: $expenseId,
            description: 'Logged a new expense entry.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense logged successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense logged successfully.']);
            return;
        }

        $this->redirect('expenses');
    }

    public function show(Request $request): void
    {
        $expense = (new Expense())->find((int) $request->query('id'), $this->branchId());
        if ($expense === null) {
            throw new HttpException(404, 'Expense not found.');
        }

        $this->render('expenses/show', [
            'title' => 'Expense Details',
            'breadcrumbs' => ['Dashboard', 'Expenses', 'Expense'],
            'expense' => $expense,
        ]);
    }

    public function edit(Request $request): void
    {
        $expenseModel = new Expense();
        $expense = $expenseModel->find((int) $request->query('id'), $this->branchId());
        if ($expense === null) {
            throw new HttpException(404, 'Expense not found.');
        }

        $this->render('expenses/edit', [
            'title' => 'Edit Expense',
            'breadcrumbs' => ['Dashboard', 'Expenses', 'Edit Expense'],
            'categories' => $expenseModel->categories(),
            'expense' => $expense,
            'errors' => [],
        ]);
    }

    public function update(Request $request): void
    {
        $expenseModel = new Expense();
        $expenseId = (int) $request->input('id');
        $existing = $expenseModel->find($expenseId, $this->branchId());
        if ($existing === null) {
            throw new HttpException(404, 'Expense not found.');
        }

        $payload = $this->expensePayload($request, $existing);
        $errors = $this->validateExpense($payload);

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please provide a valid expense entry.', 'errors' => $errors]);
                return;
            }

            $this->render('expenses/edit', [
                'title' => 'Edit Expense',
                'breadcrumbs' => ['Dashboard', 'Expenses', 'Edit Expense'],
                'categories' => $expenseModel->categories(),
                'expense' => array_merge($existing, $payload),
                'errors' => $errors,
            ]);
            return;
        }

        $uploadedReceipt = (new UploadService())->store($request->file('receipt'), 'expenses');
        if ($uploadedReceipt !== null) {
            $payload['receipt_path'] = $uploadedReceipt;
        } elseif ($request->boolean('remove_receipt')) {
            $payload['receipt_path'] = null;
        }

        $expenseModel->updateExpense($expenseId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'expense',
            entityId: $expenseId,
            description: 'Updated expense entry #' . $expenseId . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense updated successfully.']);
            return;
        }

        $this->redirect('expenses/show?id=' . $expenseId);
    }

    public function delete(Request $request): void
    {
        $expenseModel = new Expense();
        $expenseId = (int) $request->input('id');
        $expense = $expenseModel->find($expenseId, $this->branchId());
        if ($expense === null) {
            throw new HttpException(404, 'Expense not found.');
        }

        $expenseModel->deleteExpense($expenseId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'expense',
            entityId: $expenseId,
            description: 'Archived expense entry #' . $expenseId . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense archived successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense archived successfully.', 'redirect' => url('expenses')]);
            return;
        }

        $this->redirect('expenses');
    }

    public function storeCategory(Request $request): void
    {
        $categoryModel = new ExpenseCategory();
        $payload = $this->categoryPayload($request);
        $errors = $this->validateCategory($payload);

        if ($categoryModel->nameExists($payload['name'])) {
            $errors['name'][] = 'That category name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([], [
                'categoryForm' => $payload,
                'categoryCreateErrors' => $errors,
            ]);
            return;
        }

        $categoryId = $categoryModel->createCategory($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'expense_category',
            entityId: $categoryId,
            description: 'Created expense category ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense category created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense category created successfully.']);
            return;
        }

        $this->redirect('expenses');
    }

    public function updateCategory(Request $request): void
    {
        $categoryModel = new ExpenseCategory();
        $categoryId = (int) $request->input('id');
        $existing = $categoryModel->find($categoryId);

        if ($existing === null) {
            throw new HttpException(404, 'Expense category not found.');
        }

        $payload = $this->categoryPayload($request);
        $errors = $this->validateCategory($payload);

        if ($categoryModel->nameExists($payload['name'], $categoryId)) {
            $errors['name'][] = 'That category name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'errors' => $errors]);
                return;
            }

            $this->renderIndex([], [
                'editCategoryId' => $categoryId,
                'categoryEditErrors' => $errors,
            ]);
            return;
        }

        $categoryModel->updateCategory($categoryId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'expense_category',
            entityId: $categoryId,
            description: 'Updated expense category ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense category updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense category updated successfully.']);
            return;
        }

        $this->redirect('expenses');
    }

    public function deleteCategory(Request $request): void
    {
        $categoryModel = new ExpenseCategory();
        $categoryId = (int) $request->input('id');
        $category = $categoryModel->find($categoryId);

        if ($category === null) {
            throw new HttpException(404, 'Expense category not found.');
        }

        if ((int) ($category['expense_count'] ?? 0) > 0) {
            $message = 'This category is already used by expense records and cannot be deleted.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('expenses');
        }

        $categoryModel->deleteCategory($categoryId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'expense_category',
            entityId: $categoryId,
            description: 'Deleted expense category ' . $category['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Expense category deleted successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Expense category deleted successfully.']);
            return;
        }

        $this->redirect('expenses');
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function renderIndex(array $filters = [], array $overrides = []): void
    {
        $expenseModel = new Expense();
        $categoryModel = new ExpenseCategory();

        $this->render('expenses/index', [
            'title' => 'Expenses',
            'breadcrumbs' => ['Dashboard', 'Expenses'],
            'expenses' => $expenseModel->list($filters, $this->branchId()),
            'summary' => $expenseModel->summary($filters, $this->branchId()),
            'categories' => $expenseModel->categories(),
            'expenseCategories' => $categoryModel->allWithUsage(),
            'filters' => $filters,
            'categoryCreateErrors' => $overrides['categoryCreateErrors'] ?? [],
            'categoryEditErrors' => $overrides['categoryEditErrors'] ?? [],
            'editCategoryId' => $overrides['editCategoryId'] ?? null,
            'categoryForm' => $overrides['categoryForm'] ?? [
                'name' => '',
                'description' => '',
            ],
        ]);
    }

    private function expensePayload(Request $request, array $existing = []): array
    {
        return [
            'branch_id' => $this->branchId(),
            'expense_category_id' => (int) $request->input('expense_category_id'),
            'user_id' => (int) ($existing['user_id'] ?? Auth::id()),
            'amount' => round((float) $request->input('amount', 0), 2),
            'expense_date' => (string) $request->input('expense_date'),
            'description' => trim((string) $request->input('description', '')),
            'status' => (string) $request->input('status', $existing['status'] ?? 'approved'),
            'receipt_path' => $existing['receipt_path'] ?? null,
        ];
    }

    private function validateExpense(array $payload): array
    {
        $errors = Validator::validate($payload, [
            'expense_category_id' => 'required|integer',
            'amount' => 'required|numeric',
            'expense_date' => 'required',
            'description' => 'required|min:2|max:255',
            'status' => 'required|in:draft,approved,rejected',
        ]);

        if ((float) $payload['amount'] < 0) {
            $errors['amount'][] = 'Amount cannot be negative.';
        }

        return $errors;
    }

    private function categoryPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'description' => trim((string) $request->input('description', '')),
        ];
    }

    private function validateCategory(array $payload): array
    {
        return Validator::validate($payload, [
            'name' => 'required|min:2|max:120',
            'description' => 'nullable|max:255',
        ]);
    }
}
