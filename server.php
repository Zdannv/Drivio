<?php

/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$uri = urldecode($uri);

$requested = __DIR__.'/public'.$uri;

// Tambahkan header Service-Worker-Allowed untuk sw.js agar PWA dapat mengontrol scope '/'
if ($uri === '/build/sw.js') {
    if (file_exists($requested)) {
        header('Content-Type: application/javascript');
        header('Service-Worker-Allowed: /');
        readfile($requested);
        exit;
    }
}

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having to install a "real" web server software here.
if ($uri !== '/' && file_exists($requested)) {
    return false;
}

require_once __DIR__.'/public/index.php';
