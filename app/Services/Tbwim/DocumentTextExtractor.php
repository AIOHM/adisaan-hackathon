<?php

declare(strict_types=1);

namespace App\Services\Tbwim;

use Illuminate\Support\Facades\Process;

/**
 * Reads the words out of a document the founder sends. Plain text formats
 * are read directly, DOCX through its XML, PDF through pdftotext when the
 * host has it. Anything else — or a document with no readable words —
 * fails in plain words instead of storing a guess.
 */
final class DocumentTextExtractor
{
    public const EXTENSIONS = ['txt', 'md', 'csv', 'pdf', 'docx'];

    public function extract(string $path, string $extension): string
    {
        $text = match (strtolower($extension)) {
            'txt', 'md', 'csv' => (string) file_get_contents($path),
            'docx' => $this->docx($path),
            'pdf' => $this->pdf($path),
            default => throw new \RuntimeException('That document type cannot be read yet. Send txt, md, csv, pdf or docx.'),
        };
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if (mb_strlen($text) < 40) {
            throw new \RuntimeException('That document holds almost no readable text.');
        }

        return mb_substr($text, 0, 12000);
    }

    private function docx(string $path): string
    {
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('That docx file could not be opened.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new \RuntimeException('That docx file holds no document text.');
        }
        $xml = str_replace(['</w:p>', '<w:tab/>', '<w:br/>'], ["\n", ' ', ' '], $xml);

        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function pdf(string $path): string
    {
        if (! Process::run('command -v pdftotext')->successful()) {
            throw new \RuntimeException('This host cannot read PDF text yet (no pdftotext). Send the words as txt or paste them instead.');
        }
        $out = tempnam(sys_get_temp_dir(), 'tbwim-doc-');
        if ($out === false) {
            throw new \RuntimeException('Could not make room to read that PDF.');
        }
        try {
            $done = Process::timeout(60)->run(['pdftotext', '-layout', $path, $out]);
            if (! $done->successful() || ! is_file($out)) {
                throw new \RuntimeException('That PDF could not be read (it may be scanned images, not text).');
            }

            return (string) file_get_contents($out);
        } finally {
            @unlink($out);
        }
    }
}
