<?php
declare(strict_types=1);

use App\Core\Translator;
use Modules\RegistreCourrier\Services\CourrierService;

/**
 * Registre annuel imprimable.
 *
 * Rendu sans le layout applicatif : la page est autonome pour que l’impression
 * (ou « Enregistrer au format PDF » du navigateur) reste nette et lisible en FR/AR.
 */
$rows = is_array($rows ?? null) ? $rows : [];
$year = (int) ($year ?? (int) date('Y'));
$sens = (string) ($sens ?? '');
$cabinetName = (string) ($cabinetName ?? '');
$generatedAt = (string) ($generatedAt ?? '');
$filtered = in_array($sens, CourrierService::SENS, true);
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return '—';
    }
    $time = strtotime($date);

    return $time === false ? '—' : date('d/m/Y', $time);
};
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('courrier.print_title')) ?> — <?= e((string) $year) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="pv-document">
    <main class="app-content" style="max-inline-size: 70rem; margin-inline: auto">
        <div class="cluster cluster--between no-print">
            <a class="button button--ghost" href="<?= e(url('/courrier')) ?>"><?= e(__('courrier.back')) ?></a>
            <button class="button button--primary" type="button" data-print>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('courrier.print_action')) ?>
            </button>
        </div>

        <header class="card stack-md">
            <div class="cluster cluster--between">
                <div>
                    <p class="page-header__eyebrow"><?= $cabinetName !== '' ? e($cabinetName) : e(__('courrier.cabinet')) ?></p>
                    <h1 class="page-header__title"><?= e(__('courrier.print_title')) ?></h1>
                    <p class="page-header__subtitle">
                        <?= e(__('courrier.print_period', ['year' => (string) $year])) ?>
                        <?php if ($filtered): ?> · <?= e(__('courrier.sens.' . $sens)) ?><?php endif; ?>
                    </p>
                </div>
                <div class="u-text-end">
                    <p class="detail-label"><?= e(__('courrier.print_generated')) ?></p>
                    <p><strong><?= e($generatedAt) ?></strong></p>
                </div>
            </div>
            <p class="table-meta" role="note"><?= e(__('courrier.print_notice')) ?></p>
        </header>

        <?php if ($rows === []): ?>
            <section class="card stack-md">
                <p class="muted"><?= e(__('courrier.print_empty')) ?></p>
            </section>
        <?php else: ?>
            <section class="card stack-md">
                <div class="table-wrap">
                    <table class="data-table">
                        <caption class="u-sr-only"><?= e(__('courrier.print_title')) ?> <?= e((string) $year) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col"><?= e(__('courrier.numero')) ?></th>
                                <th scope="col"><?= e(__('courrier.date')) ?></th>
                                <th scope="col"><?= e(__('courrier.correspondant')) ?></th>
                                <th scope="col"><?= e(__('courrier.objet')) ?></th>
                                <th scope="col"><?= e(__('courrier.mode_label')) ?></th>
                                <th scope="col"><?= e(__('courrier.projet')) ?></th>
                                <th scope="col"><?= e(__('courrier.statut_label')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <td class="u-nowrap"><?= e((string) $row['numero']) ?></td>
                                    <td><?= e($formatDate((string) $row['date_courrier'])) ?></td>
                                    <td><?= e((string) $row['correspondant']) ?></td>
                                    <td>
                                        <?= e((string) $row['objet']) ?>
                                        <?php if (($row['reference'] ?? '') !== ''): ?>
                                            <span class="table-meta"><?= e((string) $row['reference']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(__('courrier.mode.' . (string) $row['mode'])) ?></td>
                                    <td>
                                        <?php if (($row['project_reference'] ?? '') !== ''): ?>
                                            <span class="u-nowrap"><?= e((string) $row['project_reference']) ?></span>
                                        <?php elseif (($row['client_name'] ?? '') !== ''): ?>
                                            <?= e((string) $row['client_name']) ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= e(__('courrier.statut.' . (string) $row['statut'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-footer">
                    <span><?= e(__('courrier.print_count', ['count' => (string) count($rows)])) ?></span>
                </div>
            </section>
        <?php endif; ?>

        <footer class="stack-md u-text-center">
            <p class="table-meta"><?= e(__('courrier.print_signature')) ?></p>
        </footer>
    </main>
</body>
</html>
