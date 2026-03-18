<?php
namespace mvbplugins\helpers;

// include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\fotorama_multi\inc\extractMetadata.php';
include_once PLUGIN_DIR . '\inc\extractMetadata.php';

/**
 * wrapper class for decodeExtendedChunkHeader in file ../inc\extractMetadata.php
 */
class WrapExtractMetadata {


    public function getWebpMetadata( string $filename ) 
    {
        return getWebpMetadata( $filename );
    }
}