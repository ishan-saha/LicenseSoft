<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/layout.php';

sendSecureHeaders();
requireLogin();

$db = getDB();

// Active license count
$activeLicenses = $db->query("SELECT COUNT(*) as cnt FROM licenses WHERE status = 'active'")->fetch()['cnt'];

// Licenses expiring within 30 days
$expiringSoon = $db->query("SELECT COUNT(*) as cnt FROM licenses WHERE status = 'active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)")->fetch()['cnt'];

// Total customers
$totalCustomers = $db->query("SELECT COUNT(*) as cnt FROM customers")->fetch()['cnt'];

// Total tools
$totalTools = $db->query("SELECT COUNT(*) as cnt FROM tools")->fetch()['cnt'];

// Recent activity
$recentActivity = $db->query("SELECT al.*, l.license_key FROM activity_logs al LEFT JOIN licenses l ON al.license_id = l.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();

renderHeader('Dashboard', 'dashboard');
?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-number"><?= $activeLicenses ?></div>
        <div class="stat-label">Active Licenses</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-number"><?= $expiringSoon ?></div>
        <div class="stat-label">Expiring in 30 Days</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $totalCustomers ?></div>
        <div class="stat-label">Customers</div>
    </div>
    <div class="stat-card">
        <div class="stat-number"><?= $totalTools ?></div>
        <div class="stat-label">Tools</div>
    </div>
</div>

<h2>Recent Activity</h2>
<?php if (empty($recentActivity)): ?>
    <p class="empty-state">No activity recorded yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Time</th>
            <th>Tool</th>
            <th>Installation ID</th>
            <th>Action</th>
            <th>License Key</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($recentActivity as $log): ?>
        <tr>
            <td><?= htmlspecialchars($log['created_at']) ?></td>
            <td><?= htmlspecialchars($log['tool_slug']) ?></td>
            <td><?= htmlspecialchars($log['installation_id']) ?></td>
            <td><span class="badge badge-<?= $log['action'] ?>"><?= htmlspecialchars($log['action']) ?></span></td>
            <td class="mono"><?= htmlspecialchars(substr($log['license_key'] ?? '', 0, 8)) ?>...</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
