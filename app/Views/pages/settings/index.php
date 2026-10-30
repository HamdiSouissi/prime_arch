<?php
declare(strict_types=1);

use App\Core\View;
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => 'Réglages du cabinet',
    'eyebrow' => 'Votre espace de travail',
    'subtitle' => 'Personnalisez les coordonnées, la fiscalité, l’équipe et les modules de Prime Architecture.',
]) ?>

<div class="settings-layout">
    <nav class="settings-nav" role="tablist" aria-label="Sections des réglages" data-tab-group data-tab-panels="#settings-panels">
        <button class="settings-nav__item" type="button" role="tab" id="settings-tab-cabinet" aria-selected="true" aria-controls="settings-panel-cabinet" data-tab-target="settings-panel-cabinet"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#building"></use></svg> Cabinet</button>
        <button class="settings-nav__item" type="button" role="tab" id="settings-tab-fiscal" aria-selected="false" aria-controls="settings-panel-fiscal" data-tab-target="settings-panel-fiscal" tabindex="-1"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#currency"></use></svg> Fiscalité</button>
        <button class="settings-nav__item" type="button" role="tab" id="settings-tab-users" aria-selected="false" aria-controls="settings-panel-users" data-tab-target="settings-panel-users" tabindex="-1"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#people"></use></svg> Utilisateurs</button>
        <button class="settings-nav__item" type="button" role="tab" id="settings-tab-modules" aria-selected="false" aria-controls="settings-panel-modules" data-tab-target="settings-panel-modules" tabindex="-1"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#layers"></use></svg> Modules</button>
    </nav>

    <div class="settings-panels" id="settings-panels">
        <section class="card settings-panel" id="settings-panel-cabinet" role="tabpanel" aria-labelledby="settings-tab-cabinet" data-tab-panel>
            <div class="card-header"><div><h2 class="card-header__title">Identité du cabinet</h2><p class="section-heading__note">Ces informations apparaissent sur vos documents officiels.</p></div><span class="badge badge--success">À jour</span></div>
            <form class="settings-form" method="post" action="#" data-demo-form>
                <?= csrf_field() ?>
                <div class="settings-logo-row"><img src="<?= e(asset('img/logo-placeholder.svg')) ?>" alt="Logo de remplacement du cabinet" width="64" height="64"><div><strong>Logo du cabinet</strong><p class="muted text-footnote">SVG ou PNG · recommandé : carré, 512 × 512 px</p></div><button class="button button--secondary button--small" type="button" data-toast-message="Le téléversement du logo sera activé avec le module Médias.">Remplacer</button></div>
                <div class="content-grid"><div class="field"><label class="field__label" for="firm-name">Nom du cabinet</label><input id="firm-name" name="firm_name" value="Atelier Souissi — Architecture & Urbanisme"></div><div class="field"><label class="field__label" for="architect-name">Architecte responsable</label><input id="architect-name" name="architect_name" value="Amel Trabelsi"></div></div>
                <div class="field"><label class="field__label" for="firm-address">Adresse</label><input id="firm-address" name="address" value="18, avenue Habib Bourguiba, 1001 Tunis"></div>
                <div class="content-grid">
                    <?php View::partial('partials/tunisian-phone-field', ['inputId' => 'firm-phone', 'value' => '+216 71 245 880']) ?>
                    <div class="field"><label class="field__label" for="firm-email">Email professionnel</label><input id="firm-email" type="email" name="email" value="contact@ateliersouissi.tn"></div>
                </div>
                <div class="content-grid"><div class="field"><label class="field__label" for="firm-tax">Matricule fiscal</label><input id="firm-tax" name="tax_id" value="1234567/A/M/000"></div><div class="field"><label class="field__label" for="firm-city">Ville</label><select id="firm-city" name="city"><option selected>Tunis</option><option>Sfax</option><option>Sousse</option><option>Nabeul</option></select></div></div>
                <div class="form-actions"><button class="button button--primary" type="submit"><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#check"></use></svg> Enregistrer les modifications</button></div>
            </form>
        </section>

        <section class="card settings-panel" id="settings-panel-fiscal" role="tabpanel" aria-labelledby="settings-tab-fiscal" data-tab-panel hidden>
            <div class="card-header"><div><h2 class="card-header__title">Fiscalité & facturation</h2><p class="section-heading__note">Paramètres indicatifs — à valider avec votre expert-comptable.</p></div><span class="badge badge--warning">Validation recommandée</span></div>
            <form class="settings-form" method="post" action="#" data-demo-form>
                <?= csrf_field() ?>
                <div class="content-grid"><div class="field"><label class="field__label" for="default-vat">Taux de TVA par défaut</label><select id="default-vat" name="vat"><option selected>19 %</option><option>13 %</option><option>7 %</option><option>0 %</option></select><span class="field__hint">Le taux applicable dépend de la nature de la prestation.</span></div><div class="field"><label class="field__label" for="stamp-duty">Timbre fiscal (DT)</label><input id="stamp-duty" name="stamp_duty" type="number" step="0.001" value="1.000"><span class="field__hint">Valeur de démonstration, confirmez la réglementation en vigueur.</span></div></div>
                <div class="content-grid"><div class="field"><label class="field__label" for="withholding">Retenue à la source (%)</label><input id="withholding" name="withholding" type="number" step="0.01" value="0.00"></div><div class="field"><label class="field__label" for="currency">Devise</label><select id="currency" name="currency"><option selected>TND — Dinar tunisien (DT)</option></select></div></div>
                <div class="form-actions"><button class="button button--primary" type="submit">Enregistrer les paramètres</button></div>
            </form>
        </section>

        <section class="card settings-panel" id="settings-panel-users" role="tabpanel" aria-labelledby="settings-tab-users" data-tab-panel hidden>
            <div class="card-header"><div><h2 class="card-header__title">Équipe & accès</h2><p class="section-heading__note">4 membres dans votre espace</p></div><button class="button button--primary button--small" type="button" data-toast-message="Invitation prête à être envoyée dans la version connectée."><svg class="icon icon--small"><use href="<?= e(asset('img/icons.svg')) ?>#plus"></use></svg> Inviter</button></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th scope="col">Membre</th><th scope="col">Rôle</th><th scope="col">Accès</th><th scope="col">Statut</th></tr></thead><tbody><tr><td><div class="table-primary"><span class="avatar avatar--small">AT</span><span class="table-primary__text"><strong class="table-primary__name">Amel Trabelsi</strong><span class="table-primary__meta">amel@ateliersouissi.tn</span></span></div></td><td>Administratrice</td><td>Tous les modules</td><td><span class="badge badge--success">Actif</span></td></tr><tr><td><div class="table-primary"><span class="avatar avatar--small">NK</span><span class="table-primary__text"><strong class="table-primary__name">Nour Kammoun</strong><span class="table-primary__meta">nour@ateliersouissi.tn</span></span></div></td><td>Architecte</td><td>Projets, Temps, Documents</td><td><span class="badge badge--success">Actif</span></td></tr><tr><td><div class="table-primary"><span class="avatar avatar--small">SB</span><span class="table-primary__text"><strong class="table-primary__name">Sami Ben Amor</strong><span class="table-primary__meta">sami@ateliersouissi.tn</span></span></div></td><td>Assistant projet</td><td>Projets, Documents</td><td><span class="badge badge--warning">Invitation envoyée</span></td></tr></tbody></table></div>
        </section>

        <section class="card settings-panel" id="settings-panel-modules" role="tabpanel" aria-labelledby="settings-tab-modules" data-tab-panel hidden>
            <div class="card-header"><div><h2 class="card-header__title">Modules installés</h2><p class="section-heading__note">Étendez votre espace selon les besoins du cabinet.</p></div><a class="button button--primary button--small" href="<?= e(url('/design/modules')) ?>">Gérer les modules <svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-up-right"></use></svg></a></div>
            <div class="module-shortcut"><span class="module-note__icon"><svg class="icon"><use href="<?= e(asset('img/icons.svg')) ?>#layers"></use></svg></span><div><strong>6 modules disponibles</strong><p class="muted text-footnote">Clients, Projets, Documents, Temps, Bibliothèque BIM et Export comptable.</p></div></div>
        </section>
    </div>
</div>
