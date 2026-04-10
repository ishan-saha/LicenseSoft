<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crypto.php';
require_once __DIR__ . '/../includes/headers.php';
require_once __DIR__ . '/../includes/rate_limit.php';
require_once __DIR__ . '/../includes/validator.php';

sendSecureHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (!checkRateLimit($clientIp)) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['tool_slug'], $input['payload'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request format']);
    exit;
}

$toolSlug = $input['tool_slug'];
$encodedPayload = $input['payload'];

// Step 1: Validate tool_slug format
if (!validateToolSlug($toolSlug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid tool slug format']);
    exit;
}

// Step 2: Look up tool
$db = getDB();
$stmt = $db->prepare("SELECT id, aes_key FROM tools WHERE slug = ?");
$stmt->execute([$toolSlug]);
$tool = $stmt->fetch();

if (!$tool) {
    http_response_code(400);
    echo json_encode(['error' => 'Unknown tool']);
    exit;
}

$aesKey = $tool['aes_key'];

// Step 3: Decrypt payload
$decrypted = decryptPayload($encodedPayload, $aesKey);
if ($decrypted === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Decryption failed']);
    exit;
}

$payload = json_decode($decrypted, true);
if (!$payload || !isset($payload['license_key'], $payload['install_id'], $payload['ts'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload structure']);
    exit;
}

$licenseKey = $payload['license_key'];
$installId = $payload['install_id'];
$ts = $payload['ts'];

// Validate fields
if (!validateLicenseKey($licenseKey)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid license key format']);
    exit;
}

if (!validateTimestamp($ts)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid timestamp']);
    exit;
}

$installId = validateInstallId($installId);

// Step 4: Check timestamp (replay prevention)
$ts = (int)$ts;
if (abs(time() - $ts) > TIMESTAMP_TOLERANCE) {
    http_response_code(401);
    sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'replay_detected']);
    exit;
}

// Step 5: Look up license
$stmt = $db->prepare("SELECT id, customer_id, installation_id, expires_at, status FROM licenses WHERE license_key = ?");
$stmt->execute([$licenseKey]);
$license = $stmt->fetch();

if (!$license) {
    logActivity(null, $installId, $toolSlug, 'failed');
    sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'invalid_key']);
    exit;
}

// Step 6: Check status
if ($license['status'] !== 'active') {
    logActivity($license['id'], $installId, $toolSlug, 'failed');
    sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'revoked']);
    exit;
}

// Step 7: Check expiry
if (strtotime($license['expires_at']) <= time()) {
    logActivity($license['id'], $installId, $toolSlug, 'failed');
    sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'expired']);
    exit;
}

// Step 8: Check tool is licensed
$stmt = $db->prepare("SELECT id FROM license_tools WHERE license_id = ? AND tool_id = ?");
$stmt->execute([$license['id'], $tool['id']]);
if (!$stmt->fetch()) {
    logActivity($license['id'], $installId, $toolSlug, 'failed');
    sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'tool_not_licensed']);
    exit;
}

// Step 9: Check installation binding (with row lock for race condition prevention)
$db->beginTransaction();
try {
    $stmt = $db->prepare("SELECT installation_id FROM licenses WHERE id = ? FOR UPDATE");
    $stmt->execute([$license['id']]);
    $locked = $stmt->fetch();

    if ($locked['installation_id'] === null) {
        // First activation — bind
        $db->prepare("UPDATE licenses SET installation_id = ? WHERE id = ?")
           ->execute([$installId, $license['id']]);
        $db->commit();
        logActivity($license['id'], $installId, $toolSlug, 'activated');
    } elseif ($locked['installation_id'] === $installId) {
        $db->commit();
        logActivity($license['id'], $installId, $toolSlug, 'verified');
    } else {
        $db->commit();
        logActivity($license['id'], $installId, $toolSlug, 'failed');
        sendEncryptedResponse($aesKey, ['valid' => false, 'reason' => 'install_mismatch']);
        exit;
    }
} catch (Exception $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit;
}

// Step 10: Success
sendEncryptedResponse($aesKey, [
    'valid'      => true,
    'expires_at' => date('Y-m-d', strtotime($license['expires_at'])),
]);

// --- Helper functions ---

function sendEncryptedResponse(string $aesKey, array $data): void
{
    $json = json_encode($data);
    $encrypted = encryptPayload($json, $aesKey);
    echo json_encode(['response' => $encrypted]);
}

function logActivity(?int $licenseId, string $installId, string $toolSlug, string $action): void
{
    if ($licenseId === null) {
        return;
    }
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_logs (license_id, installation_id, tool_slug, action) VALUES (?, ?, ?, ?)")
           ->execute([$licenseId, $installId, $toolSlug, $action]);
    } catch (Exception $e) {
        // Don't fail the request if logging fails
    }
}
