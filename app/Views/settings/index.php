<?php
$multiBranchEnabled = filter_var($settings['multi_branch_enabled'], FILTER_VALIDATE_BOOLEAN);
$logoPath = $settings['business_logo_path'] ?? '';
$thermalPrinterEnabled = filter_var($settings['thermal_printer_enabled'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$thermalPrinterConnector = (string) ($settings['thermal_printer_connector'] ?? 'windows');
$thermalPrinterConfigured = $thermalPrinterEnabled && (
    ($thermalPrinterConnector === 'network' && trim((string) ($settings['thermal_printer_host'] ?? '')) !== '')
    || ($thermalPrinterConnector !== 'network' && trim((string) ($settings['thermal_printer_target'] ?? '')) !== '')
);
$databaseRestoreEnabled = (bool) config('app.allow_database_restore', false);
?>
<div class="metric-grid" data-refresh-region="settings-summary">
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Branches</span><span>Configured</span></div>
        <h3><?= e((string) $summary['total_branches']) ?></h3>
        <div class="text-muted">Locations currently defined inside the system.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Active Branches</span><span>Live</span></div>
        <h3><?= e((string) $summary['active_branches']) ?></h3>
        <div class="text-muted">Branches available for assignment and operations.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Default Branch</span><span>Fallback</span></div>
        <h3><?= e($summary['default_branch']) ?></h3>
        <div class="text-muted">Used when a branch context is not explicitly set.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Multi-Branch</span><span>Mode</span></div>
        <h3><?= e($summary['multi_branch_enabled'] ? 'Enabled' : 'Disabled') ?></h3>
        <div class="text-muted">Controls whether the UI is configured for multi-location operation.</div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Tax Rules</span><span>Configured</span></div>
        <h3><?= e((string) $summary['total_taxes']) ?></h3>
        <div class="text-muted">Default: <?= e($settings['tax_default'] !== '' ? $settings['tax_default'] : 'None') ?></div>
    </section>
    <section class="metric-card card-panel">
        <div class="metric-meta"><span>Local Backups</span><span>Safety</span></div>
        <h3><?= e((string) $summary['backup_count']) ?></h3>
        <div class="text-muted">Latest snapshot: <?= e((string) $summary['latest_backup']) ?></div>
    </section>
</div>

<div class="surface-card card-panel workspace-panel mb-4" data-refresh-region="settings-general">
    <?php if ($generalErrors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-0">
            <strong>Please fix the settings form errors and try again.</strong>
        </div>
    <?php endif; ?>

    <form action="<?= e(url('settings/update')) ?>" method="post" enctype="multipart/form-data" class="workspace-panel" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-general"]'>
        <?= csrf_field() ?>
        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Business Profile</p>
                    <h3><i class="bi bi-gear me-2"></i>Business Settings</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Affects receipts, branding, and currency display</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label">Business name</label>
                    <input type="text" name="business_name" class="form-control" value="<?= e($settings['business_name']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Business email</label>
                    <input type="email" name="business_email" class="form-control" value="<?= e($settings['business_email']) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label">Business phone</label>
                    <input type="text" name="business_phone" class="form-control" value="<?= e($settings['business_phone']) ?>">
                </div>
                <div class="field-stack">
                    <label class="form-label">Currency</label>
                    <input type="text" name="currency" class="form-control" value="<?= e($settings['currency']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Barcode format</label>
                    <select name="barcode_format" class="form-select">
                        <?php foreach (['CODE128', 'CODE39', 'EAN13', 'UPC'] as $format): ?>
                            <option value="<?= e($format) ?>" <?= $settings['barcode_format'] === $format ? 'selected' : '' ?>><?= e($format) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Default tax</label>
                    <select name="tax_default" class="form-select">
                        <option value="">No default tax</option>
                        <?php foreach ($taxes as $tax): ?>
                            <?php $taxLabel = $tax['name'] . ' (' . number_format((float) $tax['rate'], 2) . '%)' . ((int) ($tax['inclusive'] ?? 0) === 1 ? ' Inclusive' : ' Exclusive'); ?>
                            <option value="<?= e($tax['name']) ?>" <?= $settings['tax_default'] === $tax['name'] ? 'selected' : '' ?>><?= e($taxLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label">Business address</label>
                    <textarea name="business_address" class="form-control" rows="3"><?= e($settings['business_address']) ?></textarea>
                </div>
                <div class="field-stack">
                    <label class="form-label">Receipt header</label>
                    <textarea name="receipt_header" class="form-control" rows="3"><?= e($settings['receipt_header']) ?></textarea>
                </div>
                <div class="field-stack">
                    <label class="form-label">Receipt footer</label>
                    <textarea name="receipt_footer" class="form-control" rows="3"><?= e($settings['receipt_footer']) ?></textarea>
                </div>
                <div class="field-stack">
                    <label class="form-label">Business logo</label>
                    <input type="file" name="business_logo" class="form-control" accept="image/png,image/jpeg,image/webp">
                    <?php if ($logoPath !== ''): ?>
                        <div class="media-preview">
                            <img src="<?= e(url($logoPath)) ?>" alt="Business logo" class="img-fluid">
                        </div>
                    <?php endif; ?>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="multi_branch_enabled" id="multi_branch_enabled" value="1" <?= $multiBranchEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="multi_branch_enabled">Enable multi-branch workflows</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Operational Alerts</p>
                    <h3><i class="bi bi-bell me-2"></i>Email Delivery Rules</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Controls low-stock and daily-summary mail delivery</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email_low_stock_alerts_enabled" id="email_low_stock_alerts_enabled" value="1" <?= filter_var($settings['email_low_stock_alerts_enabled'], FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="email_low_stock_alerts_enabled">Send low-stock email alerts</label>
                    </div>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="email_daily_summary_enabled" id="email_daily_summary_enabled" value="1" <?= filter_var($settings['email_daily_summary_enabled'], FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="email_daily_summary_enabled">Allow daily summary emails</label>
                    </div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Recipient scope</label>
                    <select name="ops_email_recipient_scope" class="form-select">
                        <option value="business" <?= $settings['ops_email_recipient_scope'] === 'business' ? 'selected' : '' ?>>Business email only</option>
                        <option value="team" <?= $settings['ops_email_recipient_scope'] === 'team' ? 'selected' : '' ?>>Admin and manager team only</option>
                        <option value="business_and_team" <?= $settings['ops_email_recipient_scope'] === 'business_and_team' ? 'selected' : '' ?>>Business email and team</option>
                    </select>
                </div>
                <div class="field-stack field-span-full">
                    <label class="form-label">Additional recipients</label>
                    <textarea name="ops_email_additional_recipients" class="form-control" rows="3" placeholder="ops@example.com, owner@example.com"><?= e($settings['ops_email_additional_recipients']) ?></textarea>
                    <div class="small text-muted mt-1">Optional comma-separated email addresses for extra operations recipients.</div>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">SMTP Delivery</p>
                    <h3><i class="bi bi-envelope-at me-2"></i>Mail Server</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">Used for password reset, low-stock alerts, summaries, and test mail</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="field-stack">
                    <label class="form-label">SMTP host</label>
                    <input type="text" name="mail_host" class="form-control" value="<?= e($settings['mail_host']) ?>" placeholder="smtp.example.com">
                </div>
                <div class="field-stack">
                    <label class="form-label">SMTP port</label>
                    <input type="number" name="mail_port" class="form-control" min="1" max="65535" value="<?= e($settings['mail_port']) ?>" placeholder="587">
                </div>
                <div class="field-stack">
                    <label class="form-label">Encryption</label>
                    <select name="mail_encryption" class="form-select">
                        <option value="tls" <?= $settings['mail_encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                        <option value="ssl" <?= $settings['mail_encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS (465)</option>
                        <option value="none" <?= $settings['mail_encryption'] === 'none' ? 'selected' : '' ?>>No encryption</option>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">SMTP username</label>
                    <input type="text" name="mail_username" class="form-control" value="<?= e($settings['mail_username']) ?>" placeholder="username@example.com">
                    <div class="small text-muted mt-1">Leave blank only if your mail server allows sending without SMTP authentication.</div>
                </div>
                <div class="field-stack">
                    <label class="form-label">SMTP password</label>
                    <input type="password" name="mail_password" class="form-control" value="" placeholder="Leave blank to keep the current password" autocomplete="new-password">
                    <div class="small text-muted mt-1">Leave this blank to keep the saved password. Clear the username to disable SMTP authentication.</div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Sender email</label>
                    <input type="email" name="mail_from_address" class="form-control" value="<?= e($settings['mail_from_address']) ?>" placeholder="pos@example.com">
                </div>
                <div class="field-stack">
                    <label class="form-label">Sender name</label>
                    <input type="text" name="mail_from_name" class="form-control" value="<?= e($settings['mail_from_name']) ?>" placeholder="Your business name">
                </div>
                <div class="field-stack field-span-full">
                    <div class="alert alert-info rounded-4 mb-0">
                        Save these values once, then use the mail verification card below to confirm that password reset emails and operational alerts can be delivered.
                    </div>
                </div>
            </div>
        </section>

        <section class="form-section-card">
            <div class="workspace-panel__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Receipt Printer</p>
                    <h3><i class="bi bi-printer me-2"></i>Thermal Printing</h3>
                </div>
                <div class="workspace-panel__meta">
                    <span class="badge-soft">ESC/POS via Windows share, TCP 9100, or file connector</span>
                </div>
            </div>
            <div class="form-grid">
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="thermal_printer_enabled" id="thermal_printer_enabled" value="1" <?= $thermalPrinterEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label" for="thermal_printer_enabled">Enable direct thermal printing</label>
                    </div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Connector</label>
                    <select name="thermal_printer_connector" class="form-select">
                        <option value="windows" <?= $thermalPrinterConnector === 'windows' ? 'selected' : '' ?>>Windows printer or SMB share</option>
                        <option value="network" <?= $thermalPrinterConnector === 'network' ? 'selected' : '' ?>>Network printer (TCP 9100)</option>
                        <option value="file" <?= $thermalPrinterConnector === 'file' ? 'selected' : '' ?>>Local device or file path</option>
                    </select>
                </div>
                <div class="field-stack">
                    <label class="form-label">Printer target</label>
                    <input type="text" name="thermal_printer_target" class="form-control" value="<?= e($settings['thermal_printer_target']) ?>" placeholder="POS-80C or smb://workstation/POS-80C">
                    <div class="small text-muted mt-1">Used for Windows printer names, shared printers, and file connector paths.</div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Network host</label>
                    <input type="text" name="thermal_printer_host" class="form-control" value="<?= e($settings['thermal_printer_host']) ?>" placeholder="192.168.1.50">
                    <div class="small text-muted mt-1">Used only for network printers.</div>
                </div>
                <div class="field-stack">
                    <label class="form-label">Network port</label>
                    <input type="number" name="thermal_printer_port" class="form-control" min="1" max="65535" value="<?= e($settings['thermal_printer_port']) ?>" placeholder="9100">
                </div>
                <div class="field-stack field-span-full">
                    <div class="alert alert-info rounded-4 mb-0">
                        Windows connector accepts local printer names like <code>POS-80C</code> or shared targets like <code>smb://cashier-pc/POS-80C</code>. Network printers usually listen on port <code>9100</code>.
                    </div>
                </div>
            </div>
        </section>

        <div class="workspace-panel__actions justify-content-end">
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </div>
    </form>
</div>

<section class="surface-card card-panel workspace-panel mb-4" data-refresh-region="settings-taxes">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Tax Configuration</p>
            <h3><i class="bi bi-percent me-2"></i>Taxes</h3>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Existing Taxes</p>
                    <h4>Manage tax rules</h4>
                </div>
            </div>
            <div class="stack-grid">
                <?php if ($taxes === []): ?>
                    <div class="empty-state">No tax rules have been created yet.</div>
                <?php else: ?>
                    <?php foreach ($taxes as $tax): ?>
                        <form action="<?= e(url('settings/taxes/update')) ?>" method="post" class="record-card" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-taxes"]'>
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= e((string) $tax['id']) ?>">
                            <?php if ($editTaxId !== null && (int) $editTaxId === (int) $tax['id'] && $taxEditErrors !== []): ?>
                                <div class="alert alert-danger rounded-4 mb-0">
                                    <strong>Tax update failed.</strong>
                                </div>
                            <?php endif; ?>
                            <div class="record-card__header">
                                <div class="workspace-panel__intro">
                                    <h4><?= e($tax['name']) ?></h4>
                                    <div class="small text-muted"><?= e((string) $tax['product_count']) ?> products assigned</div>
                                </div>
                                <div class="record-card__meta d-flex gap-2 flex-wrap">
                                    <?php if ($settings['tax_default'] === $tax['name']): ?>
                                        <span class="badge-soft">Default</span>
                                    <?php endif; ?>
                                    <span class="badge-soft"><?= e((int) $tax['inclusive'] === 1 ? 'Inclusive' : 'Exclusive') ?></span>
                                </div>
                            </div>
                            <div class="form-grid">
                                <div class="field-stack">
                                    <label class="form-label">Name</label>
                                    <input type="text" name="name" class="form-control" value="<?= e($tax['name']) ?>" required>
                                </div>
                                <div class="field-stack">
                                    <label class="form-label">Rate (%)</label>
                                    <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" value="<?= e(number_format((float) $tax['rate'], 2, '.', '')) ?>" required>
                                </div>
                                <div class="check-card">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="inclusive" id="tax_inclusive_<?= e((string) $tax['id']) ?>" value="1" <?= (int) $tax['inclusive'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tax_inclusive_<?= e((string) $tax['id']) ?>">Inclusive tax</label>
                                    </div>
                                </div>
                                <div class="check-card">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="set_as_default" id="tax_default_<?= e((string) $tax['id']) ?>" value="1" <?= $settings['tax_default'] === $tax['name'] ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="tax_default_<?= e((string) $tax['id']) ?>">Set as default</label>
                                    </div>
                                </div>
                            </div>
                            <div class="workspace-panel__actions justify-content-between flex-wrap gap-2">
                                <button
                                    type="submit"
                                    class="btn btn-outline-danger"
                                    formaction="<?= e(url('settings/taxes/delete')) ?>"
                                    formmethod="post"
                                    formnovalidate
                                    <?= (int) $tax['product_count'] > 0 ? 'disabled' : '' ?>
                                    data-confirm-action
                                    data-confirm-title="Delete this tax?"
                                    data-confirm-text="This tax will be removed permanently."
                                    data-confirm-button="Delete Tax"
                                >
                                    Delete
                                </button>
                                <button type="submit" class="btn btn-outline-primary">Save Tax</button>
                            </div>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>

        <section class="utility-card">
            <?php if ($taxCreateErrors !== []): ?>
                <div class="alert alert-danger rounded-4 mb-3">
                    <strong>Please fix the tax form errors and try again.</strong>
                </div>
            <?php endif; ?>
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">New Tax</p>
                    <h4>Create a tax rule</h4>
                </div>
            </div>
            <form action="<?= e(url('settings/taxes/store')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-taxes"]'>
                <?= csrf_field() ?>
                <div class="field-stack">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="<?= e($taxForm['name']) ?>" required>
                </div>
                <div class="field-stack">
                    <label class="form-label">Rate (%)</label>
                    <input type="number" step="0.01" min="0" max="100" name="rate" class="form-control" value="<?= e((string) $taxForm['rate']) ?>" required>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="inclusive" id="new_tax_inclusive" value="1" <?= (int) $taxForm['inclusive'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="new_tax_inclusive">Inclusive tax</label>
                    </div>
                </div>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="set_as_default" id="new_tax_default" value="1" <?= (int) $taxForm['set_as_default'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="new_tax_default">Set as default</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create Tax</button>
            </form>
        </section>
    </div>
</section>

<section class="surface-card card-panel workspace-panel mb-4" data-refresh-region="settings-backups">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Business Continuity</p>
            <h3><i class="bi bi-hdd me-2"></i>Backups</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft">SQL snapshots are stored locally in <code>storage/backups</code></span>
            <span class="badge-soft"><?= e($databaseRestoreEnabled ? 'Restore enabled' : 'Restore disabled') ?></span>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Create Backup</p>
                    <h4>Generate a SQL snapshot</h4>
                </div>
            </div>
            <form action="<?= e(url('settings/backups/create')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-backups"]'>
                <?= csrf_field() ?>
                <div class="check-card">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="schema_only" id="schema_only" value="1">
                        <label class="form-check-label" for="schema_only">Create schema-only backup</label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Create and Download Backup</button>
            </form>

            <div class="utility-card__header mt-2">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Backup History</p>
                    <h4>Recent local snapshots</h4>
                </div>
            </div>
            <?php if ($backups === []): ?>
                <div class="empty-state">No local backups have been created yet.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle data-table">
                        <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Size</th>
                            <th>Modified</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($backups as $backup): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($backup['name']) ?></td>
                                <td><?= e(format_file_size((int) $backup['size'])) ?></td>
                                <td><?= e((string) $backup['modified_at']) ?></td>
                                <td class="text-end">
                                    <a href="<?= e(url('settings/backups/download?file=' . rawurlencode($backup['name']))) ?>" class="btn btn-sm btn-outline-secondary" data-download="true" data-no-loader="true">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Restore Database</p>
                    <h4>Import a SQL backup</h4>
                </div>
            </div>
            <?php if (!$databaseRestoreEnabled): ?>
                <div class="alert alert-secondary rounded-4 mb-0">
                    Database restore is disabled. Set <code>ALLOW_DB_RESTORE=true</code> in <code>.env</code> only for a controlled maintenance window.
                </div>
            <?php else: ?>
                <form action="<?= e(url('settings/backups/restore')) ?>" method="post" enctype="multipart/form-data" class="stack-grid" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-backups"]'>
                    <?= csrf_field() ?>
                    <div class="field-stack">
                        <label class="form-label">SQL backup file</label>
                        <input type="file" name="backup_file" class="form-control" accept=".sql,text/plain,application/sql" required>
                        <div class="small text-muted mt-1">Max 20MB.</div>
                    </div>
                    <div class="alert alert-warning rounded-4 mb-0">
                        Restoring will replace current database contents. NovaPOS creates an automatic pre-restore backup before applying the uploaded SQL.
                    </div>
                    <button type="submit" class="btn btn-outline-danger" data-confirm-action data-confirm-title="Restore this database backup?" data-confirm-text="Current data will be replaced. A pre-restore backup will be created automatically first." data-confirm-button="Restore Database">Restore Backup</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</section>

<div data-refresh-region="settings-branches">
<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">System Health</p>
            <h3><i class="bi bi-activity me-2"></i>Diagnostics</h3>
        </div>
        <div class="workspace-panel__meta">
            <span class="badge-soft"><?= e((string) $systemHealth['healthy']) ?> healthy</span>
            <span class="badge-soft"><?= e((string) $systemHealth['warning']) ?> warning</span>
            <span class="badge-soft"><?= e((string) $systemHealth['critical']) ?> critical</span>
        </div>
    </div>
    <div class="content-grid">
        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Readiness Checks</p>
                    <h4>Runtime status</h4>
                </div>
            </div>
            <div class="stack-grid">
                <?php foreach ($systemHealth['checks'] as $check): ?>
                    <article class="record-card health-check health-check--<?= e($check['status']) ?>">
                        <div class="record-card__header">
                            <div class="workspace-panel__intro">
                                <h4><i class="bi <?= e($check['icon']) ?> me-2"></i><?= e($check['label']) ?></h4>
                                <div class="small text-muted"><?= e($check['detail']) ?></div>
                            </div>
                            <div class="record-card__meta">
                                <span class="badge-soft health-check__status health-check__status--<?= e($check['status']) ?>">
                                    <?= e(ucfirst($check['status'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="fw-semibold"><?= e($check['value']) ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Mail Verification</p>
                    <h4>Send a test message</h4>
                </div>
            </div>
            <form action="<?= e(url('settings/mail/test')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true">
                <?= csrf_field() ?>
                <div class="field-stack">
                    <label class="form-label">Recipient email</label>
                    <input type="email" name="recipient_email" class="form-control" value="<?= e($mailTestDefaults['recipient_email']) ?>" required>
                    <div class="small text-muted mt-1">Use this to verify SMTP host, sender, and credential configuration safely.</div>
                </div>
                <div class="alert alert-info rounded-4 mb-0">
                    Test delivery uses the configured sender address and does not alter customer or transactional records.
                </div>
                <button type="submit" class="btn btn-outline-primary">Send Test Email</button>
            </form>
        </section>

        <section class="utility-card">
            <div class="utility-card__header">
                <div class="workspace-panel__intro">
                    <p class="eyebrow mb-1">Printer Verification</p>
                    <h4>Send a test page</h4>
                </div>
            </div>
            <form action="<?= e(url('settings/printer/test')) ?>" method="post" class="stack-grid" data-loading-form data-ajax="true">
                <?= csrf_field() ?>
                <div class="small text-muted">
                    Current target: <strong><?= e($thermalPrinterEnabled ? strtoupper($thermalPrinterConnector) : 'DISABLED') ?></strong>
                    <?php if ($thermalPrinterConnector === 'network'): ?>
                        <?= e(($settings['thermal_printer_host'] ?: 'host required') . ':' . ($settings['thermal_printer_port'] ?: '9100')) ?>
                    <?php else: ?>
                        <?= e($settings['thermal_printer_target'] !== '' ? $settings['thermal_printer_target'] : 'target required') ?>
                    <?php endif; ?>
                </div>
                <div class="alert alert-info rounded-4 mb-0">
                    Use this after saving printer settings to verify the connected receipt printer can receive ESC/POS jobs.
                </div>
                <button type="submit" class="btn btn-outline-primary" <?= $thermalPrinterConfigured ? '' : 'disabled' ?>>Send Test Print</button>
            </form>
        </section>
    </div>
</section>

<section class="surface-card card-panel workspace-panel mb-4">
    <div class="workspace-panel__header">
        <div class="workspace-panel__intro">
            <p class="eyebrow mb-1">Branch Configuration</p>
            <h3><i class="bi bi-house-door me-2"></i>Branches</h3>
        </div>
    </div>
    <div class="stack-grid">
        <?php foreach ($branches as $branch): ?>
            <form action="<?= e(url('settings/branches/update')) ?>" method="post" class="record-card" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-branches"]'>
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= e((string) $branch['id']) ?>">
                <?php if ($editBranchId !== null && (int) $editBranchId === (int) $branch['id'] && $branchEditErrors !== []): ?>
                    <div class="alert alert-danger rounded-4 mb-0">
                        <strong>Branch update failed.</strong>
                    </div>
                <?php endif; ?>
                <div class="record-card__header">
                    <div class="workspace-panel__intro">
                        <h4><?= e($branch['name']) ?></h4>
                        <div class="inline-note"><?= e((string) $branch['total_users']) ?> users | <?= e((string) $branch['total_products']) ?> products</div>
                    </div>
                    <div class="record-card__meta">
                        <span class="badge-soft"><?= e((int) $branch['is_default'] === 1 ? 'Default Branch' : ucfirst($branch['status'])) ?></span>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="field-stack">
                        <label class="form-label">Branch name</label>
                        <input type="text" name="name" class="form-control" value="<?= e($branch['name']) ?>" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label">Code</label>
                        <input type="text" name="code" class="form-control" value="<?= e($branch['code']) ?>" required>
                    </div>
                    <div class="field-stack">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($branch['phone'] ?? '') ?>">
                    </div>
                    <div class="field-stack">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($branch['email'] ?? '') ?>">
                    </div>
                    <div class="field-stack field-span-full">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($branch['address'] ?? '') ?></textarea>
                    </div>
                    <div class="field-stack">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $branch['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $branch['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="check-card">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_default" id="branch_default_<?= e((string) $branch['id']) ?>" value="1" <?= (int) $branch['is_default'] === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="branch_default_<?= e((string) $branch['id']) ?>">Set as default branch</label>
                        </div>
                    </div>
                </div>
                <div class="workspace-panel__actions justify-content-end">
                    <button type="submit" class="btn btn-outline-primary">Save Branch</button>
                </div>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="surface-card card-panel workspace-panel">
    <?php if ($branchCreateErrors !== []): ?>
        <div class="alert alert-danger rounded-4 mb-0">
            <strong>Please fix the new branch form errors and try again.</strong>
        </div>
    <?php endif; ?>
    <form action="<?= e(url('settings/branches/store')) ?>" method="post" class="workspace-panel" data-loading-form data-ajax="true" data-refresh-target='[data-refresh-region="settings-summary"],[data-refresh-region="settings-branches"]'>
        <?= csrf_field() ?>
        <div class="workspace-panel__header">
            <div class="workspace-panel__intro">
                <p class="eyebrow mb-1">New Location</p>
                <h3><i class="bi bi-plus-lg me-2"></i>New Branch</h3>
            </div>
        </div>
        <div class="form-grid">
            <div class="field-stack">
                <label class="form-label">Branch name</label>
                <input type="text" name="name" class="form-control" value="<?= e($branchForm['name']) ?>" required>
            </div>
            <div class="field-stack">
                <label class="form-label">Code</label>
                <input type="text" name="code" class="form-control" value="<?= e($branchForm['code']) ?>" required>
            </div>
            <div class="field-stack">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($branchForm['phone']) ?>">
            </div>
            <div class="field-stack">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= e($branchForm['email']) ?>">
            </div>
            <div class="field-stack field-span-full">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="2"><?= e($branchForm['address']) ?></textarea>
            </div>
            <div class="field-stack">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $branchForm['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $branchForm['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="check-card">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="is_default" id="new_branch_default" value="1" <?= (int) $branchForm['is_default'] === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="new_branch_default">Set as default branch</label>
                </div>
            </div>
        </div>
        <div class="workspace-panel__actions justify-content-end">
            <button type="submit" class="btn btn-primary">Create Branch</button>
        </div>
    </form>
</section>
</div>
