<?php
declare(strict_types=1);

use App\Core\Translator;
use Modules\SuiviChantier\Services\ChantierService;

/**
 * Procès-verbal imprimable.
 *
 * Rendu sans le layout applicatif : la page est autonome afin que l’impression
 * (ou « Enregistrer au format PDF » du navigateur) reste nette et lisible en FR/AR.
 */
$project = is_array($project ?? null) ? $project : [];
$reception = is_array($reception ?? null) ? $reception : [];
$reserves = is_array($reserves ?? null) ? $reserves : [];
$cabinetName = (string) ($cabinetName ?? '');
$receptionDate = (string) ($reception['reception_date'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('chantier.pv.title')) ?> — <?= e((string) ($project['name'] ?? '')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/module-assets/suivi-chantier?file=css/module.css')) ?>">
</head>
<body class="pv-document">
    <main class="app-content" style="max-inline-size: 52rem; margin-inline: auto">
        <div class="cluster cluster--between no-print">
            <a class="button button--ghost" href="<?= e(url('/chantier/' . (int) ($project['id'] ?? 0))) ?>"><?= e(__('chantier.back')) ?></a>
            <button class="button button--primary" type="button" data-print>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('chantier.pv.print')) ?>
            </button>
        </div>

        <header class="card stack-md">
            <div class="cluster cluster--between">
                <div>
                    <p class="page-header__eyebrow"><?= $cabinetName !== '' ? e($cabinetName) : e(__('chantier.pv.cabinet')) ?></p>
                    <h1 class="page-header__title"><?= e(__('chantier.kind.' . (string) ($reception['kind'] ?? 'provisional'))) ?></h1>
                </div>
                <div class="u-text-end">
                    <p class="detail-label"><?= e(__('chantier.reception_date')) ?></p>
                    <p><strong><?= e($receptionDate !== '' ? date('d/m/Y', strtotime($receptionDate)) : '—') ?></strong></p>
                </div>
            </div>

            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('chantier.project')) ?></dt><dd><?= e((string) ($project['name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.reference')) ?></dt><dd><?= e((string) ($project['reference'] ?? '—')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.client')) ?></dt><dd><?= e((string) ($project['client_name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('chantier.city')) ?></dt><dd><?= ($project['city'] ?? '') !== '' ? e((string) $project['city']) : '—' ?></dd></div>
            </dl>

            <?php if (($reception['notes'] ?? '') !== ''): ?>
                <div class="document-text-block">
                    <h3><?= e(__('chantier.reception_notes')) ?></h3>
                    <p><?= nl2br(e((string) $reception['notes'])) ?></p>
                </div>
            <?php endif; ?>
        </header>

        <section class="card stack-md">
            <h2 class="card-header__title"><?= e(__('chantier.pv.reserves')) ?></h2>
            <?php if ($reserves === []): ?>
                <p class="muted"><?= e(__('chantier.pv.no_reserves')) ?></p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('chantier.reference')) ?></th>
                                <th scope="col"><?= e(__('chantier.description')) ?></th>
                                <th scope="col"><?= e(__('chantier.severity')) ?></th>
                                <th scope="col"><?= e(__('chantier.opened_on')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reserves as $reserve): ?>
                                <tr>
                                    <td><?= e((string) $reserve['reference']) ?></td>
                                    <td><?= e((string) $reserve['description']) ?></td>
                                    <td><?= e(__('chantier.severity_value.' . (string) $reserve['severity'])) ?></td>
                                    <td><?= e(date('d/m/Y', strtotime((string) $reserve['opened_on']))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="table-meta"><?= e(__('chantier.pv.reserves_note', ['count' => (string) count($reserves)])) ?></p>
            <?php endif; ?>
        </section>

        <section class="card stack-md">
            <div class="pv-signatures">
                <div>
                    <p class="detail-label"><?= e(__('chantier.pv.owner')) ?></p>
                    <p class="pv-signature-line"></p>
                </div>
                <div>
                    <p class="detail-label"><?= e(__('chantier.pv.architect')) ?></p>
                    <p class="pv-signature-line"></p>
                </div>
                <div>
                    <p class="detail-label"><?= e(__('chantier.pv.contractor')) ?></p>
                    <p class="pv-signature-line"></p>
                </div>
            </div>
            <p class="table-meta"><?= e(__('chantier.pv.disclaimer')) ?></p>
        </section>
    </main>
    <script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
