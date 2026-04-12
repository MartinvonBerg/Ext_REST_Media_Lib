<?php
use PHPUnit\Framework\TestCase;
use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;
use function Brain\Monkey\Functions\stubs;

// Define WordPress result constants used by wpdb (namespaced code falls back to these)
if (!defined('ARRAY_A')) define('ARRAY_A', 'ARRAY_A');
if (!defined('ARRAY_N')) define('ARRAY_N', 'ARRAY_N');
if (!defined('OBJECT'))  define('OBJECT',  'OBJECT');

// Include the class under test
include_once PLUGIN_DIR . '/classes/replacer.php';

final class ReplacerClassTest extends TestCase {
    public function setUp(): void {
        parent::setUp();
        setUp();

        // Brain Monkey stubs to satisfy the constructor without a full WP environment
        stubs([
            'get_attached_file' => function($post_id, $arg = null) { return __FILE__; },
            'get_post' => function($post_id) { $p = new \WP_Post(); $p->ID = $post_id; return $p; },
            'wp_attachment_is' => function($type, $post) { return true; },
            'wp_get_attachment_metadata' => function($post_id) { return ['file' => 'image.jpg', 'sizes' => []]; },
            'wp_get_attachment_url' => function($post_id) { return '/uploads/image.jpg'; },
            'apply_filters' => function($tag, $value) { return $value; },
            'wp_check_filetype_and_ext' => function($file, $filename, $mimes=null) { return ['type' => 'image/webp']; }
        ]);
        include_once PLUGIN_DIR . '\tests\src\WrapRestApiFieldFunctions.php';
        
        if ( ! class_exists( 'WP_HTML_Tag_Processor' && defined('WP_ROOT') ) ) {
            //require_once WP_ROOT . '/wp-includes/html-api/class-wp-html-span.php';
            //require_once WP_ROOT . '/wp-includes/html-api/class-wp-html-text-replacement.php';
            //require_once WP_ROOT . '/wp-includes/html-api/class-wp-html-attribute-token.php';
            //require_once WP_ROOT .'\wp-includes/html-api/class-wp-html-decoder.php';
	        //require_once WP_ROOT .'\wp-includes/html-api/class-wp-html-tag-processor.php';
            require_once WP_ROOT . '/wp-includes/class-wp-block-parser.php';
            require_once WP_ROOT . '/wp-includes/class-wp-block-parser-block.php';
            require_once WP_ROOT . '/wp-includes/blocks.php';
        }

    }

    public function tearDown(): void {
        tearDown();
        parent::tearDown();
    }

    public function test_replacer_constructor_sets_post_id() {
        $tested = new mvbplugins\extmedialib\Replacer('1111');

        $ref = new \ReflectionClass($tested);
        $prop = $ref->getProperty('post_id');
        $prop->setAccessible(true);
        $value = $prop->getValue($tested);
        $this->assertEquals('1111', $value);

        // check this also
        //$this->sourceFile// = new emrFile($source_file);
        $prop = $ref->getProperty('sourceFile');
        $prop->setAccessible(true);
        $value = $prop->getValue($tested);
        // get the basenmae of __FILE__ to compare
        $basename = basename(__FILE__);
        $this->assertEquals($basename, $value->getFileName());

		//$this->source_post// = get_post($post_id);
        $prop = $ref->getProperty('source_post');
        $prop->setAccessible(true);
        $value = $prop->getValue($tested);
        $this->assertEquals('1111', $value->ID);

		//$this->source_is_image// = wp_attachment_is('image', $this->source_post);
        $prop = $ref->getProperty('source_is_image');
        $prop->setAccessible(true);
        $value = $prop->getValue($tested);
        $this->assertTrue($value);

		//$this->source_metadata// = wp_get_attachment_metadata( $post_id );
        $prop = $ref->getProperty('source_metadata');
        $prop->setAccessible(true);
        $value = $prop->getValue($tested);
        $this->assertIsArray($value);
    }

    public function test_replacer_doMetaReplaceQuery_1() {
        $tested = new mvbplugins\extmedialib\Replacer('1111');

        $ref = new \ReflectionClass($tested);
        // prepare a post_content that contains a gutenberg image comment with the image ID
        $post_content = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt="oldalt"/><figcaption>oldcap</figcaption></figure><!-- /wp:image -->';

        // Minimal wpdb stub to satisfy queries
        $wpdb_stub = new class($post_content) {
            public $posts = 'wp_posts';
            private $post_content;
            public $last_query;
            public function __construct($post_content) { $this->post_content = $post_content; }
            public function prepare($query /*, ...$args */) { return $query; }
            public function get_results($sql, $output = null) { return [['ID' => 123, 'post_content' => $this->post_content]]; }
            public function query($sql) { $this->last_query = $sql; return 1; }
        };

        $GLOBALS['wpdb'] = $wpdb_stub;

        // define minimal WP helpers used in the method
        if (!function_exists('wp_update_post')) {
            function wp_update_post($arg, $silent = false) { return $arg['ID']; }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($v) { return false; }
        }
        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($id, $group = null) { return true; }
        }
        if (!function_exists('get_gmt_from_date')) {
            function get_gmt_from_date($date) { return $date; }
        }

        // prepare replacer internal state: target metadata and docaption
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        // invoke private method doMetaReplaceQuery
        $method = $ref->getMethod('doMetaReplaceQuery');
        $method->setAccessible(true);
        $updated = $method->invoke($tested, '/uploads/image');

        $this->assertEquals(1, $updated);
        $this->assertStringContainsString('UPDATE', $GLOBALS['wpdb']->last_query);
    }

    public function test_replacer_doMetaReplaceQuery_2() {
        $tested = new mvbplugins\extmedialib\Replacer('2829');

        $ref = new \ReflectionClass($tested);
        // prepare a post_content that contains a gutenberg image comment with the image ID
        $post_content = '<!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
                alt="Blumen im botanischen Garten München" class="wp-image-2829"/><figcaption class="wp-element-caption">test19</figcaption></figure>
                <!-- /wp:image -->';

        // Minimal wpdb stub to satisfy queries
        $wpdb_stub = new class($post_content) {
            public $posts = 'wp_posts';
            private $post_content;
            public $last_query;
            public function __construct($post_content) { $this->post_content = $post_content; }
            public function prepare($query /*, ...$args */) { return $query; }
            public function get_results($sql, $output = null) { return [['ID' => 123, 'post_content' => $this->post_content]]; }
            public function query($sql) { $this->last_query = $sql; return 1; }
        };

        $GLOBALS['wpdb'] = $wpdb_stub;

        // define minimal WP helpers used in the method
        if (!function_exists('wp_update_post')) {
            function wp_update_post($arg, $silent = false) { return $arg['ID']; }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($v) { return false; }
        }
        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($id, $group = null) { return true; }
        }
        if (!function_exists('get_gmt_from_date')) {
            function get_gmt_from_date($date) { return $date; }
        }

        // prepare replacer internal state: target metadata and docaption
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        // invoke private method doMetaReplaceQuery
        $method = $ref->getMethod('doMetaReplaceQuery');
        $method->setAccessible(true);
        $updated = $method->invoke($tested, '/uploads/image');

        $this->assertEquals(1, $updated);
        $this->assertStringContainsString('UPDATE', $GLOBALS['wpdb']->last_query);
    }

    public function test_replacer_NEW_doMetaReplaceQuery_1() {
        $tested = new mvbplugins\extmedialib\Replacer('2829');

        $ref = new \ReflectionClass($tested);
        // prepare a post_content that contains a gutenberg image comment with the image ID
        $post_content = '<!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
                alt="Blumen im botanischen Garten München" class="wp-image-2829"/><figcaption class="wp-element-caption">test19</figcaption></figure>
                <!-- /wp:image -->';

        // Minimal wpdb stub to satisfy queries
        $wpdb_stub = new class($post_content) {
            public $posts = 'wp_posts';
            private $post_content;
            public $last_query;
            public function __construct($post_content) { $this->post_content = $post_content; }
            public function prepare($query /*, ...$args */) { return $query; }
            public function get_results($sql, $output = null) { return [['ID' => 123, 'post_content' => $this->post_content]]; }
            public function query($sql) { $this->last_query = $sql; return 1; }
        };

        $GLOBALS['wpdb'] = $wpdb_stub;

        // define minimal WP helpers used in the method
        if (!function_exists('wp_update_post')) {
            function wp_update_post($arg, $silent = false) { return $arg['ID']; }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($v) { return false; }
        }
        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($id, $group = null) { return true; }
        }
        if (!function_exists('get_gmt_from_date')) {
            function get_gmt_from_date($date) { return $date; }
        }

        // prepare replacer internal state: target metadata and docaption
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        // invoke private method doMetaReplaceQuery
        $method = $ref->getMethod('doMetaReplaceQuery');
        $method->setAccessible(true);
        $updated = $method->invoke($tested, '/uploads/image');
        
        $this->assertEquals(1, $updated);
        $this->assertStringContainsString('UPDATE', $GLOBALS['wpdb']->last_query);
    }

    public function test_replacer_NEW_doMetaReplaceQuery_2() {
        $tested = new mvbplugins\extmedialib\Replacer('2829');

        $ref = new \ReflectionClass($tested);
        // prepare a post_content that contains a gutenberg image comment with the image ID
        $post_content = '<!-- wp:paragraph -->
            <p>nun folgt ein gutenberg gallerie block</p>
            <!-- /wp:paragraph -->

            <!-- wp:gallery {"linkTo":"none"} -->
            <figure class="wp-block-gallery has-nested-images columns-default is-cropped">
            
            <!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
            alt="oldalt" class="wp-image-2829"/><figcaption class="wp-element-caption">oldcap</figcaption></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2751,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/S-Satz/Kira-Sprung-1024x683.jpg" alt="" class="wp-image-2751"/></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2744,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/test/Elba-014-2560-18-1024x683.avif" alt="" class="wp-image-2744"/></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2733,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/2023/02/PXL_20230129_101132675-1024x771.jpg" alt="" class="wp-image-2733"/></figure>
            <!-- /wp:image --></figure>
            <!-- /wp:gallery -->';

        // Minimal wpdb stub to satisfy queries
        $wpdb_stub = new class($post_content) {
            public $posts = 'wp_posts';
            private $post_content;
            public $last_query;
            public function __construct($post_content) { $this->post_content = $post_content; }
            public function prepare($query /*, ...$args */) { return $query; }
            public function get_results($sql, $output = null) { return [['ID' => 123, 'post_content' => $this->post_content]]; }
            public function query($sql) { $this->last_query = $sql; return 1; }
        };

        $GLOBALS['wpdb'] = $wpdb_stub;

        // define minimal WP helpers used in the method
        if (!function_exists('wp_update_post')) {
            function wp_update_post($arg, $silent = false) { return $arg['ID']; }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($v) { return false; }
        }
        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($id, $group = null) { return true; }
        }
        if (!function_exists('get_gmt_from_date')) {
            function get_gmt_from_date($date) { return $date; }
        }

        // prepare replacer internal state: target metadata and docaption
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        // invoke private method doMetaReplaceQuery
        $method = $ref->getMethod('doMetaReplaceQuery');
        $method->setAccessible(true);
        $updated = $method->invoke($tested, '/uploads/image');
        
        $this->assertEquals(1, $updated);
        $this->assertStringContainsString('UPDATE', $GLOBALS['wpdb']->last_query);
    }

    public function test_replacer_NEW_doMetaReplaceQuery_3() {
        $tested = new mvbplugins\extmedialib\Replacer('2829');

        $ref = new \ReflectionClass($tested);

        $post_content = '<!-- wp:media-text {"mediaPosition":"right","mediaId":2829,"mediaLink":"http://localhost/wordpress/blumen-im-botanischen-garten-m%c3%bcnchen/","linkDestination":"none","mediaType":"image"} -->
        <div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile"><div class="wp-block-media-text__content"><!-- wp:paragraph {"placeholder":"Content…"} -->
        <p>Dss ist ein Bild mit Blumen.</p>
        <!-- /wp:paragraph --></div><figure class="wp-block-media-text__media"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" alt="Blumen im botanischen Garten München" class="wp-image-2829 size-full"/></figure></div>
        <!-- /wp:media-text -->';

        // Minimal wpdb stub to satisfy queries
        $wpdb_stub = new class($post_content) {
            public $posts = 'wp_posts';
            private $post_content;
            public $last_query;
            public function __construct($post_content) { $this->post_content = $post_content; }
            public function prepare($query /*, ...$args */) { return $query; }
            public function get_results($sql, $output = null) { return [['ID' => 123, 'post_content' => $this->post_content]]; }
            public function query($sql) { $this->last_query = $sql; return 1; }
        };

        $GLOBALS['wpdb'] = $wpdb_stub;

        // define minimal WP helpers used in the method
        if (!function_exists('wp_update_post')) {
            function wp_update_post($arg, $silent = false) { return $arg['ID']; }
        }
        if (!function_exists('is_wp_error')) {
            function is_wp_error($v) { return false; }
        }
        if (!function_exists('wp_cache_delete')) {
            function wp_cache_delete($id, $group = null) { return true; }
        }
        if (!function_exists('get_gmt_from_date')) {
            function get_gmt_from_date($date) { return $date; }
        }

        // prepare replacer internal state: target metadata and docaption
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        // invoke private method doMetaReplaceQuery
        $method = $ref->getMethod('doMetaReplaceQuery');
        $method->setAccessible(true);
        $updated = $method->invoke($tested, '/uploads/image');
        
        $this->assertEquals(1, $updated);
        $this->assertStringContainsString('UPDATE', $GLOBALS['wpdb']->last_query);
    }
    /*
    public function test_replaceMetaInContent_variants() {
        $tested = new mvbplugins\extmedialib\Replacer('1111');
        $ref = new \ReflectionClass($tested);

        // set target metadata and enable caption replacement
        $prop = $ref->getProperty('target_metadata');
        $prop->setAccessible(true);
        $prop->setValue($tested, [
            'image_meta' => ['alt_text' => 'newalt', 'caption' => 'newcap'],
            'file' => 'image.jpg',
            'sizes' => []
        ]);

        $prop = $ref->getProperty('docaption');
        $prop->setAccessible(true);
        $prop->setValue($tested, true);

        $method = $ref->getMethod('replaceMetaInContent');
        $method->setAccessible(true);

        // --- wp:image case (alt + caption)
        $image_block = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt="oldalt" /><figcaption>oldcap</figcaption></figure><!-- /wp:image -->';

        $out = $method->invoke($tested, '/uploads/image', $image_block, 'wp:image {"id":1111}', true, false, false);
        $this->assertStringContainsString('alt="newalt"', $out);
        $this->assertStringContainsString('newcap', $out);

        // --- wp:image case (alt + caption)
        $image_block = '<!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
                alt="Blumen im botanischen Garten München" class="wp-image-2829"/><figcaption class="wp-element-caption">test19</figcaption></figure>
                <!-- /wp:image -->';

        $out = $method->invoke($tested, '/uploads/image', $image_block, 'wp:image {"id":2829', true, false, false);
        $this->assertStringContainsString('alt="newalt"', $out);
        $this->assertStringContainsString('newcap', $out);

        // --- wp:gallery case (special handling)
        $gallery_inner = '<figure><img src="/uploads/image.jpg" alt="oldalt" data-id="1111"/><figcaption>oldcap</figcaption></figure>';
        $gallery_block = '<!-- wp:gallery {"ids":[1111]} -->' . $gallery_inner . '<!-- /wp:gallery -->';

        $out = $method->invoke($tested, '/uploads/image', $gallery_block, 'wp:gallery {"ids":[1111]}', false, true, false);
        $this->assertStringContainsString('alt="newalt"', $out);
        $this->assertStringContainsString('newcap', $out);

        // --- wp:gallery case II (special handling)
        $gallery_block = '<!-- wp:paragraph -->
            <p>nun folgt ein gutenberg gallerie block</p>
            <!-- /wp:paragraph -->

            <!-- wp:gallery {"linkTo":"none"} -->
            <figure class="wp-block-gallery has-nested-images columns-default is-cropped">
            
            <!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
            alt="oldalt" class="wp-image-2829"/><figcaption class="wp-element-caption">oldcap</figcaption></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2751,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/S-Satz/Kira-Sprung-1024x683.jpg" alt="" class="wp-image-2751"/></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2744,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/test/Elba-014-2560-18-1024x683.avif" alt="" class="wp-image-2744"/></figure>
            <!-- /wp:image -->

            <!-- wp:image {"id":2733,"sizeSlug":"large","linkDestination":"none"} -->
            <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/2023/02/PXL_20230129_101132675-1024x771.jpg" alt="" class="wp-image-2733"/></figure>
            <!-- /wp:image --></figure><!-- /wp:gallery -->';

        $out = $method->invoke($tested, '/uploads/image', $gallery_block, 'wp:gallery {"id":2829}', false, true, false);
        $this->assertStringContainsString('alt="newalt"', $out);
        $this->assertStringContainsString('newcap', $out);

        // --- wp:media-text case (alt only, caption may appear but still controlled by docaption)
        $mediatext = '<!-- wp:media-text {"id":1111} --><figure class="wp-block-media-text">'
            . '<img src="/uploads/image.jpg" alt="oldalt" /></figure><!-- /wp:media-text -->';

        $out = $method->invoke($tested, '/uploads/image', $mediatext, 'wp:media-text {"id":1111}', false, false, true);
        $this->assertStringContainsString('alt="newalt"', $out);

        // --- wp:media-text case II (alt only, caption may appear but still controlled by docaption)
        $mediatext = '<!-- wp:paragraph -->
                <p>nun folgt ein media + text block</p>
                <!-- /wp:paragraph -->

                <!-- wp:media-text {"mediaPosition":"right","mediaId":2829,"mediaLink":"http://localhost/wordpress/blumen-im-botanischen-garten-m%c3%bcnchen/", "linkDestination":"none","mediaType":"image","imageFill":false} -->
                <div class="wp-block-media-text has-media-on-the-right is-stacked-on-mobile">
                <div class="wp-block-media-text__content">
                <!-- wp:paragraph {"placeholder":"Content…"} -->
                <p>Das ist ein Bild mit Blumen.</p>
                <!-- /wp:paragraph -->
                </div><figure class="wp-block-media-text__media"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
                alt="Blumen im botanischen Garten München" class="wp-image-2829 size-full"/></figure></div>
                <!-- /wp:media-text -->';

        $out = $method->invoke($tested, '/uploads/image', $mediatext, 'wp:media-text {"id":2928}', false, false, true);
        $this->assertStringContainsString('alt="newalt"', $out);
    }
    */
    public function test_getAltCaption() {
        $tested = new mvbplugins\extmedialib\Replacer('1111');
        $ref = new \ReflectionClass($tested);

        $method = $ref->getMethod('getAltCaption');
        $method->setAccessible(true);

        $post_content = '<!-- wp:image {"id":2829,"sizeSlug":"large","linkDestination":"none"} -->
                <figure class="wp-block-image size-large"><img src="http://localhost/wordpress/wp-content/uploads/zeichnungen/DSC_2533-DxO-1-1024x683.webp" 
                alt="Blumen im botanischen Garten München" class="wp-image-2829"/><figcaption class="wp-element-caption">test19</figcaption></figure>
                <!-- /wp:image -->';

        $out = $method->invoke($tested, $post_content);
        $this->assertEquals('Blumen im botanischen Garten München', $out['alt_text']);
        $this->assertEquals('test19', $out['caption']);

        $post_content = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt = "oldalt" /><figcaption>oldcap</figcaption></figure><!-- /wp:image -->';

        $out = $method->invoke($tested, $post_content);
        $this->assertEquals('oldalt', $out['alt_text']);
        $this->assertEquals('oldcap', $out['caption']);

        $post_content = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt = "oldalt" /><figcaption>oldcap</figcaption></figure alt="2.oldalt"><!-- /wp:image -->';

        $out = $method->invoke($tested, $post_content);
        $this->assertEquals(null, $out['alt_text']);
        $this->assertEquals('oldcap', $out['caption']);

        // add a test for escaped Quotes in the alt-Text
        $post_content = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt="old&#38;alt" /><figcaption>oldcap</figcaption></figure><!-- /wp:image -->';

        $out = $method->invoke($tested, $post_content);
        $this->assertEquals('old&#38;alt', $out['alt_text']);
        $this->assertEquals('oldcap', $out['caption']);

        // test with html in figcaption
        $post_content = '<!-- wp:image {"id":1111} --><figure class="wp-block-image">'
            . '<img src="/uploads/image.jpg" alt="old&#38;alt" /><figcaption>Zweite <strong>Caption</strong></figcaption></figure><!-- /wp:image -->';

        $out = $method->invoke($tested, $post_content);
        $this->assertEquals('old&#38;alt', $out['alt_text']);
        $this->assertEquals('Zweite <strong>Caption</strong>', $out['caption']);
    }

}