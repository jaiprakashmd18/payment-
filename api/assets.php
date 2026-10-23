<?php

/*
 * Serves the theme assets that Vite builds into public/default/ during the
 * function build. Files generated there are bundled with the function but are
 * not part of Vercel's static output, so they are streamed from here instead.
 */

$root = realpath(__DIR__ . '/../public/default');
$file = $root ? realpath($root . '/' . ltrim((string) ($_GET['path'] ?? ''), '/')) : false;

if ($file === false || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    exit;
}

$types = [
    'css' => 'text/css; charset=utf-8',
    'js' => 'text/javascript; charset=utf-8',
    'json' => 'application/json',
    'map' => 'application/json',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
];

header('Content-Type: ' . ($types[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($file));
// Built files have content hashes in their names, so they can be cached forever.
header(str_contains($file, '/assets/')
    ? 'Cache-Control: public, max-age=31536000, immutable'
    : 'Cache-Control: public, max-age=300');

readfile($file);
