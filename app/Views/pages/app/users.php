<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\View;

$users = is_array($users ?? null) ? $users : [];
$roles = is_array($roles ?? null) ? $roles : [];
$errors = is_array($errors ?? null) ? $errors : [];
$values = is_array($values ?? null) ? $values : [];
$currentUserId = Auth::id();
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('users.title'),
    'subtitle' => 'Les comptes sont créés par un administrateur. Aucun lien d’inscription public n’est disponible.',
    'eyebrow' => 'Sécurité du cabinet',
    'secondaryAction' => ['label' => 'Journal d’audit', 'icon' => 'clock', 'url' => url('/admin/audit')],
]) ?>

<?php if (!empty($notice)): ?>
    <p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p>
<?php endif; ?>
<?php if (!empty($errors['general'])): ?>
    <p class="form-feedback form-feedback--error" role="alert"><?= e($errors['general']) ?></p>
<?php endif; ?>

<div class="users-layout">
    <section class="card" aria-labelledby="new-user-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="new-user-title"><?= e(__('users.create')) ?></h2>
                <p class="section-heading__note"><?= e(__('users.password_help')) ?></p>
            </div>
        </div>
        <form class="settings-form" method="post" action="<?= e(url('/admin/users')) ?>">
            <?= csrf_field() ?>
            <div class="field">
                <label class="field__label" for="new-user-name"><?= e(__('users.name')) ?></label>
                <input class="field__control" id="new-user-name" name="name" maxlength="150" required autocomplete="name" value="<?= e($values['name'] ?? '') ?>">
            </div>
            <div class="field">
                <label class="field__label" for="new-user-email"><?= e(__('settings.email')) ?></label>
                <input class="field__control" id="new-user-email" name="email" type="email" maxlength="190" required autocomplete="email" value="<?= e($values['email'] ?? '') ?>">
                <?php if (!empty($errors['email'])): ?><span class="field__hint field__hint--error"><?= e($errors['email']) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="new-user-password"><?= e(__('auth.password')) ?></label>
                <input class="field__control" id="new-user-password" name="password" type="password" minlength="12" maxlength="1024" required autocomplete="new-password">
            </div>
            <div class="field">
                <label class="field__label" for="new-user-role"><?= e(__('users.role')) ?></label>
                <select class="field__control" id="new-user-role" name="role" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role['slug']) ?>" <?= ($values['role'] ?? 'read_only') === $role['slug'] ? 'selected' : '' ?>><?= e($role['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions"><button class="button button--primary" type="submit"><?= e(__('users.save')) ?></button></div>
        </form>
    </section>

    <section class="card" aria-labelledby="users-table-title">
        <div class="card-header">
            <div>
                <h2 class="card-header__title" id="users-table-title">Comptes du cabinet</h2>
                <p class="section-heading__note"><?= e((string) count($users)) ?> utilisateur(s)</p>
            </div>
        </div>
        <?php if ($users === []): ?>
            <?php View::partial('partials/empty-state', [
                'title' => 'Aucun compte utilisateur',
                'description' => 'Créez un premier compte pour votre équipe depuis le formulaire.',
                'image' => asset('img/empty-clients.svg'),
                'imageAlt' => '',
            ]) ?>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table users-table">
                    <thead><tr>
                        <th scope="col"><?= e(__('users.name')) ?></th>
                        <th scope="col"><?= e(__('settings.email')) ?></th>
                        <th scope="col"><?= e(__('users.role')) ?></th>
                        <th scope="col"><?= e(__('users.status')) ?></th>
                        <th scope="col"><?= e(__('users.last_login')) ?></th>
                        <th scope="col"><span class="u-sr-only"><?= e(__('users.manage')) ?></span></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $isSelf = (int) $user['id'] === (int) $currentUserId;
                        $lastLoginTimestamp = !empty($user['last_login_at']) ? strtotime((string) $user['last_login_at']) : false;
                        ?>
                        <tr>
                            <td><strong><?= e($user['name']) ?></strong><?php if ($isSelf): ?><span class="users-self-label"> · Vous</span><?php endif; ?></td>
                            <td><a href="<?= e('mailto:' . $user['email']) ?>"><?= e($user['email']) ?></a></td>
                            <td><?= e($user['role_label']) ?></td>
                            <td><span class="badge <?= !empty($user['is_active']) ? 'badge--success' : 'badge--neutral' ?>"><?= e(!empty($user['is_active']) ? __('common.enabled') : __('common.disabled')) ?></span></td>
                            <td><?= e($lastLoginTimestamp !== false ? date('d/m/Y H:i', $lastLoginTimestamp) : __('users.never')) ?></td>
                            <td>
                                <?php if (!$isSelf): ?>
                                    <form class="user-access-form" method="post" action="<?= e(url('/admin/users/' . $user['id'])) ?>">
                                        <?= csrf_field() ?>
                                        <label class="u-sr-only" for="role-<?= e((string) $user['id']) ?>"><?= e(__('users.role')) ?> — <?= e($user['name']) ?></label>
                                        <select class="field__control" id="role-<?= e((string) $user['id']) ?>" name="role">
                                            <?php foreach ($roles as $role): ?>
                                                <option value="<?= e($role['slug']) ?>" <?= $role['slug'] === $user['role_slug'] ? 'selected' : '' ?>><?= e($role['label']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="check-label"><input type="checkbox" name="is_active" value="1" <?= !empty($user['is_active']) ? 'checked' : '' ?>> <?= e(__('users.active')) ?></label>
                                        <button class="button button--secondary button--small" type="submit"><?= e(__('users.manage')) ?></button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted text-footnote">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
