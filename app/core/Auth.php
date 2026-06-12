<?php

class Auth
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);

            session_start();
        }
    }

    public static function check()
    {
        self::start();

        return isset($_SESSION['user']);
    }

    public static function user()
    {
        self::start();

        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user)
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role_id' => $user['role_id'],
            'role_name' => $user['role_name'],
            'role_slug' => $user['role_slug'],
        ];
    }

    public static function logout()
    {
        self::start();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function requireLogin()
    {
        if (!self::check()) {
            $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? base_url('dashboard');
            redirect('login');
        }
    }

    public static function intendedUrl()
    {
        self::start();
        $intendedUrl = $_SESSION['intended_url'] ?? null;
        unset($_SESSION['intended_url']);

        return $intendedUrl;
    }

    public static function hasRole($roles)
    {
        $user = self::user();
        $roles = (array) $roles;

        return $user && in_array($user['role_slug'], $roles, true);
    }

    public static function homePathFor(array $user)
    {
        $paths = [
            'administrateur' => 'dashboard',
            'direction' => 'direction',
            'agent-pont-bascule' => 'terrain/pont-bascule',
            'agent-silo' => 'terrain/silo',
            'agent-production' => 'terrain/production',
            'agent-emballage' => 'terrain/emballage',
            'agent-distribution' => 'terrain/distribution',
        ];

        return $paths[$user['role_slug']] ?? 'dashboard';
    }

    public static function menu()
    {
        $items = [];

        foreach (self::menuGroups() as $group) {
            foreach ($group['items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    public static function menuGroups()
    {
        $user = self::user();

        if (!$user) {
            return [];
        }

        $groups = [
            ['label' => 'Accueil', 'items' => [
                ['label' => 'Tableau de bord', 'path' => 'dashboard', 'icon' => 'bi-speedometer2', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Accueil', 'path' => 'terrain/pont-bascule', 'icon' => 'bi-house-door', 'roles' => ['agent-pont-bascule']],
                ['label' => 'Accueil', 'path' => 'terrain/silo', 'icon' => 'bi-house-door', 'roles' => ['agent-silo']],
                ['label' => 'Accueil', 'path' => 'terrain/production', 'icon' => 'bi-house-door', 'roles' => ['agent-production']],
                ['label' => 'Accueil', 'path' => 'terrain/emballage', 'icon' => 'bi-house-door', 'roles' => ['agent-emballage']],
                ['label' => 'Accueil', 'path' => 'terrain/distribution', 'icon' => 'bi-house-door', 'roles' => ['agent-distribution']],
            ]],
            ['label' => 'Actions rapides', 'quick' => true, 'items' => [
                ['label' => 'Pesée entrée', 'path' => 'weighings/entry', 'icon' => 'bi-box-arrow-in-down', 'roles' => ['agent-pont-bascule']],
                ['label' => 'Pesée sortie', 'path' => 'weighings/exit', 'icon' => 'bi-box-arrow-up-right', 'roles' => ['agent-pont-bascule'], 'badge' => 'pending_weighings'],
                ['label' => 'Alimenter machine', 'path' => 'machine-feeds/create', 'icon' => 'bi-arrow-down-up', 'roles' => ['agent-silo']],
                ['label' => 'Encoder production', 'path' => 'production/create', 'icon' => 'bi-check2-circle', 'roles' => ['agent-production'], 'badge' => 'pending_batches'],
                ['label' => 'Encoder déchets', 'path' => 'waste/process', 'icon' => 'bi-recycle', 'roles' => ['agent-production']],
                ['label' => 'Nouvel emballage', 'path' => 'packaging/create', 'icon' => 'bi-plus-circle', 'roles' => ['agent-emballage']],
                ['label' => 'Nouvelle sortie', 'path' => 'distributions/create', 'icon' => 'bi-plus-circle', 'roles' => ['agent-distribution']],
            ]],
            ['label' => 'Opérations', 'items' => [
                ['label' => 'Pont-bascule', 'path' => 'weighings', 'icon' => 'bi-truck', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule'], 'badge' => 'pending_weighings'],
                ['label' => 'Alimentation', 'path' => 'machine-feeds', 'icon' => 'bi-arrow-down-up', 'roles' => ['administrateur', 'direction', 'agent-silo', 'agent-production']],
                ['label' => 'Production', 'path' => 'production', 'icon' => 'bi-gear-wide-connected', 'roles' => ['administrateur', 'direction', 'agent-production'], 'badge' => 'pending_batches'],
                ['label' => 'Dechets', 'path' => 'waste', 'icon' => 'bi-recycle', 'roles' => ['administrateur', 'direction', 'agent-production']],
                ['label' => 'Emballage', 'path' => 'packaging', 'icon' => 'bi-box-seam', 'roles' => ['administrateur', 'direction', 'agent-emballage']],
                ['label' => 'Distribution', 'path' => 'distributions', 'icon' => 'bi-send-check', 'roles' => ['administrateur', 'direction', 'agent-distribution']],
            ]],
            ['label' => 'Stocks', 'items' => [
                ['label' => 'Silos', 'path' => 'silos', 'icon' => 'bi-database', 'roles' => ['administrateur', 'direction', 'agent-silo'], 'badge' => 'silo_alerts'],
                ['label' => 'Stock finis', 'path' => 'finished-stocks', 'icon' => 'bi-boxes', 'roles' => ['administrateur', 'direction', 'agent-emballage', 'agent-distribution'], 'badge' => 'finished_stock_alerts'],
            ]],
            ['label' => 'Référentiels', 'items' => [
                ['label' => 'Fournisseurs', 'path' => 'suppliers', 'icon' => 'bi-building-check', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']],
                ['label' => 'Camions', 'path' => 'trucks', 'icon' => 'bi-truck-front', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']],
                ['label' => 'Machines', 'path' => 'machines', 'icon' => 'bi-gear-wide-connected', 'roles' => ['administrateur', 'direction', 'agent-production']],
            ]],
            ['label' => 'Suivi', 'items' => [
                ['label' => 'Rapports', 'path' => 'reports', 'icon' => 'bi-file-earmark-bar-graph', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Alertes', 'path' => 'alerts', 'icon' => 'bi-bell', 'roles' => ['administrateur', 'direction'], 'badge' => 'unread_alerts'],
                ['label' => 'Journal activite', 'path' => 'activity-logs', 'icon' => 'bi-clock-history', 'roles' => ['administrateur', 'direction']],
            ]],
            ['label' => 'Administration', 'items' => [
                ['label' => 'Utilisateurs', 'path' => 'users', 'icon' => 'bi-people', 'roles' => ['administrateur']],
            ]],
        ];

        $filteredGroups = [];

        foreach ($groups as $group) {
            $items = array_values(array_filter($group['items'], function ($item) use ($user) {
                return in_array($user['role_slug'], $item['roles'], true);
            }));

            foreach ($items as &$item) {
                $item['badge_value'] = isset($item['badge']) ? self::badgeValue($item['badge']) : null;
            }
            unset($item);

            if (!empty($items)) {
                $group['items'] = $items;
                $filteredGroups[] = $group;
            }
        }

        return $filteredGroups;
    }

    private static function badgeValue($key)
    {
        try {
            $db = Database::getInstance()->connection();

            $queries = [
                'pending_weighings' => "SELECT COUNT(*) FROM weighings WHERE status = 'pending' AND deleted_at IS NULL",
                'pending_batches' => "SELECT COUNT(*) FROM production_batches WHERE status = 'pending' AND deleted_at IS NULL",
                'unread_alerts' => "SELECT COUNT(*) FROM alerts WHERE status = 'active' AND read_at IS NULL AND deleted_at IS NULL",
                'silo_alerts' => "SELECT COUNT(*) FROM silos WHERE deleted_at IS NULL AND status IN ('active', 'validated') AND ((alert_threshold_kg > 0 AND current_stock_kg <= alert_threshold_kg) OR (capacity_kg > 0 AND (current_stock_kg / capacity_kg) >= 0.9))",
                'finished_stock_alerts' => "SELECT COUNT(*) FROM (SELECT products.id, COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS available_kg FROM products LEFT JOIN finished_stocks ON finished_stocks.product_id = products.id AND finished_stocks.deleted_at IS NULL AND finished_stocks.status IN ('active', 'validated') WHERE products.category = 'finished_product' AND products.deleted_at IS NULL GROUP BY products.id HAVING available_kg <= 500) stock_alerts",
            ];

            if (!isset($queries[$key])) {
                return null;
            }

            $count = (int) $db->query($queries[$key])->fetchColumn();
            return $count > 0 ? $count : null;
        } catch (Exception $exception) {
            return null;
        }
    }
}
