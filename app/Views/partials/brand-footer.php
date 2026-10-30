<?php
declare(strict_types=1);

/**
 * Pied de page légal : éditeur du produit, adresse publique et copyright.
 *
 * Il est rendu au bas de la colonne de contenu (et sous la page de connexion), pas dans la
 * barre latérale : l'identité du produit reste sobre en haut, les mentions utiles mais
 * secondaires sont regroupées en bas. Le millésime suit l'horloge du serveur.
 */
$vendor = (string) config('brand.vendor', 'Prime Vision Solutions');
$site = (string) config('brand.site', 'primevisionsolutions.fr');
$siteUrl = (string) config('brand.site_url', 'https://primevisionsolutions.fr');
?>
<footer class="app-footer">
    <p class="app-footer__legal"><?= e(__('app.copyright', ['year' => date('Y'), 'vendor' => $vendor])) ?></p>
    <a class="app-footer__site" href="<?= e($siteUrl) ?>" target="_blank" rel="noopener noreferrer" title="<?= e(__('app.brand_site_label', ['vendor' => $vendor])) ?>"><?= e($site) ?></a>
</footer>
