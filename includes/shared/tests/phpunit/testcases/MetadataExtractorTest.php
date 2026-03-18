<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\expect;


final class MetadataExtractorTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		setUp();
        //include_once PLUGIN_DIR . '\src\MetadataExtractor.php';

	}
	public function tearDown(): void {
		tearDown();
		parent::tearDown();
	}

    public function test_getMetadata_file_not_supported() {
        $filename = 'test.cr3'; 

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );

        $this->assertIsArray( $result );
        $this->assertEquals([], $result, 'equals to emtpy array');

    }

    public function test_getMetadata_file_supported_not_existing() {
        $filename = 'test.jpg'; 

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );

        $this->assertIsArray( $result );
        $this->assertEquals([], $result, 'equals to emtpy array');

    }

    // // test with a real file from ./tests/data    
    //    $filename = PLUGIN_DIR . '\tests\data\webp_test_1.webp';

    public function test_getMetadata_file_supported__existing() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\webp_test_1.webp'; 

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );
        $supported = $tested->isFileSupported( $filename);
        $filetypes = $tested->getSupportedFileTypes();
        $expectedresult = [ 'jpg', 'jpeg', 'webp', 'avif', ];

        $this->assertIsArray( $result );
        $this->assertEquals('image/webp', $result['mime'], 'mime is image/webp');
        $this->assertEquals('webp', $result['ext'], 'extension is webp');
        $this->assertTrue($supported, 'file is supported');
        $this->assertEquals($expectedresult, $filetypes, 'file types are supported');
    }

    public function test_getWebpMetadata_1() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\webp_test_1.webp';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );

        $this->assertIsArray( $result );
        //$this->assertEquals('0.0.1', $result['meta_version'], 'equals to emtpy array');
        $this->assertEquals('XMP-Titel 2', $result['title'], '');
        //$this->assertEquals(1.85, $result['aperture'], '');
        $this->assertEquals(['2CV', 'abyss', 'allee', 'abgestorben'], $result['keywords'], '');

    }

    public function test_getAvifMetadata_1() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\avif_test.avif';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );

        $this->assertIsArray( $result );
        //$this->assertEquals('0.0.1', $result['meta_version'], 'equals to emtpy array');
        $this->assertEquals('XMP-Titel 2', $result['title'], '');
        //$this->assertEquals(1.85, $result['aperture'], '');
        $this->assertEquals(['2CV', 'abyss', 'allee', 'abgestorben'], $result['keywords'], '');

    }

    public function test_getJpgMetadata_1() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\jpg_test.jpg';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename );

        $this->assertIsArray( $result );
        //$this->assertEquals('0.0.1', $result['meta_version'], 'equals to emtpy array');
        $this->assertEquals('XMP-Titel 2', $result['title'], '');
        //$this->assertEquals(1.85, $result['aperture'], '');
        $this->assertEquals(['2CV', 'abyss', 'allee', 'abgestorben'], $result['keywords'], '');

    }

    public function test_getJpgMetadata_2() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\IMG_6977.JPG';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename, 'wordpress' );
        // camera data only in EXIF
        $this->assertIsArray( $result );
        $this->assertEquals(8, $result['aperture'], ''); 
        $this->assertEquals(1, $result['orientation'], '');
        $this->assertEquals(125, $result['iso'], '');
        $this->assertEquals(0.003125, $result['shutter_speed'], '');
        $this->assertEquals("1760717826", $result['created_timestamp'], '');
        $this->assertEquals(28, $result['focal_length'], '');
        $this->assertEquals('Canon EOS R6m2 RF24-240mm F4-6.3 IS USM', $result['camera'], '');
        // data also in XMP
        $this->assertEquals('XMP-Titel', $result['title'], '');
        $this->assertEquals('XMP All rights reserved', $result['copyright'], '');
        $this->assertEquals('XMP-Autor Martin von Berg', $result['credit'], '');
        $this->assertEquals('XMP-Beschreibung', $result['caption'], '');
        $this->assertEquals(['allee', 'Italien'], $result['keywords'], '');
    }

    public function test_getWebpMetadata_2() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\IMG_6977.webp';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename, 'wordpress' );
        // camera data only in EXIF
        $this->assertIsArray( $result );
        $this->assertEquals(8, $result['aperture'], ''); 
        $this->assertEquals(1, $result['orientation'], '');
        $this->assertEquals(125, $result['iso'], '');
        $this->assertEquals(0.003125, $result['shutter_speed'], '');
        $this->assertEquals("1760717826", $result['created_timestamp'], '');
        $this->assertEquals(28, $result['focal_length'], '');
        $this->assertEquals('Canon EOS R6m2 RF24-240mm F4-6.3 IS USM', $result['camera'], '');
        
        // data also in XMP
        $this->assertEquals('XMP-Titel', $result['title'], '');
        $this->assertEquals('XMP All rights reserved', $result['copyright'], '');
        $this->assertEquals('XMP-Autor Martin von Berg', $result['credit'], '');
        $this->assertEquals('XMP-Beschreibung', $result['caption'], '');
        $this->assertEquals(['allee', 'Italien'], $result['keywords'], '');
    }

    public function test_getAvifMetadata_2() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\IMG_6977.avif';

        $tested = new mvbplugins\Extractors\MetadataExtractor();
        $result = $tested->getMetadata( $filename, 'wordpress' );
        // camera data only in EXIF
        $this->assertIsArray( $result );
        $this->assertEquals(8, $result['aperture'], ''); 
        $this->assertEquals(1, $result['orientation'], '');
        $this->assertEquals(125, $result['iso'], '');
        $this->assertEquals(0.003125, $result['shutter_speed'], '');
        $this->assertEquals("1760717826", $result['created_timestamp'], '');
        $this->assertEquals(28, $result['focal_length'], '');
        $this->assertEquals('Canon EOS R6m2 RF24-240mm F4-6.3 IS USM', $result['camera'], '');
        
        // data also in XMP
        $this->assertEquals('XMP-Titel', $result['title'], '');
        $this->assertEquals('XMP All rights reserved', $result['copyright'], '');
        $this->assertEquals('XMP-Autor Martin von Berg', $result['credit'], '');
        $this->assertEquals('XMP-Beschreibung', $result['caption'], '');
        $this->assertEquals(['allee', 'Italien'], $result['keywords'], '');
    }
}