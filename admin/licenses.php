<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/layout.php';

sendSecureHeaders();
requireLogin();

$db = getDB();
$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $customerId = (int)($_POST['customer_id'] ?? 0);
        $toolIds = $_POST['tool_ids'] ?? [];
        $expiresAt = $_POST['expires_at'] ?? '';

        if ($customerId <= 0 || empty($toolIds) || $expiresAt === '') {
            $message = 'Customer, at least one tool, and expiry date are required.';
            $messageType = 'error';
        } else {
            $licenseKey = bin2hex(random_bytes(20));

            $db->prepare("INSERT INTO licenses (customer_id, license_key, expires_at) VALUES (?, ?, ?)")
               ->execute([$customerId, $licenseKey, $expiresAt . ' 23:59:59']);

            $licenseId = $db->lastInsertId();

            $stmt = $db->prepare("INSERT INTO license_tools (license_id, tool_id) VALUES (?, ?)");
            foreach ($toolIds as $toolId) {
                $stmt->execute([$licenseId, (int)$toolId]);
            }

            $message = 'License created. Key: ' . $licenseKey;
            $messageType = 'success';
        }
    } elseif ($action === 'revoke') {
        $licenseId = (int)($_POST['license_id'] ?? 0);
        $db->prepare("UPDATE licenses SET status = 'revoked' WHERE id = ?")->execute([$licenseId]);

        // Log revocation
        $stmt = $db->prepare("SELECT installation_id FROM licenses WHERE id = ?");
        $stmt->execute([$licenseId]);
        $lic = $stmt->fetch();
        $db->prepare("INSERT INTO activity_logs (license_id, installation_id, tool_slug, action) VALUES (?, ?, 'admin', 'revoked')")
           ->execute([$licenseId, $lic['installation_id'] ?? 'N/A']);

        $message = 'License revoked.';
        $messageType = 'success';
    } elseif ($action === 'extend') {
        $licenseId = (int)($_POST['license_id'] ?? 0);
        $newExpiry = $_POST['new_expires_at'] ?? '';

        if ($newExpiry === '') {
            $message = 'New expiry date is required.';
            $messageType = 'error';
        } else {
            $db->prepare("UPDATE licenses SET expires_at = ? WHERE id = ?")
               ->execute([$newExpiry . ' 23:59:59', $licenseId]);
            $message = 'Expiry date updated.';
            $messageType = 'success';
        }
    } elseif ($action === 'transfer') {
        $licenseId = (int)($_POST['license_id'] ?? 0);
        $db->prepare("UPDATE licenses SET installation_id = NULL WHERE id = ?")->execute([$licenseId]);
        $message = 'Installation binding cleared. License will re-activate on next verification call.';
        $messageType = 'success';
    }
    }
}

// View single license
$viewLicense = null;
$viewTools = [];
if (isset($_GET['view'])) {
    $stmt = $db->prepare("SELECT l.*, c.name as customer_name, c.email as customer_email, c.organisation_name FROM licenses l JOIN customers c ON l.customer_id = c.id WHERE l.id = ?");
    $stmt->execute([(int)$_GET['view']]);
    $viewLicense = $stmt->fetch();

    if ($viewLicense) {
        $stmt = $db->prepare("SELECT t.name, t.slug FROM license_tools lt JOIN tools t ON lt.tool_id = t.id WHERE lt.license_id = ?");
        $stmt->execute([$viewLicense['id']]);
        $viewTools = $stmt->fetchAll();
    }
}

// Fetch data for forms
$customers = $db->query("SELECT id, name, organisation_name FROM customers ORDER BY name")->fetchAll();
$tools = $db->query("SELECT id, name, slug FROM tools ORDER BY name")->fetchAll();
$licenses = $db->query("SELECT l.*, c.name as customer_name, c.organisation_name FROM licenses l JOIN customers c ON l.customer_id = c.id ORDER BY l.created_at DESC")->fetchAll();

// Fetch tools for each license (for list display)
$licenseToolsMap = [];
if (!empty($licenses)) {
    $allLicenseIds = array_column($licenses, 'id');
    $placeholders = implode(',', array_fill(0, count($allLicenseIds), '?'));
    $stmt = $db->prepare("SELECT lt.license_id, GROUP_CONCAT(t.name SEPARATOR ', ') as tool_names FROM license_tools lt JOIN tools t ON lt.tool_id = t.id WHERE lt.license_id IN ($placeholders) GROUP BY lt.license_id");
    $stmt->execute($allLicenseIds);
    foreach ($stmt->fetchAll() as $row) {
        $licenseToolsMap[$row['license_id']] = $row['tool_names'];
    }
}

renderHeader('Licenses', 'licenses');
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($viewLicense): ?>
<div class="section license-detail">
    <h2>License Detail</h2>
    <div class="detail-grid">
        <div><strong>License Key:</strong></div>
        <div class="mono">
            <code id="license-key"><?= htmlspecialchars($viewLicense['license_key']) ?></code>
            <button type="button" class="btn btn-sm" data-copy-target="license-key">Copy</button>
        </div>
        <div><strong>Customer:</strong></div>
        <div><?= htmlspecialchars($viewLicense['customer_name']) ?> (<?= htmlspecialchars($viewLicense['organisation_name']) ?>)</div>
        <div><strong>Status:</strong></div>
        <div><span class="badge badge-<?= $viewLicense['status'] ?>"><?= htmlspecialchars($viewLicense['status']) ?></span></div>
        <div><strong>Expires:</strong></div>
        <div><?= htmlspecialchars($viewLicense['expires_at']) ?></div>
        <div><strong>Installation ID:</strong></div>
        <div class="mono"><?= htmlspecialchars($viewLicense['installation_id'] ?? 'Not bound yet') ?></div>
        <div><strong>Tools:</strong></div>
        <div>
            <?php foreach ($viewTools as $t): ?>
                <span class="badge"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['slug']) ?>)</span>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="action-buttons">
        <?php if ($viewLicense['status'] === 'active'): ?>
        <form method="POST" class="inline-action" data-confirm="Revoke this license?">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
            <button type="submit" class="btn btn-danger">Revoke</button>
        </form>

        <form method="POST" class="inline-form">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="extend">
            <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
            <input type="date" name="new_expires_at" required>
            <button type="submit" class="btn btn-primary">Extend Expiry</button>
        </form>

        <?php if ($viewLicense['installation_id']): ?>
        <form method="POST" class="inline-action" data-confirm="Clear installation binding? The license will re-activate on the next server that calls in.">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="transfer">
            <input type="hidden" name="license_id" value="<?= $viewLicense['id'] ?>">
            <button type="submit" class="btn btn-secondary">Transfer (Clear Binding)</button>
        </form>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <a href="/admin/licenses.php" class="btn btn-secondary">Back to Licenses</a>
</div>
<?php else: ?>

<div class="section">
    <h2>Create New License</h2>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-row">
            <div class="form-group">
                <label for="customer_id">Customer</label>
                <select id="customer_id" name="customer_id" required>
                    <option value="">-- Select Customer --</option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['organisation_name']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="expires_at">Expiry Date</label>
                <input type="date" id="expires_at" name="expires_at" required>
            </div>
        </div>
        <div class="form-group">
            <label>Tools (select multiple)</label>
            <div class="checkbox-group">
                <?php foreach ($tools as $t): ?>
                <label class="checkbox-label">
                    <input type="checkbox" name="tool_ids[]" value="<?= $t['id'] ?>">
                    <?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['slug']) ?>)
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create License</button>
    </form>
</div>

<h2>All Licenses</h2>
<?php if (empty($licenses)): ?>
    <p class="empty-state">No licenses created yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>License Key</th>
            <th>Customer</th>
            <th>Tools</th>
            <th>Expires</th>
            <th>Status</th>
            <th>Installation ID</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($licenses as $lic): ?>
        <tr>
            <td class="mono"><?= htmlspecialchars(substr($lic['license_key'], 0, 12)) ?>...</td>
            <td><?= htmlspecialchars($lic['customer_name']) ?></td>
            <td><?= htmlspecialchars($licenseToolsMap[$lic['id']] ?? '-') ?></td>
            <td><?= htmlspecialchars($lic['expires_at']) ?></td>
            <td><span class="badge badge-<?= $lic['status'] ?>"><?= htmlspecialchars($lic['status']) ?></span></td>
            <td class="mono"><?= htmlspecialchars($lic['installation_id'] ?? 'Unbound') ?></td>
            <td>
                <a href="?view=<?= $lic['id'] ?>" class="btn btn-sm">View</a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php endif; ?>

<?php renderFooter(); ?>
