<?php

namespace mvbplugins\helpers;

// Prevent direct access
defined('ABSPATH') or die('Are you ok?');

/**
 * Get the upload URL/path in right way (works with SSL).
 *
 * @param string $param  "basedir" or "baseurl"
 *
 * @param string $subfolder  subfolder to append to basedir or baseurl
 * 
 * @return string the base appended with subfolder
 */
function gpxview_get_upload_dir($param, $subfolder = '') {
    // Get the upload directory details
    $upload_dir = wp_get_upload_dir();

    // Validate the param to prevent accessing undefined keys
    if (!in_array($param, ['basedir', 'baseurl'], true)) {
        return '';
    }

    // Automatically handles HTTPS for 'baseurl'
    $url = $upload_dir[$param];

    // Append the subfolder and return
    return trailingslashit($url) . ltrim($subfolder, '/');
}