<?php

declare(strict_types=1);

namespace mvbplugins\Admin;

// TODO : show the imagick version and supported formats in the settings page. copy from strip_meta

/**
 * Class AdminSettings. Configure the plugin settings page.
 * 
 * @phpstan-type FieldArray array{
 *     id: string,
 *     label: string,
 *     description: string,
 *     type: string,
 *     options?: array<string, string>,
 *     min?: int,
 *     max?: int,
 * } 
 */
class AdminSettings {

	/**
	 * Capability required by the user to access the My Plugin menu entry.
	 *
	 * @var string $capability
	 */
	private $capability = 'manage_options';

	/**
	 * Array of fields that should be displayed in the settings page.
	 *
	 * @var FieldArray $fields
	 * @phpstan-var array<int, FieldArray> $fields
	 */
	private $fields = [];

	/**
	 * Array of fields that should be displayed in the settings page.
	 *
	 * @var FieldArray $hookFields
	 * @phpstan-var array<int, FieldArray> $hookFields
	 */
    private $hookFields = [];

	/**
	 * Array of image editor related settings fields.
	 *
	 * @var FieldArray $imageEditorFields
	 * @phpstan-var array<int, FieldArray> $imageEditorFields
	 */
	private $imageEditorFields = [];

	/**
	 * The Plugin Settings constructor.
	 */
	public function __construct() {
		$this->fields = 
		[
			[
				'id'          => 'use_rest_api_extension',
				'label'       => __( 'Use REST API Extension', 'change-to-plugin-textdomain' ),
				'description' => __( 'Enables the REST API extension for the plugin (required for all following options)', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			],
			[
				'id'          => 'update_post_on_rest_update',
				'label'       => __( 'Update Post with Caption and alt_text', 'change-to-plugin-textdomain' ),
				'description' => __( 'Updates the caption and alt_text in the content when they are changed via REST API. Used as an alternative to \'docaption\' in the REST request', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			]
		];

        $this->hookFields = 
		[
            [
                'id'          => 'use_media_upload_hook',
                'label'       => __( 'Use Media Upload Hook', 'change-to-plugin-textdomain' ),
                'description' => __( 'Adds Metadata to File-Types to be treated like JPG upload (required for all following options)', 'change-to-plugin-textdomain' ),
                'type'        => 'checkbox',
            ],
			[
				'id'          => 'treat_jpg_upload',
				'label'       => __( 'for JPG', 'change-to-plugin-textdomain' ),
				'description' => __( 'Treat JPG-Files during Media Upload', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			],
			[
				'id'          => 'treat_webp_upload',
				'label'       => __( 'for WEBP', 'change-to-plugin-textdomain' ),
				'description' => __( 'Treat WEBP-Files during Media Upload', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			],
			[
				'id'          => 'treat_avif_upload',
				'label'       => __( 'for AVIF', 'change-to-plugin-textdomain' ),
				'description' => __( 'Treat AVIF-Files during Media Upload', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			],
			// add two settings to select for post_excerpt and _wp_attachment_image_alt whether they are filled by the XMP title or description or left empty.
			[
                'id'          => 'fill_post_excerpt_from_xmp',
                'label'       => __( 'post_excerpt source', 'change-to-plugin-textdomain' ),
                'description' => __( 'Choose how post_excerpt used as sub-title / caption is filled', 'change-to-plugin-textdomain' ),
                'type'        => 'select',
                'options'     => [
                    ''            => __( 'Leave empty', 'change-to-plugin-textdomain' ),
                    'xmp_title'   => __( 'XMP title', 'change-to-plugin-textdomain' ),
                    'xmp_desc'    => __( 'XMP description', 'change-to-plugin-textdomain' ),
                ],
            ],
            [
                'id'          => 'fill_alt_from_xmp',
                'label'       => __( '_wp_attachment_image_alt source', 'change-to-plugin-textdomain' ),
                'description' => __( 'Choose how image alt text is filled', 'change-to-plugin-textdomain' ),
                'type'        => 'select',
                'options'     => [
                    ''            => __( 'Leave empty', 'change-to-plugin-textdomain' ),
                    'xmp_title'   => __( 'XMP title', 'change-to-plugin-textdomain' ),
                    'xmp_desc'    => __( 'XMP description', 'change-to-plugin-textdomain' ),
                ],
            ],
        ];

		// Settings used by Custom_Image_Editor in classes/class_image_editor.php.
		$this->imageEditorFields =
		[
			[
				'id'          => 'use_custom_image_editor',
				'label'       => __( 'Use Custom Image Editor', 'change-to-plugin-textdomain' ),
				'description' => __( 'Enable the custom Imagick editor for media resizing.', 'change-to-plugin-textdomain' ),
				'type'        => 'checkbox',
			],
			[
				'id'          => 'jpeg_resize_quality',
				'label'       => __( 'JPEG Resize Quality', 'change-to-plugin-textdomain' ),
				'description' => __( 'Quality for JPEG resizing (default in image editor class: 85).', 'change-to-plugin-textdomain' ),
				'type'        => 'number',
				'min' 		  => 0,
				'max' 		  => 100,
			],
			[
				'id'          => 'webp_resize_quality',
				'label'       => __( 'WEBP Resize Quality', 'change-to-plugin-textdomain' ),
				'description' => __( 'Quality for WEBP resizing (default in image editor class: 55).', 'change-to-plugin-textdomain' ),
				'type'        => 'number',
				'min' 		  => 0,
				'max' 		 => 100,
			],
			[
				'id'          => 'avif_resize_quality',
				'label'       => __( 'AVIF Resize Quality', 'change-to-plugin-textdomain' ),
				'description' => __( 'Quality for AVIF resizing (default in image editor class: 55).', 'change-to-plugin-textdomain' ),
				'type'        => 'number',
				'min' 		  => 0,
				'max' 		 => 100,
			],
			[
				'id'          => 'min_resize_quality',
				'label'       => __( 'Minimum Resize Quality', 'change-to-plugin-textdomain' ),
				'description' => __( 'Minimum quality for image resizing (default in image editor class: 30).', 'change-to-plugin-textdomain' ),
				'type'        => 'number',
				'min' 		  => 0,
				'max' 		  => 100,
			],
		];

		add_action( 'admin_init', [$this, 'settings_init'] );
		add_action( 'admin_menu', [$this, 'options_page'] );
	}

	/**
	 * Register the settings and all fields.
	 */
	public function settings_init() : void {

		register_setting( 'media-lib-extension', 'media-lib-extension' );

		// Register a new section.
		add_settings_section(
			'media-lib-hooks-section',
			__( 'Media Hook Settings', 'change-to-plugin-textdomain' ),
			[$this, 'render_section'],
			'media-lib-extension',
			['before_section' => '<hr>']
		);

		/* Register All The Fields. */
		foreach( $this->hookFields as $field ) {
			// Register a new field in the main section.
			add_settings_field(
				$field['id'], /* ID for the field. Only used internally. To set the HTML ID attribute, use $args['label_for']. */
				$field['label'], /* Label for the field. */
				[$this, 'render_field'], /* The name of the callback public function. */
				'media-lib-extension', /* The menu page on which to display this field. */
				'media-lib-hooks-section', /* The section of the settings page in which to show the box. */
				[
					'label_for' => $field['id'], /* The ID of the field. */
					'class' => 'media-lib-extension_row', /* The class of the field. */
					'field' => $field, /* Custom data for the field. */
				]
			);
		}

        // Register a new section.
		add_settings_section(
			'media-lib-extension-section',
			__( 'REST-API Settings', 'change-to-plugin-textdomain' ),
			[$this, 'render_section'],
			'media-lib-extension',
			['before_section' => '<hr>']
		);

		/* Register All The Fields. */
		foreach( $this->fields as $field ) {
			// Register a new field in the main section.
			add_settings_field(
				$field['id'], /* ID for the field. Only used internally. To set the HTML ID attribute, use $args['label_for']. */
				$field['label'], /* Label for the field. */
				[$this, 'render_field'], /* The name of the callback public function. */
				'media-lib-extension', /* The menu page on which to display this field. */
				'media-lib-extension-section', /* The section of the settings page in which to show the box. */
				[
					'label_for' => $field['id'], /* The ID of the field. */
					'class' => 'media-lib-extension_row', /* The class of the field. */
					'field' => $field, /* Custom data for the field. */
				]
			);
		}

		add_settings_section(
			'media-lib-image-editor-section',
			__( 'Image Editor Settings', 'change-to-plugin-textdomain' ),
			[$this, 'render_section'],
			'media-lib-extension',
			['before_section' => '<hr>']
		);

		/* Register all image editor fields. */
		foreach( $this->imageEditorFields as $field ) {
			add_settings_field(
				$field['id'],
				$field['label'],
				[$this, 'render_field'],
				'media-lib-extension',
				'media-lib-image-editor-section',
				[
					'label_for' => $field['id'],
					'class' => 'media-lib-extension_row',
					'field' => $field,
				]
			);
		}
	}

	/**
	 * Add a subpage to the WordPress Settings menu.
	 */
	public function options_page() : void {
		add_submenu_page(
			'options-general.php', /* Parent Menu Slug */
			__( 'Media Library Extension Settings', 'change-to-plugin-textdomain' ), /* Page Title */
			__( 'Media Lib Extension', 'change-to-plugin-textdomain' ), /* Menu Title */
			$this->capability, /* Capability */
			'media-lib-extension', /* Menu Slug */
			[$this, 'render_options_page'], /* Callback */
		);
	}

	/**
	 * Render the settings page.
	 */
	public function render_options_page() : void {

		// check user capabilities
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		// show error/update messages
		settings_errors( 'wporg_messages' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<h2 class="description"><?php echo esc_html( __( 'Settings for the Extension of the Media Library via REST-API and Hooks', 'change-to-plugin-textdomain' ) ); ?></h2>
			<form action="options.php" method="post">
				<?php
				/* output the fields for the registered settings */
				settings_fields( 'media-lib-extension' );
				/* output setting sections and their fields */
				/* (sections are registered for "wporg", each field is registered to a specific section) */
				do_settings_sections( 'media-lib-extension' );
				/* output save settings button */
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a settings field.
	 *
	 * @param array<string, FieldArray> $args Args to configure the field.
	 */
	public function render_field( array $args ) : void {

		$field = $args['field'];

		// Get the value of the setting we've registered with register_setting()
		$options = get_option( 'media-lib-extension' );

		switch ( $field['type'] ) {

			case "text": {
				?>
				<input
					type="text"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "checkbox": {
				?>
				<label for="<?php echo esc_attr( $field['id'] ); ?>">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $field['id'] ); ?>"
						name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
						value="1"
						<?php echo isset( $options[ $field['id'] ] ) ? ( checked( $options[ $field['id'] ], 1, false ) ) : ( '' ); ?>
					>
					<?php echo esc_html( $field['description'] ); ?>
				</label>
				<?php
				break;
			}

			case "textarea": {
				?>
				<textarea
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
				><?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?></textarea>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "select": {
				?>
				<label for="<?php echo esc_attr( $field['id'] ); ?>">
					<select
						id="<?php echo esc_attr( $field['id'] ); ?>"
						name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					>
						<?php foreach( ($field['options'] ?? []) as $key => $option ) { ?>
							<option value="<?php echo esc_attr( $key ); ?>" 
								<?php echo isset( $options[ $field['id'] ] ) ? ( selected( $options[ $field['id'] ], $key, false ) ) : ( '' ); ?>
							>
								<?php echo esc_html( $option ); ?>
							</option>
						<?php } ?>
					</select>
					<?php echo esc_html( $field['description'] ); ?>
				</label>
				<?php
				break;
			}

			case "password": {
				?>
				<input
					type="password"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "wysiwyg": {
				wp_editor(
					isset( $options[ $field['id'] ] ) ? $options[ $field['id'] ] : '',
					$field['id'],
					array(
						'textarea_name' => 'media-lib-extension[' . $field['id'] . ']',
						'textarea_rows' => 5,
					)
				);
				break;
			}

			case "email": {
				?>
				<input
					type="email"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "url": {
				?>
				<input
					type="url"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "color": {
				?>
				<input
					type="color"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}

			case "date": {
				?>
				<input
					type="date"
					id="<?php echo esc_attr( $field['id'] ); ?>"
					name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
					value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
				>
				<p class="description">
					<?php echo esc_html( $field['description'] ); ?>
				</p>
				<?php
				break;
			}
			// add a number input type for the quality settings with min 0 and max 100.
			case "number": {
				?>
				<label for="<?php echo esc_attr( $field['id'] ); ?>">
					<input
						type="number"
						id="<?php echo esc_attr( $field['id'] ); ?>"
						name="media-lib-extension[<?php echo esc_attr( $field['id'] ); ?>]"
						value="<?php echo isset( $options[ $field['id'] ] ) ? esc_attr( $options[ $field['id'] ] ) : ''; ?>"
						min="<?php echo esc_attr( (string)($field['min'] ?? '') ); ?>"
						max="<?php echo esc_attr( (string)($field['max'] ?? '') ); ?>"
					>
					<?php echo esc_html( $field['description'] ); ?>
				</label>
				<?php
				break;
			}

		}
	}

	/**
	 * Render a section on a page.
	 */
	public function render_section( ) : void {
	}

}

