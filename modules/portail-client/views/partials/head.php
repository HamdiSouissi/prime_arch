<?php
declare(strict_types=1);

/**
 * En-tête autonome de l’espace client public.
 *
 * La page ne charge aucune ressource du noyau (les assets de l’application exigent une session)
 * et n’embarque aucun script : la CSP peut donc interdire tout script, toute police distante et
 * tout formulaire. Les styles sont écrits en propriétés logiques pour que le sens arabe (rtl)
 * fonctionne sans feuille supplémentaire.
 *
 * @var string $locale
 * @var string $direction
 * @var string $pageTitle
 * @var array<string,string> $cabinet
 */
$paLocale = ($locale ?? 'fr') === 'ar' ? 'ar' : 'fr';
$paDir = ($direction ?? 'ltr') === 'rtl' ? 'rtl' : 'ltr';
$paCabinetName = (string) ($cabinet['name'] ?? '');
?>
<!DOCTYPE html>
<html lang="<?= e($paLocale) ?>" dir="<?= e($paDir) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="referrer" content="no-referrer">
    <meta name="color-scheme" content="light dark">
    <title><?= e(($pageTitle ?? '') . ($paCabinetName !== '' ? ' · ' . $paCabinetName : '')) ?></title>
    <style>
        :root {
            color-scheme: light dark;
            --pa-canvas: #F4F7FF;
            --pa-surface: #FFFFFF;
            --pa-surface-muted: #F6F8FD;
            --pa-ink: #25334B;
            --pa-ink-soft: #53627A;
            --pa-border: rgba(91, 112, 149, 0.20);
            --pa-primary: #2E67F2;
            --pa-primary-tint: #EAF1FF;
            --pa-success: #176B49;
            --pa-success-tint: #E8F8EF;
            --pa-warning: #805900;
            --pa-warning-tint: #FFF6D9;
            --pa-info: #1D4FA8;
            --pa-info-tint: #EAF1FF;
            --pa-error: #B4234A;
            --pa-error-tint: #FFF0F4;
            --pa-radius: 16px;
            --pa-radius-sm: 10px;
            --pa-font: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans Arabic", sans-serif;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --pa-canvas: #10151F;
                --pa-surface: #171E2B;
                --pa-surface-muted: #1D2635;
                --pa-ink: #E7ECF7;
                --pa-ink-soft: #A9B6CC;
                --pa-border: rgba(160, 178, 208, 0.22);
                --pa-primary: #7FA5FF;
                --pa-primary-tint: rgba(46, 103, 242, 0.22);
                --pa-success: #7FE0B0;
                --pa-success-tint: rgba(23, 107, 73, 0.35);
                --pa-warning: #FFD98A;
                --pa-warning-tint: rgba(128, 89, 0, 0.32);
                --pa-info: #A9C6FF;
                --pa-info-tint: rgba(29, 79, 168, 0.30);
                --pa-error: #FFB3C7;
                --pa-error-tint: rgba(180, 35, 74, 0.32);
            }
        }

        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            padding: clamp(1rem, 3vw, 2.5rem) clamp(0.75rem, 3vw, 2rem) 2.5rem;
            background: var(--pa-canvas);
            color: var(--pa-ink);
            font-family: var(--pa-font);
            font-size: 16px;
            line-height: 1.55;
            -webkit-text-size-adjust: 100%;
        }
        .pa-shell { max-inline-size: 68rem; margin-inline: auto; display: grid; gap: 1.5rem; }
        .pa-main { display: grid; gap: 1.25rem; }
        .pa-sr-only {
            position: absolute; overflow: hidden; inline-size: 1px; block-size: 1px;
            padding: 0; border: 0; clip: rect(0, 0, 0, 0); white-space: nowrap;
        }
        .pa-skip {
            position: absolute; inset-inline-start: -999px; inset-block-start: 0.5rem;
            padding: 0.5rem 0.75rem; border-radius: var(--pa-radius-sm);
            background: var(--pa-primary); color: #FFFFFF; z-index: 10;
        }
        .pa-skip:focus { inset-inline-start: 0.5rem; }

        .pa-header {
            display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
            gap: 1rem; padding: 1rem 1.25rem; border: 1px solid var(--pa-border);
            border-radius: var(--pa-radius); background: var(--pa-surface);
        }
        .pa-brand { display: grid; gap: 0.15rem; min-inline-size: 0; }
        .pa-brand__name { font-weight: 700; font-size: 1.05rem; overflow-wrap: anywhere; }
        .pa-brand__tag { color: var(--pa-ink-soft); font-size: 0.78rem; letter-spacing: 0.08em; text-transform: uppercase; }
        .pa-langs { display: flex; gap: 0.25rem; }
        .pa-langs a {
            display: inline-flex; align-items: center; justify-content: center;
            min-inline-size: 2.75rem; min-block-size: 2.25rem; padding-inline: 0.5rem;
            border: 1px solid var(--pa-border); border-radius: 999px;
            color: var(--pa-ink-soft); font-size: 0.82rem; text-decoration: none;
        }
        .pa-langs a[aria-current="true"] {
            border-color: transparent; background: var(--pa-primary-tint); color: var(--pa-primary); font-weight: 700;
        }

        .pa-banner {
            display: flex; flex-wrap: wrap; gap: 0.4rem 1rem; align-items: baseline;
            padding: 0.85rem 1.1rem; border-radius: var(--pa-radius);
            border: 1px solid var(--pa-border); background: var(--pa-surface-muted);
            color: var(--pa-ink-soft); font-size: 0.85rem;
        }
        .pa-banner strong { color: var(--pa-ink); }

        .pa-card {
            display: grid; gap: 1rem; padding: clamp(1rem, 2.2vw, 1.5rem);
            border: 1px solid var(--pa-border); border-radius: var(--pa-radius);
            background: var(--pa-surface);
        }
        .pa-card__head { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem 1rem; align-items: flex-start; }
        .pa-card__title { margin: 0; font-size: 1.15rem; line-height: 1.3; overflow-wrap: anywhere; }
        .pa-eyebrow { margin: 0; color: var(--pa-ink-soft); font-size: 0.78rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .pa-muted { margin: 0; color: var(--pa-ink-soft); font-size: 0.85rem; }
        .pa-subtitle { margin: 0; font-size: 1rem; line-height: 1.35; }
        .pa-meta { display: flex; flex-wrap: wrap; gap: 0.35rem 1rem; margin: 0; padding: 0; list-style: none; color: var(--pa-ink-soft); font-size: 0.85rem; }

        .pa-badge {
            display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px;
            font-size: 0.75rem; font-weight: 700; white-space: nowrap;
        }
        .pa-badge--success { background: var(--pa-success-tint); color: var(--pa-success); }
        .pa-badge--info { background: var(--pa-info-tint); color: var(--pa-info); }
        .pa-badge--warning { background: var(--pa-warning-tint); color: var(--pa-warning); }
        .pa-badge--neutral { background: var(--pa-surface-muted); color: var(--pa-ink-soft); }
        .pa-badge--error { background: var(--pa-error-tint); color: var(--pa-error); }

        .pa-progress { display: grid; gap: 0.35rem; }
        .pa-progress__label { display: flex; justify-content: space-between; gap: 1rem; font-size: 0.85rem; color: var(--pa-ink-soft); }
        .pa-progress__track { block-size: 0.5rem; border-radius: 999px; background: var(--pa-surface-muted); overflow: hidden; }
        .pa-progress__bar { block-size: 100%; border-radius: 999px; background: var(--pa-primary); }

        .pa-table-wrap { overflow-x: auto; }
        .pa-table { inline-size: 100%; border-collapse: collapse; font-size: 0.88rem; }
        .pa-table th, .pa-table td {
            padding: 0.55rem 0.65rem; border-block-end: 1px solid var(--pa-border); text-align: start;
            vertical-align: top;
        }
        .pa-table th { color: var(--pa-ink-soft); font-weight: 600; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .pa-table tbody tr:last-child td { border-block-end: 0; }
        .pa-num { font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; text-align: end; white-space: nowrap; }

        .pa-photos { display: grid; grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr)); gap: 0.75rem; margin: 0; padding: 0; list-style: none; }
        .pa-photo { display: grid; gap: 0.35rem; }
        .pa-photo figure { margin: 0; display: grid; gap: 0.35rem; }
        .pa-photo img {
            inline-size: 100%; block-size: 8rem; object-fit: cover;
            border: 1px solid var(--pa-border); border-radius: var(--pa-radius-sm); background: var(--pa-surface-muted);
        }
        .pa-photo figcaption { color: var(--pa-ink-soft); font-size: 0.75rem; }

        .pa-empty { margin: 0; padding: 1rem; border: 1px dashed var(--pa-border); border-radius: var(--pa-radius-sm); color: var(--pa-ink-soft); font-size: 0.88rem; }

        .pa-notice { display: grid; gap: 0.75rem; max-inline-size: 34rem; margin-inline: auto; padding: clamp(1.25rem, 4vw, 2rem); }
        .pa-notice__title { margin: 0; font-size: 1.3rem; }
        .pa-notice__text { margin: 0; color: var(--pa-ink-soft); }

        .pa-footer {
            display: grid; gap: 0.35rem; padding-block-start: 0.5rem;
            color: var(--pa-ink-soft); font-size: 0.78rem; text-align: center;
        }
        .pa-footer a { color: inherit; }

        @media print {
            body { background: #FFFFFF; color: #000000; padding: 0; }
            .pa-card, .pa-header { border-color: #CCCCCC; box-shadow: none; }
            .pa-langs, .pa-skip { display: none; }
            .pa-photo img { block-size: auto; }
        }
    </style>
</head>
<body>
<a class="pa-skip" href="#pa-main"><?= e(__('portail.skip_to_content')) ?></a>
<div class="pa-shell">
    <header class="pa-header">
        <div class="pa-brand">
            <span class="pa-brand__name"><?= e($paCabinetName !== '' ? $paCabinetName : __('portail.portal')) ?></span>
            <span class="pa-brand__tag"><?= e(__('portail.portal')) ?></span>
        </div>
        <?php if (isset($langLinks) && is_array($langLinks) && $langLinks !== []): ?>
            <nav class="pa-langs" aria-label="<?= e(__('portail.language')) ?>">
                <?php foreach ($langLinks as $code => $href): ?>
                    <a href="<?= e($href) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>" <?= $code === $paLocale ? 'aria-current="true"' : '' ?>><?= e(strtoupper($code)) ?></a>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </header>
    <main class="pa-main" id="pa-main">
