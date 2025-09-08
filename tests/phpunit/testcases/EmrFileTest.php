<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\stubs;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Actions\expectDone;
use function Brain\Monkey\Filters\expectApplied;

include_once PLUGIN_DIR . '\classes\emrFile.php';

final class EmrFileTest extends TestCase {
	public function setUp(): void {
		parent::setUp();
		setUp();
	}
	public function tearDown(): void {
		tearDown();
		parent::tearDown();
	}

    public function test_emrFileClass_1 () {

        expect( 'wp_check_filetype_and_ext')
            ->times(1)
            ->andReturn( [] );

        $tested = new mvbplugins\extmedialib\emrFile( '' );
        $result = $tested->exists();
        $this->assertEquals( $result, false );

        $result = $tested->getFileMime();
        $this->assertEquals( $result, false );
    }

    public function test_emrFileClass_2 () {

        $path = PLUGIN_DIR . '\test\testdata';
        $filename =  'DSC_1722.webp';
        $file = $path . '/' . $filename;

        expect( 'wp_check_filetype_and_ext')
            ->times(1)
            #->andReturn( ['type' => 'image/webp'] );
            ->andReturn( [] );
        
        $tested = new mvbplugins\extmedialib\emrFile( $file );
        $result = $tested->exists();
        $this->assertEquals( $result, true );

        $result = $tested->getFullFilePath();
        $this->assertEquals( $result, $file );

        $result = $tested->getPermissions();
        $this->assertEquals( $result, 438 );
        /* not on Windows
        $result = $tested->setPermissions( 777 );
        $result = $tested->getPermissions();
        $this->assertEquals( $result, 777 );
        $result = $tested->setPermissions( 438 );
        $this->assertEquals( $result, 438 );
        */

        $result = $tested->getFileSize();
        $this->assertEquals( $result, 399834 );

        $result = $tested->getFilePath();
        $this->assertEquals( $result, $path . '/' );

        $result = $tested->getFileName();
        $this->assertEquals( $result, $filename );   
        
        $result = $tested->getFileExtension();
        $this->assertEquals( $result, 'webp' );

        $result = $tested->getFileMime();
        $this->assertEquals( $result, 'image/webp' );
    }

    public function test_emrFileClass_3 () {

        $path = PLUGIN_DIR . '\test\testdata';
        $filename =  'DSC_1722.webp';
        $file = $path . '/' . $filename;

        expect( 'wp_check_filetype_and_ext')
            ->times(1)
            #->andReturn( ['type' => 'image/webp'] );
            ->andReturn( ['type' => 'image/webp'] );
        
        $tested = new mvbplugins\extmedialib\emrFile( $file );
        $result = $tested->exists();
        $this->assertEquals( $result, true );

        $result = $tested->getFileMime();
        $this->assertEquals( $result, 'image/webp' );
    }
}