<?php
/**
 * The following snippets uses `PLUGIN` to prefix
 * the constants and class names. You should replace
 * it with something that matches your plugin name.
 */
// define test environment
define( 'PLUGIN_PHPUNIT', true );

// define fake ABSPATH
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() );
}
// define fake PLUGIN_ABSPATH
if ( ! defined( 'PLUGIN_ABSPATH' ) ) {
	define( 'PLUGIN_ABSPATH', sys_get_temp_dir() . '/wp-content/plugins/wp-wpcat-json-rest/' );
}

// $comp_path = "C:/Users/Martin von Berg/AppData/Roaming/Composer"; // TODO: get the global path
define ( 'PLUGIN_DIR', 'C:\wamp64\www\wordpress\wp-content\plugins\wp-wpcat-json-rest' );
// C:\wamp64\www\wordpress\wp-content\plugins\wp-wpcat-json-rest\vendor\autoload.php


require_once PLUGIN_DIR . '/vendor/autoload.php';