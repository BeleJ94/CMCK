<?php

// Router for PHP's development server. Existing assets are served directly;
// all application URLs continue through the project's front controller.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$publicFile = dirname(__DIR__) . $requestPath;

if ($requestPath !== '/' && is_file($publicFile)) {
    return false;
}

require dirname(__DIR__) . '/index.php';

