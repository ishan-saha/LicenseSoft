<?php

function validateToolSlug(string $slug): bool
{
    return preg_match('/^[a-z0-9\-]{1,100}$/', $slug) === 1;
}

function validateLicenseKey(string $key): bool
{
    return preg_match('/^[a-f0-9]{40}$/', $key) === 1;
}

function validateInstallId(string $id): string
{
    $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $id);
    return substr($cleaned, 0, 255);
}

function validateTimestamp($ts): bool
{
    return is_int($ts) || (is_string($ts) && ctype_digit($ts));
}
