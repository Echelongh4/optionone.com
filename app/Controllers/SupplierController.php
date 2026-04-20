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
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
        ];
        $suppliers = (new Supplier())->list($this->branchId(), $filters['search']);

        $this->render('suppliers/index', [
            'title' => 'Suppliers',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers'],
            'suppliers' => $suppliers,
            'filters' => $filters,
            'summary' => [
                'total_suppliers' => count($suppliers),
                'reachable_suppliers' => count(array_filter($suppliers, static fn (array $supplier): bool => trim((string) ($supplier['email'] ?? '')) !== '' || trim((string) ($supplier['phone'] ?? '')) !== '')),
                'linked_products' => array_sum(array_map(static fn (array $supplier): int => (int) ($supplier['total_products'] ?? 0), $suppliers)),
                'purchase_orders' => array_sum(array_map(static fn (array $supplier): int => (int) ($supplier['total_purchase_orders'] ?? 0), $suppliers)),
                'purchase_value' => array_sum(array_map(static fn (array $supplier): float => (float) ($supplier['total_purchase_value'] ?? 0), $suppliers)),
            ],
        ]);
    }

    public function show(Request $request): void
    {
        $supplier = (new Supplier())->findDetailed((int) $request->query('id'), $this->branchId());
        if ($supplier === null) {
            throw new HttpException(404, 'Supplier not found.');
        }

        $this->render('suppliers/show', [
            'title' => 'Supplier Details',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers', (string) $supplier['name']],
            'supplier' => $supplier,
        ]);
    }

    public function create(Request $request): void
    {
        $this->render('suppliers/create', [
            'title' => 'Add Supplier',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers', 'Add Supplier'],
            'supplier' => [],
            'errors' => [],
        ]);
    }

    public function store(Request $request): void
    {
        $supplierModel = new Supplier();
        $payload = $this->payload($request);
        $errors = $this->validateForm($payload);

        if ($supplierModel->nameExists($payload['name'], $this->branchId())) {
            $errors['name'][] = 'That supplier name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the supplier form errors.', 'errors' => $errors]);
                return;
            }

            $this->render('suppliers/create', [
                'title' => 'Add Supplier',
                'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers', 'Add Supplier'],
                'supplier' => $payload,
                'errors' => $errors,
            ]);
            return;
        }

        $supplierId = $supplierModel->createSupplier($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'supplier',
            entityId: $supplierId,
            description: 'Created supplier ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Supplier created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Supplier created successfully.']);
            return;
        }

        $this->redirect('suppliers');
    }

    public function edit(Request $request): void
    {
        $supplier = (new Supplier())->find((int) $request->query('id'), $this->branchId());
        if ($supplier === null) {
            throw new HttpException(404, 'Supplier not found.');
        }

        $this->render('suppliers/edit', [
            'title' => 'Edit Supplier',
            'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers', 'Edit Supplier'],
            'supplier' => $supplier,
            'errors' => [],
        ]);
    }

    public function update(Request $request): void
    {
        $supplierModel = new Supplier();
        $supplierId = (int) $request->input('id');
        $existing = $supplierModel->find($supplierId, $this->branchId());
        if ($existing === null) {
            throw new HttpException(404, 'Supplier not found.');
        }

        $payload = $this->payload($request);
        $errors = $this->validateForm($payload);

        if ($supplierModel->nameExists($payload['name'], $this->branchId(), $supplierId)) {
            $errors['name'][] = 'That supplier name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the supplier form errors.', 'errors' => $errors]);
                return;
            }

            $this->render('suppliers/edit', [
                'title' => 'Edit Supplier',
                'breadcrumbs' => ['Dashboard', 'Inventory', 'Suppliers', 'Edit Supplier'],
                'supplier' => array_merge($existing, $payload),
                'errors' => $errors,
            ]);
            return;
        }

        $supplierModel->updateSupplier($supplierId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'supplier',
            entityId: $supplierId,
            description: 'Updated supplier ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Supplier updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Supplier updated successfully.']);
            return;
        }

        $this->redirect('suppliers');
    }

    public function delete(Request $request): void
    {
        $supplierModel = new Supplier();
        $supplierId = (int) $request->input('id');
        $supplier = $supplierModel->findDetailed($supplierId, $this->branchId());
        if ($supplier === null) {
            throw new HttpException(404, 'Supplier not found.');
        }

        $supplierModel->deleteSupplier($supplierId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'supplier',
            entityId: $supplierId,
            description: 'Archived supplier ' . $supplier['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Supplier archived successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Supplier archived successfully.']);
            return;
        }

        $this->redirect('suppliers');
    }

    private function payload(Request $request): array
    {
        return [
            'branch_id' => $this->branchId(),
            'name' => trim((string) $request->input('name', '')),
            'contact_person' => trim((string) $request->input('contact_person', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'address' => trim((string) $request->input('address', '')),
            'tax_number' => trim((string) $request->input('tax_number', '')),
        ];
    }

    private function validateForm(array $payload): array
    {
        return Validator::validate($payload, [
            'name' => 'required|min:2|max:150',
            'contact_person' => 'nullable|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|max:50',
            'address' => 'nullable|max:255',
            'tax_number' => 'nullable|max:100',
        ]);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }
}
