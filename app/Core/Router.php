<?php
namespace App\Core;

class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return parse_url($uri, PHP_URL_PATH) ?? '/';
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $body = $this->body();
        return $body[$key] ?? $_POST[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $this->body());
    }

    public function filled(string $key): bool
    {
        $v = $this->input($key);
        return $v !== null && $v !== '';
    }

    public function body(): array
    {
        static $parsed = null;
        if ($parsed !== null) return $parsed;

        $raw = file_get_contents('php://input');
        if (!$raw) return $parsed = [];

        $data = json_decode($raw, true);
        return $parsed = is_array($data) ? $data : [];
    }

    public function bearerToken(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    public function validate(array $rules): array
    {
        $errors = [];
        $data   = $this->all();

        foreach ($rules as $field => $rule) {
            $ruleList = explode('|', $rule);
            $value    = $data[$field] ?? null;

            foreach ($ruleList as $r) {
                if ($r === 'required' && ($value === null || $value === '')) {
                    $errors[$field][] = "The $field field is required.";
                }
                if (str_starts_with($r, 'max:')) {
                    $max = (int) substr($r, 4);
                    if (strlen((string) $value) > $max) {
                        $errors[$field][] = "The $field may not be greater than $max characters.";
                    }
                }
                if ($r === 'email' && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The $field must be a valid email address.";
                }
            }
        }

        if ($errors) {
            Response::json(['errors' => $errors], 422);
            exit;
        }

        return array_intersect_key($data, $rules);
    }
}

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(string $message, int $status = 400): void
    {
        self::json(['message' => $message], $status);
    }
}

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->routes[] = ['DELETE', $path, $handler];
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path   = $request->path();

        // Strip /api prefix
        $path = preg_replace('#^/api#', '', $path) ?: '/';

        foreach ($this->routes as [$routeMethod, $routePath, $handler]) {
            if ($routeMethod !== $method) continue;

            $pattern = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                // Extract named params
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_array($handler)) {
                    [$class, $method_name] = $handler;
                    $obj = new $class();
                    $obj->$method_name($request, $params);
                } else {
                    $handler($request, $params);
                }
                return;
            }
        }

        Response::error('Route not found', 404);
    }
}
