<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Réponse HTTP (code, en-têtes, corps).
 */
final class Response
{
    /** @var array<string,string> */
    private array $headers = [];

    public function __construct(
        private string $body = '',
        private int $status = 200,
    ) {
    }

    public static function html(string $html, int $status = 200): self
    {
        return (new self($html, $status))->header('Content-Type', 'text/html; charset=utf-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return (new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status))
            ->header('Content-Type', 'application/json; charset=utf-8');
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return (new self('', $status))->header('Location', $url);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
    }

    // TODO : download() pour les PDF (storage/pdf), cookies, cache HTTP.
}
