<?php

declare(strict_types=1);

namespace VotingSystem\Core;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        private readonly array $body
    ) {
    }

    public static function capture(): self
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawBody = file_get_contents('php://input') ?: '';
        $body = [];

        if ($rawBody !== '') {
            $decoded = json_decode($rawBody, true);

            if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                throw new HttpException(400, 'Invalid JSON request body.');
            }

            $body = $decoded;
        }

        return new self($method, rtrim($uri, '/') ?: '/', $_GET, $body);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function body(): array
    {
        return $this->body;
    }
}
