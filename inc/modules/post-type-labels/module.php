<?php
/**
 * Module: Post Type Labels.
 *
 * Renames the admin labels of a post type (e.g. Posts -> "Verhalen") from
 * settings: per post type a singular + plural name, expanded into the full
 * WP label set through editable templates. Nothing client-specific lives in
 * code; the starting values are data in defaults.php. A type left blank
 * keeps WordPress' own labels, and a label key without a template keeps the
 * label the post type registered itself.
 *
 * Labels are applied late on 'init' (after every post type is registered)
 * by editing the registered post type object, so it also works for custom
 * post types registered by other plugins.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'post-type-labels',
	'name'            => __( 'Post Type Labels', 'szm-admin-suite' ),
	'description'     => __( 'Rename a post type in the admin (for example Posts to "Stories") by entering a singular and plural name. All labels (menu, "Add new", "Search", ...) follow from editable templates; blank types keep WordPress\' own labels.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-tag',
	'default_enabled' => false,
	'boot'            => 'szm_as_ptl_boot',
	'settings'        => szm_as_ptl_seed_settings(),
	'tab_title'       => __( 'Post Type Labels', 'szm-admin-suite' ),
	'render'          => 'szm_as_ptl_render',
	'sanitize'        => 'szm_as_ptl_sanitize',
) );

/**
 * Starting settings, read from defaults.php. Templates are stored as one
 * "key = template" line per label (same textarea pattern as Plugin Groups).
 */
function szm_as_ptl_seed_settings() {
	$file     = __DIR__ . '/defaults.php';
	$defaults = file_exists( $file ) ? include $file : array();
	$lines    = array();
	foreach ( (array) ( $defaults['templates'] ?? array() ) as $key => $template ) {
		$lines[] = $key . ' = ' . $template;
	}
	return array(
		'templates' => implode( "\n", $lines ),
		'types'     => (array) ( $defaults['types'] ?? array() ),
	);
}

/** Parse "key = template" lines into an array, ignoring blanks/garbage. */
function szm_as_ptl_parse_templates( $raw ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
		$parts = explode( '=', $line, 2 );
		if ( 2 !== count( $parts ) ) {
			continue;
		}
		$key      = sanitize_key( trim( $parts[0] ) );
		$template = trim( $parts[1] );
		if ( '' !== $key && '' !== $template ) {
			$out[ $key ] = $template;
		}
	}
	return $out;
}

/** Expand a template for a singular/plural pair. */
function szm_as_ptl_expand( $template, $singular, $plural ) {
	return strtr( $template, array(
		'{singular}'    => $singular,
		'{plural}'      => $plural,
		'{singular_lc}' => mb_strtolower( $singular ),
		'{plural_lc}'   => mb_strtolower( $plural ),
	) );
}

/**
 * Pure: the labels to apply for one type, given settings. Empty array when
 * the type is not configured (blank singular or plural).
 */
function szm_as_ptl_labels_for( $post_type, array $settings ) {
	$type     = $settings['types'][ $post_type ] ?? array();
	$singular = trim( (string) ( $type['singular'] ?? '' ) );
	$plural   = trim( (string) ( $type['plural'] ?? '' ) );
	if ( '' === $singular || '' === $plural ) {
		return array();
	}
	$labels = array();
	foreach ( szm_as_ptl_parse_templates( $settings['templates'] ?? '' ) as $key => $template ) {
		$labels[ $key ] = szm_as_ptl_expand( $template, $singular, $plural );
	}
	return $labels;
}

function szm_as_ptl_boot( $settings = array() ) {
	if ( empty( $settings['types'] ) ) {
		return;
	}
	// After every post type (core, theme, plugin) is registered.
	add_action( 'init', function () use ( $settings ) {
		foreach ( array_keys( (array) $settings['types'] ) as $post_type ) {
			$obj = get_post_type_object( $post_type );
			if ( ! $obj ) {
				continue;
			}
			$new = szm_as_ptl_labels_for( $post_type, $settings );
			if ( ! $new ) {
				continue;
			}
			$obj->labels = (object) array_merge( (array) $obj->labels, $new );
			if ( isset( $new['name'] ) ) {
				$obj->label = $new['name'];
			}
		}
	}, 999 );
}

/* ---------------------------------------------------------------------
 * Settings tab
 * ------------------------------------------------------------------ */

function szm_as_ptl_sanitize( $input, $current ) {
	$templates = isset( $input['templates'] ) ? (string) wp_unslash( $input['templates'] ) : '';
	$lines     = array();
	foreach ( szm_as_ptl_parse_templates( $templates ) as $key => $template ) {
		$lines[] = $key . ' = ' . $template;
	}

	// Blank entries are stored explicitly: a missing type would fall back to
	// the seeded default again in the settings merge.
	$types  = array();
	$posted = isset( $input['types'] ) && is_array( $input['types'] ) ? $input['types'] : array();
	foreach ( $posted as $post_type => $row ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! is_array( $row ) ) {
			continue;
		}
		$types[ $post_type ] = array(
			'singular' => sanitize_text_field( wp_unslash( $row['singular'] ?? '' ) ),
			'plural'   => sanitize_text_field( wp_unslash( $row['plural'] ?? '' ) ),
		);
	}

	return array(
		'templates' => implode( "\n", $lines ),
		'types'     => $types,
	);
}

function szm_as_ptl_render( $settings ) {
	$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
	$types      = isset( $settings['types'] ) ? (array) $settings['types'] : array();
	$name       = SZM_AS_OPTION . '[post-type-labels]';
	?>
	<h3><?php esc_html_e( 'Post types', 'szm-admin-suite' ); ?></h3>
	<p class="description"><?php esc_html_e( 'Fill in a singular and plural name to rename a post type in the admin. Leave both blank to keep WordPress\' own labels.', 'szm-admin-suite' ); ?></p>
	<table class="widefat striped" style="max-width:700px; margin-top:8px;">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Post type', 'szm-admin-suite' ); ?></th>
				<th><?php esc_html_e( 'Singular', 'szm-admin-suite' ); ?></th>
				<th><?php esc_html_e( 'Plural', 'szm-admin-suite' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $post_types as $slug => $obj ) :
				// Show the current value, or WP's own label as placeholder.
				$row = isset( $types[ $slug ] ) ? $types[ $slug ] : array();
				?>
				<tr>
					<td><?php echo esc_html( $obj->labels->name ); ?> <code style="color:#888;"><?php echo esc_html( $slug ); ?></code></td>
					<td><input type="text" class="regular-text" style="width:100%;" name="<?php echo esc_attr( $name ); ?>[types][<?php echo esc_attr( $slug ); ?>][singular]" value="<?php echo esc_attr( $row['singular'] ?? '' ); ?>"></td>
					<td><input type="text" class="regular-text" style="width:100%;" name="<?php echo esc_attr( $name ); ?>[types][<?php echo esc_attr( $slug ); ?>][plural]" value="<?php echo esc_attr( $row['plural'] ?? '' ); ?>"></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h3 style="margin-top:24px;"><?php esc_html_e( 'Label templates', 'szm-admin-suite' ); ?></h3>
	<p class="description"><?php esc_html_e( 'One "label = template" per line. Placeholders: {singular}, {plural}, {singular_lc}, {plural_lc}. Labels without a line keep what the post type registered itself. Valid keys are the WordPress post type label names (name, add_new_item, not_found, ...).', 'szm-admin-suite' ); ?></p>
	<textarea
		name="<?php echo esc_attr( $name ); ?>[templates]"
		rows="14" cols="60"
		style="font-family:monospace; max-width:100%;"
	><?php echo esc_textarea( isset( $settings['templates'] ) ? $settings['templates'] : '' ); ?></textarea>
	<?php
}
