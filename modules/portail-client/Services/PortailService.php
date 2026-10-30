<?php
declare(strict_types=1);

namespace Modules\PortailClient\Services;

use DateTimeImmutable;

/**
 * Règles de sécurité et de présentation de l’espace client.
 *
 * Le jeton d’accès est un secret aléatoire de 256 bits, transmis dans l’URL et stocké uniquement
 * sous forme d’empreinte SHA-256 : une fuite de la base ne permet pas de reconstituer un lien.
 * Chaque lien est borné à un client et, au choix, à un seul projet, avec des droits de lecture
 * explicites (documents, montants, photos), une date d’expiration et une révocation immédiate.
 */
final class PortailService
{
    /** Portée d’un lien : tout le client ou un seul projet. */
    public const SCOPES = ['client', 'project'];

    /** Droits de lecture proposés à la création. */
    public const VIEW_OPTIONS = ['documents', 'amounts', 'photos'];

    /** Bornes de la durée de validité, en jours. */
    public const DAYS_MIN = 1;
    public const DAYS_MAX = 365;
    public const DAYS_DEFAULT = 30;

    /** Seuil d’alerte avant expiration. */
    public const EXPIRING_DAYS = 7;

    /** Longueur du libellé du lien. */
    public const LABEL_MIN = 2;
    public const LABEL_MAX = 120;

    /** Taille du jeton en octets (64 caractères hexadécimaux). */
    public const TOKEN_BYTES = 32;

    /** Fenêtre et seuils de limitation de débit, par adresse. */
    public const RATE_WINDOW_MINUTES = 10;
    public const RATE_MAX_ATTEMPTS = 240;
    public const RATE_MAX_DENIALS = 20;

    /** Nombre de photos présentées par projet. */
    public const MAX_PHOTOS = 12;

    /** Millisecondes d’une journée. */
    private const DAY = 86400;

    /** Jeton neuf : 32 octets cryptographiques, jamais réutilisés. */
    public static function newToken(): string
    {
        return bin2hex(random_bytes(self::TOKEN_BYTES));
    }

    /**
     * Empreinte du jeton telle qu’elle est stockée.
     *
     * SHA-256 suffit ici (contrairement à un mot de passe) : le jeton est déjà un secret de
     * 256 bits tiré au hasard, il n’est pas devinable par force brute.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', 'portail-token|' . $token);
    }

    /** Forme attendue d’un jeton : 64 caractères hexadécimaux minuscules. */
    public static function isTokenFormat(string $token): bool
    {
        return preg_match('/^[a-f0-9]{64}$/D', $token) === 1;
    }

    /**
     * Empreinte salée de l’adresse IP, pour la limitation de débit et le journal.
     *
     * Le sel provient de `app.key` (repli : URL de l’application) : deux installations ne
     * produisent pas les mêmes empreintes. Il s’agit d’une pseudonymisation, pas d’un
     * anonymat : l’espace des adresses IPv4 reste petit.
     */
    public static function ipHash(string $ip, string $salt): string
    {
        return hash('sha256', 'portail-ip|' . $salt . '|' . trim($ip));
    }

    /** En-tête User-Agent nettoyé : caractères de contrôle retirés, longueur bornée. */
    public static function safeUserAgent(mixed $userAgent): string
    {
        $text = is_string($userAgent) ? $userAgent : '';
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);
        $text = trim((string) preg_replace('/\s+/', ' ', $text));

        return mb_substr($text, 0, 255, 'UTF-8');
    }

    /** Durée de validité saisie ; null si la valeur n’est pas un entier dans les bornes. */
    public static function days(mixed $value): ?int
    {
        $text = is_int($value) ? (string) $value : (is_string($value) ? trim($value) : '');
        if ($text === '') {
            return self::DAYS_DEFAULT;
        }
        if (!ctype_digit($text)) {
            return null;
        }
        $days = (int) $text;

        return $days >= self::DAYS_MIN && $days <= self::DAYS_MAX ? $days : null;
    }

    /** Date d’expiration (incluse) à partir d’aujourd’hui. */
    public static function expiryDate(int $days, string $today): ?string
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($start === false) {
            return null;
        }

        return $start->modify('+' . max(1, $days) . ' days')->format('Y-m-d');
    }

    /** Nombre de jours restants avant expiration (négatif une fois dépassée). */
    public static function daysLeft(string $expiresOn, string $today): ?int
    {
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $expiresOn);
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($end === false || $start === false) {
            return null;
        }

        return (int) round(((float) $end->getTimestamp() - (float) $start->getTimestamp()) / self::DAY);
    }

    /**
     * État affiché d’un lien : `revoked`, `expired`, `expiring` ou `active`.
     *
     * @param array<string,mixed> $row
     */
    public static function state(array $row, string $today): string
    {
        if (($row['revoked_at'] ?? null) !== null) {
            return 'revoked';
        }
        $expiresOn = isset($row['expires_on']) ? (string) $row['expires_on'] : '';
        $daysLeft = $expiresOn === '' ? null : self::daysLeft($expiresOn, $today);
        if ($daysLeft === null) {
            return 'expired';
        }
        if ($daysLeft < 0) {
            return 'expired';
        }

        return $daysLeft <= self::EXPIRING_DAYS ? 'expiring' : 'active';
    }

    /** Un lien est utilisable s’il est actif ou proche de l’expiration. */
    public static function isUsable(array $row, string $today): bool
    {
        return in_array(self::state($row, $today), ['active', 'expiring'], true);
    }

    /**
     * Décide de l’accès à partir de la ligne éventuelle trouvée pour l’empreinte du jeton.
     *
     * @param array<string,mixed>|null $row
     * @return array{ok:bool,reason:string}
     */
    public static function decide(?array $row, string $token, string $today): array
    {
        if ($row === null || !isset($row['token_hash'])) {
            return ['ok' => false, 'reason' => 'unknown'];
        }
        // Comparaison en temps constant : la recherche se fait déjà par empreinte, on évite en
        // plus toute différence de temps exploitable sur la comparaison finale.
        if (!hash_equals((string) $row['token_hash'], self::hashToken($token))) {
            return ['ok' => false, 'reason' => 'unknown'];
        }
        $state = self::state($row, $today);
        if ($state === 'revoked') {
            return ['ok' => false, 'reason' => 'revoked'];
        }
        if ($state === 'expired') {
            return ['ok' => false, 'reason' => 'expired'];
        }

        return ['ok' => true, 'reason' => $state];
    }

    /**
     * Synthèse des liens : actifs, proches de l’expiration, expirés, révoqués.
     *
     * @param array<int,array<string,mixed>> $links
     * @return array<string,int>
     */
    public static function statistics(array $links, string $today): array
    {
        $stats = ['total' => count($links), 'active' => 0, 'expiring' => 0, 'expired' => 0, 'revoked' => 0];
        foreach ($links as $link) {
            $state = self::state($link, $today);
            $stats[$state] = ($stats[$state] ?? 0) + 1;
        }

        return $stats;
    }

    /**
     * Valide la création d’un lien.
     *
     * @param array<string,mixed> $input
     * @param array<int,int>      $clientIds      clients autorisés
     * @param array<int,int>      $projectClients projet => client
     * @return array{values:array<string,mixed>,errors:array<string,string>}
     */
    public function validateLink(array $input, array $clientIds, array $projectClients): array
    {
        $clientRaw = trim(self::string($input['client_id'] ?? ''));
        $scopeRaw = self::string($input['scope'] ?? 'project');
        $projectRaw = trim(self::string($input['project_id'] ?? ''));
        $days = self::days($input['expires_days'] ?? '');
        $documents = (string) ($input['can_view_documents'] ?? '0') === '1';
        $amounts = (string) ($input['can_view_amounts'] ?? '0') === '1';
        $photos = (string) ($input['can_view_photos'] ?? '0') === '1';

        $values = [
            'label' => trim(self::string($input['label'] ?? '')),
            'client_id' => null,
            'scope' => in_array($scopeRaw, self::SCOPES, true) ? $scopeRaw : 'project',
            'project_id' => null,
            'can_view_documents' => $documents,
            // Les montants ne peuvent pas être visibles si les documents ne le sont pas.
            'can_view_amounts' => $documents && $amounts,
            'can_view_photos' => $photos,
            'expires_days' => $days ?? self::DAYS_DEFAULT,
        ];
        $errors = [];

        $labelLength = mb_strlen($values['label'], 'UTF-8');
        if ($labelLength < self::LABEL_MIN) {
            $errors['label'] = __('portail.invalid_label');
        } elseif ($labelLength > self::LABEL_MAX) {
            $errors['label'] = __('portail.invalid_label_length');
        }
        if (!ctype_digit($clientRaw) || !in_array((int) $clientRaw, $clientIds, true)) {
            $errors['client_id'] = __('portail.invalid_client');
        } else {
            $values['client_id'] = (int) $clientRaw;
        }
        if ($values['scope'] === 'project') {
            if ($projectRaw === '') {
                $errors['project_id'] = __('portail.invalid_project_required');
            } elseif (!ctype_digit($projectRaw) || !isset($projectClients[(int) $projectRaw])) {
                $errors['project_id'] = __('portail.invalid_project');
            } elseif ($values['client_id'] !== null && $projectClients[(int) $projectRaw] !== $values['client_id']) {
                $errors['project_id'] = __('portail.invalid_project_client');
            } else {
                $values['project_id'] = (int) $projectRaw;
            }
        }
        if ($days === null) {
            $errors['expires_days'] = __('portail.invalid_days');
        }
        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Avancement pondéré des phases, avec la même règle que le noyau : somme des poids nulle
     * ⇒ aucun pourcentage n’est inventé et l’avancement affiché reste à 0.
     *
     * @param array<int,array<string,mixed>> $phases
     * @return array{percent:int,weighted:bool}
     */
    public static function progress(array $phases): array
    {
        $weightSum = 0.0;
        $weightedSum = 0.0;
        foreach ($phases as $phase) {
            $weight = (float) ($phase['weight_percent'] ?? 0);
            $progress = (float) ($phase['progress_percent'] ?? 0);
            if ($weight <= 0.0) {
                continue;
            }
            $weightSum += $weight;
            $weightedSum += $weight * min(100.0, max(0.0, $progress));
        }
        if ($weightSum <= 0.0) {
            return ['percent' => 0, 'weighted' => false];
        }

        return ['percent' => (int) round($weightedSum / $weightSum), 'weighted' => true];
    }

    /** Pourcentage borné à [0, 100] pour l’affichage d’une phase. */
    public static function clampPercent(mixed $value): int
    {
        $percent = is_numeric($value) ? (float) $value : 0.0;

        return (int) round(min(100.0, max(0.0, $percent)));
    }

    /** Adresse de l’espace client à partir du jeton, sans exposer d’autre paramètre. */
    public static function linkUrl(string $token, string $locale = ''): string
    {
        $url = url('/portail/' . $token);
        if ($locale === 'fr' || $locale === 'ar') {
            $url .= '?lang=' . $locale;
        }

        return $url;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) || is_int($value) ? (string) $value : '';
    }
}
