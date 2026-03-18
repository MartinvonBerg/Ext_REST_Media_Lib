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
define ( 'PLUGIN_DIR', 'C:\wamp64\www\wordpress\wp-content\plugins\wp-plugin-shared-utils' );
// C:\wamp64\www\wordpress\wp-content\plugins\wp-wpcat-json-rest\vendor\autoload.php

define( 'WP_ROOT', 'C:\wamp64\www\wordpress' );

require_once PLUGIN_DIR . '/vendor/autoload.php';


// --- Mini-Polyfills für WP-Funktionen, die du im SUT nutzt ---

if (!function_exists('wp_normalize_path')) {
    function wp_normalize_path(string $path): string {
        $path = str_replace('\\', '/', $path);
        // Mehrfache Slashes zu einem Slash reduzieren
        $path = preg_replace('#/+#', '/', $path);
        return $path;
    }
}

if (!function_exists('untrailingslashit')) {
    function untrailingslashit(string $value): string {
        return rtrim($value, '/');
    }
}

if (!function_exists('path_join')) {
    function path_join(string $base, string $path): string {
        $base = untrailingslashit(wp_normalize_path($base));
        $path = trim(wp_normalize_path($path), '/');
        return $path === '' ? $base : ($base . '/' . $path);
    }
}
