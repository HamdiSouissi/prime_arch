<?php
declare(strict_types=1);
?>
<section class="auth-aside" aria-label="Présentation de Prime Arch">
    <a class="auth-aside__brand" href="<?= e(url('/design/dashboard')) ?>"><img src="<?= e(asset('img/logo-placeholder.svg')) ?>" alt="" width="48" height="48"><span>Prime Arch</span></a>
    <div class="auth-aside__message"><p class="page-header__eyebrow">Votre cabinet, tout simplement</p><h1>Plus de temps pour concevoir.</h1><p>Les projets, l’équipe et les honoraires réunis dans un espace calme et clair.</p></div>
    <p class="auth-aside__footer">Un espace pensé pour les architectes tunisiens · Tunis · Sfax · Sousse</p>
</section>
<section class="auth-form-area">
    <div class="auth-card">
        <p class="page-header__eyebrow">Espace sécurisé</p>
        <h2 class="auth-card__heading">Ravi de vous revoir</h2>
        <p class="auth-card__intro">Connectez-vous à votre espace Atelier Souissi.</p>
        <form class="auth-card__form" method="post" action="#" data-demo-form>
            <?= csrf_field() ?>
            <div class="field"><label class="field__label" for="login-email">Adresse email</label><input class="field__control" id="login-email" type="email" name="email" autocomplete="username" placeholder="vous@cabinet.tn" required value="amel@ateliersouissi.tn"></div>
            <div class="field"><label class="field__label" for="login-password">Mot de passe</label><input class="field__control" id="login-password" type="password" name="password" autocomplete="current-password" placeholder="8 caractères minimum" required value="primearch-demo"><span class="field__hint">Le formulaire est illustratif et ne transmet aucune donnée.</span></div>
            <div class="auth-card__meta"><label class="check-label"><input type="checkbox" name="remember" checked> Se souvenir de moi</label><a href="#forgot" data-toast-message="Un lien de réinitialisation serait envoyé par email.">Mot de passe oublié ?</a></div>
            <button class="button button--primary button--full" type="submit">Se connecter <svg class="icon icon--small icon--directional"><use href="<?= e(asset('img/icons.svg')) ?>#arrow-right"></use></svg></button>
        </form>
        <p class="auth-card__footer">Besoin d’aide ? <a href="mailto:support@primearch.tn">Contacter l’équipe Prime Arch</a></p>
    </div>
</section>
