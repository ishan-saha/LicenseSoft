<?php

function renderHeader(string $title, string $activePage = ''): void
{
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - LicenseSoft</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <?php if (isset($_SESSION['admin_id'])): ?>
    <nav class="sidebar">
        <div class="sidebar-header">
            <h2>LicenseSoft</h2>
        </div>
        <ul class="nav-links">
            <li class="<?= $activePage === 'dashboard' ? 'active' : '' ?>">
                <a href="/admin/dashboard.php">Dashboard</a>
            </li>
            <li class="<?= $activePage === 'tools' ? 'active' : '' ?>">
                <a href="/admin/tools.php">Tools</a>
            </li>
            <li class="<?= $activePage === 'customers' ? 'active' : '' ?>">
                <a href="/admin/customers.php">Customers</a>
            </li>
            <li class="<?= $activePage === 'licenses' ? 'active' : '' ?>">
                <a href="/admin/licenses.php">Licenses</a>
            </li>
            <li class="<?= $activePage === 'logs' ? 'active' : '' ?>">
                <a href="/admin/logs.php">Activity Logs</a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="/logout.php" class="btn btn-logout">Logout</a>
        </div>
    </nav>
    <main class="content">
        <div class="content-header">
            <h1><?= htmlspecialchars($title) ?></h1>
        </div>
        <div class="content-body">
    <?php endif; ?>
    <?php
}

function renderFooter(): void
{
    if (isset($_SESSION['admin_id'])):
    ?>
        </div>
    </main>
    <?php endif; ?>
    <script src="/assets/app.js"></script>
</body>
</html>
    <?php
}
