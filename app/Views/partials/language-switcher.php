<?php
declare(strict_types=1);

use App\Core\Translator;

/**
 * Sélecteur de langue partagé (français / arabe).
 *
 * Chaque langue possède son propre formulaire : la valeur est portée par un champ
 * caché et non par le bouton. Un bouton désactivé ou un navigateur qui n'envoie pas
 * le déclencheur ne peut donc pas perdre la langue choisie.
 *
 * Variables optionnelles : $returnTo (chemin interne), $variant ('compact' par défaut).
 */
$currentLocale = Translator::locale();
$returnTo = is_string($returnTo ?? null) && $returnTo !== '' ? $returnTo : current_path();
$variant = ($variant ?? 'compact') === 'labeled' ? 'language-switcher--labeled' : '';
?>
<div class="language-switcher <?= e($variant) ?>" role="group" aria-label="<?= e(__('language.switch')) ?>">
    <svg class="icon icon--small language-switcher__icon" aria-hidden="true" focusable="false"><use href="<?= e(asset('img/icons.svg')) ?>#globe"></use></svg>
    <span class="language-switcher__options" role="group" aria-label="<?= e(__('language.available')) ?>">
        <?php foreach (Translator::locales() as $locale): ?>
            <?php $isCurrent = $locale === $currentLocale; ?>
            <form class="language-switcher__form" method="post" action="<?= e(url('/locale')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="locale" value="<?= e($locale) ?>">
                <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
                <button class="language-switcher__option" type="submit"
                        lang="<?= e($locale) ?>" dir="<?= e(Translator::directionOf($locale)) ?>"
                        aria-pressed="<?= $isCurrent ? 'true' : 'false' ?>"
                        title="<?= e(__('language.' . $locale)) ?>">
                    <?= e(__('language.' . $locale . '_short')) ?><span class="u-sr-only"> <?= e(__('language.' . $locale)) ?></span>
                </button>
            </form>
        <?php endforeach; ?>
    </span>
</div>
