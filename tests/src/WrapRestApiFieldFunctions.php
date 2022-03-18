<?php
namespace mvbplugins\extmedialib;

include_once 'C:\Bitnami\wordpress-5.2.2-0\apps\wordpress\htdocs\wp-content\plugins\wp-wpcat-json-rest\includes\rest_api_field_functions.php';

/**
 * wrapper class for functions in file ..\wp-wpcat-json-rest\includes\rest_api_field_functions.php
 */
class WrapRestApiFieldFunctions {

    public function cbUpdateField ( string $value, object $post, string $field ) 
    {
        return \mvbplugins\extmedialib\cb_update_field( $value, $post, $field);
    }

    public function cbGetMd5 ( array $data ) 
    {
        return \mvbplugins\extmedialib\cb_get_md5( $data );
    }
}