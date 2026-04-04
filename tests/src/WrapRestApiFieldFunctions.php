<?php
namespace mvbplugins\extmedialib;

include_once PLUGIN_DIR . '\includes\rest_api_field_functions.php';

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

// Minimal stub for emrFile class used by the Replacer constructor.
class emrFileWrapper {
    private $file;
    public function __construct($file) { $this->file = $file; }
    public function getFileName() { return basename($this->file); }
}

function serialize_blocks( $blocks ) {
	return implode( '', array_map( '\mvbplugins\extmedialib\serialize_block', $blocks ) );
}

function serialize_block( $block ) {
	$block_content = '';

	$index = 0;
	foreach ( $block['innerContent'] as $chunk ) {
		$block_content .= is_string( $chunk ) ? $chunk : serialize_block( $block['innerBlocks'][ $index++ ] );
	}

	if ( ! is_array( $block['attrs'] ) ) {
		$block['attrs'] = array();
	}

	return \mvbplugins\extmedialib\get_comment_delimited_block_content(
		$block['blockName'],
		$block['attrs'],
		$block_content
	);
}

function get_comment_delimited_block_content( $block_name, $block_attributes, $block_content ) {
	if ( is_null( $block_name ) ) {
		return $block_content;
	}

	$serialized_block_name = \mvbplugins\extmedialib\strip_core_block_namespace( $block_name );
	$serialized_attributes = empty( $block_attributes ) ? '' : \mvbplugins\extmedialib\serialize_block_attributes( $block_attributes ) . ' ';

	if ( empty( $block_content ) ) {
		return sprintf( '<!-- wp:%s %s/-->', $serialized_block_name, $serialized_attributes );
	}

	return sprintf(
		'<!-- wp:%s %s-->%s<!-- /wp:%s -->',
		$serialized_block_name,
		$serialized_attributes,
		$block_content,
		$serialized_block_name
	);
}

function strip_core_block_namespace( $block_name = null ) {
	if ( is_string( $block_name ) && str_starts_with( $block_name, 'core/' ) ) {
		return substr( $block_name, 5 );
	}

	return $block_name;
}

function serialize_block_attributes( $block_attributes ) {
	$encoded_attributes = wp_json_encode( $block_attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	return strtr(
		$encoded_attributes,
		array(
			'\\\\' => '\\u005c',
			'--'   => '\\u002d\\u002d',
			'<'    => '\\u003c',
			'>'    => '\\u003e',
			'&'    => '\\u0026',
			'\\"'  => '\\u0022',
		)
	);
}

function wp_strip_all_tags( $text, $remove_breaks = false ) {
	if ( is_null( $text ) ) {
		return '';
	}
	
	if ( ! is_scalar( $text ) ) {

		return '';
	}

	$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
	$text = strip_tags( $text );

	if ( $remove_breaks ) {
		$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
	}

	return trim( $text );
}

