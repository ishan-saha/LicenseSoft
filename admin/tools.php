<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/validator.php';
require_once __DIR__ . '/../includes/layout.php';

sendSecureHeaders();
requireLogin();

$db = getDB();
$message = '';
$messageType = '';
$newToolKey = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $message = 'Tool name is required.';
            $messageType = 'error';
        } else {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
            $slug = trim($slug, '-');

            if (!validateToolSlug($slug)) {
                $message = 'Generated slug is invalid. Use only letters, numbers, and hyphens.';
                $messageType = 'error';
            } else {
                // Check uniqueness
                $stmt = $db->prepare("SELECT id FROM tools WHERE slug = ?");
                $stmt->execute([$slug]);
                if ($stmt->fetch()) {
                    $message = 'A tool with this slug already exists.';
                    $messageType = 'error';
                } else {
                    $aesKey = generateAesKey();
                    $db->prepare("INSERT INTO tools (name, slug, aes_key) VALUES (?, ?, ?)")
                       ->execute([$name, $slug, $aesKey]);
                    $newToolKey = $aesKey;
                    $message = "Tool \"$name\" created. Copy the AES key below — it will NOT be shown again.";
                    $messageType = 'success';
                }
            }
        }
    } elseif ($action === 'delete') {
        $toolId = (int)($_POST['tool_id'] ?? 0);

        // Check for active licenses referencing this tool
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM license_tools lt JOIN licenses l ON lt.license_id = l.id WHERE lt.tool_id = ? AND l.status = 'active'");
        $stmt->execute([$toolId]);
        $activeCount = $stmt->fetch()['cnt'];

        if ($activeCount > 0) {
            $message = "Cannot delete: $activeCount active license(s) reference this tool.";
            $messageType = 'error';
        } else {
            $db->prepare("DELETE FROM tools WHERE id = ?")->execute([$toolId]);
            $message = 'Tool deleted.';
            $messageType = 'success';
        }
    }
    }
}

$tools = $db->query("SELECT * FROM tools ORDER BY created_at DESC")->fetchAll();

renderHeader('Tools', 'tools');
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<?php if ($newToolKey): ?>
    <div class="alert alert-warning key-display">
        <strong>AES-256 Key (copy now — shown only once):</strong>
        <code class="key-value" id="aes-key"><?= htmlspecialchars($newToolKey) ?></code>
        <button type="button" class="btn btn-sm" data-copy-target="aes-key">Copy</button>
    </div>
<?php endif; ?>

<div class="section">
    <h2>Add New Tool</h2>
    <form method="POST" class="inline-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add">
        <div class="form-group">
            <label for="name">Tool Name</label>
            <input type="text" id="name" name="name" placeholder="e.g. PortScanner" required maxlength="100">
        </div>
        <button type="submit" class="btn btn-primary">Add Tool</button>
    </form>
</div>

<h2>All Tools</h2>
<?php if (empty($tools)): ?>
    <p class="empty-state">No tools registered yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>AES Key</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($tools as $tool): ?>
        <tr>
            <td><?= htmlspecialchars($tool['name']) ?></td>
            <td class="mono"><?= htmlspecialchars($tool['slug']) ?></td>
            <td class="mono"><?= htmlspecialchars(substr($tool['aes_key'], 0, 8)) ?>...****</td>
            <td><?= htmlspecialchars($tool['created_at']) ?></td>
            <td>
                <form method="POST" class="inline-action" data-confirm="Delete this tool?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="tool_id" value="<?= $tool['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
