<?php
declare(strict_types=1);

use App\Core\Translator;
use App\Core\View;

$entries = is_array($entries ?? null) ? $entries : [];
/**
 * Libellé traduit d'un événement d'audit, avec repli sur la clé technique.
 */
$eventLabel = static function (string $key): string {
    return $key !== '' && Translator::has('audit.event.' . $key) ? __('audit.event.' . $key) : $key;
};
/**
 * Libellé traduit d'un détail d'audit, avec repli sur le nom du champ.
 */
$detailLabel = static function (string $key): string {
    return Translator::has('audit.detail.' . $key) ? __('audit.detail.' . $key) : $key;
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('audit.title'),
    'subtitle' => __('audit.subtitle'),
    'eyebrow' => __('audit.eyebrow'),
    'secondaryAction' => ['label' => __('users.title'), 'icon' => 'people', 'url' => url('/admin/users')],
]) ?>

<section class="card" aria-labelledby="audit-list-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="audit-list-title"><?= e(__('audit.recent')) ?></h2>
            <p class="section-heading__note">
                <?= e(__('audit.count', ['count' => (string) ($total ?? 0)])) ?>
            </p>
        </div>
    </div>
    <?php if ($entries === []): ?>
        <?php View::partial('partials/empty-state', [
            'title' => __('audit.empty_title'),
            'description' => __('audit.empty_help'),
            'image' => asset('img/empty-projects.svg'),
            'imageAlt' => '',
        ]) ?>
    <?php else: ?>
        <div class="table-wrap" role="region" aria-label="<?= e(__('audit.table_label')) ?>" tabindex="0">
            <table class="data-table audit-table">
                <thead><tr>
                    <th scope="col"><?= e(__('audit.date')) ?></th>
                    <th scope="col"><?= e(__('audit.event')) ?></th>
                    <th scope="col"><?= e(__('audit.account')) ?></th>
                    <th scope="col"><?= e(__('audit.subject')) ?></th>
                    <th scope="col"><?= e(__('audit.details')) ?></th>
                </tr></thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <?php
                    $timestamp = strtotime((string) ($entry['created_at'] ?? ''));
                    $eventKey = (string) ($entry['event_key'] ?? '');
                    $details = is_array($entry['details'] ?? null) ? $entry['details'] : [];
                    ?>
                    <tr>
                        <td><?= e($timestamp !== false ? date('d/m/Y H:i:s', $timestamp) : '—') ?></td>
                        <td><strong><?= e($eventLabel($eventKey)) ?></strong></td>
                        <td><?= e((string) ($entry['actor_name'] ?? __('audit.system'))) ?></td>
                        <td>
                            <?= e((string) ($entry['subject_type'] ?? '')) ?>
                            <?php if (!empty($entry['subject_id'])): ?>
                                #<?= e((string) $entry['subject_id']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($details === []): ?>
                                <span class="muted">—</span>
                            <?php else: ?>
                                <ul class="audit-details">
                                <?php foreach ($details as $key => $value): ?>
                                    <li>
                                        <span><?= e($detailLabel((string) $key)) ?> :</span>
                                        <?= e(is_bool($value) ? ($value ? __('common.yes') : __('common.no')) : (string) $value) ?>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ((int) ($pageCount ?? 1) > 1): ?>
    <nav class="pagination" aria-label="<?= e(__('audit.pagination_label')) ?>">
        <?php if ((int) $page > 1): ?>
            <a class="button button--secondary button--small"
                href="<?= e(url('/admin/audit?page=' . ((int) $page - 1))) ?>"><?= e(__('common.previous')) ?></a>
        <?php endif; ?>
        <span aria-current="page"><?= e(__('common.page', ['current' => (string) $page, 'total' => (string) $pageCount])) ?></span>
        <?php if ((int) $page < (int) $pageCount): ?>
            <a class="button button--secondary button--small"
                href="<?= e(url('/admin/audit?page=' . ((int) $page + 1))) ?>"><?= e(__('common.next')) ?></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
