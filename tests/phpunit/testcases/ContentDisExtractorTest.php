
<?php
use PHPUnit\Framework\TestCase;

include_once PLUGIN_DIR . '\includes\rest_api_functions.php';


/**
 * @covers ::extract_filename_from_content_disposition
 */
final class ExtractFilenameFromContentDispositionTest extends TestCase
{
    /**
     * @dataProvider headerProvider
     */
    public function testExtractFilename(?string $header, string $expected): void
    {
        $actual = mvbplugins\extmedialib\extract_filename_from_content_disposition($header);
        $this->assertSame($expected, $actual);
    }

    public static function headerProvider(): array
    {
        return [
            'null header' => [
                null,
                '',
            ],
            'empty header' => [
                '',
                '',
            ],
            'simple filename unquoted' => [
                'attachment; filename=example.jpg',
                'example.jpg',
            ],
            'simple filename quoted' => [
                'attachment; filename="example.jpg"',
                'example.jpg',
            ],
            'extra params different order' => [
                'inline; size=123; filename="report.pdf"',
                'report.pdf',
            ],
            'whitespace around tokens' => [
                'attachment;   filename =    "space-name.txt"   ',
                'space-name.txt', // sanitize_file_name() kommt in der Funktion NICHT vor; hier testen wir NUR Extraktion. ABER extract_filename_from_content_disposition() gibt den Originalnamen zurück. Wenn deine Funktion NUR extrahiert, ohne Sanitizing, muss hier "space name.txt" erwartet werden. Passe entsprechend an!
            ],
            'windows backslashes in name' => [
                'attachment; filename="C:\\temp\\my\\file.png"',
                'file.png',
            ],
            'unix path in name' => [
                'attachment; filename="/var/tmp/archive.tar.gz"',
                'archive.tar.gz',
            ],
            'double dots traversal' => [
                'attachment; filename="../../etc/passwd"',
                'passwd',
            ],
            'prefer filename* over filename (UTF-8)' => [
                "attachment; filename=\"fallback.jpg\"; filename*=UTF-8''f%C3%BCnf%20%C3%9Cberraschungen.jpg",
                'fünf Überraschungen.jpg',
            ],
            'filename* only UTF-8 encoded' => [
                "attachment; filename*=UTF-8''%E2%9C%93-check.txt",
                "✓-check.txt",
            ],
            'filename* with charset ISO-8859-1' => [
                // "Übergröße.jpg" in ISO-8859-1 dann percent-encoded:
                // Ü = 0xDC -> %DC, ber = ber, größe (ö=0xF6 -> %F6, ß=0xDF -> %DF)
                "attachment; filename*=ISO-8859-1''%DCbergr%F6%DFe.jpg",
                "Übergröße.jpg",
            ],
            'filename* without language part (still has \'\')' => [
                "attachment; filename*=UTF-8''uber_grosse.png",
                "uber_grosse.png",
            ],
            'filename* without charset/lang separator (fallback decode)' => [
                // Manchmal kaputt angeliefert:
                "attachment; filename*=uber%20gross.txt",
                "uber gross.txt",
            ],
            'lowercase tokens' => [
                'attachment; filename="readme.md"; filename*=utf-8\'\'readme-utf8.md',
                'readme-utf8.md',
            ],
            'inline disposition' => [
                'inline; filename="index.html"',
                'index.html',
            ],
            //'quoted semicolons inside quotes (rare but possible)' => [
            //    'attachment; filename="report;final;v2.pdf"',
            //    'report;final;v2.pdf',
            //],
            'filename only (no disposition token)' => [
                'filename="lonely.txt"',
                'lonely.txt',
            ],
            'filename only_2 (no disposition token)' => [
                'lonely.txt',
                '',
            ],
        ];
    }

    /**
     * Manche Header sind tricky: filename* mit Sprache, verschiedene Reihenfolge, überflüssige Leerzeichen.
     */
    /*
    public function testFilenameStarWithLanguage(): void
    {
        $header = "attachment; filename*=UTF-8'en'%E2%82%ACuro.txt"; // "€uro.txt"
        $this->assertSame("€uro.txt", mvbplugins\extmedialib\extract_filename_from_content_disposition($header));
    }
    */
    public function testFilenameAndFilenameStarOrderReversed(): void
    {
        $header = "attachment; filename=\"fallback.txt\"; size=1; filename*=UTF-8''final.txt";
        $this->assertSame("final.txt", mvbplugins\extmedialib\extract_filename_from_content_disposition($header));
    }

    public function testPathComponentsAreRemoved(): void
    {
        $header = "attachment; filename=\"..\\..\\evil\\hack.exe\"";
        $this->assertSame("hack.exe", mvbplugins\extmedialib\extract_filename_from_content_disposition($header));
    }
}
