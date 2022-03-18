<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Filters\expectApplied;

final class RestApiFieldsTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		setUp();
	}
	public function tearDown(): void {
		tearDown();
		parent::tearDown();
	}

    /**
     * @dataProvider FieldsProvider
     */
    public function test_update_field( $ret1, $oldValue, $value, $post, $field, $expected) {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\wp-wpcat-json-rest\tests\src\WrapRestApiFieldFunctions.php';
		
        expect( 'get_post_meta')
            ->times(1)
            ->andReturn( $oldValue );
        
        expect( 'update_post_meta')
            ->times(1)
            ->andReturn( $ret1 );

        $tested = new mvbplugins\extmedialib\WrapRestApiFieldFunctions();
        $result = $tested::cbUpdateField( $value, $post, $field );
        $this->assertEquals( $result, $expected );
	}

    public function FieldsProvider() :array
    {
        return [ // invalid post-id
            [ true,  'alter-Wert1', 'neuer-Wert1', (object) ['ID' => 1], 'gallery', true ],
            [ true,   false       , 'neuer-Wert1', (object) ['ID' => 1], 'gallery', true ],
            [ false, 'alter-Wert2', 'neuer-Wert2', (object) ['ID' => 2], 'gallery', false ],
            [ false, 'alter-Wert3', 'alter-Wert3', (object) ['ID' => 3], 'gallery', true ],
            [ false,  false,        'alter-Wert3', (object) ['ID' => 3], 'gallery', false ],
            [ 4,        false,      'neuer-Wert4', (object) ['ID' => 4], 'gallery', true ],
            [ 4,     'alter-Wert3', 'neuer-Wert4', (object) ['ID' => 4], 'gallery', true ],
        ]
        ;
    }

    /**
     * @dataProvider md5Provider
     */
    public function test_cb_get_md5( $data, $expected) {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\wp-wpcat-json-rest\tests\src\WrapRestApiFieldFunctions.php';
		
        expect( 'wp_get_original_image_path' )
            ->with( 0 )
            ->andReturn( false )
            ->with( 1 )
            ->andReturn( 'test.jpg' );
       
        $tested = new mvbplugins\extmedialib\WrapRestApiFieldFunctions();
        $result = $tested::cbGetMd5( $data );
        $this->assertEquals( $result, $expected );
	}

    public function md5Provider() :array
    {
        return [ 
            [ ['id' => 0], array(
                            'MD5' => '0',
                            'size' => 0,
                            'file' => $original_filename,
                            ) ],
            [ ['id' => 1], array(
                            'MD5' => '0',
                            'size' => 0,
                            'file' => 'test.jpg',
                            ) ],
        ]
        ;
    }

    public function test_cb_get_md5_2() {
		include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\wp-wpcat-json-rest\tests\src\WrapRestApiFieldFunctions.php';
		
        $file = 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\wp-wpcat-json-rest\test\testdata\DSC_1722.webp';

        expect( 'wp_get_original_image_path' )
            ->with( 1 )
            ->andReturn( $file );
       
        $data = ['id' => 1];
        $expected = array(
                'MD5' => '5B6B317E32120C6DB8EF3B8C17A08A00',
                'size' => 399834,
        );

        $tested = new mvbplugins\extmedialib\WrapRestApiFieldFunctions();
        $result = $tested::cbGetMd5( $data );
        $this->assertEquals( $result, $expected );
	}

}