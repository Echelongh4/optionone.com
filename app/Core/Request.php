<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $files,
        private readonly array $server
    ) {
    }

    public static function capture(): self
    {
        $uri = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
        $basePath = trim((string) config('app.base_path', ''), '/');
        $basePath = $basePath !== '' ? '/' . $basePath : '';

        while ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        $path = '/' . trim($uri, '/');
        $path = $path === '//' ? '/' : $path;

        return new self(
            method: strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            path: $path,
            query: $_GET,
            body: $_POST,
            files: $_FILES,
            server: $_SERVER
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }

        return $this->query[$key] ?? $default;
    }

    public function input(string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }

        return $this->body[$key] ?? $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        return filter_var($this->input($key, $default), FILTER_VALIDATE_BOOLEAN);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function all(): array
    {
        return $this->body;
    }

    public function isStateChanging(): bool
    {
        return in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? 'unknown');
    }

    public function isAjax(): bool
    {
        $requestedWith = $this->server['HTTP_X_REQUESTED_WITH'] ?? '';
        return $requestedWith !== '' && strtolower((string) $requestedWith) === 'xmlhttprequest';
    }
}
