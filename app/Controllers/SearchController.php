<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HookManager;
use App\Core\Request;
use App\Core\Response;
use App\Core\Translator;
use App\Models\SearchRepository;
use Throwable;

/**
 * Recherche globale de la palette de commandes.
 *
 * `GET /api/recherche?q=` renvoie des groupes de fiches (clients, projets, documents) dans la
 * langue de l'utilisateur, limités à huit résultats par catégorie et filtrés par ses droits :
 * un utilisateur qui ne voit pas les documents ne reçoit jamais de document. Les modules
 * ajoutent leurs propres groupes avec le filtre `search.results`.
 */
final class SearchController
{
    /** En deçà de deux caractères, la recherche n'est pas lancée (coût inutile, bruit). */
    public const MIN_LENGTH = 2;

    /** Longueur maximale acceptée : au-delà, le terme est tronqué, jamais rejeté. */
    public const MAX_LENGTH = 80;

    public function index(Request $request): Response
    {
        // `?q[]=` (tableau) est ignoré : le terme doit rester une chaîne, jamais un tableau.
        $rawQuery = $request->query('q', '');
        $query = self::normalize(is_string($rawQuery) ? $rawQuery : '');
        if (mb_strlen($query, 'UTF-8') < self::MIN_LENGTH) {
            return self::payload($query, [], true);
        }

        $repository = new SearchRepository();
        $groups = [];

        // Chaque catégorie n'est interrogée que si l'utilisateur peut la consulter : la
        // permission est vérifiée ici, pas seulement dans le menu.
        if (Auth::can('clients.view')) {
            $groups[] = $this->group(
                'clients',
                __('search.group_clients'),
                'people',
                $repository->clients($query),
                static function (array $row): array {
                    return [
                        'title' => (string) ($row['display_name'] ?? ''),
                        'meta' => self::meta([$row['reference'] ?? '', $row['city'] ?? '']),
                        'url' => url('/clients/' . (int) ($row['id'] ?? 0)),
                        'icon' => 'people',
                        'type' => 'client',
                    ];
                }
            );
        }

        if (Auth::can('projects.view')) {
            $groups[] = $this->group(
                'projects',
                __('search.group_projects'),
                'briefcase',
                $repository->projects($query),
                static function (array $row): array {
                    return [
                        'title' => (string) ($row['name'] ?? ''),
                        'meta' => self::meta([$row['reference'] ?? '', $row['client_name'] ?? '']),
                        'url' => url('/projects/' . (int) ($row['id'] ?? 0)),
                        'icon' => 'briefcase',
                        'type' => 'project',
                    ];
                }
            );
        }

        if (self::canSeeDocuments()) {
            $groups[] = $this->group(
                'documents',
                __('search.group_documents'),
                'file-text',
                $repository->documents($query),
                static function (array $row): array {
                    $type = (string) ($row['document_type'] ?? '');
                    $typeKey = 'documents.type.' . $type;
                    $typeLabel = $type !== '' && Translator::has($typeKey) ? __($typeKey) : '';

                    return [
                        'title' => (string) ($row['title'] ?? ''),
                        'meta' => self::meta([$row['document_number'] ?? '', $typeLabel, $row['client_name'] ?? '']),
                        'url' => url('/documents/' . (int) ($row['id'] ?? 0)),
                        'icon' => 'file-text',
                        'type' => 'document',
                    ];
                }
            );
        }

        // Passerelle prévue pour les modules : ils ajoutent un groupe au même format.
        $groups = HookManager::applyFilters('search.results', $groups, $query);
        $groups = array_values(array_filter(
            is_array($groups) ? $groups : [],
            static fn (mixed $group, int|string $key): bool => is_array($group)
                && isset($group['items'])
                && is_array($group['items'])
                && $group['items'] !== [],
            ARRAY_FILTER_USE_BOTH
        ));

        return self::payload($query, $groups, false);
    }

    /**
     * Terme nettoyé : espaces compactés, caractères de contrôle retirés, longueur bornée.
     * L'échappement SQL est fait par SearchRepository::likeTerm().
     */
    public static function normalize(string $query): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $query) ?? '';
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? '';
        $clean = trim($clean);

        return mb_substr($clean, 0, self::MAX_LENGTH, 'UTF-8');
    }

    /** Mêmes droits que l'entrée « Documents » du menu. */
    private static function canSeeDocuments(): bool
    {
        foreach ([
            'documents.view',
            'documents.create',
            'documents.manage',
            'documents.issue',
            'documents.payments.manage',
            'documents.templates.manage',
            'settings.manage',
        ] as $permission) {
            if (Auth::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param callable(array<string,mixed>):array<string,string> $formatter
     * @return array<string,mixed>
     */
    private function group(string $key, string $label, string $icon, array $rows, callable $formatter): array
    {
        $items = [];
        foreach ($rows as $row) {
            try {
                $item = $formatter($row);
            } catch (Throwable) {
                // Une ligne incomplète ne doit pas vider la palette.
                continue;
            }
            if (trim($item['title'] ?? '') === '') {
                continue;
            }
            $items[] = $item;
        }

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'items' => $items,
            'truncated' => count($rows) >= SearchRepository::LIMIT,
        ];
    }

    /** @param array<int,mixed> $parts */
    private static function meta(array $parts): string
    {
        $kept = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value !== '' && !in_array($value, $kept, true)) {
                $kept[] = $value;
            }
        }

        return implode(' · ', $kept);
    }

    /**
     * @param array<int,array<string,mixed>> $groups
     */
    private static function payload(string $query, array $groups, bool $tooShort): Response
    {
        $total = 0;
        foreach ($groups as $group) {
            $total += count($group['items']);
        }

        return Response::json([
            'query' => $query,
            'tooShort' => $tooShort,
            'total' => $total,
            'groups' => $groups,
        ])
            // Les résultats dépendent des droits et des données du cabinet : jamais de cache partagé.
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
