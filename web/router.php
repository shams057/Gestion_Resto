<?php
// web/router.php

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Normalize: remove any leading "/web" and trailing slash
$path = preg_replace('#^/web#', '', $path);
$path = rtrim($path, '/');
if ($path === '') {
    $path = '/';
}

// Serve existing files directly
$full = __DIR__ . $path;
if ($path !== '/' && file_exists($full) && !is_dir($full)) {
    return false;
}

switch ($path) {
    case '/':
    case '/shop':
        require __DIR__ . '/buypage.html';
        break;
    case '/cam':
        require __DIR__ . '/cam.php';
        break;

    case '/login':
        require __DIR__ . '/login.php';
        break;

    case '/signup':
        require __DIR__ . '/signup.php';
        break;

    case '/dashboard':
        require __DIR__ . '/dashboard.html'; // or dashboard.php
        break;

    case '/cart':
        require __DIR__ . '/cart.php';
        break;

    case '/logout':
        require __DIR__ . '/logout.php';
        break;

    case '/api':
        require __DIR__ . '/api.php';
        break;

    case '/api_dashboard':
        require __DIR__ . '/api_dashboard.php';
        break;

    case '/auth':
        require __DIR__ . '/auth_redirect.php';
        break;

    case '/callback':
        require __DIR__ . '/auth_callback.php';
        break;

    default:
        http_response_code(404);
        echo 'Not Found';
}