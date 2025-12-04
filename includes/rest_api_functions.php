<?php

/**
 * Helper functions for the extension of the rest-api
 *
 * PHP version 7.4.0 - 8.0.0
 *
 * @category   Rest_Api_Functions
 * @package    Rest_Api_Functions
 * @author     Martin von Berg <mail@mvb1.de>
 * @copyright  2021 Martin von Berg
 * @license    http://www.php.net/license/3_01.txt  PHP License 3.01
 * @link       https://github.com/MartinvonBerg/Ext_REST_Media_Lib
 * @since      File available since Release 5.3.0
 */

// phpstan: level 8 reached without baseline

namespace mvbplugins\extmedialib;

// ---------------- general helper functions ----------------------------------------------------

/**
 * Return the original files that were already added OR NOT added to WP-Cat from THIS $folder
 *
 * @param string $folder the folder that should be used.
 * @param bool $get_added_files either provide an array with files that are IN WP-Cat or NOI in WP-Cat
 *
 * @return array<int, string> | array<int, array<string, array|int|string>> the original-files in the given $folder that are IN or NOI IN WP-Cat yet
 */
function get_files_from_folder(string $folder, bool $get_added_files)
{
	$result = array();
	$all = glob($folder . '/*');
	$i = 0;

	$dir = get_upload_dir();
	$url = get_upload_url();

	if (false == $all) {
		$all = array();
	}

	foreach ($all as $file) {
		$test=$file;
		if ((! preg_match_all('/[0-9]+x[0-9]+/', $test)) && (! strstr($test, '-' . EXT_SCALED)) && (! is_dir($test))) {
			// Check if one of the files in $result was already attached to WPCat.
			$file = str_replace($dir, $url, $file);
			$addedbefore = attachment_url_to_postid($file);

			if ($addedbefore === 0) {
				$ext = '.' . pathinfo($file, PATHINFO_EXTENSION); //['extension'];
				$file = str_replace($ext, '-' . EXT_SCALED . $ext, $file);
				$addedbefore = attachment_url_to_postid($file);
			}

			if ($get_added_files) {
				$result [ $i ] ['id']   = $addedbefore;
				$result [ $i ] ['file'] = $file;
				++$i;
			} 
			elseif ($addedbefore === 0) {
				$result[$i] = $test;
				++$i;
			}
		}
	}
	return $result;
}

/**
 * Update image_meta function to update meta-data given by $post_ID and new metadata
 * (for jpgs: only keywords, credit, copyright, caption, title) 
 *
 * @param int   $post_id ID of the attachment in the WP-Mediacatalog.
 *
 * @param array<string[]|array> $newmeta array with new metadata taken from the JSON-data in the POST-Request body.
 *
 * @param string $origin the source of the function call 
 * 
 * @return int|bool true if success, false if not: ouput of the WP function to update attachment metadata
 */
function update_metadata(int $post_id, array $newmeta, string $origin)
{
	// get and check current Meta-Data from WP-database.
	$meta = wp_get_attachment_metadata($post_id);
	if ( $meta === false) { $meta = [];	}
	$oldmeta = $meta;

	if (array_key_exists('image_meta', $newmeta)) {
		$newmeta = $newmeta['image_meta'];
		// sanitize the keywords
		if (array_key_exists('keywords', $newmeta)) {
			foreach ($newmeta['keywords'] as $key => $entry) {
				$newmeta['keywords'][$key] = \htmlspecialchars( $entry );
			};
		}

		// organize metadata. GPS-data is missing. Does not matter: is not used in WP. GPS is updated via file-update.
		array_key_exists('keywords', $newmeta)  ? $meta['image_meta']['keywords']  = $newmeta['keywords'] : ''; 
		array_key_exists('credit', $newmeta)    ? $meta['image_meta']['credit']    = \htmlspecialchars($newmeta['credit']) : '';
		array_key_exists('copyright', $newmeta) ? $meta['image_meta']['copyright'] = \htmlspecialchars($newmeta['copyright']) : '';
		array_key_exists('caption', $newmeta)   ? $meta['image_meta']['caption']   = \htmlspecialchars($newmeta['caption']) : '';
		array_key_exists('title', $newmeta)     ? $meta['image_meta']['title']     = \htmlspecialchars($newmeta['title'])  : '';

		// change the image capture metadata for webp only due to the fact that WP does not write this data to the database.
		$type = get_post_mime_type($post_id);
		if ('image/webp' == $type || 'image/avif' == $type) {
			array_key_exists('aperture', $newmeta)          ? $meta['image_meta']['aperture']           = \htmlspecialchars($newmeta['aperture']) : '';
			array_key_exists('camera', $newmeta)            ? $meta['image_meta']['camera']             = \htmlspecialchars($newmeta['camera']) : '';
			array_key_exists('created_timestamp', $newmeta) ? $meta['image_meta']['created_timestamp']  = \htmlspecialchars($newmeta['created_timestamp']) : '';
			array_key_exists('focal_length', $newmeta)      ? $meta['image_meta']['focal_length']       = \htmlspecialchars($newmeta['focal_length']) : '';
			array_key_exists('iso', $newmeta)               ? $meta['image_meta']['iso']                = \htmlspecialchars($newmeta['iso']) : '';
			array_key_exists('shutter_speed', $newmeta)     ? $meta['image_meta']['shutter_speed']      = \htmlspecialchars($newmeta['shutter_speed']) : '';
			array_key_exists('orientation', $newmeta)       ? $meta['image_meta']['orientation']        = \htmlspecialchars($newmeta['orientation']) : '';
		}
	}

	// reset title and caption in $meta to prevent overwrite with the route update_meta
	if ('mvbplugin' === $origin) {
		$meta['image_meta']['title']   = $oldmeta['image_meta']['title'];
		$meta['image_meta']['caption'] = $oldmeta['image_meta']['caption'];
	}
	// write metadata.
	$success = wp_update_attachment_metadata($post_id, $meta); // write new Meta-data to WP SQL-Database.

	return $success;
}

/**
 * Get the upload URL/path in right way (works with SSL).
 *
 * @return string the base url
 */
function get_upload_url(): string {
	$ud   = wp_get_upload_dir();
	$url  = isset($ud['baseurl']) ? $ud['baseurl'] : '';
	$url  = set_url_scheme( $url );
	return untrailingslashit( $url );
}

/**
 * get the upload DIR 
 *
 * @return string the upload base DIR without subfolder
 */
function get_upload_dir()
{
	$upload_dir = wp_upload_dir();
	$dir = $upload_dir['basedir'];
	$search = ['\\', '\\\\', '//'];
	$dir = str_replace($search, '/', $dir);
	return $dir;
}

/**
 * Check if given content is JSON format
 *
 * @param mixed $content
 * @return mixed return the decoded content from json to an php-array if successful
 */
function bodyIsJSON($content)
{
	if (is_array($content) || is_object($content))
		return false; // can never be.

	$json = json_decode($content);
	return $json && $json != $content;
}

/**
 * set the filename to the complete path
 *
 * @param  string $dir Path that shall be trailing the filename
 * @param  string $fileName the $filename that shall include dir
 * @return string the corrected fileName
 */
function set_complete_path( $dir, $fileName ) {
	// if provided filename is empty : use the old filename
	$isCompletePath = strpos( $fileName, $dir . '/');

	if ( $isCompletePath === false) {
		$path_to_new_file = $dir . \DIRECTORY_SEPARATOR . $fileName;
	} else {
		$path_to_new_file = $fileName;
	}
	return $path_to_new_file;
}

/**
 * Concatenate multidimensional-array-to-string with glue separator.
 * 
 * @source https://stackoverflow.com/questions/12309047/multidimensional-array-to-string multidimensional-array-to-string
 * @param  string $glue the separator for the string concetantion of array contents.
 * @param  array $arr input array
 * @return string|mixed return string on success or the input if it is not a string
 */
function implode_all( $glue, $arr ) {
	if( is_array( $arr ) ){
  
	  foreach( $arr as $key => &$value ){
  
		if( @is_array( $value ) ){
		  $arr[ $key ] = implode_all( $glue, $value );
		}
	  }
  
	  return implode( $glue, $arr );
	}
  
	// Not array
	return $arr;
}

/**
 * Extrahiert einen Dateinamen aus dem Content-Disposition-Header.
 * - Bevorzugt RFC 5987: filename*=
 * - Fällt zurück auf filename=
 * - Akzeptiert Whitespaces um '=' und ';'
 * - Entfernt Quotes um den Wert
 * - Schneidet Pfadanteile ab (Basename)
 * - Sanitiset den Dateinamen (spaces → '-', unsichere Zeichen raus)
 *
 * Beispiele:
 *  attachment;   filename =  "space name.txt"   -> "space-name.txt"
 *  attachment; filename*=UTF-8''f%C3%BCnf%20%C3%9Cberraschungen.jpg -> "fuenf-ueberraschungen.jpg" (je nach WP remove_accents)
 *
 * @param string|null $cd Content-Disposition-Header
 * @return string cleaned and sanitized filename from the header
 */
function extract_filename_from_content_disposition(?string $cd): string
{
    if ($cd === null) {
        return '';
    }

    // Normalisieren
    $cd = trim($cd);
    if ($cd === '') {
        return '';
    }

    // In Parameter zerlegen (Semikolon als Trenner, unterschiedliche Whitespaces zulassen)
    $parts = array_map('trim', explode(';', $cd));

    $filename = '';
    $filenameStar = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        // Robust gegen Whitespaces um '='
        // Beispiel: 'filename =  "space name.txt"'
        if (preg_match('/^filename\*\s*=\s*(.+)$/i', $part, $m)) {
            // RFC 5987: <charset>''<lang
            $value = trim($m[1]);
            $value = trim($value, "\"' \t\n\r\0\x0B");

            $pos = strpos($value, "''");
            if ($pos !== false) {
                $charset = substr($value, 0, $pos);
                $encoded = substr($value, $pos + 2);
                $decoded = rawurldecode($encoded);

                if ($charset && strcasecmp($charset, 'utf-8') !== 0 && function_exists('mb_convert_encoding')) {
                    $decoded = @mb_convert_encoding($decoded, 'UTF-8', $charset);
                }
                $filenameStar = $decoded;
            } else {
                // Kaputte/vereinfachte Variante: kein '' enthalten
                $filenameStar = rawurldecode($value);
            }
            continue;
        }

        if (preg_match('/^filename\s*=\s*(.+)$/i', $part, $m)) {
            $value = trim($m[1]);
            // Quotes entfernen
            $value = trim($value, "\"' \t\n\r\0\x0B");
            $filename = $value;
            continue;
        }
    }

    // Priorität: filename* > filename
    $out = $filenameStar !== '' ? $filenameStar : $filename;

    if ($out === '') {
        return '';
    }

    // Nur den letzten Pfadteil (Traversal-Prävention)
    $out = basename(str_replace('\\', '/', $out));

    // Jetzt sanitizen, damit Test "space-name.txt" erfüllt ist
    if (function_exists('sanitize_file_name')) {
        $out = sanitize_file_name($out);
    }

    return $out;
}

/**
 * Kanonisiert eine Slash-getrennte Pfadliste segmentweise:
 * - vereinheitlicht Backslashes zu '/'
 * - trimmt führende/abschließende Slashes/Whitespace
 * - entfernt leere Segmente
 * - entfernt '.' und löst '..' aus (Stack-Prinzip)
 * Rückgabe: Array der sauberen Segmente
 */
function normalize_path_segments(string $input): array {
    $input = trim(str_replace('\\', '/', $input), "/ \t\n\r\0\x0B");
    if ($input === '') {
        return [];
    }
    $raw = explode('/', $input);
    $stack = [];
    foreach ($raw as $seg) {
        if ($seg === '' || $seg === '.') {
            continue;
        }
        if ($seg === '..') {
            // Nur aus dem Stack poppen, nicht über die Wurzel hinaus
            if (!empty($stack)) {
                array_pop($stack);
            }
            continue;
        }
        $stack[] = $seg;
    }
    return $stack;
}

/**
 * Normalisiert einen Unterordner (aus Request) für Filesystem und URL.
 * Rückgabe:
 *  - 'folder_fs'  absoluter Filesystempfad unterhalb $basedir (ohne trailing slash)
 *  - 'reqfolder'  URL-Pfad relativ (ohne leading/trailing slash)
 */
function normalize_target_folder(string $requestFolder, string $basedir): array {
    // 1) Segmente kanonisieren (wir verwenden die gleiche Logik für FS & URL)
    $segs = normalize_path_segments($requestFolder);

    // 2) Filesystempfad bauen
    //    - join per '/' (wir haben bereits Segmente bereinigt)
    //    - an basedir anhängen
    //    - am Ende sauber normalisieren und ohne trailing slash zurückgeben
    $basedir = untrailingslashit(wp_normalize_path($basedir));
    $suffix  = implode('/', $segs);

    if ($suffix === '') {
        $folder_fs = $basedir;
    } else {
        // path_join hilft gegen Doppel-Slashes; wp_normalize_path vereinheitlicht
        $folder_fs = wp_normalize_path(path_join($basedir, $suffix));
        $folder_fs = untrailingslashit($folder_fs);
    }

    // 3) URL-Teil (reqfolder) zurückgeben – ohne leading/trailing slash
    $reqfolder = $suffix; // bereits segmentbereinigt, Slashes als Trenner

    return [
        'folder_fs' => $folder_fs,
        'reqfolder' => $reqfolder,
    ];
}
