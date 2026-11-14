<?php
/**
 * Tiny dependency-free PDF writer for the admin reports export (T-44).
 *
 * It only supports what a report needs: A4 pages, Helvetica 10pt text, and
 * automatic pagination. That keeps the export working on stock XAMPP without
 * Composer, a headless browser, or an external service.
 */

if (!function_exists('pdrPdfText')) {
    function pdrPdfText(string $value): string
    {
        // Standard PDF fonts use WinAnsi. Transpose the few characters that
        // matter for this report and drop anything else safely.
        $value = str_replace(['₱', '–', '—', '…', '’', '“', '”'], ['PHP ', '-', '-', '...', "'", '"', '"'], $value);
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        if ($converted === false) {
            $converted = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? '';
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $converted);
    }
}

if (!function_exists('pdrBuildReportPdf')) {
    /**
     * @param string[] $lines Plain text lines (already formatted).
     */
    function pdrBuildReportPdf(array $lines, string $title = 'Pio De Roda Report'): string
    {
        $lines = array_values(array_map('strval', $lines));
        if (!$lines) {
            $lines = ['No data for this period.'];
        }

        $pageWidth = 595.28;
        $pageHeight = 841.89;
        $left = 42;
        $top = 800;
        $leading = 13.5;
        $maxLines = 54;
        $pages = array_chunk($lines, $maxLines);
        if (!$pages) {
            $pages = [['No data.']];
        }

        $objects = [];
        $pageObjectNumbers = [];
        $next = 4; // 1 catalog, 2 pages, 3 font

        foreach ($pages as $pageLines) {
            $pageObj = $next++;
            $contentObj = $next++;
            $pageObjectNumbers[] = $pageObj;

            $stream = "BT\n/F1 10 Tf\n" . $left . ' ' . $top . " Td\n" . $leading . " TL\n";
            foreach ($pageLines as $index => $line) {
                if ($index > 0) {
                    $stream .= "T*\n";
                }
                $stream .= '(' . pdrPdfText($line) . ") Tj\n";
            }
            $stream .= "ET\n";

            $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '
                . $pageWidth . ' ' . $pageHeight . '] /Resources << /Font << /F1 3 0 R >> >> /Contents '
                . $contentObj . ' 0 R >>';
            $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        }

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[2] = '<< /Type /Pages /Kids ['
            . implode(' ', array_map(static fn(int $n): string => $n . ' 0 R', $pageObjectNumbers))
            . '] /Count ' . count($pageObjectNumbers) . ' >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPosition = strlen($pdf);
        $maxObject = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxObject + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $maxObject; $i++) {
            if (!isset($offsets[$i])) {
                $pdf .= "0000000000 00000 f \n";
                continue;
            }
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . ($maxObject + 1) . " /Root 1 0 R /Info << /Title ("
            . pdrPdfText($title) . ") >> >>\nstartxref\n" . $xrefPosition . "\n%%EOF";

        return $pdf;
    }
}
