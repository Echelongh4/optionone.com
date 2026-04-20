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
use App\Models\Customer;
use Throwable;

class CustomerController extends Controller
{
    public function index(Request $request): void
    {
        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'group_id' => trim((string) $request->query('group_id', '')),
            'credit_status' => trim((string) $request->query('credit_status', '')),
        ];

        $this->renderIndex($filters);
    }

    public function create(Request $request): void
    {
        $customerModel = new Customer();

        $this->render('customers/create', [
            'title' => 'Add Customer',
            'breadcrumbs' => ['Dashboard', 'Customers', 'Add Customer'],
            'customer' => [],
            'groups' => $customerModel->groups(),
            'errors' => [],
        ]);
    }

    public function suggest(Request $request): void
    {
        $query = trim((string) $request->query('q', ''));
        $customerId = $this->nullableInt($request->query('id'));
        $limit = max(1, min((int) $request->query('limit', 20), 50));
        $rows = (new Customer())->suggestForPos($this->branchId(), $query, $limit, $customerId);

        header('Content-Type: application/json');
        echo json_encode(array_map([$this, 'serializePosCustomer'], $rows));
    }

    public function store(Request $request): void
    {
        $customerModel = new Customer();
        $payload = $this->payload($request);
        $openingCredit = (float) $payload['credit_balance'];
        $errors = Validator::validate($request->all(), [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'nullable|email|max:150',
            'credit_balance' => 'numeric',
            'special_pricing_value' => 'numeric',
        ]);

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please review the customer details and try again.', 'errors' => $errors]);
                return;
            }

            $this->render('customers/create', [
                'title' => 'Add Customer',
                'breadcrumbs' => ['Dashboard', 'Customers', 'Add Customer'],
                'customer' => $payload,
                'groups' => $customerModel->groups(),
                'errors' => $errors,
            ]);
            return;
        }

        $payload['credit_balance'] = 0.0;
        $customerId = $customerModel->createCustomer($payload);

        if ($openingCredit > 0) {
            $customerModel->adjustCreditBalance(
                customerId: $customerId,
                amount: $openingCredit,
                transactionType: 'adjustment',
                userId: (int) Auth::id(),
                notes: 'Opening customer credit balance set during creation.'
            );
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'customer',
            entityId: $customerId,
            description: 'Created customer profile for ' . $payload['first_name'] . ' ' . $payload['last_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer created successfully.', 'redirect' => url('customers/show?id=' . $customerId)]);
            return;
        }

        $this->redirect('customers/show?id=' . $customerId);
    }

    public function show(Request $request): void
    {
        $customerModel = new Customer();
        $customerId = (int) $request->query('id');
        $customer = $customerModel->find($customerId, $this->branchId());

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        $this->render('customers/show', [
            'title' => 'Customer Profile',
            'breadcrumbs' => ['Dashboard', 'Customers', 'Profile'],
            'customer' => $customer,
            'purchaseHistory' => $customerModel->purchaseHistory($customerId),
            'loyaltyHistory' => $customerModel->loyaltyHistory($customerId),
            'creditHistory' => $customerModel->creditHistory($customerId),
        ]);
    }

    public function edit(Request $request): void
    {
        $customerModel = new Customer();
        $customer = $customerModel->find((int) $request->query('id'), $this->branchId());

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        $this->render('customers/edit', [
            'title' => 'Edit Customer',
            'breadcrumbs' => ['Dashboard', 'Customers', 'Edit Customer'],
            'customer' => $customer,
            'groups' => $customerModel->groups(),
            'errors' => [],
        ]);
    }

    public function update(Request $request): void
    {
        $customerModel = new Customer();
        $customerId = (int) $request->input('id');
        $customer = $customerModel->find($customerId, $this->branchId());

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        $payload = $this->payload($request);
        $targetCredit = (float) $payload['credit_balance'];
        $errors = Validator::validate($request->all(), [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'nullable|email|max:150',
            'credit_balance' => 'numeric',
            'special_pricing_value' => 'numeric',
        ]);

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please review the customer details and try again.', 'errors' => $errors]);
                return;
            }

            $this->render('customers/edit', [
                'title' => 'Edit Customer',
                'breadcrumbs' => ['Dashboard', 'Customers', 'Edit Customer'],
                'customer' => array_merge($customer, $payload),
                'groups' => $customerModel->groups(),
                'errors' => $errors,
            ]);
            return;
        }

        unset($payload['credit_balance']);
        $customerModel->updateCustomer($customerId, $payload);
        $customerModel->syncCreditBalance(
            customerId: $customerId,
            targetBalance: $targetCredit,
            userId: (int) Auth::id(),
            notes: 'Customer profile credit balance updated.'
        );

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'customer',
            entityId: $customerId,
            description: 'Updated customer profile for ' . $payload['first_name'] . ' ' . $payload['last_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer updated successfully.', 'redirect' => url('customers/show?id=' . $customerId)]);
            return;
        }

        $this->redirect('customers/show?id=' . $customerId);
    }

    public function delete(Request $request): void
    {
        $customerModel = new Customer();
        $customerId = (int) $request->input('id');
        $customer = $customerModel->find($customerId, $this->branchId());

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        if ((float) ($customer['credit_balance'] ?? 0) > 0) {
            $message = 'Customers with outstanding credit cannot be archived.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('customers/show?id=' . $customerId);
        }

        $customerModel->deleteCustomer($customerId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'customer',
            entityId: $customerId,
            description: 'Archived customer profile for ' . $customer['full_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer archived successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer archived successfully.', 'redirect' => url('customers')]);
            return;
        }

        $this->redirect('customers');
    }

    public function recordCreditPayment(Request $request): void
    {
        $customerModel = new Customer();
        $customerId = (int) $request->input('customer_id');
        $customer = $customerModel->find($customerId, $this->branchId());

        if ($customer === null) {
            throw new HttpException(404, 'Customer not found.');
        }

        $amount = round((float) $request->input('amount', 0), 2);
        $notes = trim((string) $request->input('notes', 'Account payment received.'));

        if ($amount <= 0) {
            $message = 'Enter a valid account payment amount.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('customers/show?id=' . $customerId);
        }

        try {
            $result = $customerModel->adjustCreditBalance(
                customerId: $customerId,
                amount: -1 * $amount,
                transactionType: 'payment',
                userId: (int) Auth::id(),
                notes: $notes !== '' ? $notes : 'Account payment received.'
            );

            (new AuditLog())->record(
                userId: Auth::id(),
                action: 'credit_payment',
                entityType: 'customer',
                entityId: $customerId,
                description: 'Recorded customer credit payment of ' . format_currency($amount) . '. New balance: ' . format_currency($result['balance']) . '.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            Session::flash('success', 'Customer payment recorded successfully.');
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => 'Customer payment recorded successfully.',
                    'balance' => $result['balance'] ?? null,
                ]);
                return;
            }
        } catch (Throwable $exception) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $exception->getMessage()]);
                return;
            }

            Session::flash('error', $exception->getMessage());
        }

        $this->redirect('customers/show?id=' . $customerId);
    }

    public function storeGroup(Request $request): void
    {
        $customerModel = new Customer();
        $payload = $this->groupPayload($request);
        $errors = $this->validateGroup($payload);

        if ($customerModel->groupNameExists($payload['name'])) {
            $errors['name'][] = 'That customer group name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the customer group form errors.', 'errors' => $errors]);
                return;
            }

            $this->renderIndex([], [
                'groupForm' => $payload,
                'groupCreateErrors' => $errors,
            ]);
            return;
        }

        $groupId = $customerModel->createGroup($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'customer_group',
            entityId: $groupId,
            description: 'Created customer group ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer group created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer group created successfully.']);
            return;
        }

        $this->redirect('customers');
    }

    public function updateGroup(Request $request): void
    {
        $customerModel = new Customer();
        $groupId = (int) $request->input('id');
        $existing = $customerModel->findGroup($groupId);

        if ($existing === null) {
            throw new HttpException(404, 'Customer group not found.');
        }

        $payload = $this->groupPayload($request);
        $errors = $this->validateGroup($payload);

        if ($customerModel->groupNameExists($payload['name'], $groupId)) {
            $errors['name'][] = 'That customer group name is already in use.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Please fix the customer group form errors.', 'errors' => $errors]);
                return;
            }

            $this->renderIndex([], [
                'editGroupId' => $groupId,
                'groupEditErrors' => $errors,
            ]);
            return;
        }

        $customerModel->updateGroup($groupId, $payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'customer_group',
            entityId: $groupId,
            description: 'Updated customer group ' . $payload['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer group updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer group updated successfully.']);
            return;
        }

        $this->redirect('customers');
    }

    public function deleteGroup(Request $request): void
    {
        $customerModel = new Customer();
        $groupId = (int) $request->input('id');
        $group = $customerModel->findGroup($groupId);

        if ($group === null) {
            throw new HttpException(404, 'Customer group not found.');
        }

        if ((int) ($group['customer_count'] ?? 0) > 0) {
            $message = 'Customer groups assigned to active customers cannot be deleted.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('customers');
        }

        $customerModel->deleteGroup($groupId);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'delete',
            entityType: 'customer_group',
            entityId: $groupId,
            description: 'Deleted customer group ' . $group['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Customer group deleted successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Customer group deleted successfully.']);
            return;
        }

        $this->redirect('customers');
    }

    private function payload(Request $request): array
    {
        return [
            'branch_id' => $this->branchId(),
            'customer_group_id' => $this->nullableInt($request->input('customer_group_id')),
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'address' => trim((string) $request->input('address', '')),
            'credit_balance' => (float) $request->input('credit_balance', 0),
            'loyalty_balance' => (int) $request->input('loyalty_balance', 0),
            'special_pricing_type' => (string) $request->input('special_pricing_type', 'none'),
            'special_pricing_value' => (float) $request->input('special_pricing_value', 0),
        ];
    }

    private function groupPayload(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'discount_type' => (string) $request->input('discount_type', 'none'),
            'discount_value' => (float) $request->input('discount_value', 0),
            'description' => trim((string) $request->input('description', '')),
        ];
    }

    private function validateGroup(array $payload): array
    {
        $errors = Validator::validate($payload, [
            'name' => 'required|min:2|max:120',
            'discount_type' => 'required|in:none,percentage,fixed',
            'discount_value' => 'required|numeric',
            'description' => 'nullable|max:255',
        ]);

        if ($payload['discount_type'] === 'none' && (float) $payload['discount_value'] !== 0.0) {
            $errors['discount_value'][] = 'Discount value must be 0 when the group pricing type is none.';
        }

        if ((float) $payload['discount_value'] < 0) {
            $errors['discount_value'][] = 'Discount value cannot be negative.';
        }

        if ($payload['discount_type'] === 'percentage' && (float) $payload['discount_value'] > 100) {
            $errors['discount_value'][] = 'Percentage discounts cannot exceed 100.';
        }

        return $errors;
    }

    private function renderIndex(array $filters = [], array $overrides = []): void
    {
        $customerModel = new Customer();
        $customers = $customerModel->allActive($this->branchId(), $filters);
        $groups = $customerModel->groups();
        $groupRecords = $customerModel->groupsWithUsage($this->branchId());

        $this->render('customers/index', [
            'title' => 'Customers',
            'breadcrumbs' => ['Dashboard', 'Customers'],
            'customers' => $customers,
            'groups' => $groups,
            'customerGroups' => $groupRecords,
            'filters' => $filters,
            'groupCreateErrors' => $overrides['groupCreateErrors'] ?? [],
            'groupEditErrors' => $overrides['groupEditErrors'] ?? [],
            'editGroupId' => $overrides['editGroupId'] ?? null,
            'groupForm' => $overrides['groupForm'] ?? [
                'name' => '',
                'discount_type' => 'none',
                'discount_value' => 0,
                'description' => '',
            ],
        ]);
    }

    private function branchId(): int
    {
        return (int) (current_branch_id() ?? 0);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function serializePosCustomer(array $customer): array
    {
        $fullName = trim((string) ($customer['full_name'] ?? ''));

        return [
            'id' => (string) ($customer['id'] ?? ''),
            'full_name' => $fullName !== '' ? $fullName : 'Walk-in Customer',
            'phone' => (string) ($customer['phone'] ?? ''),
            'email' => (string) ($customer['email'] ?? ''),
            'credit_balance' => (float) ($customer['credit_balance'] ?? 0),
            'loyalty_balance' => (int) ($customer['loyalty_balance'] ?? 0),
            'special_pricing_type' => (string) ($customer['special_pricing_type'] ?? 'none'),
            'special_pricing_value' => (float) ($customer['special_pricing_value'] ?? 0),
            'customer_group_name' => (string) ($customer['customer_group_name'] ?? ''),
            'total_orders' => (int) ($customer['total_orders'] ?? 0),
            'total_spent' => (float) ($customer['total_spent'] ?? 0),
            'last_purchase_at' => (string) ($customer['last_purchase_at'] ?? ''),
            'search_blob' => strtolower(trim(implode(' ', [
                $fullName,
                (string) ($customer['phone'] ?? ''),
                (string) ($customer['email'] ?? ''),
                (string) ($customer['customer_group_name'] ?? ''),
            ]))),
        ];
    }
}
