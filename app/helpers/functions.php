<?php

if (!function_exists('config')) {
    function config($key = null, $default = null)
    {
        static $config = [];

        if (empty($config)) {
            $config['app'] = require dirname(__DIR__, 2) . '/config/app.php';
            $config['database'] = require dirname(__DIR__, 2) . '/config/database.php';
        }

        if ($key === null) {
            return $config;
        }

        $segments = explode('.', $key);
        $value = $config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('base_url')) {
    function base_url($path = '')
    {
        $baseUrl = rtrim(config('app.base_url', ''), '/');

        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $scriptDir = rawurldecode(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')));
            $scriptDir = $scriptDir === '/' ? '' : rtrim($scriptDir, '/');
            $baseUrl = $scheme . '://' . $host . $scriptDir;
        }

        $baseUrl = str_replace(' ', '%20', $baseUrl);
        $path = ltrim($path, '/');

        return $path === '' ? $baseUrl : $baseUrl . '/' . $path;
    }
}

if (!function_exists('asset_url')) {
    function asset_url($path)
    {
        $path = ltrim($path, '/');
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $isPublicEntry = basename($scriptDir) === 'public';

        return base_url(($isPublicEntry ? 'assets/' : 'public/assets/') . $path);
    }
}

if (!function_exists('view_path')) {
    function view_path($view)
    {
        return dirname(__DIR__) . '/views/' . str_replace('.', '/', $view) . '.php';
    }
}

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect($path)
    {
        header('Location: ' . base_url($path));
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        Auth::start();

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf($token)
    {
        Auth::start();

        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string) $token);
    }
}

if (!function_exists('flash')) {
    function flash($key, $value = null)
    {
        Auth::start();

        if ($value !== null) {
            $_SESSION['flash'][$key] = $value;
            return null;
        }

        $message = $_SESSION['flash'][$key] ?? null;
        unset($_SESSION['flash'][$key]);

        return $message;
    }
}
