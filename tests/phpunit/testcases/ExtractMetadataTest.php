<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\expect;


final class extractMetaDataTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		setUp();
        include_once PLUGIN_DIR . '\src\extractMetadata.php';

	}
	public function tearDown(): void {
		tearDown();
		parent::tearDown();
	}

    public function test_getWebpMetadata_1() {
        $filename = 'test.jpg';

        $result = mvbplugins\helpers\getWebpMetadata( $filename);

        $this->assertIsArray( $result );
        $this->assertEquals([], $result, 'equals to emtpy array');

    }

    public function test_getWebpMetadata_2() {
        expect( 'mvbplugins\helpers\extractMetadata' )
            ->once() 
            ->with( 'test.jpg',  ) // with specified arguments
            ->andReturn( 'metadata' ); // return a string value which will cause to return an empty array
    
        $filename = 'test.jpg';

        $result = mvbplugins\helpers\getWebpMetadata( $filename );

        $this->assertIsArray( $result );
        $this->assertEquals([], $result, 'equals to emtpy array');

    }

    public function test_getWebpMetadata_3() {
        $data = [
            'image_meta' => ['title' => 'title', 'caption' => 'caption', 'keywords' => 'keywords'],
        ];
        expect( 'mvbplugins\helpers\extractMetadata' )
            ->once() 
            ->with( 'test.jpg',  ) // with specified arguments
            ->andReturn( $data ); // return a valid array
    
        $filename = 'test.jpg';

        $result = mvbplugins\helpers\getWebpMetadata( $filename );

        $this->assertIsArray( $result );
        $this->assertEquals('0.0.1', $result['meta_version'], 'equals to emtpy array');

    }

    public function test_getWebpMetadata_4() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\webp_test_1.webp';

        $result = mvbplugins\helpers\getWebpMetadata( $filename );

        $this->assertIsArray( $result );
        $this->assertEquals('0.0.1', $result['meta_version'], 'equals to emtpy array');
        $this->assertEquals('in den Bergen', $result['title'], '');
        $this->assertEquals(1.85, $result['aperture'], '');
        //$this->assertEquals(['2CV', 'abyss', 'allee', 'abgestorben'], $result['keywords'], '');

    }
    public function test_getJpgMetadata_2() {
        // test with a real file from ./tests/data    
        $filename = PLUGIN_DIR . '\tests\data\IMG_6977.JPG';

        $result = mvbplugins\helpers\getJpgMetadata( $filename );
        
        // camera data only in EXIF : expected format taken from MetadataExtractorTest::test_getJpgMetadata_2() test
        $this->assertIsArray( $result );
        $this->assertEquals(8, $result['aperture'], ''); 
        $this->assertEquals(1, $result['orientation'], '');
        $this->assertEquals(125, $result['iso'], '');
        $this->assertEquals(8, $result['aperture'], '');
        $this->assertEquals(0.003125, $result['shutter_speed'], '');
        $this->assertEquals("1760717826", $result['created_timestamp'], '');
        $this->assertEquals(28, $result['focal_length'], '');
        $this->assertEquals('Canon EOS R6m2', $result['camera'], '');
        // data also in XMP
        $this->assertEquals('EX: XP-Titel', $result['title'], '');
        $this->assertEquals('EX: All rights reserved', $result['copyright'], '');
        $this->assertEquals('EX: Martin von Berg', $result['credit'], '');
        $this->assertEquals('EX: Beschreibung', $result['caption'], '');
    }
    /**
     * @dataProvider ChunksProvider
     */
    /*
    public function test_extractMetaDataFromChunks_1( array $chunks, string $filename, $expected ) {
		
        expect( 'file_get_contents' )
            //->once() 
            //->with( 'test.jpg',  ) // with specified arguments, like get_option( 'plugin-settings', [] );
            ->andReturn( '<?xml version="1.0"?><dcx:descriptionSet><dcx:description>
            <dc:title><dcx:valueString>Home Page Title</dcx:valueString></dc:title>
            <dc:description><dcx:valueString>Test-Description</dcx:valueString></dc:description>
            <rdf:bag><rdf:li>tag1</rdf:li><rdf:li>tag2</rdf:li></rdf:bag>
            </dcx:description></dcx:descriptionSet>'); // what it should return?
    

		$tested = new mvbplugins\helpers\WrapExtractMetadata();
        $result = $tested->wrapExtractMetadataFromChunks($chunks, $filename);
        $this->assertEquals( $result, $expected );
	}
    
    public function ChunksProvider() :array
    {
        return [
            [ [], '', [] ],
            [ array(
                array('fourCC' => 'VP8L', 'start' => 0, 'size' => 10)), 'test1.jpg', [] ],
            [ array(
                array('fourCC' => 'XMP ', 'start' => 0, 'size' => 10)), 'test2.jpg', 
                array (
                    'title' => 'Home Page Title',
                    'caption' => 'Test-Description',
                    'keywords' => array (0 => 'tag1', 1 => 'tag2' )
                     ) ],
        ];
    }
    
    public function test_extractMetaDataFromChunks_2() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\tests\src\WrapExtractMetadata.php';
		
        expect( 'fread' )
            ->with( 'test1.jpg', 4 )
            ->andReturn( 'wrong_Content');

      	$tested = new mvbplugins\fotoramamulti\WrapExtractMetadata();
        $result = $tested::FindChunks( 'test1.jpg' );
        $this->assertFalse( $result );
	}

    public function test_extractMetaDataFromChunks_3() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\tests\src\WrapExtractMetadata.php';
		
        expect( 'fread' )
            ->twice()
            ->with( 'test2.jpg', 4 )
            ->andReturn( 'RIFF', 'wrong_length');

      	$tested = new mvbplugins\fotoramamulti\WrapExtractMetadata();
        $result = $tested::FindChunks( 'test2.jpg' );
        $this->assertFalse( $result );
	}

    public function test_extractMetaDataFromChunks_4() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\tests\src\WrapExtractMetadata.php';
		
        expect( 'fread' )
            ->times(3)
            ->with( 'test3.jpg', 4 )
            ->andReturn( 'RIFF', '1234', 'not_fourCC');

      	$tested = new mvbplugins\fotoramamulti\WrapExtractMetadata();
        $result = $tested::FindChunks( 'test3.jpg' );
        $this->assertFalse( $result );
	}

    public function test_extractMetaDataFromChunks_5() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\tests\src\WrapExtractMetadata.php';
		
        expect( 'fread' )
            ->times(5)
            ->with( 'test4.jpg', 4 )
            ->andReturn( 'RIFF', '1234', '44CC', 'prt1', 'chunk_size');
        
        expect( 'feof')
            ->once()
            ->with( 'test4.jpg' )
            ->andReturn( false );

        expect( 'ftell')
            ->once()
            ->with( 'test4.jpg' )
            ->andReturn( 17 );
        
      	$tested = new mvbplugins\fotoramamulti\WrapExtractMetadata();
        $result = $tested::FindChunks( 'test4.jpg' );
        $this->assertEquals( $result, 
                            array (
                                'fileSize' => 875770417,
                                'fourCC' => '44CC',
                                'chunks' => []
                                ) );
	}

    public function test_extractMetaDataFromChunks_6() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\tests\src\WrapExtractMetadata.php';
		
        expect( 'fread' )
            ->times(5)
            ->with( 'test5.jpg', 4 )
            ->andReturn( 'RIFF', '1234', '44CC', 'prt1', '1234');
        
        expect( 'feof')
            ->twice()
            ->with( 'test5.jpg' )
            ->andReturn( false, true );

        expect( 'ftell')
            ->once()
            ->with( 'test5.jpg' )
            ->andReturn( 17 );

        expect( 'fseek')
            ->once()
            ->with( 'test5.jpg', 875770418 , 1 )
            ->andReturn( -1 );
        
      	$tested = new mvbplugins\fotoramamulti\WrapExtractMetadata();
        $result = $tested::FindChunks( 'test5.jpg' );
        $this->assertEquals( $result, 
                            array (
                                'fileSize' => 875770417,
                                'fourCC' => '44CC',
                                'chunks' => array(
                                    0 => array(
                                    'fourCC' => 'prt1',
                                    'start' => 17,
                                    'size' => 875770417,
                                    ))
                                ) );
	}
    */
}