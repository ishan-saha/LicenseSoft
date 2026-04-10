<?php

require_once __DIR__ . '/db.php';

function checkRateLimit(string $ip): bool
{
    if (!RATE_LIMIT_ENABLED) {
        return true;
    }

    $db = getDB();
    $now = date('Y-m-d H:i:s');

    // Prune expired windows
    $db->prepare("DELETE FROM rate_limits WHERE window_start < DATE_SUB(?, INTERVAL ? SECOND)")
       ->execute([$now, RATE_LIMIT_WINDOW]);

    $stmt = $db->prepare("SELECT id, request_count, window_start FROM rate_limits WHERE ip = ?");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();

    if (!$row) {
        $db->prepare("INSERT INTO rate_limits (ip, request_count, window_start) VALUES (?, 1, ?)")
           ->execute([$ip, $now]);
        return true;
    }

    $windowStart = strtotime($row['window_start']);
    if (time() - $windowStart > RATE_LIMIT_WINDOW) {
        $db->prepare("UPDATE rate_limits SET request_count = 1, window_start = ? WHERE id = ?")
           ->execute([$now, $row['id']]);
        return true;
    }

    if ($row['request_count'] >= RATE_LIMIT_MAX) {
        return false;
    }

    $db->prepare("UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?")
       ->execute([$row['id']]);
    return true;
}
