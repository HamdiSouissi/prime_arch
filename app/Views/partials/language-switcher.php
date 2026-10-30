<?php
declare(strict_types=1);

use App\Core\Translator;

/**
 * Sélecteur de langue partagé (français / arabe).
 * Le formulaire renvoie vers /locale ; la langue est conservée en session.
 *
 * Variables optionnelles : $returnTo (chemin interne), $variant ('compact' par défaut).
 */
$currentLocale = Translator::locale();
$returnTo = is_string($returnTo ?? null) && $returnTo !== '' ? $returnTo : current_path();
$variant = ($variant ?? 'compact') === 'labeled' ? 'language-switcher--labeled' : '';
?>
<form class="language-switcher <?= e($variant) ?>" method="post" action="<?= e(url('/locale')) ?>" aria-label="<?= e(__('language.switch')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
    <svg class="icon icon--small language-switcher__icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#globe"></use></svg>
    <span class="language-switcher__options" role="group" aria-label="<?= e(__('language.available')) ?>">
        <?php foreach (Translator::locales() as $locale): ?>
            <button class="language-switcher__option" type="submit" name="locale" value="<?= e($locale) ?>"
                    lang="<?= e($locale) ?>" dir="<?= e(Translator::directionOf($locale)) ?>"
                    aria-pressed="<?= $locale === $currentLocale ? 'true' : 'false' ?>"
                    title="<?= e(__('language.' . $locale)) ?>">
                <?= e(__('language.' . $locale . '_short')) ?><span class="u-sr-only"> <?= e(__('language.' . $locale)) ?></span>
            </button>
        <?php endforeach; ?>
    </span>
</form>
