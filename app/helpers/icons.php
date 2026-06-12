<?php

if (!function_exists('icon')) {
    function icon($name, $class = 'icon')
    {
        $icons = [
            'dashboard' => '<path d="M4 13h7V4H4v9z"></path><path d="M13 20h7V4h-7v16z"></path><path d="M4 20h7v-5H4v5z"></path>',
            'scale' => '<path d="M12 4v16"></path><path d="M5 7h14"></path><path d="M6 7l-3 6h6L6 7z"></path><path d="M18 7l-3 6h6l-3-6z"></path>',
            'silo' => '<path d="M7 8l5-4 5 4"></path><path d="M8 8h8v12H8V8z"></path><path d="M8 12h8"></path><path d="M8 16h8"></path>',
            'factory' => '<path d="M3 20h18"></path><path d="M5 20V9l5 3V9l5 3V6h4v14"></path><path d="M8 16h2"></path><path d="M13 16h2"></path>',
            'package' => '<path d="M12 3l8 4-8 4-8-4 8-4z"></path><path d="M4 7v10l8 4 8-4V7"></path><path d="M12 11v10"></path>',
            'truck' => '<path d="M3 7h11v9H3V7z"></path><path d="M14 10h4l3 3v3h-7v-6z"></path><path d="M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path><path d="M17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4z"></path>',
            'bell' => '<path d="M18 9a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9z"></path><path d="M10 21h4"></path>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"></path><path d="M22 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
            'shield' => '<path d="M12 3l8 4v5c0 5-3.4 8.6-8 9-4.6-.4-8-4-8-9V7l8-4z"></path><path d="M9 12l2 2 4-5"></path>',
            'mail' => '<path d="M4 6h16v12H4V6z"></path><path d="M4 7l8 6 8-6"></path>',
            'lock' => '<path d="M6 10h12v10H6V10z"></path><path d="M8 10V7a4 4 0 0 1 8 0v3"></path>',
            'login' => '<path d="M10 17l5-5-5-5"></path><path d="M15 12H3"></path><path d="M21 3v18"></path>',
            'logout' => '<path d="M14 17l5-5-5-5"></path><path d="M19 12H8"></path><path d="M5 4v16"></path>',
            'activity' => '<path d="M3 12h4l3 8 4-16 3 8h4"></path>',
            'check' => '<path d="M20 6L9 17l-5-5"></path>',
            'clock' => '<path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18z"></path><path d="M12 7v5l3 2"></path>',
            'grain' => '<path d="M12 21s6-4.2 6-10a6 6 0 0 0-12 0c0 5.8 6 10 6 10z"></path><path d="M12 7v10"></path><path d="M9 10l3 2 3-2"></path><path d="M9 14l3 2 3-2"></path>',
        ];

        $path = $icons[$name] ?? $icons['activity'];

        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
    }
}
