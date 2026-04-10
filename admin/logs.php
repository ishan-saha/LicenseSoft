<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/layout.php';

sendSecureHeaders();
requireLogin();

$db = getDB();

// Build filter query
$where = [];
$params = [];

$filterTool = $_GET['tool'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterLicense = $_GET['license'] ?? '';

if ($filterTool !== '') {
    $where[] = "al.tool_slug = ?";
    $params[] = $filterTool;
}
if ($filterAction !== '') {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}
if ($filterDateFrom !== '') {
    $where[] = "al.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo !== '') {
    $where[] = "al.created_at <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
}
if ($filterLicense !== '') {
    $where[] = "l.license_key LIKE ?";
    $params[] = $filterLicense . '%';
}

$sql = "SELECT al.*, l.license_key FROM activity_logs al LEFT JOIN licenses l ON al.license_id = l.id";
if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY al.created_at DESC LIMIT 200";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get distinct tool slugs for filter dropdown
$toolSlugs = $db->query("SELECT DISTINCT tool_slug FROM activity_logs ORDER BY tool_slug")->fetchAll(PDO::FETCH_COLUMN);

renderHeader('Activity Logs', 'logs');
?>

<div class="section">
    <h2>Filters</h2>
    <form method="GET" class="filter-form">
        <div class="form-row">
            <div class="form-group">
                <label for="license">License Key (prefix)</label>
                <input type="text" id="license" name="license" placeholder="e.g. a3f9..."
                       value="<?= htmlspecialchars($filterLicense) ?>">
            </div>
            <div class="form-group">
                <label for="tool">Tool</label>
                <select id="tool" name="tool">
                    <option value="">All</option>
                    <?php foreach ($toolSlugs as $slug): ?>
                        <option value="<?= htmlspecialchars($slug) ?>" <?= $filterTool === $slug ? 'selected' : '' ?>>
                            <?= htmlspecialchars($slug) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="action">Action</label>
                <select id="action" name="action">
                    <option value="">All</option>
                    <option value="activated" <?= $filterAction === 'activated' ? 'selected' : '' ?>>Activated</option>
                    <option value="verified" <?= $filterAction === 'verified' ? 'selected' : '' ?>>Verified</option>
                    <option value="failed" <?= $filterAction === 'failed' ? 'selected' : '' ?>>Failed</option>
                    <option value="revoked" <?= $filterAction === 'revoked' ? 'selected' : '' ?>>Revoked</option>
                </select>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="date_from">From</label>
                <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="form-group">
                <label for="date_to">To</label>
                <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="form-group" style="align-self: end">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/admin/logs.php" class="btn btn-secondary">Clear</a>
            </div>
        </div>
    </form>
</div>

<h2>Logs (<?= count($logs) ?> entries, max 200)</h2>
<?php if (empty($logs)): ?>
    <p class="empty-state">No activity logs found.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Timestamp</th>
            <th>Tool</th>
            <th>Installation ID</th>
            <th>Action</th>
            <th>License Key</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td class="mono"><?= htmlspecialchars($log['tool_slug']) ?></td>
            <td class="mono"><?= htmlspecialchars($log['installation_id']) ?></td>
            <td><span class="badge badge-<?= $log['action'] ?>"><?= htmlspecialchars($log['action']) ?></span></td>
            <td class="mono"><?= htmlspecialchars(substr($log['license_key'] ?? '', 0, 12)) ?>...</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
