<?php

declare(strict_types=1);

namespace VotingSystem\Core;

final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $this->compilePattern($pattern),
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            if (preg_match($route['pattern'], $request->path, $matches) === 1) {
                $params = array_filter($matches, static fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
                ($route['handler'])($request, $params);
                return;
            }
        }

        throw new HttpException(404, 'Endpoint not found.');
    }

    private function compilePattern(string $pattern): string
    {
        $pattern = rtrim($pattern, '/') ?: '/';
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)}#', '(?P<$1>[0-9]+)', $pattern);

        return '#^' . $regex . '$#';
    }
}
