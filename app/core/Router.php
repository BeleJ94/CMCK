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

                if (!empty($route['roles']) && !Auth::hasRole($route['roles'])) {
                    http_response_code(403);
                    echo '403 - Acces refuse';
                    return null;
                }

                return $this->execute($route['handler'], $params);
            }
        }

        http_response_code(404);
        echo '404 - Page introuvable';
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
