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
use App\Models\Branch;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request): void
    {
        $userModel = new User();
        $users = $userModel->listUsers(companyId: current_company_id());
        $recentLogs = (new AuditLog())->recent(12);
        $roles = $this->manageableRoles();
        $permissions = $userModel->permissionsCatalog();

        $this->render('users/index', [
            'title' => 'Users',
            'breadcrumbs' => ['Dashboard', 'Users'],
            'users' => $users,
            'recentLogs' => $recentLogs,
            'roles' => $roles,
            'permissionsByModule' => $this->groupPermissionsByModule($permissions),
            'rolePermissionIds' => $userModel->rolePermissionIds(),
            'summary' => [
                'total_users' => count($users),
                'active_users' => count(array_filter($users, static fn (array $user): bool => $user['status'] === 'active')),
                'inactive_users' => count(array_filter($users, static fn (array $user): bool => $user['status'] === 'inactive')),
                'admin_users' => count(array_filter($users, static fn (array $user): bool => in_array($user['role_name'], ['Super Admin', 'Admin'], true))),
                'managed_roles' => count($roles),
                'managed_permissions' => count($permissions),
            ],
        ]);
    }

    public function create(Request $request): void
    {
        $userModel = new User();
        $this->render('users/create', [
            'title' => 'Add User',
            'breadcrumbs' => ['Dashboard', 'Users', 'Add User'],
            'errors' => [],
            'userData' => ['status' => 'active', 'username' => ''],
            'roles' => $this->availableRoles(),
            'branches' => (new Branch())->active(current_company_id()),
            'supportsUsername' => $userModel->supportsUsername(),
        ]);
    }

    public function store(Request $request): void
    {
        $userModel = new User();
        $payload = $this->payload($request);
        $errors = $this->validateForm($request, $payload);
        $this->guardAssignableRole((int) $payload['role_id']);

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please review the user details and try again.',
                    'errors' => $errors,
                ]);
                return;
            }

            $this->render('users/create', [
                'title' => 'Add User',
                'breadcrumbs' => ['Dashboard', 'Users', 'Add User'],
                'errors' => $errors,
                'userData' => $payload,
                'roles' => $this->availableRoles(),
                'branches' => (new Branch())->active(current_company_id()),
                'supportsUsername' => $userModel->supportsUsername(),
            ]);
            return;
        }

        $password = trim((string) $request->input('password', ''));
        $payload['password'] = password_hash($password, PASSWORD_BCRYPT);
        $userId = $userModel->createUser($payload);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'create',
            entityType: 'user',
            entityId: $userId,
            description: 'Created user ' . $payload['first_name'] . ' ' . $payload['last_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'User created successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully.',
                'redirect' => url('users'),
            ]);
            return;
        }

        $this->redirect('users');
    }

    public function show(Request $request): void
    {
        $user = (new User())->findById((int) $request->query('id'), current_company_id());

        if ($user === null) {
            throw new HttpException(404, 'User not found.');
        }

        $this->guardManagedUser($user);

        $this->render('users/show', [
            'title' => 'User Profile',
            'breadcrumbs' => ['Dashboard', 'Users', $user['full_name']],
            'userData' => $user,
            'activity' => (new AuditLog())->recent(50, (int) $user['id']),
        ]);
    }

    public function edit(Request $request): void
    {
        $userModel = new User();
        $user = $userModel->findById((int) $request->query('id'), current_company_id());

        if ($user === null) {
            throw new HttpException(404, 'User not found.');
        }

        $this->guardManagedUser($user);

        $this->render('users/edit', [
            'title' => 'Edit User',
            'breadcrumbs' => ['Dashboard', 'Users', 'Edit User'],
            'errors' => [],
            'userData' => $user,
            'roles' => $this->availableRoles(),
            'branches' => (new Branch())->active(current_company_id()),
            'supportsUsername' => $userModel->supportsUsername(),
        ]);
    }
    public function update(Request $request): void
    {
        $userModel = new User();
        $userId = (int) $request->input('id');
        $existing = $userModel->findById($userId, current_company_id());

        if ($existing === null) {
            throw new HttpException(404, 'User not found.');
        }

        $this->guardManagedUser($existing);
        $payload = $this->payload($request);
        $errors = $this->validateForm($request, $payload, $userId);
        $this->guardAssignableRole((int) $payload['role_id']);

        if ((int) $existing['id'] === (int) Auth::id() && $payload['status'] === 'inactive') {
            $errors['status'][] = 'You cannot deactivate your own account.';
        }

        if ($errors !== []) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Please review the user details and try again.',
                    'errors' => $errors,
                ]);
                return;
            }

            $this->render('users/edit', [
                'title' => 'Edit User',
                'breadcrumbs' => ['Dashboard', 'Users', 'Edit User'],
                'errors' => $errors,
                'userData' => array_merge($existing, $payload),
                'roles' => $this->availableRoles(),
                'branches' => (new Branch())->active(current_company_id()),
                'supportsUsername' => $userModel->supportsUsername(),
            ]);
            return;
        }

        $userModel->updateUserProfile($userId, $payload);
        $password = trim((string) $request->input('password', ''));
        if ($password !== '') {
            $userModel->setPassword($userId, password_hash($password, PASSWORD_BCRYPT));
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update',
            entityType: 'user',
            entityId: $userId,
            description: 'Updated user ' . $payload['first_name'] . ' ' . $payload['last_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'User updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully.',
                'redirect' => url('users/show?id=' . $userId),
            ]);
            return;
        }

        $this->redirect('users/show?id=' . $userId);
    }

    public function toggleStatus(Request $request): void
    {
        $userModel = new User();
        $userId = (int) $request->input('id');
        $user = $userModel->findById($userId, current_company_id());

        if ($user === null) {
            throw new HttpException(404, 'User not found.');
        }

        $this->guardManagedUser($user);

        if ($userId === (int) Auth::id()) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'You cannot deactivate your own account.',
                ]);
                return;
            }

            Session::flash('error', 'You cannot deactivate your own account.');
            $this->redirect('users');
        }

        $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
        $userModel->setStatus($userId, $newStatus);

        (new AuditLog())->record(
            userId: Auth::id(),
            action: $newStatus === 'active' ? 'activate' : 'deactivate',
            entityType: 'user',
            entityId: $userId,
            description: ($newStatus === 'active' ? 'Reactivated user ' : 'Deactivated user ') . $user['full_name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'User status updated.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'User status updated.',
            ]);
            return;
        }

        $this->redirect('users');
    }

    public function updateRolePermissions(Request $request): void
    {
        $userModel = new User();
        $roleId = (int) $request->input('role_id', 0);
        $role = $this->findManageableRole($roleId);

        if ($role === null) {
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Role not found.']);
                return;
            }

            throw new HttpException(404, 'Role not found.');
        }

        if ((string) $role['name'] === 'Super Admin') {
            $message = 'Super Admin permissions are fixed and cannot be edited here.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('users');
        }

        $catalog = $userModel->permissionsCatalog();
        $catalogById = [];
        $catalogByName = [];

        foreach ($catalog as $permission) {
            $permissionId = (int) $permission['id'];
            $catalogById[$permissionId] = $permission;
            $catalogByName[(string) $permission['name']] = $permissionId;
        }

        $submittedIds = $request->input('permission_ids', []);
        $submittedIds = is_array($submittedIds) ? $submittedIds : [];
        $permissionIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $submittedIds)));

        foreach ($permissionIds as $permissionId) {
            if (!isset($catalogById[$permissionId])) {
                $message = 'One or more selected permissions are invalid.';
                if ($request->isAjax()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $message]);
                    return;
                }

                Session::flash('error', $message);
                $this->redirect('users');
            }
        }

        $currentUser = Auth::user();
        if ($currentUser !== null
            && (int) ($currentUser['role_id'] ?? 0) === $roleId
            && isset($catalogByName['manage_users'])
            && !in_array((int) $catalogByName['manage_users'], $permissionIds, true)
        ) {
            $message = 'You cannot remove Manage Users from your own active role.';
            if ($request->isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $message]);
                return;
            }

            Session::flash('error', $message);
            $this->redirect('users');
        }

        $userModel->syncRolePermissions($roleId, $permissionIds);

        if ($currentUser !== null && (int) ($currentUser['role_id'] ?? 0) === $roleId) {
            Session::put('auth_permissions', $userModel->permissionsForUser((int) $currentUser['id']));
        }

        (new AuditLog())->record(
            userId: Auth::id(),
            action: 'update_permissions',
            entityType: 'role',
            entityId: $roleId,
            description: 'Updated permissions for role ' . $role['name'] . '.',
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        Session::flash('success', 'Role permissions updated successfully.');
        if ($request->isAjax()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Role permissions updated successfully.']);
            return;
        }

        $this->redirect('users');
    }

    private function payload(Request $request): array
    {
        return [
            'branch_id' => $this->nullableInt($request->input('branch_id')),
            'role_id' => (int) $request->input('role_id', 0),
            'first_name' => trim((string) $request->input('first_name', '')),
            'last_name' => trim((string) $request->input('last_name', '')),
            'username' => $this->normalizeUsername((string) $request->input('username', '')),
            'email' => strtolower(trim((string) $request->input('email', ''))),
            'phone' => trim((string) $request->input('phone', '')),
            'status' => (string) $request->input('status', 'active'),
        ];
    }

    private function validateForm(Request $request, array $payload, ?int $userId = null): array
    {
        $errors = Validator::validate(array_merge($request->all(), $payload), [
            'first_name' => 'required|min:2|max:100',
            'last_name' => 'required|min:2|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'nullable|max:50',
            'role_id' => 'required|integer',
            'branch_id' => 'required|integer',
            'status' => 'required|in:active,inactive',
        ]);

        $userModel = new User();
        if ($userModel->supportsUsername()) {
            $username = (string) ($payload['username'] ?? '');
            if ($username === '') {
                $errors['username'][] = 'A username is required.';
            } elseif (mb_strlen($username) < 3) {
                $errors['username'][] = 'Usernames must be at least 3 characters long.';
            } elseif (mb_strlen($username) > 100) {
                $errors['username'][] = 'Usernames must not exceed 100 characters.';
            } elseif (!$this->isValidUsername($username)) {
                $errors['username'][] = 'Use 3 or more characters without spaces or @ symbols.';
            } elseif ($userModel->usernameExists($username, $userId)) {
                $errors['username'][] = 'This username is already in use.';
            }
        }

        if ($userModel->emailExists($payload['email'], $userId)) {
            $errors['email'][] = 'This email address is already in use.';
        }
        $roleIds = array_map(static fn (array $role): int => (int) $role['id'], $this->availableRoles());
        if (!in_array((int) $payload['role_id'], $roleIds, true)) {
            $errors['role_id'][] = 'Select a valid role.';
        }

        $branchIds = array_map(static fn (array $branch): int => (int) $branch['id'], (new Branch())->active(current_company_id()));
        if (!in_array((int) $payload['branch_id'], $branchIds, true)) {
            $errors['branch_id'][] = 'Select a valid branch.';
        }

        $password = trim((string) $request->input('password', ''));
        $confirmation = trim((string) $request->input('password_confirmation', ''));

        if ($userId === null && $password === '') {
            $errors['password'][] = 'A password is required when creating a user.';
        }

        if ($password !== '' && mb_strlen($password) < 8) {
            $errors['password'][] = 'Passwords must be at least 8 characters long.';
        }

        if (($password !== '' || $confirmation !== '') && $password !== $confirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        return $errors;
    }

    private function normalizeUsername(string $value): string
    {
        return strtolower(trim($value));
    }

    private function isValidUsername(string $username): bool
    {
        return preg_match('/^[^\s@]{3,100}$/', $username) === 1;
    }

    private function availableRoles(): array
    {
        $roles = (new User())->roles();

        if (can('Super Admin')) {
            return $roles;
        }

        return array_values(array_filter($roles, static fn (array $role): bool => $role['name'] !== 'Super Admin'));
    }

    private function manageableRoles(): array
    {
        return $this->availableRoles();
    }

    private function findManageableRole(int $roleId): ?array
    {
        foreach ($this->manageableRoles() as $role) {
            if ((int) $role['id'] === $roleId) {
                return $role;
            }
        }

        return null;
    }

    private function groupPermissionsByModule(array $permissions): array
    {
        $grouped = [];

        foreach ($permissions as $permission) {
            $module = (string) ($permission['module'] ?? 'general');
            $grouped[$module] ??= [];
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }

    private function guardManagedUser(array $user): void
    {
        if (!can('Super Admin') && $user['role_name'] === 'Super Admin') {
            throw new HttpException(403, 'Only a super admin can manage super admin accounts.');
        }
    }

    private function guardAssignableRole(int $roleId): void
    {
        foreach ((new User())->roles() as $role) {
            if ((int) $role['id'] === $roleId && !can('Super Admin') && $role['name'] === 'Super Admin') {
                throw new HttpException(403, 'Only a super admin can assign the super admin role.');
            }
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
