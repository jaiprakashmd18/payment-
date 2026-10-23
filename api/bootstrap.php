<?php

/*
 * Shared setup for running Paymenter on Vercel's serverless PHP runtime.
 *
 * The deployment bundle is read-only, so everything Laravel writes at runtime
 * goes to /tmp, and anything that has to survive between requests (sessions,
 * queued jobs) lives in the database.
 */

$defaults = [
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'LOG_CHANNEL' => 'stderr',
    'SESSION_DRIVER' => 'database',
    'QUEUE_CONNECTION' => 'database',
    'CACHE_STORE' => getenv('REDIS_HOST') || getenv('REDIS_URL') ? 'redis' : 'array',
    'FILESYSTEM_DISK' => 'local',
];

foreach ($defaults as $key => $value) {
    if (getenv($key) === false || getenv($key) === '') {
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    } else {
        $_ENV[$key] = $_SERVER[$key] = getenv($key);
    }
}

foreach ([
    'app/public',
    'app/invoices',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
] as $dir) {
    $path = $_ENV['LARAVEL_STORAGE_PATH'] . '/' . $dir;
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

// Vercel terminates TLS at its edge, so tell Laravel the original request was HTTPS.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
}
