<?php
declare(strict_types=1);

use App\Core\View;

$rules = is_array($rules ?? null) ? $rules : [];
$tones = is_array($tones ?? null) ? $tones : [];
$templates = is_array($templates ?? null) ? $templates : [];
$defaults = is_array($defaults ?? null) ? $defaults : [];
$exclusions = is_array($exclusions ?? null) ? $exclusions : [];
$ruleErrors = is_array($ruleErrors ?? null) ? $ruleErrors : [];
$ruleValues = is_array($ruleValues ?? null) ? $ruleValues : [];
$templateErrors = is_array($templateErrors ?? null) ? $templateErrors : [];
$templateValues = is_array($templateValues ?? null) ? $templateValues : [];

$formatDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    $time = strtotime($value);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<?php View::partial('partials/page-header', [
    'pageTitle' => __('relance.rules_title'),
    'eyebrow' => __('relance.eyebrow'),
    'subtitle' => __('relance.rules_intro'),
    'secondaryAction' => ['label' => __('relance.back'), 'icon' => 'arrow-left', 'url' => url('/relances')],
]) ?>

<?php if (!empty($notice)): ?><p class="form-feedback form-feedback--success" role="status"><?= e($notice) ?></p><?php endif; ?>
<?php if (!empty($error)): ?><p class="form-feedback form-feedback--error" role="alert"><?= e($error) ?></p><?php endif; ?>

<section class="card stack-md" id="regles" aria-labelledby="relance-rules-list-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-rules-list-title"><?= e(__('relance.rules_list_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.rules_list_help')) ?></p>
        </div>
    </div>

    <?php if ($rules === []): ?>
        <p class="muted"><?= e(__('relance.no_rules')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.rule_name')) ?></th>
                        <th scope="col"><?= e(__('relance.rule_delay')) ?></th>
                        <th scope="col"><?= e(__('relance.rule_min_amount')) ?></th>
                        <th scope="col"><?= e(__('relance.tone')) ?></th>
                        <th scope="col"><?= e(__('relance.rule_state')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td>
                                <span class="table-primary__text">
                                    <strong class="table-primary__name"><?= e((string) $rule['name']) ?></strong>
                                    <span class="table-primary__meta"><?= e(__('relance.rule_order', ['order' => (string) $rule['sort_order']])) ?></span>
                                </span>
                            </td>
                            <td><?= e(__('relance.rule_delay_value', ['days' => (string) $rule['delay_days']])) ?></td>
                            <td class="u-numeric"><?= e(money_format_tnd((string) $rule['min_amount'])) ?></td>
                            <td><span class="badge badge--info"><?= e(__('relance.tone_value.' . (string) $rule['tone'])) ?></span></td>
                            <td>
                                <?php if ((bool) $rule['is_active']): ?>
                                    <span class="badge badge--success"><?= e(__('relance.rule_active')) ?></span>
                                <?php else: ?>
                                    <span class="badge badge--neutral"><?= e(__('relance.rule_paused')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($canManage)): ?>
                                    <div class="cluster">
                                        <form method="post" action="<?= e(url('/relances/regles/' . (int) $rule['id'])) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="toggle">
                                            <button class="button button--small button--secondary" type="submit">
                                                <?= e((bool) $rule['is_active'] ? __('relance.pause_rule') : __('relance.resume_rule')) ?>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(url('/relances/regles/' . (int) $rule['id'])) ?>"
                                              data-confirm="<?= e(__('relance.confirm_delete_rule')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete">
                                            <button class="button button--small button--ghost" type="submit">
                                                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#trash"></use></svg><?= e(__('common.delete')) ?>
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if (!empty($canManage)): ?>
            <?php foreach ($rules as $rule): ?>
                <details class="stack-md">
                    <summary class="text-callout"><?= e(__('relance.edit_rule', ['name' => (string) $rule['name']])) ?></summary>
                    <form class="form-grid" method="post" action="<?= e(url('/relances/regles')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $rule['id'] ?>">
                        <div class="field">
                            <label class="field__label" for="rule-name-<?= (int) $rule['id'] ?>"><?= e(__('relance.rule_name')) ?></label>
                            <input class="field__control" type="text" id="rule-name-<?= (int) $rule['id'] ?>" name="name" maxlength="120" value="<?= e((string) $rule['name']) ?>" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="rule-delay-<?= (int) $rule['id'] ?>"><?= e(__('relance.rule_delay')) ?></label>
                            <input class="field__control" type="number" min="0" max="3650" id="rule-delay-<?= (int) $rule['id'] ?>" name="delay_days" value="<?= (int) $rule['delay_days'] ?>" required>
                        </div>
                        <div class="field">
                            <label class="field__label" for="rule-min-<?= (int) $rule['id'] ?>"><?= e(__('relance.rule_min_amount')) ?></label>
                            <input class="field__control" type="text" inputmode="decimal" id="rule-min-<?= (int) $rule['id'] ?>" name="min_amount" value="<?= e((string) $rule['min_amount']) ?>">
                        </div>
                        <div class="field">
                            <label class="field__label" for="rule-tone-<?= (int) $rule['id'] ?>"><?= e(__('relance.tone')) ?></label>
                            <select class="field__control" id="rule-tone-<?= (int) $rule['id'] ?>" name="tone">
                                <?php foreach ($tones as $tone): ?>
                                    <option value="<?= e($tone) ?>" <?= (string) $rule['tone'] === $tone ? 'selected' : '' ?>><?= e(__('relance.tone_value.' . $tone)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field">
                            <label class="field__label" for="rule-order-<?= (int) $rule['id'] ?>"><?= e(__('relance.rule_order_label')) ?></label>
                            <input class="field__control" type="number" min="0" max="999" id="rule-order-<?= (int) $rule['id'] ?>" name="sort_order" value="<?= (int) $rule['sort_order'] ?>">
                        </div>
                        <div class="field">
                            <input type="hidden" name="is_active" value="0">
                            <label class="check-label">
                                <input type="checkbox" name="is_active" value="1" <?= (bool) $rule['is_active'] ? 'checked' : '' ?>>
                                <?= e(__('relance.rule_active')) ?>
                            </label>
                        </div>
                        <div class="form-actions form-grid__full">
                            <button class="button button--secondary" type="submit"><?= e(__('common.save')) ?></button>
                        </div>
                    </form>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($canManage)): ?>
        <form class="stack-md" method="post" action="<?= e(url('/relances/regles')) ?>" id="relance-add-rule">
            <?= csrf_field() ?>
            <h3 class="section-heading__title"><?= e(__('relance.add_rule')) ?></h3>
            <?php if (!empty($ruleErrors)): ?>
                <p class="form-feedback form-feedback--error" role="alert"><?= e(__('relance.fix_errors')) ?></p>
            <?php endif; ?>
            <div class="form-grid">
                <div class="field">
                    <label class="field__label" for="relance-rule-name"><?= e(__('relance.rule_name')) ?></label>
                    <input class="field__control" type="text" id="relance-rule-name" name="name" maxlength="120"
                           value="<?= e((string) ($ruleValues['name'] ?? '')) ?>" placeholder="<?= e(__('relance.rule_name_placeholder')) ?>">
                    <?php if (!empty($ruleErrors['name'])): ?><span class="field__hint field__hint--error"><?= e($ruleErrors['name']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="relance-rule-delay"><?= e(__('relance.rule_delay')) ?></label>
                    <input class="field__control" type="number" min="0" max="3650" id="relance-rule-delay" name="delay_days"
                           value="<?= e((string) ($ruleValues['delay_days'] ?? '7')) ?>">
                    <span class="field__hint"><?= e(__('relance.rule_delay_hint')) ?></span>
                    <?php if (!empty($ruleErrors['delay_days'])): ?><span class="field__hint field__hint--error"><?= e($ruleErrors['delay_days']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="relance-rule-min"><?= e(__('relance.rule_min_amount')) ?></label>
                    <input class="field__control" type="text" inputmode="decimal" id="relance-rule-min" name="min_amount"
                           value="<?= e((string) ($ruleValues['min_amount'] ?? '')) ?>" placeholder="0.000">
                    <span class="field__hint"><?= e(__('relance.amount_hint')) ?></span>
                    <?php if (!empty($ruleErrors['min_amount'])): ?><span class="field__hint field__hint--error"><?= e($ruleErrors['min_amount']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="relance-rule-tone"><?= e(__('relance.tone')) ?></label>
                    <select class="field__control" id="relance-rule-tone" name="tone">
                        <?php foreach ($tones as $tone): ?>
                            <option value="<?= e($tone) ?>" <?= (string) ($ruleValues['tone'] ?? 'courtesy') === $tone ? 'selected' : '' ?>><?= e(__('relance.tone_value.' . $tone)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($ruleErrors['tone'])): ?><span class="field__hint field__hint--error"><?= e($ruleErrors['tone']) ?></span><?php endif; ?>
                </div>
                <div class="field">
                    <label class="field__label" for="relance-rule-order"><?= e(__('relance.rule_order_label')) ?></label>
                    <input class="field__control" type="number" min="0" max="999" id="relance-rule-order" name="sort_order"
                           value="<?= e((string) ($ruleValues['sort_order'] ?? '0')) ?>">
                </div>
                <div class="field">
                    <input type="hidden" name="is_active" value="0">
                    <label class="check-label">
                        <input type="checkbox" name="is_active" value="1" checked>
                        <?= e(__('relance.rule_active')) ?>
                    </label>
                </div>
            </div>
            <div class="form-actions">
                <button class="button button--primary" type="submit"><?= e(__('relance.add_rule_submit')) ?></button>
            </div>
        </form>
    <?php endif; ?>
    <p class="table-meta"><?= e(__('relance.rules_warning')) ?></p>
</section>

<section class="card stack-md" id="modeles" aria-labelledby="relance-models-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-models-title"><?= e(__('relance.models_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.models_help')) ?></p>
        </div>
    </div>

    <?php if (!empty($templateErrors)): ?>
        <p class="form-feedback form-feedback--error" role="alert"><?= e(__('relance.fix_errors')) ?></p>
    <?php endif; ?>

    <p class="table-meta"><?= e(__('relance.tokens_help')) ?></p>

    <?php foreach ($tones as $tone): ?>
        <div class="content-grid">
            <?php foreach (['fr', 'ar'] as $locale): ?>
                <?php
                $key = $tone . '|' . $locale;
                $custom = $templates[$key] ?? null;
                $default = $defaults[$key] ?? ['subject' => '', 'body' => ''];
                $subjectValue = is_array($templateValues) && (string) ($templateValues['tone'] ?? '') === $tone
                    && (string) ($templateValues['locale'] ?? '') === $locale
                    ? (string) ($templateValues['subject'] ?? '')
                    : (string) (is_array($custom) ? $custom['subject'] : $default['subject']);
                $bodyValue = is_array($templateValues) && (string) ($templateValues['tone'] ?? '') === $tone
                    && (string) ($templateValues['locale'] ?? '') === $locale
                    ? (string) ($templateValues['body'] ?? '')
                    : (string) (is_array($custom) ? $custom['body'] : $default['body']);
                ?>
                <form class="card stack-md" lang="<?= e($locale) ?>" method="post" action="<?= e(url('/relances/modeles')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="tone" value="<?= e($tone) ?>">
                    <input type="hidden" name="locale" value="<?= e($locale) ?>">
                    <div class="card-header">
                        <h3 class="card-header__title"><?= e(__('relance.tone_value.' . $tone)) ?> · <?= e(strtoupper($locale)) ?></h3>
                        <span class="badge <?= is_array($custom) ? 'badge--info' : 'badge--neutral' ?>">
                            <?= e(is_array($custom) ? __('relance.custom_model') : __('relance.default_model')) ?>
                        </span>
                    </div>
                    <div class="field">
                        <label class="field__label" for="model-subject-<?= e($tone . '-' . $locale) ?>"><?= e(__('relance.letter_subject')) ?></label>
                        <input class="field__control" type="text" id="model-subject-<?= e($tone . '-' . $locale) ?>" name="subject"
                               maxlength="180" value="<?= e($subjectValue) ?>" dir="<?= $locale === 'ar' ? 'rtl' : 'ltr' ?>" <?= empty($canManage) ? 'readonly' : '' ?>>
                        <?php if (!empty($templateErrors['subject'])): ?><span class="field__hint field__hint--error"><?= e($templateErrors['subject']) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label class="field__label" for="model-body-<?= e($tone . '-' . $locale) ?>"><?= e(__('relance.letter_body')) ?></label>
                        <textarea class="field__control" id="model-body-<?= e($tone . '-' . $locale) ?>" name="body" rows="9"
                                  maxlength="4000" dir="<?= $locale === 'ar' ? 'rtl' : 'ltr' ?>" <?= empty($canManage) ? 'readonly' : '' ?>><?= e($bodyValue) ?></textarea>
                        <?php if (!empty($templateErrors['body'])): ?><span class="field__hint field__hint--error"><?= e($templateErrors['body']) ?></span><?php endif; ?>
                    </div>
                    <?php if (!empty($canManage)): ?>
                        <div class="form-actions">
                            <button class="button button--small button--secondary" type="submit" name="action" value="save"><?= e(__('common.save')) ?></button>
                            <?php if (is_array($custom)): ?>
                                <button class="button button--small button--ghost" type="submit" name="action" value="reset"><?= e(__('relance.reset_model')) ?></button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>

<section class="card stack-md" id="exclusions" aria-labelledby="relance-exclusions-title">
    <div class="card-header">
        <div>
            <h2 class="card-header__title" id="relance-exclusions-title"><?= e(__('relance.exclusions_title')) ?></h2>
            <p class="section-heading__note"><?= e(__('relance.exclusions_help')) ?></p>
        </div>
    </div>

    <?php if ($exclusions === []): ?>
        <p class="muted"><?= e(__('relance.no_exclusions')) ?></p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col"><?= e(__('relance.client')) ?></th>
                        <th scope="col"><?= e(__('relance.exclusion_reason')) ?></th>
                        <th scope="col"><?= e(__('relance.excluded_on')) ?></th>
                        <th scope="col"><?= e(__('common.actions')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exclusions as $exclusion): ?>
                        <tr>
                            <td><?= e((string) $exclusion['client_name']) ?></td>
                            <td><?= e((string) ($exclusion['reason'] ?? '—')) ?></td>
                            <td><?= e($formatDate((string) $exclusion['created_at'])) ?></td>
                            <td>
                                <?php if (!empty($canManage)): ?>
                                    <form method="post" action="<?= e(url('/relances/clients/' . (int) $exclusion['client_id'] . '/exclusion')) ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="include">
                                        <input type="hidden" name="back" value="/relances/regles">
                                        <button class="button button--small button--secondary" type="submit"><?= e(__('relance.include_client')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
