<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PdfReportTest extends TestCase
{
    public function testTransliteratesPesoAndTypography(): void
    {
        self::assertSame('PHP 1,250.00', pdrPdfText("\u{20B1}1,250.00"));
        self::assertSame('-', pdrPdfText("\u{2013}"));
        self::assertSame("'quoted'", pdrPdfText("\u{2019}quoted\u{2019}"));
        self::assertSame('...', pdrPdfText("\u{2026}"));
    }

    public function testEscapesPdfStringDelimiters(): void
    {
        self::assertSame('a\\\\b\\(c\\)', pdrPdfText('a\\b(c)'));
    }

    public function testLineBreaksBecomeSpaces(): void
    {
        self::assertSame('line one line two', pdrPdfText("line one\nline two"));
    }

    public function testEmptyReportStillProducesAValidDocument(): void
    {
        $pdf = pdrBuildReportPdf([]);

        self::assertStringStartsWith("%PDF-1.4\n", $pdf);
        self::assertStringEndsWith('%%EOF', $pdf);
        self::assertStringContainsString('(No data for this period.) Tj', $pdf);
        self::assertStringContainsString('/Count 1', $pdf);
    }

    public function testReportContentIsEscapedInsideThePageStream(): void
    {
        $pdf = pdrBuildReportPdf(["Total: \u{20B1}1,000.00", 'Path C:\\reports (final)']);

        self::assertStringContainsString('(Total: PHP 1,000.00) Tj', $pdf);
        self::assertStringContainsString('(Path C:\\\\reports \\(final\\)) Tj', $pdf);
    }

    public function testLongReportsPaginate(): void
    {
        $lines = array_map(static fn(int $i): string => 'Line ' . $i, range(1, 55));
        $pdf = pdrBuildReportPdf($lines);

        self::assertSame(2, substr_count($pdf, '/Type /Page /Parent'));
        self::assertStringContainsString('/Count 2', $pdf);
        self::assertStringContainsString('(Line 1) Tj', $pdf);
        self::assertStringContainsString('(Line 55) Tj', $pdf);
    }

    public function testTitleIsEscapedInTheDocumentInfo(): void
    {
        $pdf = pdrBuildReportPdf(['x'], 'Sales (January)');

        self::assertStringContainsString('/Title (Sales \\(January\\))', $pdf);
    }
}
