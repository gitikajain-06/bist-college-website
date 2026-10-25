
<?php

if (!defined('BASE_URL')) {
    define('BASE_URL', '/bist-website');
}

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function imageUrl(?string $path): string
{
    if (!$path) {
        return '';
    }

    // Only local website paths are accepted for uploaded images.
    $path = str_replace('\\', '/', $path);

    if (
        str_starts_with($path, '/') ||
        preg_match('/^[a-z][a-z0-9+.-]*:/i', $path) ||
        str_contains($path, '..')
    ) {
        return '';
    }

    return url($path);
}
