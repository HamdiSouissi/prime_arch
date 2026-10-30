<?php
declare(strict_types=1);

use App\Core\Translator;
use Modules\MetreQuantitatif\Services\Decimal;
use Modules\MetreQuantitatif\Services\MetreService;

/**
 * DQE imprimable.
 *
 * Rendu sans layout applicatif : le document est autonome pour que l’impression
 * (ou « Enregistrer au format PDF » du navigateur) reste nette en FR comme en AR.
 * Les marges internes du cabinet ne figurent pas sur ce document destiné au client.
 */
$project = is_array($project ?? null) ? $project : [];
$summary = is_array($summary ?? null) ? $summary : [];
$cabinet = is_array($cabinet ?? null) ? $cabinet : [];
$lots = is_array($summary['lots'] ?? null) ? $summary['lots'] : [];
$cabinetName = trim((string) ($cabinet['name'] ?? '')) !== '' ? (string) $cabinet['name'] : (string) config('app.name', 'Prime Arch');
$cabinetAddress = trim((string) ($cabinet['address'] ?? ''));
$cabinetPhone = trim((string) ($cabinet['phone'] ?? ''));
$cabinetEmail = trim((string) ($cabinet['email'] ?? ''));
$projectId = (int) ($project['id'] ?? 0);
$quantityLabel = static function (string $value): string {
    $millimes = Decimal::parse($value);

    return $millimes === null ? $value : Decimal::label($millimes);
};
?>
<!DOCTYPE html>
<html lang="<?= e(Translator::locale()) ?>" dir="<?= e(Translator::direction()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e(__('metre.dqe.title')) ?> — <?= e((string) ($project['name'] ?? '')) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/module-assets/metre-quantitatif?file=css/module.css')) ?>">
</head>
<body class="dqe-document">
    <main class="app-content" style="max-inline-size: 60rem; margin-inline: auto">
        <div class="cluster cluster--between no-print">
            <a class="button button--ghost" href="<?= e(url('/metre/' . $projectId)) ?>"><?= e(__('metre.back_to_metre')) ?></a>
            <button class="button button--primary" type="button" data-print>
                <svg class="icon icon--small" aria-hidden="true"><use href="<?= e(asset('img/icons.svg')) ?>#printer"></use></svg><?= e(__('metre.print')) ?>
            </button>
        </div>

        <header class="card stack-md">
            <div class="cluster cluster--between">
                <div>
                    <p class="page-header__eyebrow"><?= e($cabinetName) ?></p>
                    <h1 class="page-header__title"><?= e(__('metre.dqe.title')) ?></h1>
                    <?php if ($cabinetAddress !== '' || $cabinetPhone !== '' || $cabinetEmail !== ''): ?>
                        <p class="table-meta">
                            <?= e(implode(' · ', array_filter([$cabinetAddress, $cabinetPhone, $cabinetEmail], static fn (string $value): bool => $value !== ''))) ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="u-text-end">
                    <p class="detail-label"><?= e(__('metre.dqe.date')) ?></p>
                    <p><strong><?= e(date('d/m/Y')) ?></strong></p>
                </div>
            </div>

            <dl class="detail-list">
                <div class="detail-list__item"><dt><?= e(__('metre.project')) ?></dt><dd><?= e((string) ($project['name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('metre.reference')) ?></dt><dd><?= e((string) ($project['reference'] ?? '—')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('metre.client')) ?></dt><dd><?= e((string) ($project['client_name'] ?? '')) ?></dd></div>
                <div class="detail-list__item"><dt><?= e(__('metre.city')) ?></dt><dd><?= ($project['city'] ?? '') !== '' ? e((string) $project['city']) : '—' ?></dd></div>
            </dl>
        </header>

        <?php if ($lots === []): ?>
            <section class="card stack-md">
                <p class="muted"><?= e(__('metre.dqe.empty')) ?></p>
            </section>
        <?php endif; ?>

        <?php foreach ($lots as $row): ?>
            <?php $postes = is_array($row['postes'] ?? null) ? $row['postes'] : []; ?>
            <section class="card stack-md">
                <div class="cluster cluster--between">
                    <h2 class="card-header__title"><?= e((string) $row['lot']['name']) ?></h2>
                    <span class="detail-label"><?= e(__('metre.subtotal')) ?> · <?= e(money_format_tnd((string) $row['total'])) ?></span>
                </div>
                <?php if ($postes === []): ?>
                    <p class="muted"><?= e(__('metre.no_postes')) ?></p>
                <?php else: ?>
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th scope="col"><?= e(__('metre.code')) ?></th>
                                    <th scope="col"><?= e(__('metre.designation')) ?></th>
                                    <th scope="col"><?= e(__('metre.unit')) ?></th>
                                    <th scope="col"><?= e(__('metre.quantity')) ?></th>
                                    <th scope="col"><?= e(__('metre.unit_price')) ?></th>
                                    <th scope="col"><?= e(__('metre.line_total')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($postes as $poste): ?>
                                    <tr>
                                        <td><?= (string) ($poste['code'] ?? '') !== '' ? e((string) $poste['code']) : '—' ?></td>
                                        <td><?= e((string) $poste['designation']) ?></td>
                                        <td><?= e(MetreService::unitLabel((string) $poste['unit'])) ?></td>
                                        <td class="u-numeric"><?= e($quantityLabel((string) $poste['quantity'])) ?></td>
                                        <td class="u-numeric"><?= e(money_format_tnd((string) $poste['unit_price'])) ?></td>
                                        <td class="u-numeric"><?= e(money_format_tnd((string) $poste['total'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($lots !== []): ?>
            <section class="card stack-md dqe-total">
                <div class="cluster cluster--between">
                    <h2 class="card-header__title"><?= e(__('metre.grand_total')) ?></h2>
                    <strong class="dqe-total__amount"><?= e(money_format_tnd((string) ($summary['total'] ?? '0.000'))) ?></strong>
                </div>
                <p class="table-meta"><?= e(__('metre.dqe.total_note')) ?></p>
            </section>
        <?php endif; ?>

        <footer class="card stack-md">
            <p class="table-meta"><?= e(__('metre.dqe.disclaimer')) ?></p>
            <div class="dqe-signature">
                <p class="detail-label"><?= e(__('metre.dqe.architect')) ?></p>
                <p class="dqe-signature__line"></p>
            </div>
        </footer>
    </main>
    <script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
