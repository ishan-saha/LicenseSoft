<?php
/**
 * One-time installation script.
 * Run this after importing schema.sql to create the default admin account.
 * DELETE THIS FILE after use.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';

if (php_sapi_name() !== 'cli') {
    die('This script must be run from the command line.');
}

$username = 'admin';
$password = 'admin';

$hash = password_hash($password, PASSWORD_BCRYPT);

$db = getDB();
$stmt = $db->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
$stmt->execute([$username, $hash]);

echo "Admin account created.\n";
echo "Username: $username\n";
echo "Password: $password\n";
echo "IMPORTANT: Change this password immediately after first login.\n";
echo "IMPORTANT: Delete this file (install.php) from your server.\n";
