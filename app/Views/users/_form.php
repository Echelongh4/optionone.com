<div class="surface-card card-panel workspace-panel">
    <?php if ($errors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-0">
            <strong>Please fix the highlighted fields.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e($action) ?>" method="post" class="workspace-panel" data-loading-form data-ajax="true">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= e((string) $userData['id']) ?>">
        <?php endif; ?>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Account Profile</p>
                    <h3><?= e($isEdit ? 'Edit User' : 'Create User') ?></h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft"><?= e($isEdit ? 'Password reset optional' : 'Invite-ready account') ?></span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label">First name</label>
                    <input type="text" name="first_name" class="form-control" value="<?= e($userData['first_name'] ?? '') ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Last name</label>
                    <input type="text" name="last_name" class="form-control" value="<?= e($userData['last_name'] ?? '') ?>" required>
                </div>
                <?php if (!empty($supportsUsername)): ?>
                    <div class="field-stack">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" value="<?= e($userData['username'] ?? '') ?>" required>
                        <div class="small text-muted mt-1">Used for sign-in. Keep it unique and avoid spaces or the @ symbol.</div>
                    </div>
                <?php endif; ?>
                <div class="field-stack">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= e($userData['email'] ?? '') ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" value="<?= e($userData['phone'] ?? '') ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label">Role</label>
                    <select name="role_id" class="form-select" required>
                        <option value="">Select role</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= e((string) $role['id']) ?>" <?= (string) ($userData['role_id'] ?? '') === (string) $role['id'] ? 'selected' : '' ?>><?= e($role['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-select" required>
                        <option value="">Select branch</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= e((string) $branch['id']) ?>" <?= (string) ($userData['branch_id'] ?? '') === (string) $branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label"><?= e($isEdit ? 'New password' : 'Password') ?></label>
                    <input type="password" name="password" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                    <div class="small text-muted mt-1"><?= e($isEdit ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.') ?></div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Confirm password</label>
                    <input type="password" name="password_confirmation" class="form-control" <?= $isEdit ? '' : 'required' ?>>
                </div>
                <div class="field-stack">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="active" <?= ($userData['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($userData['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php if ($isEdit): ?>
                    <div class="field-stack">
                        <label class="form-label">Last Login</label>
                        <input type="text" class="form-control" value="<?= e((string) ($userData['last_login_at'] ?? 'Never')) ?>" disabled>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="workspace-panel__actions justify-content-end">
            <a href="<?= e(url('users')) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary"><?= e($buttonLabel) ?></button>
        </div>
    </form>
</div>
