<?php

declare(strict_types=1);

namespace mvbplugins\extmedialib {
    function add_filter(...$args): bool {
        return true;
    }

    function wp_get_upload_dir(): array {
        $basedir = $GLOBALS['mvb_wp_upload_basedir'] ?? sys_get_temp_dir();
        return ['basedir' => $basedir];
    }

    function wp_getimagesize(string $path) {
        return $GLOBALS['mvb_mock_image_sizes'][$path] ?? false;
    }

    function image_resize_dimensions(
        int $orig_w,
        int $orig_h,
        int $dest_w,
        int $dest_h,
        bool $crop = false
    ) {
        if ($orig_w <= 0 || $orig_h <= 0) {
            return false;
        }

        if ($dest_w < 0 || $dest_h < 0 || ($dest_w === 0 && $dest_h === 0)) {
            return false;
        }

        if ($crop) {
            if ($dest_w === 0 || $dest_h === 0) {
                return false;
            }
            return [0, 0, 0, 0, $dest_w, $dest_h, $orig_w, $orig_h];
        }

        $ratio = $orig_w / $orig_h;

        if ($dest_h === 0) {
            if ($dest_w > $orig_w) {
                $dest_w = $orig_w;
            }
            $dest_h = (int) round($dest_w / $ratio);
        } elseif ($dest_w === 0) {
            if ($dest_h > $orig_h) {
                $dest_h = $orig_h;
            }
            $dest_w = (int) round($dest_h * $ratio);
        } else {
            $scale = min($dest_w / $orig_w, $dest_h / $orig_h, 1);
            $dest_w = (int) round($orig_w * $scale);
            $dest_h = (int) round($orig_h * $scale);
        }

        if ($dest_w <= 0 || $dest_h <= 0) {
            return false;
        }

        return [0, 0, 0, 0, $dest_w, $dest_h, $orig_w, $orig_h];
    }

    function wp_get_image_mime(string $path): string {
        return 'image/jpeg';
    }

    function wp_filesize(string $path): int {
        return 12345;
    }
}

namespace {
    use PHPUnit\Framework\TestCase;

    require_once PLUGIN_DIR . '/includes/handle_subsizes_in_db.php';

    final class GenerateWpImageSubsizesTest extends TestCase {
        private string $originalPath;

        protected function setUp(): void {
            parent::setUp();

            $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mvbplugins-subsize-tests';
            if (!is_dir($tmpDir)) {
                mkdir($tmpDir, 0777, true);
            }

            $this->originalPath = $tmpDir . DIRECTORY_SEPARATOR . 'sample-scaled.jpg';
            if (!is_file($this->originalPath)) {
                file_put_contents($this->originalPath, 'test');
            }

            $GLOBALS['mvb_wp_upload_basedir'] = $tmpDir;
            $GLOBALS['mvb_mock_image_sizes'][$this->originalPath] = [2400, 1600, 'mime' => 'image/jpeg'];

            if (!isset($_GET)) {
                $_GET = [];
            }
            $_GET['subsizesuploaded'] = 'false';
        }

        protected function tearDown(): void {
            unset($GLOBALS['mvb_mock_image_sizes'][$this->originalPath]);
            parent::tearDown();
        }

        /**
         * @dataProvider newsizesProvider
         */
        public function test_generate_wp_image_subsizes_with_mocked_path_and_dimensions(
            array $mockImageSize,
            array $newsizes,
            array $expectedChecks
        ): void {
            $GLOBALS['mvb_mock_image_sizes'][$this->originalPath] = $mockImageSize;

            $result = \mvbplugins\extmedialib\generate_wp_image_subsizes($this->originalPath, $newsizes);

            $this->assertNotEmpty($result);

            foreach ($expectedChecks as $sizeName => $expected) {
                $this->assertArrayHasKey($sizeName, $result);
                $this->assertSame($expected['width'], $result[$sizeName]['width']);
                $this->assertSame($expected['height'], $result[$sizeName]['height']);
                $this->assertSame($expected['crop'], $result[$sizeName]['crop']);
                $this->assertSame('sample-' . $expected['width'] . 'x' . $expected['height'] . '.jpg', $result[$sizeName]['file']);
                $this->assertFalse($result[$sizeName]['exists']);
                $this->assertSame('', $result[$sizeName]['mime-type']);
                $this->assertSame(0, $result[$sizeName]['filesize']);
            }
        }

        public function newsizesProvider(): array {
            $mockImageSizeA = [2400, 1600, 'mime' => 'image/jpeg'];

            $newsizesA = [
                'thumbnail' => ['width' => 200, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 500, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 0, 'crop' => false],
                'large' => ['width' => 2048, 'height' => 2048, 'crop' => false],
                'middle_1024' => ['width' => 1024, 'height' => 0, 'crop' => false],
                'middle_1300' => ['width' => 1300, 'height' => 0, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false],
                'responsive-100' => ['width' => 100, 'height' => 9999, 'crop' => false],
                'responsive-150' => ['width' => 150, 'height' => 9999, 'crop' => false],
                'responsive-200' => ['width' => 200, 'height' => 9999, 'crop' => false],
                'responsive-300' => ['width' => 300, 'height' => 9999, 'crop' => false],
                'responsive-450' => ['width' => 450, 'height' => 9999, 'crop' => false],
                'responsive-600' => ['width' => 600, 'height' => 9999, 'crop' => false],
                'responsive-900' => ['width' => 900, 'height' => 9999, 'crop' => false],
            ];

            $checksA = [
                'thumbnail' => ['width' => 200, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 450, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 512, 'crop' => false],
                'large' => ['width' => 2048, 'height' => 1365, 'crop' => false],
                'middle_1024' => ['width' => 1024, 'height' => 683, 'crop' => false],
                'middle_1300' => ['width' => 1300, 'height' => 867, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1024, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 1365, 'crop' => false],
                'responsive-100' => ['width' => 100, 'height' => 67, 'crop' => false],
                'responsive-150' => ['width' => 150, 'height' => 100, 'crop' => false],
                'responsive-200' => ['width' => 200, 'height' => 133, 'crop' => false],
                'responsive-300' => ['width' => 300, 'height' => 200, 'crop' => false],
                'responsive-450' => ['width' => 450, 'height' => 300, 'crop' => false],
                'responsive-600' => ['width' => 600, 'height' => 400, 'crop' => false],
                'responsive-900' => ['width' => 900, 'height' => 600, 'crop' => false],
            ];

            $mockImageSizeB = [3000, 2000, 'mime' => 'image/jpeg'];

            $newsizesB = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 0, 'crop' => false],
                'large' => ['width' => 1024, 'height' => 1024, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false],
            ];

            $checksB = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 300, 'height' => 200, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 512, 'crop' => false],
                'large' => ['width' => 1024, 'height' => 683, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1024, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 1365, 'crop' => false],
            ];

            $mockImageSizeC = [2556, 1704, 'mime' => 'image/jpeg'];

            $newsizesC = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 0, 'crop' => false],
                'large' => ['width' => 1024, 'height' => 1024, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false],
            ];

            $checksC = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 300, 'height' => 200, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 512, 'crop' => false],
                'large' => ['width' => 1024, 'height' => 683, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1024, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 1365, 'crop' => false],
            ];

            $mockImageSizeD = [1704, 2263, 'mime' => 'image/jpeg'];

            $newsizesD = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 300, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 0, 'crop' => false],
                'large' => ['width' => 1024, 'height' => 1024, 'crop' => false],
                '1536x1536' => ['width' => 1536, 'height' => 1536, 'crop' => false],
                '2048x2048' => ['width' => 2048, 'height' => 2048, 'crop' => false],
            ];

            $checksD = [
                'thumbnail' => ['width' => 150, 'height' => 150, 'crop' => true],
                'medium' => ['width' => 226, 'height' => 300, 'crop' => false],
                'medium_large' => ['width' => 768, 'height' => 1020, 'crop' => false],
                'large' => ['width' => 771, 'height' => 1024, 'crop' => false],
                '1536x1536' => ['width' => 1157, 'height' => 1536, 'crop' => false],
                '2048x2048' => ['width' => 1542, 'height' => 2048, 'crop' => false],
            ];

            return [
                'newsizes profile A with 2400x1600 source' => [$mockImageSizeA, $newsizesA, $checksA],
                'newsizes profile B with 3000x2000 source' => [$mockImageSizeB, $newsizesB, $checksB],
                'newsizes profile C with 2556x1704 source' => [$mockImageSizeC, $newsizesC, $checksC],
                'newsizes profile D with 1704x2263 source' => [$mockImageSizeD, $newsizesD, $checksD],
            ];
        }
    }
}
