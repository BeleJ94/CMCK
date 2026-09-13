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

        if (isset($_SESSION['user'])) {
            self::refreshSessionUser();
        }

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
            'role_name' => $user['role_name'] ?? '',
            'role_slug' => $user['role_slug'] ?? '',
        ];
        self::refreshSessionUser();
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

        return $user && in_array(self::roleSlug($user), $roles, true);
    }

    public static function canSelfValidate($userId)
    {
        $user = self::user();
        return $user && (int)$user['id'] === (int)$userId && self::hasRole(['administrateur']);
    }

    public static function can($component, $action, $siteId = null)
    {
        $user = self::user();
        if (!$user) { return false; }
        if ($siteId === null) { $siteId = self::currentSiteId(); }
        require_once dirname(__DIR__) . '/services/AuthorizationService.php';
        return (new AuthorizationService())->can($user['id'], $component, $action, $siteId);
    }

    public static function requirePermission($component, $action, $siteId = null)
    {
        $user = self::user();
        if (!$user) { self::requireLogin(); }
        require_once dirname(__DIR__) . '/services/AuthorizationService.php';
        (new AuthorizationService())->assertAllowed($user['id'], $component, $action, $siteId === null ? self::currentSiteId() : $siteId);
    }

    public static function canViewConsolidated()
    {
        $user = self::user();
        if (!$user) { return false; }
        if (self::hasRole(['administrateur', 'direction'])) { return true; }
        require_once dirname(__DIR__) . '/services/AuthorizationService.php';
        return (new AuthorizationService())->canGlobally($user['id'], 'dashboard', 'read');
    }

    public static function sites()
    {
        $user = self::user();
        return $user['sites'] ?? [];
    }

    public static function currentSiteId()
    {
        self::start();

        if (self::canViewConsolidated() && ($_SESSION['current_site_id'] ?? 'all') === 'all') {
            return null;
        }

        $siteId = $_SESSION['current_site_id'] ?? null;
        if ($siteId !== null && self::canAccessSite($siteId)) {
            return (int) $siteId;
        }

        $sites = self::sites();
        if (!$sites) {
            return null;
        }

        $default = null;
        foreach ($sites as $site) {
            if (!empty($site['is_default'])) {
                $default = (int) $site['id'];
                break;
            }
        }

        $_SESSION['current_site_id'] = $default ?: (int) $sites[0]['id'];
        return (int) $_SESSION['current_site_id'];
    }

    public static function canAccessSite($siteId)
    {
        if ($siteId === null || $siteId === '' || !ctype_digit((string) $siteId)) {
            return false;
        }

        foreach (self::sites() as $site) {
            if ((int) $site['id'] === (int) $siteId) {
                return true;
            }
        }

        return false;
    }

    public static function requireSiteAccess($siteId)
    {
        if (!self::canAccessSite($siteId)) {
            http_response_code(403);
            throw new RuntimeException('Acces refuse pour ce site.');
        }
    }

    public static function requireCurrentSite()
    {
        $siteId = self::currentSiteId();
        if ($siteId === null) {
            throw new RuntimeException('Selectionnez un site avant d effectuer cette operation.');
        }
        self::requireSiteAccess($siteId);
        return $siteId;
    }

    public static function selectSite($siteId)
    {
        self::start();

        if ($siteId === 'all' && self::canViewConsolidated()) {
            $_SESSION['current_site_id'] = 'all';
            return;
        }

        self::requireSiteAccess($siteId);
        $_SESSION['current_site_id'] = (int) $siteId;
    }

    public static function siteClause($column, array &$params, $parameter = 'scope_site_id')
    {
        $siteId = self::currentSiteId();
        if ($siteId === null && self::canViewConsolidated()) {
            return '';
        }

        if ($siteId === null) {
            return ' AND 1 = 0';
        }

        $params[$parameter] = $siteId;
        return ' AND ' . $column . ' = :' . $parameter;
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

        return $paths[self::roleSlug($user)] ?? 'dashboard';
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

        $roleSlug = self::roleSlug($user);

        $groups = [
            ['label' => 'Accueil', 'icon' => 'bi-grid-1x2', 'items' => [
                ['label' => 'Tableau de bord', 'path' => 'dashboard', 'icon' => 'bi-speedometer2', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Accueil', 'path' => 'terrain/pont-bascule', 'icon' => 'bi-house-door', 'roles' => ['agent-pont-bascule']],
                ['label' => 'Accueil', 'path' => 'terrain/silo', 'icon' => 'bi-house-door', 'roles' => ['agent-silo']],
                ['label' => 'Accueil', 'path' => 'terrain/production', 'icon' => 'bi-house-door', 'roles' => ['agent-production']],
                ['label' => 'Accueil', 'path' => 'terrain/emballage', 'icon' => 'bi-house-door', 'roles' => ['agent-emballage']],
                ['label' => 'Accueil', 'path' => 'terrain/distribution', 'icon' => 'bi-house-door', 'roles' => ['agent-distribution']],
            ]],
            ['label' => 'Actions rapides', 'icon' => 'bi-lightning-charge', 'quick' => true, 'items' => [
                ['label' => 'Pesée entrée', 'path' => 'weighings/entry', 'icon' => 'bi-box-arrow-in-down', 'roles' => ['agent-pont-bascule']],
                ['label' => 'Pesée sortie', 'path' => 'weighings/exit', 'icon' => 'bi-box-arrow-up-right', 'roles' => ['agent-pont-bascule'], 'badge' => 'pending_weighings'],
                ['label' => 'Alimenter machine', 'path' => 'machine-feeds/create', 'icon' => 'bi-arrow-down-up', 'roles' => ['agent-silo']],
                ['label' => 'Encoder production', 'path' => 'production/create', 'icon' => 'bi-check2-circle', 'roles' => ['agent-production'], 'badge' => 'pending_batches'],
                ['label' => 'Encoder déchets', 'path' => 'waste/process', 'icon' => 'bi-recycle', 'roles' => ['agent-production']],
                ['label' => 'Nouvel emballage', 'path' => 'packaging/create', 'icon' => 'bi-plus-circle', 'roles' => ['agent-emballage']],
                ['label' => 'Nouvelle sortie', 'path' => 'distributions/create', 'icon' => 'bi-plus-circle', 'roles' => ['agent-distribution']],
            ]],
            ['label' => 'Ferme agricole', 'icon' => 'bi-flower1', 'items' => [
                ['label' => 'Vue d’ensemble', 'path' => 'agriculture', 'icon' => 'bi-grid-1x2', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Campagnes', 'path' => 'agriculture/campaigns', 'icon' => 'bi-calendar3', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Parcelles', 'path' => 'agriculture/plots', 'icon' => 'bi-map', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Planifications', 'path' => 'agriculture/planning', 'icon' => 'bi-bounding-box', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Intrants', 'path' => 'agriculture/inputs', 'icon' => 'bi-droplet-half', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Travaux agricoles', 'path' => 'agriculture/works', 'icon' => 'bi-tools', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Récoltes', 'path' => 'agriculture/harvests', 'icon' => 'bi-basket', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Stocks agricoles', 'path' => 'agriculture/stocks', 'icon' => 'bi-boxes', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Transports agricoles', 'path' => 'agriculture/transports', 'icon' => 'bi-truck', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Main-d’œuvre', 'path' => 'agriculture/workers', 'icon' => 'bi-people', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Matériels', 'path' => 'agriculture/equipment', 'icon' => 'bi-tools', 'roles' => ['administrateur', 'direction']],
            ]],
            ['label' => 'Approvisionnements & matières', 'icon' => 'bi-box-arrow-in-down', 'items' => [
                ['label' => 'Fournisseurs', 'path' => 'suppliers', 'icon' => 'bi-building-check', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']],
                ['label' => 'Camions', 'path' => 'trucks', 'icon' => 'bi-truck-front', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule']],
                ['label' => 'Pont-bascule', 'path' => 'weighings', 'icon' => 'bi-truck', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule'], 'badge' => 'pending_weighings'],
                ['label' => 'Silos & matière première', 'path' => 'silos', 'icon' => 'bi-database', 'roles' => ['administrateur', 'direction', 'agent-silo'], 'badge' => 'silo_alerts'],
            ]],
            ['label' => 'Production industrielle', 'icon' => 'bi-gear-wide-connected', 'items' => [
                ['label' => 'Alimentation', 'path' => 'machine-feeds', 'icon' => 'bi-arrow-down-up', 'roles' => ['administrateur', 'direction', 'agent-silo', 'agent-production']],
                ['label' => 'Machines ROOF', 'path' => 'machines', 'icon' => 'bi-gear-wide-connected', 'roles' => ['administrateur', 'direction', 'agent-production']],
                ['label' => 'Production', 'path' => 'production', 'icon' => 'bi-gear-wide-connected', 'roles' => ['administrateur', 'direction', 'agent-production'], 'badge' => 'pending_batches'],
                ['label' => 'Déchets & coproduits', 'path' => 'waste', 'icon' => 'bi-recycle', 'roles' => ['administrateur', 'direction', 'agent-production']],
                ['label' => 'Pelletisation', 'path' => 'pelletization', 'icon' => 'bi-circle-square', 'roles' => ['administrateur', 'direction', 'agent-production']],
            ]],
            ['label' => 'Conditionnement & stocks', 'icon' => 'bi-boxes', 'items' => [
                ['label' => 'Emballages vides', 'path' => 'empty-packaging', 'icon' => 'bi-bag', 'roles' => ['administrateur', 'direction', 'agent-emballage']],
                ['label' => 'Conditionnement', 'path' => 'packaging', 'icon' => 'bi-box-seam', 'roles' => ['administrateur', 'direction', 'agent-emballage']],
                ['label' => 'Produits finis', 'path' => 'finished-stocks', 'icon' => 'bi-boxes', 'roles' => ['administrateur', 'direction', 'agent-emballage', 'agent-distribution'], 'badge' => 'finished_stock_alerts'],
            ]],
            ['label' => 'Élevage & boucherie', 'icon' => 'bi-heart-pulse', 'items' => [
                ['label' => 'Élevages MUTALA', 'path' => 'livestock', 'icon' => 'bi-heart-pulse', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Boucherie', 'path' => 'butchery', 'icon' => 'bi-shop', 'roles' => ['administrateur', 'direction']],
            ]],
            ['label' => 'Distribution & logistique', 'icon' => 'bi-send-check', 'items' => [
                ['label' => 'Transferts inter-sites', 'path' => 'transfers', 'icon' => 'bi-arrow-left-right', 'roles' => ['administrateur', 'direction', 'agent-distribution']],
                ['label' => 'Distribution clients', 'path' => 'distributions', 'icon' => 'bi-send-check', 'roles' => ['administrateur', 'direction', 'agent-distribution']],
                ['label' => 'Carburant & Logistique', 'path' => 'fuel-logistics', 'icon' => 'bi-fuel-pump', 'roles' => ['administrateur', 'direction', 'agent-distribution']],
            ]],
            ['label' => 'Pilotage & conformité', 'icon' => 'bi-graph-up-arrow', 'items' => [
                ['label' => 'Budgets & engagements', 'path' => 'budgets', 'icon' => 'bi-pie-chart', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Rapports & exports', 'path' => 'reports', 'icon' => 'bi-file-earmark-bar-graph', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Traçabilité complète', 'path' => 'traceability', 'icon' => 'bi-diagram-3', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Documents officiels', 'path' => 'documents', 'icon' => 'bi-file-earmark-check', 'roles' => ['administrateur', 'direction', 'agent-pont-bascule', 'agent-silo', 'agent-production', 'agent-emballage', 'agent-distribution']],
                ['label' => 'Annulations & corrections', 'path' => 'cancellations', 'icon' => 'bi-arrow-counterclockwise', 'roles' => ['administrateur', 'direction']],
                ['label' => 'Alertes', 'path' => 'alerts', 'icon' => 'bi-bell', 'roles' => ['administrateur', 'direction'], 'badge' => 'unread_alerts'],
                ['label' => 'Journal d’activité', 'path' => 'activity-logs', 'icon' => 'bi-clock-history', 'roles' => ['administrateur', 'direction']],
            ]],
            ['label' => 'Administration', 'icon' => 'bi-sliders', 'items' => [
                ['label' => 'Gestion des silos', 'path' => 'silo-administration', 'icon' => 'bi-database-gear', 'roles' => ['administrateur']],
                ['label' => 'Utilisateurs', 'path' => 'users', 'icon' => 'bi-people', 'roles' => ['administrateur']],
                ['label' => 'Sites & structures', 'path' => 'sites', 'icon' => 'bi-diagram-3', 'roles' => ['administrateur']],
                ['label' => 'Rôles & permissions', 'path' => 'access-control', 'icon' => 'bi-shield-lock', 'roles' => ['administrateur']],
            ]],
        ];

        $filteredGroups = [];

        $legacySlugs = ['administrateur','direction','agent-pont-bascule','agent-silo','agent-production','agent-emballage','agent-distribution'];
        foreach ($groups as $group) {
            $items = array_values(array_filter($group['items'], function ($item) use ($roleSlug, $legacySlugs) {
                if ($item['path'] === 'silo-administration') { return self::can('silos', 'administer'); }
                if (in_array($roleSlug, $legacySlugs, true)) { return in_array($roleSlug, $item['roles'], true); }
                $permission = self::menuPermission($item['path']);
                return $permission ? self::can($permission[0], $permission[1]) : false;
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

    private static function menuPermission($path)
    {
        if ($path === 'silo-administration') { return ['silos', 'administer']; }
        $first = explode('/', trim($path, '/'))[0];
        $map = ['dashboard'=>'dashboard','direction'=>'dashboard','terrain'=>'dashboard','reports'=>'reports','analytics'=>'analytics','traceability'=>'traceability','cancellations'=>'cancellations','documents'=>'documents','transfers'=>'transfers','agriculture'=>'agriculture','livestock'=>'livestock','butchery'=>'butchery','budgets'=>'budgets','fuel-logistics'=>'fuel-logistics','suppliers'=>'suppliers','trucks'=>'trucks','weighings'=>'weighings','silos'=>'silos','machines'=>'machines','machine-feeds'=>'machine-feeds','production'=>'production','waste'=>'waste','pelletization'=>'pelletization','packaging'=>'packaging','empty-packaging'=>'empty-packaging','finished-stocks'=>'finished-stocks','distributions'=>'distributions','alerts'=>'alerts','activity-logs'=>'activity-logs','sites'=>'sites','users'=>'users','access-control'=>'rbac'];
        if (!isset($map[$first])) { return null; }
        $action = in_array($path, ['weighings/entry','machine-feeds/create','production/create','waste/process','packaging/create','distributions/create'], true) ? 'create' : 'read';
        if (in_array($first, ['sites','users','access-control'], true)) { $action='administer'; }
        return [$map[$first],$action];
    }

    private static function badgeValue($key)
    {
        try {
            $db = Database::getInstance()->connection();
            $siteId = self::currentSiteId();
            $siteFilter = $siteId === null && self::canViewConsolidated() ? '' : ' AND site_id = ' . (int) $siteId;

            $queries = [
                'pending_weighings' => "SELECT COUNT(*) FROM weighings WHERE status = 'pending' AND deleted_at IS NULL{$siteFilter}",
                'pending_batches' => "SELECT COUNT(*) FROM production_batches WHERE status = 'pending' AND deleted_at IS NULL{$siteFilter}",
                'unread_alerts' => "SELECT COUNT(*) FROM alerts WHERE status = 'active' AND read_at IS NULL AND deleted_at IS NULL{$siteFilter}",
                'silo_alerts' => "SELECT COUNT(*) FROM silos WHERE deleted_at IS NULL AND status IN ('active', 'validated') AND ((alert_threshold_kg > 0 AND current_stock_kg <= alert_threshold_kg) OR (capacity_kg > 0 AND (current_stock_kg / capacity_kg) >= 0.9)){$siteFilter}",
                'finished_stock_alerts' => "SELECT COUNT(*) FROM (SELECT products.id, COALESCE(SUM(finished_stocks.total_weight_kg), 0) AS available_kg FROM products LEFT JOIN finished_stocks ON finished_stocks.product_id = products.id AND finished_stocks.deleted_at IS NULL AND finished_stocks.status IN ('active', 'validated')" . ($siteFilter ? ' AND finished_stocks.site_id = ' . (int) $siteId : '') . " WHERE products.category = 'finished_product' AND products.deleted_at IS NULL GROUP BY products.id HAVING available_kg <= 500) stock_alerts",
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

    private static function roleSlug(array $user)
    {
        return $user['role_slug'] ?? '';
    }

    private static function refreshSessionUser()
    {
        $userId = $_SESSION['user']['id'] ?? null;

        if (!$userId) {
            return;
        }

        try {
            $db = Database::getInstance()->connection();
            $statement = $db->prepare(
                'SELECT users.id, users.name, users.email, users.role_id, roles.name AS role_name, roles.slug AS role_slug
                 FROM users
                 LEFT JOIN roles ON roles.id = users.role_id
                 WHERE users.id = :id
                   AND users.deleted_at IS NULL
                   AND users.status = :status
                 LIMIT 1'
            );
            $statement->execute([
                'id' => $userId,
                'status' => 'active',
            ]);
            $user = $statement->fetch();

            if ($user) {
                $sitesStatement = $db->prepare(
                    "SELECT sites.id, sites.code, sites.name, user_sites.is_default
                     FROM user_sites
                     INNER JOIN sites ON sites.id = user_sites.site_id
                     WHERE user_sites.user_id = :user_id
                       AND user_sites.status = 'active'
                       AND user_sites.deleted_at IS NULL
                       AND sites.status = 'active'
                       AND sites.deleted_at IS NULL
                     ORDER BY user_sites.is_default DESC, sites.name ASC"
                );
                $sitesStatement->execute(['user_id' => $userId]);
                $sites = $sitesStatement->fetchAll();
                $_SESSION['user'] = array_merge($_SESSION['user'], [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role_id' => $user['role_id'],
                    'role_name' => $user['role_name'],
                    'role_slug' => $user['role_slug'],
                    'sites' => $sites,
                ]);
            } else {
                self::logout();
            }
        } catch (Exception $exception) {
            return;
        }
    }
}
