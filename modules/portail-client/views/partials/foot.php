<?php
declare(strict_types=1);

/**
 * Pied de page commun : rappel de confidentialité et coordonnées du cabinet.
 *
 * @var array<string,string> $cabinet
 * @var string|null $generatedAt
 */
$paContact = array_filter([
    (string) ($cabinet['phone'] ?? ''),
    (string) ($cabinet['email'] ?? ''),
], static fn (string $value): bool => trim($value) !== '');
?>
    </main>
    <footer class="pa-footer">
        <p><?= e(__('portail.footer_notice')) ?></p>
        <?php if ($paContact !== []): ?>
            <p>
                <?= e(__('portail.footer_contact')) ?> :
                <?= e(implode(' · ', array_map('trim', $paContact))) ?>
            </p>
        <?php endif; ?>
        <?php if (!empty($generatedAt)): ?>
            <p><?= e(__('portail.footer_generated', ['date' => (string) $generatedAt])) ?></p>
        <?php endif; ?>
    </footer>
</div>
</body>
</html>
