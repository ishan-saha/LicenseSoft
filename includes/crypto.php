<?php

function encryptPayload(string $plaintext, string $hexKey): string
{
    $key = hex2bin($hexKey);
    $iv = random_bytes(12);
    $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Encryption failed');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function decryptPayload(string $encoded, string $hexKey): ?string
{
    $key = hex2bin($hexKey);
    $raw = base64_decode($encoded, true);
    if ($raw === false || strlen($raw) < 28) {
        return null;
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $ciphertext = substr($raw, 28);
    $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? null : $plaintext;
}

function generateAesKey(): string
{
    return bin2hex(random_bytes(32));
}
