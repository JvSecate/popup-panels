<?php
/**
 * Plugin Name: Popup Panels
 * Description: Adds popup panel content types and shared open and close behavior for the theme.
 * Version: 1.0.0
 * Author: Jv Secate
 * Text Domain: popup-panels
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'POPUP_PANELS_VERSION', '1.0.0' );
define( 'POPUP_PANELS_DIR', plugin_dir_path( __FILE__ ) );
define( 'POPUP_PANELS_URL', plugin_dir_url( __FILE__ ) );

if ( ! function_exists( 'popup_panels_target' ) ) {
	/**
	 * Return a stable target/id for a formatted text popup panel.
	 */
	function popup_panels_target( $post_id ) {
		$target = get_post_meta( $post_id, '_popup_panel_target', true );
		if ( ! $target ) {
			$post   = get_post( $post_id );
			$target = $post && ! empty( $post->post_name ) ? $post->post_name : 'popup-' . $post_id;
		}

		$target = sanitize_title( $target );

		return $target ? $target : 'popup-' . absint( $post_id );
	}
}

add_action( 'plugins_loaded', function () {
	load_plugin_textdomain(
		'popup-panels',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);
} );

add_action( 'init', 'popup_panels_register_post_type' );
add_action( 'add_meta_boxes', 'popup_panels_add_meta_boxes' );
add_action( 'save_post_popup_panel', 'popup_panels_save_post' );
add_action( 'wp_enqueue_scripts', 'popup_panels_enqueue_assets' );
add_action( 'wp_footer', 'popup_panels_render_custom_popups', 6 );
add_shortcode( 'popup_panel_link', 'popup_panel_link_shortcode' );

function popup_panels_asset_version( $relative_path ) {
	$path = POPUP_PANELS_DIR . ltrim( $relative_path, '/' );

	return file_exists( $path ) ? POPUP_PANELS_VERSION . '-' . filemtime( $path ) : POPUP_PANELS_VERSION;
}

function popup_panels_register_post_type() {
	register_post_type(
		'popup_panel',
		[
			'labels'       => [
				'name'          => __( 'Popup panels', 'popup-panels' ),
				'singular_name' => __( 'Popup panel', 'popup-panels' ),
				'add_new_item'  => __( 'Add New Popup panel', 'popup-panels' ),
				'edit_item'     => __( 'Edit Popup panel', 'popup-panels' ),
				'new_item'      => __( 'New Popup panel', 'popup-panels' ),
				'view_item'     => __( 'View Popup panel', 'popup-panels' ),
				'menu_name'     => __( 'Popup panels', 'popup-panels' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'menu_icon'    => 'dashicons-format-aside',
			'supports'     => [ 'title', 'editor', 'page-attributes' ],
			'show_in_rest' => true,
		]
	);
}

function popup_panels_add_meta_boxes() {
	add_meta_box(
		'popup_panel_fields',
		__( 'Popup settings', 'popup-panels' ),
		'popup_panels_metabox',
		'popup_panel',
		'side'
	);
}

function popup_panels_field( $post_id, $key, $label, $type = 'text', $placeholder = '' ) {
	$value = get_post_meta( $post_id, $key, true );
	printf( '<p><label><strong>%s</strong><br>', esc_html( $label ) );
	if ( 'textarea' === $type ) {
		printf(
			'<textarea name="%1$s" rows="3" style="width:100%%" placeholder="%3$s">%2$s</textarea>',
			esc_attr( $key ),
			esc_textarea( $value ),
			esc_attr( $placeholder )
		);
	} else {
		printf(
			'<input type="%4$s" name="%1$s" value="%2$s" style="width:100%%" placeholder="%3$s">',
			esc_attr( $key ),
			esc_attr( $value ),
			esc_attr( $placeholder ),
			esc_attr( $type )
		);
	}
	echo '</label></p>';
}

function popup_panels_select_field( $post_id, $key, $label, array $options, $help_text = '' ) {
	$value = get_post_meta( $post_id, $key, true );
	if ( ! in_array( $value, array_keys( $options ), true ) ) {
		$value = array_key_first( $options );
	}

	echo '<p><label><strong>' . esc_html( $label ) . '</strong><br>';
	echo '<select name="' . esc_attr( $key ) . '" style="width:100%">';

	foreach ( $options as $option_value => $option_label ) {
		echo '<option value="' . esc_attr( $option_value ) . '"' . selected( $value, $option_value, false ) . '>' . esc_html( $option_label ) . '</option>';
	}

	echo '</select>';

	if ( $help_text ) {
		echo '<br><span class="description">' . esc_html( $help_text ) . '</span>';
	}

	echo '</label></p>';
}

function popup_panels_metabox( $post ) {
	wp_nonce_field( 'popup_panels_save', 'popup_panels_nonce' );
	popup_panels_field( $post->ID, '_popup_panel_target', __( 'Popup target', 'popup-panels' ), 'text', 'instructions, size-guide, warranty' );
	popup_panels_select_field(
		$post->ID,
		'_popup_panel_layout',
		__( 'Popup layout', 'popup-panels' ),
		[
			'drawer' => __( 'Drawer', 'popup-panels' ),
			'modal'  => __( 'Classic popup', 'popup-panels' ),
		],
		__( 'Drawer slides in from the right. Classic popup appears centered.', 'popup-panels' )
	);
	$target = popup_panels_target( $post->ID );
	echo '<p style="padding:10px 12px;background:#f6f7f7;border-left:4px solid #2271b1;">';
	echo '<strong>' . esc_html__( 'Link trigger:', 'popup-panels' ) . '</strong><br>';
	echo '<code>' . esc_html( '[popup_panel_link target="' . $target . '" label="' . get_the_title( $post ) . '"]' ) . '</code><br>';
	echo '<span class="description">' . esc_html__( 'Use this shortcode in a product description, page, or post. Header icons can also use this target with action Show.', 'popup-panels' ) . '</span>';
	echo '</p>';
}

function popup_panels_save_post( $post_id ) {
	if ( ! isset( $_POST['popup_panels_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['popup_panels_nonce'] ) ), 'popup_panels_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['_popup_panel_target'] ) ) {
		update_post_meta( $post_id, '_popup_panel_target', sanitize_title( wp_unslash( $_POST['_popup_panel_target'] ) ) );
	}

	if ( isset( $_POST['_popup_panel_layout'] ) ) {
		$layout = sanitize_key( wp_unslash( $_POST['_popup_panel_layout'] ) );
		if ( ! in_array( $layout, [ 'drawer', 'modal' ], true ) ) {
			$layout = 'drawer';
		}
		update_post_meta( $post_id, '_popup_panel_layout', $layout );
	}
}

function popup_panels_render_custom_popups() {
	$panels = get_posts(
		[
			'post_type'      => 'popup_panel',
			'post_status'    => 'publish',
			'posts_per_page' => 50,
			'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'ASC' ],
			'order'          => 'ASC',
		]
	);

	if ( ! $panels ) {
		return;
	}

	foreach ( $panels as $panel_post ) {
		$target = popup_panels_target( $panel_post->ID );
		$layout = get_post_meta( $panel_post->ID, '_popup_panel_layout', true );
		$layout = in_array( $layout, [ 'drawer', 'modal' ], true ) ? $layout : 'drawer';

		if ( ! $target ) {
			continue;
		}
		?>
		<div class="popup-panel popup-panel--<?php echo esc_attr( $layout ); ?>" data-popup-panel="<?php echo esc_attr( $target ); ?>" aria-hidden="true">
			<div class="popup-panel__backdrop" data-popup-close></div>
			<aside class="popup-panel__panel" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( get_the_title( $panel_post ) ); ?>">
				<button class="popup-panel__close" type="button" data-popup-close aria-label="<?php echo esc_attr__( 'Close', 'popup-panels' ); ?>">&times;</button>
				<h2><?php echo esc_html( get_the_title( $panel_post ) ); ?></h2>
				<div class="popup-panel__content">
					<?php echo apply_filters( 'the_content', $panel_post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</aside>
		</div>
		<?php
	}
}

function popup_panel_link_shortcode( $atts ) {
	$atts = shortcode_atts(
		[
			'target' => '',
			'label'  => __( 'Open information', 'popup-panels' ),
			'class'  => 'popup-panel-link',
		],
		$atts,
		'popup_panel_link'
	);

	$target = sanitize_title( $atts['target'] );

	if ( ! $target ) {
		return '';
	}

	return sprintf(
		'<a href="#%1$s" class="%2$s" data-popup-target="%1$s">%3$s</a>',
		esc_attr( $target ),
		esc_attr( $atts['class'] ),
		esc_html( $atts['label'] )
	);
}

function popup_panels_enqueue_assets() {
	wp_enqueue_script(
		'popup-panels',
		POPUP_PANELS_URL . 'assets/popup-panels.js',
		[],
		popup_panels_asset_version( 'assets/popup-panels.js' ),
		true
	);
}
