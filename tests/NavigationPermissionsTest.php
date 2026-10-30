<?php
declare(strict_types=1);

namespace Tests;

use App\Services\ViewContext;
use PHPUnit\Framework\TestCase;

/**
 * Droits et menu : une entrée de navigation n'existe que si sa page est réellement ouverte
 * au rôle connecté.
 *
 * Régression d'origine : pour un architecte, le bouton « Réglages » ouvrait la configuration
 * des phases de mission (`/settings/project-phases`) tout en soulignant « Projets » dans le
 * menu, d'où l'impression d'arriver sur la page des projets. Les rôles sans réglages voyaient
 * en plus une entrée grisée, donc un bouton menant à un refus.
 */
final class NavigationPermissionsTest extends TestCase
{
    private static function file(string $relativePath): string
    {
        $contents = file_get_contents(BASE_PATH . $relativePath);
        self::assertIsString($contents, 'Fichier introuvable : ' . $relativePath);

        return $contents;
    }

    /** Le bouton « Réglages » ne mène qu'à un écran de réglages que le rôle peut ouvrir. */
    public function testSettingsButtonOnlyOpensAccessibleSettingsPages(): void
    {
        self::assertSame(url('/settings'), ViewContext::settingsDestination(true, true, true));
        self::assertSame(url('/settings'), ViewContext::settingsDestination(true, false, false));
        self::assertSame(url('/settings/hourly-rates'), ViewContext::settingsDestination(false, true, false));
        self::assertSame(url('/settings/hourly-rates'), ViewContext::settingsDestination(false, true, true));
        self::assertSame(url('/settings/modules'), ViewContext::settingsDestination(false, false, true));

        // Architecte : il configure les phases et les champs personnalisés depuis Projets et
        // Clients, mais n'a aucun accès aux réglages du cabinet, aux taux ni aux comptes.
        self::assertSame(null, ViewContext::settingsDestination(false, false, false));
    }

    /** L'entrée du menu utilise cette décision et ne retombe plus sur un écran métier. */
    public function testSettingsEntryFollowsThatDecision(): void
    {
        $context = self::file('/app/Services/ViewContext.php');

        self::assertStringContainsString('self::settingsDestination(', $context);
        self::assertStringContainsString("'available' => \$settingsAvailable", $context);
        foreach (['/settings/project-phases', '/settings/custom-fields', '/settings/document-templates'] as $metierPage) {
            self::assertStringNotContainsString(
                "url('" . $metierPage . "')",
                $context,
                'Le bouton Réglages ne doit pas renvoyer vers un écran de configuration métier.'
            );
        }
    }

    /** Le menu latéral et la feuille mobile omettent une destination fermée au rôle. */
    public function testMenuOmitsForbiddenDestinationsInsteadOfGreyingThem(): void
    {
        foreach (['/app/Views/partials/sidebar.php', '/app/Views/partials/mobile-menu.php'] as $file) {
            $source = self::file($file);
            self::assertStringContainsString(
                "array_key_exists('available', \$item)",
                $source,
                'Chaque surface de navigation doit filtrer les entrées indisponibles : ' . $file
            );
            self::assertStringNotContainsString('is-disabled', $source, 'Une entrée refusée ne doit pas rester visible : ' . $file);
        }

        $sidebar = self::file('/app/Views/partials/sidebar.php');
        // Le filtre précède le regroupement : aucun titre de section orphelin.
        self::assertStringContainsString('$sections = NavigationSections::group($navigationItems);', $sidebar);
        self::assertStringNotContainsString('common.not_available', $sidebar);

        $tabbar = self::file('/app/Views/partials/tabbar.php');
        self::assertStringContainsString("if (array_key_exists('available', \$item) && !\$item['available']) {", $tabbar);
    }

    /** Chaque entrée du noyau déclare son droit d'accès (sauf le tableau de bord, ouvert à tous). */
    public function testEveryCoreEntryDeclaresItsAccessRule(): void
    {
        $context = self::file('/app/Services/ViewContext.php');

        self::assertSame(6, substr_count($context, "'available' =>"), 'Les six entrées du noyau déclarent leur disponibilité.');
        self::assertSame(
            1,
            substr_count($context, "'available' => true,"),
            'Seul le tableau de bord, ouvert à tout utilisateur connecté, est disponible sans droit particulier.'
        );
        foreach (['clients.view', 'projects.view', 'time.view', 'time.manage', 'documents.view', 'settings.view'] as $permission) {
            self::assertStringContainsString("Auth::can('{$permission}')", $context, $permission);
        }
    }
}
