<?php
use PHPUnit\Framework\TestCase;

include_once PLUGIN_DIR . '\includes\rest_api_functions.php';


/**
 * @covers ::normalize_target_folder
 */
final class NormalizeTargetFolderTest extends TestCase
{
    private string $basedir;

    protected function setUp(): void
    {
        // Beispiel-Uploads-Basis (typisch für WP): ohne trailing slash
        $this->basedir = wp_normalize_path('/var/www/html/wp-content/uploads');
        $this->basedir = untrailingslashit($this->basedir);
    }

    /**
     * @dataProvider folderProvider
     */
    public function testNormalizeTargetFolder(string $requestFolder, string $expectedFolderFs, string $expectedReqfolder): void
    {
        $result = mvbplugins\extmedialib\normalize_target_folder($requestFolder, $this->basedir);

        $this->assertIsArray($result, 'Result must be an array');
        $this->assertArrayHasKey('folder_fs', $result);
        $this->assertArrayHasKey('reqfolder', $result);

        // Filesystem-Pfad: normalisiert, ohne trailing slash
        $this->assertSame($expectedFolderFs, $result['folder_fs']);

        // URL-Teil: ohne leading/trailing slash, mit inneren Slashes als Trenner
        $this->assertSame($expectedReqfolder, $result['reqfolder']);
    }

    public static function folderProvider(): array
    {
        $base = wp_normalize_path('/var/www/html/wp-content/uploads');
        $base = untrailingslashit($base);

        return [
            'leer' => [
                '',
                $base,   // folder_fs == basedir
                '',      // reqfolder leer
            ],
            'einfacher ordner' => [
                'Albums',
                wp_normalize_path($base . '/Albums'),
                'Albums',
            ],
            'mehrere segmente' => [
                '2024/12',
                wp_normalize_path($base . '/2024/12'),
                '2024/12',
            ],
            'backslashes werden zu slashes' => [
                "Photos\\Italien\\Roma",
                wp_normalize_path($base . '/Photos/Italien/Roma'),
                'Photos/Italien/Roma',
            ],
            'fuehrende und trailing slashes' => [
                '/Albums/Italien/',
                wp_normalize_path($base . '/Albums/Italien'),
                'Albums/Italien',
            ],
            'whitespace und doppelte slashes' => [
                "     /Albums/\/Italien  ",
                wp_normalize_path($base . '/Albums/Italien'),
                'Albums/Italien',
            ],
            'dot und dotdot werden entfernt' => [
                './2024/../12/./Final',
                wp_normalize_path($base . '/12/Final'),
                '12/Final',
            ],
            'nur dots' => [
                '../../..',
                $base,   // alles rausgefiltert -> bleibt basedir
                '',      // keine Segmente
            ],
            'gemischte slashes' => [
                "\\2024//12\\Final",
                wp_normalize_path($base . '/2024/12/Final'),
                '2024/12/Final',
            ],
            'unicode segmente' => [
                'Albüm/Übersicht',
                wp_normalize_path($base . '/Albüm/Übersicht'),
                'Albüm/Übersicht', // Encoding passiert später beim URL-Bau
            ],
        ];
    }

    public function testReturnsWithoutTrailingSlashOnFolderFs(): void
    {
        $res = mvbplugins\extmedialib\normalize_target_folder('Albums', $this->basedir);
        $this->assertSame(
            rtrim($res['folder_fs'], '/'),
            $res['folder_fs'],
            'folder_fs must not have a trailing slash'
        );
    }
}
