<?php
declare(strict_types=1);

namespace Modules\PortailClient\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use App\Core\View;
use App\Services\CabinetSettings;
use App\Services\ProjectImageStorage;
use Modules\PortailClient\Models\PortailRepository;
use Modules\PortailClient\Services\PortailService;

/**
 * Routes publiques de l’espace client (aucune session utilisateur, aucun compte).
 *
 * Défenses appliquées, dans cet ordre :
 *  1. limitation de débit par adresse (tentatives et refus récents) ;
 *  2. format du jeton, puis recherche par empreinte SHA-256 et comparaison en temps constant ;
 *  3. expiration et révocation vérifiées à chaque requête ;
 *  4. périmètre strict (un client, éventuellement un seul projet) et droits déclarés sur le lien ;
 *  5. en-têtes `noindex`, `no-referrer`, `no-store`, CSP sans script ;
 *  6. aucune écriture dans le noyau, aucun contenu du cabinet autre que ce qui est listé.
 */
final class PortalController
{
    private PortailRepository $repo;
    private PortailService $service;
    private CabinetSettings $settings;

    public function __construct(
        ?PortailRepository $repo = null,
        ?PortailService $service = null,
        ?CabinetSettings $settings = null
    ) {
        $this->repo = $repo ?? new PortailRepository();
        $this->service = $service ?? new PortailService();
        $this->settings = $settings ?? new CabinetSettings();
    }

    /** Espace client : projets, avancement, documents émis et photos autorisés. */
    public function show(Request $request, string $token): Response
    {
        $this->applyLocale($request);
        $access = $this->resolve($request, $token, 'espace', true);
        if ($access['response'] !== null) {
            return $access['response'];
        }
        $link = $access['row'];
        if ($link === null) {
            return $this->notice('invalid', 404);
        }

        $today = date('Y-m-d');
        $clientId = (int) $link['client_id'];
        $projectId = $link['scope'] === 'project' && $link['project_id'] !== null ? (int) $link['project_id'] : null;
        $client = $this->repo->client($clientId);
        if ($client === null) {
            return $this->notice('invalid', 404);
        }

        $projects = $this->repo->projects($clientId, $projectId);
        $projectIds = array_map(static fn (array $project): int => (int) $project['id'], $projects);
        $phasesByProject = [];
        foreach ($this->repo->phases($projectIds) as $phase) {
            $phasesByProject[(int) $phase['project_id']][] = $phase;
        }
        foreach ($projects as $index => $project) {
            $phases = $phasesByProject[(int) $project['id']] ?? [];
            $projects[$index]['phases'] = $phases;
            $projects[$index]['progress'] = PortailService::progress($phases);
        }

        $canDocuments = (bool) $link['can_view_documents'];
        $canAmounts = $canDocuments && (bool) $link['can_view_amounts'];
        $documents = $canDocuments ? $this->repo->documents($clientId, $projectId, $canAmounts) : [];
        $photos = (bool) $link['can_view_photos']
            ? $this->repo->photos($projectIds, PortailService::MAX_PHOTOS)
            : [];
        $photosByProject = [];
        foreach ($photos as $photo) {
            $photosByProject[(int) $photo['project_id']][] = $photo;
        }

        $this->repo->touch((int) $link['id']);

        $html = View::renderModule('portail-client', 'portal', $this->shared($link, $today) + [
            'client' => $client,
            'projects' => $projects,
            'documents' => $documents,
            'photosByProject' => $photosByProject,
            'canDocuments' => $canDocuments,
            'canAmounts' => $canAmounts,
            'canPhotos' => (bool) $link['can_view_photos'],
            'token' => $token,
            'linkScope' => (string) $link['scope'],
            'generatedAt' => date('d/m/Y H:i'),
        ], null);

        return $this->secure(Response::html($html));
    }

    /** Photo d’un projet du périmètre, servie en flux privé (jamais depuis `public/`). */
    public function photo(Request $request, string $token, string $imageId): Response
    {
        $access = $this->resolve($request, $token, 'photo', false);
        if ($access['response'] !== null) {
            return $access['response'];
        }
        $link = $access['row'];
        if ($link === null || !(bool) $link['can_view_photos'] || !ctype_digit($imageId)) {
            return $this->notice('invalid', 404);
        }
        $image = $this->repo->photo((int) $imageId);
        if ($image === null) {
            return $this->notice('invalid', 404);
        }
        $projectId = (int) $image['project_id'];
        $allowed = $link['scope'] === 'project'
            ? ($link['project_id'] !== null && (int) $link['project_id'] === $projectId)
            : $this->repo->projectBelongsToClient($projectId, (int) $link['client_id']);
        if (!$allowed) {
            return $this->notice('invalid', 404);
        }

        // Le noyau valide le chemin, le MIME réel et les dimensions ; on ne manipule aucune
        // portion de chemin nous-mêmes.
        $stored = (new ProjectImageStorage())->read($projectId, (string) $image['stored_name']);
        if ($stored === null) {
            return $this->notice('invalid', 404);
        }
        $extension = match ($stored['mime_type']) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return Response::download($stored['contents'], 'photo-' . (int) $image['id'] . '.' . $extension, $stored['mime_type'], 'inline')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY')
            ->header('Content-Security-Policy', "default-src 'none'; sandbox");
    }

    /**
     * Vérifie le jeton et la limitation de débit.
     *
     * @return array{response:Response|null,row:array<string,mixed>|null}
     */
    private function resolve(Request $request, string $token, string $page, bool $journal): array
    {
        $today = date('Y-m-d');
        $ipHash = PortailService::ipHash($request->ip(), $this->salt());
        $attempts = $this->repo->countAttempts($ipHash, PortailService::RATE_WINDOW_MINUTES);
        $denials = $attempts > 0
            ? $this->repo->countAttempts($ipHash, PortailService::RATE_WINDOW_MINUTES, true)
            : 0;
        if ($attempts >= PortailService::RATE_MAX_ATTEMPTS || $denials >= PortailService::RATE_MAX_DENIALS) {
            return ['response' => $this->notice('throttled', 429)->header('Retry-After', '600'), 'row' => null];
        }

        if (!PortailService::isTokenFormat($token)) {
            $this->recordDenial($ipHash);

            return ['response' => $this->notice('invalid', 404), 'row' => null];
        }

        $row = $this->repo->findByHash(PortailService::hashToken($token));
        $decision = PortailService::decide($row, $token, $today);
        if (!$decision['ok'] || $row === null) {
            $this->recordDenial($ipHash);
            $reason = $decision['reason'];

            // Un jeton inconnu et un jeton expiré ne se distinguent pas côté client : dans les
            // deux cas on ne divulgue aucune donnée du cabinet, seulement un message d’état.
            return [
                'response' => $this->notice($reason === 'unknown' ? 'invalid' : $reason, $reason === 'unknown' ? 404 : 410),
                'row' => null,
            ];
        }

        $this->repo->recordAttempt($ipHash, true);
        $this->maybePrune();
        if ($journal) {
            $this->repo->recordAccess(
                (int) $row['id'],
                $page,
                $ipHash,
                PortailService::safeUserAgent($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        }

        return ['response' => null, 'row' => $row];
    }

    private function recordDenial(string $ipHash): void
    {
        $this->repo->recordAttempt($ipHash, false);
        $this->maybePrune();
    }

    /**
     * Purge opportuniste des tentatives anciennes : la table de limitation de débit ne doit
     * pas croître indéfiniment, y compris sous un flot de requêtes refusées.
     */
    private function maybePrune(): void
    {
        if (random_int(1, 50) === 1) {
            $this->repo->pruneAttempts();
        }
    }

    /**
     * Données communes aux pages publiques (identité du cabinet, expiration, langue).
     *
     * @param array<string,mixed> $link
     * @return array<string,mixed>
     */
    private function shared(array $link, string $today): array
    {
        $settings = $this->settings->get();

        return [
            'locale' => Translator::locale(),
            'direction' => Translator::direction(),
            'cabinet' => [
                'name' => (string) ($settings['name'] ?? ''),
                'phone' => (string) ($settings['phone'] ?? ''),
                'email' => (string) ($settings['email'] ?? ''),
                'city' => (string) ($settings['postal_code'] ?? ''),
            ],
            'expires_on' => (string) $link['expires_on'],
            'days_left' => PortailService::daysLeft((string) $link['expires_on'], $today),
            'today' => $today,
        ];
    }

    /** Page d’état minimale (jeton invalide, expiré, révoqué, débit dépassé). */
    private function notice(string $state, int $status): Response
    {
        $settings = $this->settings->get();
        $html = View::renderModule('portail-client', 'notice', [
            'locale' => Translator::locale(),
            'direction' => Translator::direction(),
            'cabinet' => [
                'name' => (string) ($settings['name'] ?? ''),
                'phone' => (string) ($settings['phone'] ?? ''),
                'email' => (string) ($settings['email'] ?? ''),
                'city' => (string) ($settings['postal_code'] ?? ''),
            ],
            'state' => $state,
        ], null);

        return $this->secure(Response::html($html, $status));
    }

    /** En-têtes de sécurité communs aux pages publiques. */
    private function secure(Response $response): Response
    {
        return $response
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('Cross-Origin-Resource-Policy', 'same-origin')
            ->header(
                'Content-Security-Policy',
                "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; "
                . "base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
            )
            ->header('Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=()');
    }

    /** Langue de la page : paramètre explicite, sinon langue de l’installation ; rien en session. */
    private function applyLocale(Request $request): void
    {
        $requested = $request->query('lang', '');
        if (is_string($requested) && Translator::isSupported($requested)) {
            Translator::setLocale($requested);
        }
    }

    /** Sel utilisé pour l’empreinte des adresses IP (jamais affiché, jamais journalisé). */
    private function salt(): string
    {
        $key = (string) Config::get('app.key', '');
        if ($key !== '') {
            return $key;
        }

        return (string) Config::get('app.url', 'prime-arch');
    }
}
