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
$editCustomer = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken()) {
        $message = 'Invalid request. Please try again.';
        $messageType = 'error';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $orgName = trim($_POST['organisation_name'] ?? '');
        $orgDomain = trim($_POST['org_domain'] ?? '');

        if ($name === '' || $email === '' || $orgName === '') {
            $message = 'Name, email, and organisation name are required.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address.';
            $messageType = 'error';
        } else {
            $db->prepare("INSERT INTO customers (name, email, organisation_name, org_domain) VALUES (?, ?, ?, ?)")
               ->execute([$name, $email, $orgName, $orgDomain]);
            $message = 'Customer added.';
            $messageType = 'success';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['customer_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $orgName = trim($_POST['organisation_name'] ?? '');
        $orgDomain = trim($_POST['org_domain'] ?? '');

        if ($name === '' || $email === '' || $orgName === '') {
            $message = 'Name, email, and organisation name are required.';
            $messageType = 'error';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Invalid email address.';
            $messageType = 'error';
        } else {
            $db->prepare("UPDATE customers SET name = ?, email = ?, organisation_name = ?, org_domain = ? WHERE id = ?")
               ->execute([$name, $email, $orgName, $orgDomain, $id]);
            $message = 'Customer updated.';
            $messageType = 'success';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['customer_id'] ?? 0);

        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM licenses WHERE customer_id = ? AND status = 'active'");
        $stmt->execute([$id]);
        $activeCount = $stmt->fetch()['cnt'];

        if ($activeCount > 0) {
            $message = "Cannot delete: $activeCount active license(s) exist for this customer.";
            $messageType = 'error';
        } else {
            $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$id]);
            $message = 'Customer deleted.';
            $messageType = 'success';
        }
    }
    }
}

// Check if editing
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCustomer = $stmt->fetch();
}

$customers = $db->query("SELECT * FROM customers ORDER BY created_at DESC")->fetchAll();

renderHeader('Customers', 'customers');
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?>"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="section">
    <h2><?= $editCustomer ? 'Edit Customer' : 'Add New Customer' ?></h2>
    <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="<?= $editCustomer ? 'edit' : 'add' ?>">
        <?php if ($editCustomer): ?>
            <input type="hidden" name="customer_id" value="<?= $editCustomer['id'] ?>">
        <?php endif; ?>
        <div class="form-row">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" required maxlength="150"
                       value="<?= htmlspecialchars($editCustomer['name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required maxlength="255"
                       value="<?= htmlspecialchars($editCustomer['email'] ?? '') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label for="organisation_name">Organisation Name</label>
                <input type="text" id="organisation_name" name="organisation_name" required maxlength="255"
                       value="<?= htmlspecialchars($editCustomer['organisation_name'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="org_domain">Organisation Domain</label>
                <input type="text" id="org_domain" name="org_domain" maxlength="255"
                       value="<?= htmlspecialchars($editCustomer['org_domain'] ?? '') ?>">
            </div>
        </div>
        <button type="submit" class="btn btn-primary"><?= $editCustomer ? 'Update' : 'Add' ?> Customer</button>
        <?php if ($editCustomer): ?>
            <a href="/admin/customers.php" class="btn btn-secondary">Cancel</a>
        <?php endif; ?>
    </form>
</div>

<h2>All Customers</h2>
<?php if (empty($customers)): ?>
    <p class="empty-state">No customers yet.</p>
<?php else: ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Organisation</th>
            <th>Domain</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($customers as $customer): ?>
        <tr>
            <td><?= htmlspecialchars($customer['name']) ?></td>
            <td><?= htmlspecialchars($customer['email']) ?></td>
            <td><?= htmlspecialchars($customer['organisation_name']) ?></td>
            <td><?= htmlspecialchars($customer['org_domain']) ?></td>
            <td><?= htmlspecialchars($customer['created_at']) ?></td>
            <td>
                <a href="?edit=<?= $customer['id'] ?>" class="btn btn-sm">Edit</a>
                <form method="POST" class="inline-action" data-confirm="Delete this customer?">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php renderFooter(); ?>
