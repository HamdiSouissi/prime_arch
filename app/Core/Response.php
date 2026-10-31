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

    /** Fichier privé à transmettre tel quel, sans le charger entièrement en mémoire. */
    private ?string $streamPath = null;

    /** Supprime le fichier source une fois la réponse envoyée (archive de transit). */
    private bool $deleteAfterSend = false;

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
        if ($this->streamPath !== null) {
            $handle = @fopen($this->streamPath, 'rb');
            if ($handle !== false) {
                fpassthru($handle);
                fclose($handle);
            }
            if ($this->deleteAfterSend) {
                @unlink($this->streamPath);
            }
            return;
        }
        echo $this->body;
    }

    /** Retourne un contenu binaire en téléchargement privé, sans servir le fichier depuis public/. */
    public static function download(
        string $contents,
        string $filename,
        string $contentType = 'application/octet-stream',
        string $disposition = 'attachment'
    ): self {
        $filename = basename(str_replace('\\', '/', $filename));
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'download';
        $disposition = in_array($disposition, ['inline', 'attachment'], true) ? $disposition : 'attachment';
        return (new self($contents))
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', $disposition . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($filename))
            ->header('Content-Length', (string) strlen($contents))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    /**
     * Retourne un fichier privé déjà écrit sur disque (archive de sauvegarde).
     *
     * Le contenu est transmis par flux : une archive volumineuse n'est jamais chargée
     * entièrement en mémoire. `$deleteAfterSend` supprime l'archive de transit une fois
     * la réponse envoyée.
     */
    public static function fileDownload(
        string $path,
        string $filename,
        string $contentType = 'application/zip',
        bool $deleteAfterSend = false
    ): self {
        if (!is_file($path) || is_link($path) || !is_readable($path)) {
            throw new \RuntimeException('Le fichier demandé est absent ou inaccessible.');
        }
        $size = filesize($path);
        $filename = basename(str_replace('\\', '/', $filename));
        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'download';
        $response = (new self(''))
            ->header('Content-Type', $contentType)
            ->header('Content-Disposition', 'attachment; filename="' . $safeName . '"; filename*=UTF-8\'\'' . rawurlencode($filename))
            ->header('Content-Length', (string) ($size === false ? 0 : $size))
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, no-store, max-age=0');
        $response->streamPath = $path;
        $response->deleteAfterSend = $deleteAfterSend;

        return $response;
    }
}
