<?php

require_once __DIR__ . '/db.php';

function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['admin_id']);
}

function requireLogin(): void
{
    startSecureSession();
    if (!isLoggedIn()) {
        header('Location: /index.php');
        exit;
    }
}

function isLoginLocked(string $ip): bool
{
    $db = getDB();
    $stmt = $db->prepare("SELECT locked_until FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if ($row && $row['locked_until'] !== null) {
        if (strtotime($row['locked_until']) > time()) {
            return true;
        }
        // Lockout expired, reset
        $db->prepare("UPDATE login_attempts SET attempts = 0, locked_until = NULL WHERE ip = ?")
           ->execute([$ip]);
    }
    return false;
}

function recordFailedLogin(string $ip): void
{
    $db = getDB();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare("SELECT id, attempts FROM login_attempts WHERE ip = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if (!$row) {
        $db->prepare("INSERT INTO login_attempts (ip, attempts, last_attempt) VALUES (?, 1, ?)")
           ->execute([$ip, $now]);
        return;
    }

    $newAttempts = $row['attempts'] + 1;
    $lockedUntil = null;

    if ($newAttempts >= LOGIN_MAX_ATTEMPTS) {
        $lockedUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_DURATION);
    }

    $db->prepare("UPDATE login_attempts SET attempts = ?, locked_until = ?, last_attempt = ? WHERE id = ?")
       ->execute([$newAttempts, $lockedUntil, $now, $row['id']]);
}

function clearFailedLogins(string $ip): void
{
    $db = getDB();
    $db->prepare("DELETE FROM login_attempts WHERE ip = ?")->execute([$ip]);
}

function getClientIp(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

function validateCsrfToken(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return $token !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
