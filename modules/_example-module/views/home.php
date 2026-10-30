<?php
declare(strict_types=1);

use App\Core\View;
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Module d’exemple',
    'eyebrow' => 'Module',
    'subtitle' => 'Cette page est rendue depuis le dossier privé du module.',
]) ?>
<section class="card">
    <h2 class="text-headline">Point d’extension prêt</h2>
    <p class="muted text-footnote">Les vues, traductions, hooks, routes et migrations restent dans le dossier du module.</p>
</section>
