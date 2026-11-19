<?php

namespace App\Core;

class Router
{
    private $routes = [];

    public function get($path, $action)
    {
        $this->addRoute('GET', $path, $action);
    }

    public function post($path, $action)
    {
        $this->addRoute('POST', $path, $action);
    }

    private function addRoute($method, $path, $action)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'pattern' => $this->compilePattern($path),
            'action' => $action,
        ];
    }

    /**
     * Ubah path seperti /produk/{slug} menjadi regex dengan named group.
     * Bagian literal di-escape agar karakter seperti "." tidak jadi wildcard.
     */
    private function compilePattern($path)
    {
        $parts = preg_split('#(\{[a-zA-Z_][a-zA-Z0-9_]*\})#', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';

        foreach ($parts as $part) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$#', $part, $m)) {
                $regex .= '(?P<' . $m[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return '#^' . $regex . '$#';
    }

    // Load route dari file eksternal
    public function loadRoutes($file)
    {
        if (file_exists($file)) {
            $router = $this;
            require $file;
        }
    }

    public function dispatch($requestUri, $requestMethod)
    {
        $requestUri = parse_url($requestUri, PHP_URL_PATH);
        $requestUri = rtrim($requestUri, '/');
        if ($requestUri === '') {
            $requestUri = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) {
                continue;
            }

            if (preg_match($route['pattern'], $requestUri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[] = rawurldecode($value);
                    }
                }
                return $this->callAction($route['action'], $params);
            }
        }

        http_response_code(404);
        echo "Halaman tidak ditemukan (404).";
    }

    private function callAction($action, array $params = [])
    {
        if (is_callable($action)) {
            return $action(...$params);
        }

        if (is_string($action) && strpos($action, '@') !== false) {
            list($controller, $method) = explode('@', $action);

            if (strpos($controller, '/') !== false) {
                $controllerClass = "App\\Controllers\\" . str_replace('/', '\\', $controller);
            } else {
                $controllerClass = "App\\Controllers\\" . $controller;
            }

            if (class_exists($controllerClass)) {
                $controllerInstance = new $controllerClass();
                if (method_exists($controllerInstance, $method)) {
                    return $controllerInstance->$method(...$params);
                }
            }
        }

        throw new \Exception("Invalid route action: " . (is_string($action) ? $action : 'closure'));
    }
}