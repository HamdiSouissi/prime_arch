<?php
declare(strict_types=1);

namespace Tests;

use App\Controllers\LocaleController;
use App\Core\Translator;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Garde-fous du changement de langue.
 *
 * Le sélecteur a déjà été cassé par un script qui désactivait le bouton déclencheur :
 * un contrôle désactivé n'est pas envoyé, donc la langue choisie disparaissait du POST.
 * Ces tests figent les protections : valeur dans un champ caché et retour sûr.
 */
final class LanguageSwitchTest extends TestCase
{
    private static function partial(): string
    {
        return (string) file_get_contents(BASE_PATH . '/app/Views/partials/language-switcher.php');
    }

    public function testLocaleValueIsCarriedByAHiddenFieldInsideTheTemplateForm(): void
    {
        $partial = self::partial();

        self::assertSame(1, substr_count($partial, '<form'), 'Un seul formulaire modèle est rendu par langue.');
        self::assertSame(1, substr_count($partial, 'name="locale"'), 'La langue ne doit être transmise qu’une fois.');
        self::assertStringContainsString('<input type="hidden" name="locale" value="<?= e($locale) ?>">', $partial);
        self::assertStringContainsString('<input type="hidden" name="return_to"', $partial);
        self::assertStringContainsString('csrf_field()', $partial);
    }

    public function testSubmitButtonDoesNotCarryTheLocaleSoItCannotBeLost(): void
    {
        // Les blocs PHP sont retirés pour analyser uniquement le balisage des boutons.
        $markup = (string) preg_replace('/<\?(?:php|=).*?\?>/s', '', self::partial());
        preg_match_all('/<button\b[^>]*>/s', $markup, $buttons);

        self::assertNotSame([], $buttons[0], 'Le sélecteur doit exposer un bouton par langue.');
        foreach ($buttons[0] as $button) {
            self::assertDoesNotMatchRegularExpression('/\sname\s*=/', $button);
            self::assertDoesNotMatchRegularExpression('/\svalue\s*=/', $button);
        }
    }

    public function testEverySupportedLocaleIsOffered(): void
    {
        $partial = self::partial();

        self::assertSame(['fr', 'ar'], Translator::locales());
        self::assertStringContainsString("foreach (Translator::locales() as \$locale)", $partial);
        self::assertStringContainsString('aria-pressed', $partial);
    }

    public function testLocaleRouteIsDeclaredAndProtectedByCsrf(): void
    {
        $routes = (string) file_get_contents(BASE_PATH . '/config/routes.php');

        self::assertStringContainsString(
            '$router->post(\'/locale\', [LocaleController::class, \'update\'], [CsrfMiddleware::class]);',
            $routes
        );
    }

    /**
     * @dataProvider returnTargetProvider
     */
    public function testOnlySafeInternalReturnTargetsAreAccepted(string $candidate, string $expected): void
    {
        $method = new ReflectionMethod(LocaleController::class, 'safeReturn');

        self::assertSame($expected, $method->invoke(null, $candidate));
    }

    /**
     * @return array<string,array{0:string,1:string}>
     */
    public static function returnTargetProvider(): array
    {
        return [
            'chemin interne' => ['/clients', '/clients'],
            'filtres conservés' => ['/projects?status=active&page=2', '/projects?status=active&page=2'],
            'protocole relatif' => ['//evil.test', '/'],
            'url absolue' => ['https://evil.test/clients', '/'],
            'saut de ligne' => ["/clients\r\nSet-Cookie: x=1", '/'],
            'antislash' => ['/clients\\..\\admin', '/'],
            'action de langue' => ['/locale', '/dashboard'],
            'chaîne vide' => ['', '/'],
        ];
    }
}
