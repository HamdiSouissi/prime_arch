<?php
declare(strict_types=1);

use App\Core\View;
use Modules\RegistreCourrier\Services\CourrierService;

$settings = is_array($settings ?? null) ? $settings : ['prefix_in' => 'CE', 'prefix_out' => 'CS', 'separator' => '-', 'digits' => 3];
$counters = is_array($counters ?? null) ? $counters : ['in' => 0, 'out' => 0];
$previews = is_array($previews ?? null) ? $previews : [];
$year = (int) ($year ?? (int) date('Y'));
$settingsErrors = is_array($settingsErrors ?? null) ? $settingsErrors : [];
$settingsValues = is_array($settingsValues ?? null) ? $settingsValues : [];
$counterErrors = is_array($counterErrors ?? null) ? $counterErrors : [];
$values = $settingsValues !== [] ? $settingsValues + $settings : $settings;
$fieldError = static fn (string $field): string => (string) ($settingsErrors[$field] ?? '');
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('courrier.settings_title'),
    'eyebrow' => __('courrier.title'),
    'subtitle' => __('courrier.settings_intro'),
    'secondaryAction' => ['label' => __('courrier.back'), 'icon' => 'arrow-left', 'url' => url('/courrier')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<p class="table-meta" role="note"><?= e(__('courrier.settings_notice')) ?></p>

<form class="card settings-form" method="post" action="<?= e(url('/courrier/reglages')) ?>">
    <?= csrf_field() ?>
    <section class="form-section" aria-labelledby="courrier-format-title">
        <div class="section-heading">
            <div>
                <h2 class="section-heading__title" id="courrier-format-title"><?= e(__('courrier.format_title')) ?></h2>
                <p class="section-heading__note"><?= e(__('courrier.format_help')) ?></p>
            </div>
        </div>
        <div class="form-grid">
            <div class="field">
                <label class="field__label" for="courrier-prefix-in"><?= e(__('courrier.prefix_in')) ?></label>
                <input class="field__control" type="text" id="courrier-prefix-in" name="prefix_in" value="<?= e((string) ($values['prefix_in'] ?? 'CE')) ?>" maxlength="10" <?= empty($canManage) ? 'readonly' : '' ?> required>
                <?php if ($fieldError('prefix_in') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('prefix_in')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-prefix-out"><?= e(__('courrier.prefix_out')) ?></label>
                <input class="field__control" type="text" id="courrier-prefix-out" name="prefix_out" value="<?= e((string) ($values['prefix_out'] ?? 'CS')) ?>" maxlength="10" <?= empty($canManage) ? 'readonly' : '' ?> required>
                <?php if ($fieldError('prefix_out') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('prefix_out')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-separator"><?= e(__('courrier.separator')) ?></label>
                <input class="field__control" type="text" id="courrier-separator" name="separator" value="<?= e((string) ($values['separator'] ?? '-')) ?>" maxlength="3" <?= empty($canManage) ? 'readonly' : '' ?>>
                <?php if ($fieldError('separator') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('separator')) ?></span><?php endif; ?>
            </div>
            <div class="field">
                <label class="field__label" for="courrier-digits"><?= e(__('courrier.digits')) ?></label>
                <input class="field__control" type="number" id="courrier-digits" name="digits" value="<?= e((string) ($values['digits'] ?? 3)) ?>" min="1" max="8" <?= empty($canManage) ? 'readonly' : '' ?>>
                <?php if ($fieldError('digits') !== ''): ?><span class="field__hint field__hint--error"><?= e($fieldError('digits')) ?></span><?php endif; ?>
            </div>
            <div class="field form-grid__full">
                <p class="field__hint">
                    <?= e(__('courrier.preview_label')) ?>
                    <?php foreach (CourrierService::SENS as $sens): ?>
                        <?php if (isset($previews[$sens])): ?>
                            <span class="badge badge--neutral"><?= e(__('courrier.sens.' . $sens)) ?> · <?= e((string) $previews[$sens]) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </p>
            </div>
        </div>
    </section>

    <?php if (!empty($canManage)): ?>
        <div class="form-actions">
            <div class="cluster">
                <a class="button button--ghost" href="<?= e(url('/courrier')) ?>"><?= e(__('common.cancel')) ?></a>
                <button class="button button--primary" type="submit"><?= e(__('common.save')) ?></button>
            </div>
        </div>
    <?php endif; ?>
</form>

<section class="card stack-md" aria-labelledby="courrier-counters-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="courrier-counters-title"><?= e(__('courrier.counters_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('courrier.counters_help')) ?></p>
        </div>
        <form class="cluster" method="get" action="<?= e(url('/courrier/reglages')) ?>">
            <label class="u-sr-only" for="courrier-year"><?= e(__('courrier.year')) ?></label>
            <input class="field__control" type="number" id="courrier-year" name="annee" value="<?= e((string) $year) ?>" min="1900" max="2200" style="max-inline-size: 8rem">
            <button class="button button--small button--secondary" type="submit"><?= e(__('courrier.show_year')) ?></button>
        </form>
    </div>

    <?php if ($counterErrors !== []): ?>
        <p class="form-feedback form-feedback--error" role="alert"><?= e(implode(' ', $counterErrors)) ?></p>
    <?php endif; ?>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th scope="col"><?= e(__('courrier.sens_label')) ?></th>
                    <th scope="col"><?= e(__('courrier.counter_last')) ?></th>
                    <th scope="col"><?= e(__('courrier.counter_next')) ?></th>
                    <th scope="col"><?= e(__('courrier.counter_action')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (CourrierService::SENS as $sens): ?>
                    <tr>
                        <td><?= e(__('courrier.sens.' . $sens)) ?></td>
                        <td class="u-numeric"><?= e((string) (int) ($counters[$sens] ?? 0)) ?></td>
                        <td><span class="badge badge--neutral"><?= e((string) ($previews[$sens] ?? '')) ?></span></td>
                        <td>
                            <?php if (!empty($canManage)): ?>
                                <form class="cluster" method="post" action="<?= e(url('/courrier/reglages/compteur')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="sens" value="<?= e($sens) ?>">
                                    <input type="hidden" name="annee" value="<?= e((string) $year) ?>">
                                    <label class="u-sr-only" for="courrier-counter-<?= e($sens) ?>"><?= e(__('courrier.counter_last')) ?></label>
                                    <input class="field__control" type="number" id="courrier-counter-<?= e($sens) ?>" name="dernier" value="<?= e((string) (int) ($counters[$sens] ?? 0)) ?>" min="0" style="max-inline-size: 8rem">
                                    <button class="button button--small button--secondary" type="submit"><?= e(__('courrier.counter_save')) ?></button>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="table-meta" role="note"><?= e(__('courrier.counters_notice', ['year' => (string) $year])) ?></p>
</section>
