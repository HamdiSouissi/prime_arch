<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Représentation de la requête HTTP entrante.
 */
final class Request
{
    public function __construct(
        private string $method,
        private string $path,
        private array $query,
        private array $body,
        private array $files,
        private array $server,
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        // Surcharge de méthode pour les formulaires HTML (PUT/PATCH/DELETE)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper((string) $_POST['_method']);
        }

        $uri  = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        // Support d'une installation dans un sous-dossier
        $base = rtrim(str_replace('\\', '/', dirname($server['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }

        return new self($method, '/' . trim($uri, '/'), $_GET, $_POST, $_FILES, $server);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->body + $this->query;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    // TODO : isAjax(), json(), header(), cookie(), validation des entrées.
}
