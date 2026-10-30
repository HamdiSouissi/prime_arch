<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Translator;
use App\Core\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

/** Génération mPDF depuis les données courantes d'un brouillon ou un instantané émis. */
final class DocumentPdfService
{
    public function __construct(private ?DocumentStorage $storage = null)
    {
        $this->storage ??= new DocumentStorage();
    }

    /** @param array<string,mixed> $snapshot */
    public function render(array $snapshot): string
    {
        if (!class_exists(Mpdf::class)) {
            throw new RuntimeException('mPDF n’est pas installé. Exécutez composer install.');
        }
        $locale = in_array($snapshot['locale'] ?? '', ['fr', 'ar'], true)
            ? (string) $snapshot['locale']
            : 'fr';
        $previousLocale = Translator::locale();
        Translator::setLocale($locale);
        try {
            $cabinet = is_array($snapshot['cabinet'] ?? null) ? $snapshot['cabinet'] : [];
            $logoReference = is_string($cabinet['logo_reference'] ?? null) ? $cabinet['logo_reference'] : '';
            $logoPath = $logoReference !== '' ? $this->storage->resolveLogo($logoReference) : null;
            $html = View::render('pdf/generated', [
                'snapshot' => $snapshot,
                'logoPath' => $logoPath ?? '',
            ], null);

            $temporaryDirectory = BASE_PATH . '/storage/tmp';
            if (!is_dir($temporaryDirectory)
                && !mkdir($temporaryDirectory, 0750, true)
                && !is_dir($temporaryDirectory)
            ) {
                throw new RuntimeException('Le dossier temporaire privé de mPDF ne peut pas être créé.');
            }
            if (!is_writable($temporaryDirectory)) {
                throw new RuntimeException('Le dossier temporaire privé de mPDF n’est pas accessible en écriture.');
            }
            $pdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4',
                'default_font' => 'dejavusans',
                'tempDir' => $temporaryDirectory,
                'autoScriptToLang' => true,
                'autoLangToFont' => true,
                'useSubstitutions' => true,
                'margin_left' => 17,
                'margin_right' => 17,
                'margin_top' => 48,
                'margin_bottom' => 22,
                'margin_header' => 8,
                'margin_footer' => 8,
            ]);
            $pdf->SetDirectionality($locale === 'ar' ? 'rtl' : 'ltr');
            $document = is_array($snapshot['document'] ?? null) ? $snapshot['document'] : [];
            $pdf->SetTitle((string) ($document['title'] ?? __('documents.title')));
            $pdf->SetAuthor((string) ($cabinet['name'] ?? 'Prime Architecture'));
            $pdf->WriteHTML($html);
            $contents = $pdf->Output('', Destination::STRING_RETURN);
            if (!is_string($contents) || !str_starts_with($contents, '%PDF-')) {
                throw new RuntimeException('mPDF n’a pas produit un fichier exploitable.');
            }
            return $contents;
        } finally {
            Translator::setLocale($previousLocale);
        }
    }
}
