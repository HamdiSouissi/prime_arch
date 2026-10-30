<?php
declare(strict_types=1);

/**
 * Identité du produit.
 *
 * Le nom affiché dans l'interface n'est pas le nom du cabinet client (qui se règle dans
 * « Réglages ») : c'est la marque de l'application, son éditeur et son adresse publique.
 * Ces trois éléments sont affichés ensemble dans la barre latérale et sur l'écran de connexion,
 * et repris comme nom par défaut de la base et des documents PDF.
 */
return [
    // Nom du produit, tel qu'il apparaît dans la barre latérale et la connexion.
    'product' => 'Prime Architecture',
    // Éditeur du produit : affiché en petit, sous le nom.
    'vendor' => 'Prime Vision Solutions',
    // Adresse publique de l'éditeur, affichée en petit sous l'éditeur.
    'site' => 'primevisionsolutions.fr',
    'site_url' => 'https://primevisionsolutions.fr',
    // Marque vectorielle et raster du produit (public/assets/img/).
    'logo' => 'img/logo-prime-architecture.png',
    'logo_mark' => 'img/logo-prime-architecture-96.png',
];
