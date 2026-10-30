<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Sections du menu latéral (lot 3 de l'audit UX).
 *
 * Le contrat `sidebar.items` gagne une clé **facultative** `section`. Les entrées du noyau la
 * déclarent dans ViewContext ; les modules peuvent faire de même. Une entrée sans section (ou
 * avec une section inconnue) rejoint « Modules externes » : aucune extension existante ne casse,
 * et le séparateur historique est conservé.
 */
final class NavigationSections
{
    public const PILOTAGE = 'pilotage';
    public const PRODUCTION = 'production';
    public const FINANCE = 'finance';
    public const CONFORMITE = 'conformite';
    public const ADMINISTRATION = 'administration';
    /** Section de repli : tout ce qui vient d'un module et ne se range pas ailleurs. */
    public const EXTENSIONS = 'extensions';

    /**
     * Ordre d'affichage : du travail quotidien vers l'administration.
     *
     * @return array<int,string>
     */
    public static function order(): array
    {
        return [
            self::PILOTAGE,
            self::PRODUCTION,
            self::FINANCE,
            self::CONFORMITE,
            self::ADMINISTRATION,
            self::EXTENSIONS,
        ];
    }

    /**
     * Libellé et icône de chaque section, dans la langue active.
     *
     * @return array<string,array{label:string,icon:string}>
     */
    public static function definitions(): array
    {
        return [
            self::PILOTAGE => ['label' => __('nav.section.pilotage'), 'icon' => 'grid'],
            self::PRODUCTION => ['label' => __('nav.section.production'), 'icon' => 'plan'],
            self::FINANCE => ['label' => __('nav.section.finance'), 'icon' => 'currency'],
            self::CONFORMITE => ['label' => __('nav.section.conformite'), 'icon' => 'shield'],
            self::ADMINISTRATION => ['label' => __('nav.section.administration'), 'icon' => 'settings'],
            self::EXTENSIONS => ['label' => __('common.external_modules'), 'icon' => 'layers'],
        ];
    }

    /**
     * Section par défaut des entrées du noyau, quand elles ne déclarent pas la leur
     * (aperçus `/design/*`, page d'erreur avec menu de repli).
     *
     * @var array<string,string>
     */
    private const CORE_SECTIONS = [
        'dashboard' => self::PILOTAGE,
        'clients' => self::PRODUCTION,
        'projects' => self::PRODUCTION,
        'time' => self::PRODUCTION,
        'documents' => self::FINANCE,
        'settings' => self::ADMINISTRATION,
        'users' => self::ADMINISTRATION,
        'audit' => self::ADMINISTRATION,
        'modules' => self::ADMINISTRATION,
    ];

    /**
     * Section d'une entrée : la sienne, sinon celle de sa clé du noyau, sinon « Modules externes ».
     * Une extension qui ne déclare rien reste donc dans le bloc qui lui est réservé.
     */
    public static function sectionOf(array $item): string
    {
        $section = (string) ($item['section'] ?? '');
        if (array_key_exists($section, self::definitions())) {
            return $section;
        }

        $key = (string) ($item['key'] ?? '');

        return self::CORE_SECTIONS[$key] ?? self::EXTENSIONS;
    }

    /**
     * Répartit les entrées dans les sections, dans l'ordre de `order()`.
     * Les sections vides sont omises : le menu ne montre jamais de titre orphelin.
     *
     * @param array<int,array<string,mixed>> $items
     * @return array<int,array{key:string,label:string,icon:string,modules:bool,items:array<int,array<string,mixed>>}>
     */
    public static function group(array $items): array
    {
        $definitions = self::definitions();
        $buckets = array_fill_keys(self::order(), []);
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $buckets[self::sectionOf($item)][] = $item;
        }

        $groups = [];
        foreach (self::order() as $key) {
            if ($buckets[$key] === []) {
                continue;
            }
            $groups[] = [
                'key' => $key,
                'label' => $definitions[$key]['label'],
                'icon' => $definitions[$key]['icon'],
                'modules' => $key === self::EXTENSIONS,
                'items' => $buckets[$key],
            ];
        }

        return $groups;
    }
}
