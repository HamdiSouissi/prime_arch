<?php
declare(strict_types=1);

use App\Core\Translator;
use Modules\PortailClient\Services\PortailService;

/**
 * Espace client public : lecture seule, sans compte ni script.
 *
 * @var string $locale
 * @var string $direction
 * @var array<string,string> $cabinet
 * @var array<string,mixed> $client
 * @var array<int,array<string,mixed>> $projects
 * @var array<int,array<string,mixed>> $documents
 * @var array<int,array<int,array<string,mixed>>> $photosByProject
 * @var bool $canDocuments
 * @var bool $canAmounts
 * @var bool $canPhotos
 * @var string $token
 * @var string $linkScope
 * @var string $expires_on
 * @var int|null $days_left
 * @var string $generatedAt
 */
$projects = is_array($projects ?? null) ? $projects : [];
$documents = is_array($documents ?? null) ? $documents : [];
$photosByProject = is_array($photosByProject ?? null) ? $photosByProject : [];
$client = is_array($client ?? null) ? $client : [];
$cabinet = is_array($cabinet ?? null) ? $cabinet : [];
$locale = (string) ($locale ?? 'fr');
$direction = (string) ($direction ?? 'ltr');
$linkScope = (string) ($linkScope ?? 'project');

$token = (string) ($token ?? '');
$langLinks = [
    'fr' => url('/portail/' . $token . '?lang=fr'),
    'ar' => url('/portail/' . $token . '?lang=ar'),
];

$dateLabel = static function (mixed $value): string {
    $text = is_string($value) ? trim($value) : '';
    $timestamp = $text !== '' ? strtotime($text) : false;

    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
};
$projectStatusBadge = static fn (string $status): string => match ($status) {
    'completed' => 'pa-badge--success',
    'active' => 'pa-badge--info',
    'on_hold' => 'pa-badge--warning',
    'cancelled' => 'pa-badge--error',
    default => 'pa-badge--neutral',
};
$phaseStatusBadge = static fn (string $status): string => match ($status) {
    'completed' => 'pa-badge--success',
    'in_progress' => 'pa-badge--info',
    'skipped' => 'pa-badge--neutral',
    default => 'pa-badge--neutral',
};
$optionLabel = static function (string $reference, string $name): string {
    $reference = trim($reference);
    $name = trim($name);

    return $reference === '' ? $name : ($name === '' ? $reference : $reference . ' — ' . $name);
};

$projectNames = [];
foreach ($projects as $project) {
    $projectNames[(int) $project['id']] = (string) $project['name'];
}
// Portée « client » : tous les documents émis ; portée « projet » : seulement ceux du projet.
$clientDocuments = $linkScope === 'client' ? $documents : [];

$daysLeft = $days_left ?? null;
$expiryText = $daysLeft === null || (int) $daysLeft < 0
    ? __('portail.expired_on', ['date' => $dateLabel($expires_on ?? '')])
    : __('portail.valid_until', ['date' => $dateLabel($expires_on ?? ''), 'days' => (string) (int) $daysLeft]);

$pageTitle = __('portail.portal');
include __DIR__ . '/partials/head.php';
?>
        <p class="pa-banner" role="note">
            <strong><?= e(__('portail.client_space', ['name' => (string) ($client['display_name'] ?? '')])) ?></strong>
            <span><?= e((string) ($client['reference'] ?? '')) ?></span>
            <span><?= e($expiryText) ?></span>
        </p>

        <?php if ($projects === []): ?>
            <section class="pa-card" aria-labelledby="pa-empty-title">
                <h2 class="pa-card__title" id="pa-empty-title"><?= e(__('portail.no_project_title')) ?></h2>
                <p class="pa-muted"><?= e(__('portail.no_project_text')) ?></p>
            </section>
        <?php endif; ?>

        <?php foreach ($projects as $project): ?>
            <?php
            $projectId = (int) $project['id'];
            $phases = is_array($project['phases'] ?? null) ? $project['phases'] : [];
            $progress = is_array($project['progress'] ?? null) ? $project['progress'] : ['percent' => 0, 'weighted' => false];
            $projectDocuments = $linkScope === 'project'
                ? array_values(array_filter($documents, static fn (array $document): bool => (int) ($document['project_id'] ?? 0) === $projectId))
                : [];
            $projectPhotos = is_array($photosByProject[$projectId] ?? null) ? $photosByProject[$projectId] : [];
            ?>
            <section class="pa-card" aria-labelledby="pa-project-<?= $projectId ?>">
                <div class="pa-card__head">
                    <div>
                        <p class="pa-eyebrow"><?= e(__('portail.project')) ?></p>
                        <h2 class="pa-card__title" id="pa-project-<?= $projectId ?>"><?= e((string) $project['name']) ?></h2>
                        <p class="pa-muted"><?= e($optionLabel((string) ($project['reference'] ?? ''), (string) ($project['project_type'] ?? ''))) ?></p>
                    </div>
                    <span class="pa-badge <?= e($projectStatusBadge((string) $project['status'])) ?>"><?= e(__('portail.project_status.' . (string) $project['status'])) ?></span>
                </div>

                <ul class="pa-meta">
                    <?php if (trim((string) ($project['city'] ?? '')) !== ''): ?>
                        <li><?= e(__('portail.city')) ?> : <?= e((string) $project['city']) ?></li>
                    <?php endif; ?>
                    <?php if (($project['start_date'] ?? null) !== null): ?>
                        <li><?= e(__('portail.start_date')) ?> : <span class="pa-num"><?= e($dateLabel($project['start_date'])) ?></span></li>
                    <?php endif; ?>
                    <?php if (($project['target_end_date'] ?? null) !== null): ?>
                        <li><?= e(__('portail.target_end_date')) ?> : <span class="pa-num"><?= e($dateLabel($project['target_end_date'])) ?></span></li>
                    <?php endif; ?>
                    <li><?= e(__('portail.updated_at')) ?> : <span class="pa-num"><?= e($dateLabel($project['updated_at'] ?? null)) ?></span></li>
                </ul>

                <?php if (!empty($progress['weighted'])): ?>
                    <div class="pa-progress">
                        <div class="pa-progress__label">
                            <span><?= e(__('portail.progress')) ?></span>
                            <strong class="pa-num"><?= (int) $progress['percent'] ?> %</strong>
                        </div>
                        <div class="pa-progress__track" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                             aria-valuenow="<?= (int) $progress['percent'] ?>"
                             aria-label="<?= e(__('portail.progress_of', ['project' => (string) $project['name']])) ?>">
                            <div class="pa-progress__bar" style="inline-size: <?= (int) $progress['percent'] ?>%"></div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="pa-muted"><?= e(__('portail.progress_unavailable')) ?></p>
                <?php endif; ?>

                <?php if ($phases !== []): ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <caption class="pa-sr-only"><?= e(__('portail.phases_of', ['project' => (string) $project['name']])) ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?= e(__('portail.phase')) ?></th>
                                    <th scope="col"><?= e(__('portail.phase_status_label')) ?></th>
                                    <th scope="col"><?= e(__('portail.advancement')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($phases as $phase): ?>
                                    <tr>
                                        <td><?= e((string) $phase['phase_name']) ?></td>
                                        <td>
                                            <span class="pa-badge <?= e($phaseStatusBadge((string) $phase['status'])) ?>">
                                                <?= e(__('portail.phase_status.' . (string) $phase['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="pa-num"><?= PortailService::clampPercent($phase['progress_percent'] ?? 0) ?> %</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($canDocuments && $linkScope === 'project'): ?>
                    <h3 class="pa-subtitle"><?= e(__('portail.documents')) ?></h3>
                    <?php if ($projectDocuments === []): ?>
                        <p class="pa-empty"><?= e(__('portail.no_documents')) ?></p>
                    <?php else: ?>
                        <div class="pa-table-wrap">
                            <table class="pa-table">
                                <caption class="pa-sr-only"><?= e(__('portail.documents_of', ['project' => (string) $project['name']])) ?></caption>
                                <thead>
                                    <tr>
                                        <th scope="col"><?= e(__('portail.document_number')) ?></th>
                                        <th scope="col"><?= e(__('portail.document_type')) ?></th>
                                        <th scope="col"><?= e(__('portail.issue_date')) ?></th>
                                        <th scope="col"><?= e(__('portail.due_date')) ?></th>
                                        <?php if ($canAmounts): ?><th scope="col"><?= e(__('portail.total')) ?></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectDocuments as $document): ?>
                                        <tr>
                                            <td class="pa-num"><?= e((string) ($document['document_number'] ?? '—')) ?></td>
                                            <td><?= e(__('portail.document_type.' . (string) $document['document_type'])) ?></td>
                                            <td class="pa-num"><?= e($dateLabel($document['issue_date'] ?? null)) ?></td>
                                            <td class="pa-num"><?= e($dateLabel($document['due_date'] ?? null)) ?></td>
                                            <?php if ($canAmounts): ?>
                                                <td class="pa-num"><?= e(money_format_tnd((string) ($document['total_amount'] ?? '0.000'))) ?></td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($canPhotos && $projectPhotos !== []): ?>
                    <h3 class="pa-subtitle"><?= e(__('portail.photos')) ?></h3>
                    <ul class="pa-photos">
                        <?php foreach ($projectPhotos as $photo): ?>
                            <li class="pa-photo">
                                <figure>
                                    <img src="<?= e(url('/portail/' . $token . '/photo/' . (int) $photo['id'])) ?>"
                                         alt="<?= e(__('portail.photo_alt', ['project' => (string) $project['name']])) ?>"
                                         width="<?= (int) ($photo['width'] ?? 0) ?>" height="<?= (int) ($photo['height'] ?? 0) ?>"
                                         loading="lazy" decoding="async">
                                    <figcaption><?= e($dateLabel($photo['created_at'] ?? null)) ?></figcaption>
                                </figure>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>

        <?php if ($canDocuments && $linkScope === 'client'): ?>
            <section class="pa-card" aria-labelledby="pa-documents-title">
                <div class="pa-card__head">
                    <div>
                        <p class="pa-eyebrow"><?= e(__('portail.documents')) ?></p>
                        <h2 class="pa-card__title" id="pa-documents-title"><?= e(__('portail.documents_title')) ?></h2>
                    </div>
                </div>
                <p class="pa-muted"><?= e(__('portail.documents_help')) ?></p>
                <?php if ($clientDocuments === []): ?>
                    <p class="pa-empty"><?= e(__('portail.no_documents')) ?></p>
                <?php else: ?>
                    <div class="pa-table-wrap">
                        <table class="pa-table">
                            <caption class="pa-sr-only"><?= e(__('portail.documents_title')) ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col"><?= e(__('portail.document_number')) ?></th>
                                    <th scope="col"><?= e(__('portail.document_type')) ?></th>
                                    <th scope="col"><?= e(__('portail.project')) ?></th>
                                    <th scope="col"><?= e(__('portail.issue_date')) ?></th>
                                    <th scope="col"><?= e(__('portail.due_date')) ?></th>
                                    <?php if ($canAmounts): ?><th scope="col"><?= e(__('portail.total')) ?></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientDocuments as $document): ?>
                                    <tr>
                                        <td class="pa-num"><?= e((string) ($document['document_number'] ?? '—')) ?></td>
                                        <td><?= e(__('portail.document_type.' . (string) $document['document_type'])) ?></td>
                                        <td><?= e($projectNames[(int) ($document['project_id'] ?? 0)] ?? '—') ?></td>
                                        <td class="pa-num"><?= e($dateLabel($document['issue_date'] ?? null)) ?></td>
                                        <td class="pa-num"><?= e($dateLabel($document['due_date'] ?? null)) ?></td>
                                        <?php if ($canAmounts): ?>
                                            <td class="pa-num"><?= e(money_format_tnd((string) ($document['total_amount'] ?? '0.000'))) ?></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <?php if (!$canAmounts): ?>
                    <p class="pa-muted"><?= e(__('portail.amounts_hidden')) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <p class="pa-banner" role="note">
            <?= e(__('portail.readonly_notice')) ?>
            <?php if ($client !== [] && trim((string) ($client['city'] ?? '')) !== ''): ?>
                <span><?= e(__('portail.city')) ?> : <?= e((string) $client['city']) ?></span>
            <?php endif; ?>
        </p>
<?php
$generatedAt = (string) ($generatedAt ?? '');
include __DIR__ . '/partials/foot.php';
