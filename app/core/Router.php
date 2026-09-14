<?php

class Router
{
    private $routes = [];

    public function get($path, $handler, array $options = [])
    {
        $this->add('GET', $path, $handler, $options);
    }

    public function post($path, $handler, array $options = [])
    {
        $this->add('POST', $path, $handler, $options);
    }

    public function add($method, $path, $handler, array $options = [])
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '/' . trim($path, '/'),
            'handler' => $handler,
            'auth' => $options['auth'] ?? false,
            'roles' => $options['roles'] ?? [],
            'permission' => $options['permission'] ?? null,
        ];
    }

    public function dispatch($method, $uri)
    {
        $requestPath = rawurldecode(parse_url($uri, PHP_URL_PATH));
        $scriptName = rawurldecode(dirname($_SERVER['SCRIPT_NAME'] ?? ''));

        if ($scriptName !== '/' && strpos($requestPath, $scriptName) === 0) {
            $requestPath = substr($requestPath, strlen($scriptName));
        }

        $requestPath = '/' . trim($requestPath, '/');

        foreach ($this->routes as $route) {
            $params = [];

            if ($route['method'] === strtoupper($method) && $this->matches($route['path'], $requestPath, $params)) {
                if ($route['auth']) {
                    Auth::requireLogin();
                }

                $permission = $route['permission'] ?: $this->inferPermission($route, $method);
                if ($route['auth'] && $permission && !Auth::can($permission[0], $permission[1])) {
                    require_once dirname(__DIR__).'/services/AuditService.php';
                    AuditService::record('access_denied','security','Accès refusé par les permissions.',['path'=>$requestPath,'permission'=>$permission]);
                    http_response_code(403); echo '403 - Acces refuse'; return null;
                }

                return $this->execute($route['handler'], $params);
            }
        }

        http_response_code(404);
        echo '404 - Page introuvable';
    }

    private function inferPermission(array $route, $method)
    {
        if (!is_string($route['handler']) || strpos($route['handler'], '@') === false) { return null; }
        list($controller, $action) = explode('@', $route['handler'], 2);
        $components = ['Dashboard'=>'dashboard','Report'=>'reports','Analytics'=>'analytics','Traceability'=>'traceability','Cancellation'=>'cancellations','Supplier'=>'suppliers','Truck'=>'trucks','Weighing'=>'weighings','WeighbridgeTransport'=>'weighings','Agriculture'=>'agriculture','Livestock'=>'livestock','Butchery'=>'butchery','Budget'=>'budgets','FuelLogistics'=>'fuel-logistics','Silo'=>'silos','Machine'=>'machines','MachineFeed'=>'machine-feeds','Production'=>'production','Waste'=>'waste','Pelletization'=>'pelletization','Packaging'=>'packaging','EmptyPackaging'=>'empty-packaging','FinishedStock'=>'finished-stocks','Distribution'=>'distributions','Transfer'=>'transfers','Alert'=>'alerts','ActivityLog'=>'activity-logs','Site'=>'sites','AccessControl'=>'rbac','Document'=>'documents'];
        if (!isset($components[$controller]) || $action === 'selectContext') { return null; }
        $permissionAction = strtoupper($method) === 'GET' ? 'read' : 'create';
        if (preg_match('/validate/i', $action)) { $permissionAction = 'validate'; }
        elseif (preg_match('/update|toggle|mark/i', $action)) { $permissionAction = 'update'; }
        elseif (preg_match('/destroy|delete|revoke|reject/i', $action)) { $permissionAction = 'delete'; }
        elseif (in_array($action, ['assign', 'approve', 'storeAssignment', 'updatePermissions'], true)) { $permissionAction = 'administer'; }
        return [$components[$controller], $permissionAction];
    }

    private function matches($routePath, $requestPath, array &$params)
    {
        $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $routePath);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestPath, $matches)) {
            return false;
        }

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return true;
    }

    private function execute($handler, array $params = [])
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_string($handler) && strpos($handler, '@') !== false) {
            list($controller, $action) = explode('@', $handler, 2);
            $controllerClass = ucfirst($controller) . 'Controller';
            $controllerFile = dirname(__DIR__) . '/controllers/' . $controllerClass . '.php';

            if (!file_exists($controllerFile)) {
                throw new RuntimeException("Controller not found: {$controllerClass}");
            }

            require_once $controllerFile;
            $instance = new $controllerClass();

            if (!method_exists($instance, $action)) {
                throw new RuntimeException("Action not found: {$controllerClass}@{$action}");
            }

            return call_user_func_array([$instance, $action], $params);
        }

        throw new InvalidArgumentException('Invalid route handler.');
    }
}
