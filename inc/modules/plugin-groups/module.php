<?php
/**
 * Module: Plugin Groups.
 *
 * Splits the Plugins list table into extra tabs (alongside core's own
 * All/Active/Inactive/...), one per group. Groups are not hardcoded in
 * code — they're defined on the module's settings tab: type a group name
 * per line, then pick a group per installed plugin from a dropdown. A
 * plugin newly installed later just shows up in that dropdown as
 * "Ungrouped"; nothing here needs a code change to stay in sync with what's
 * actually on the site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'plugin-groups',
	'name'            => __( 'Plugin Groups', 'szm-admin-suite' ),
	'description'     => __( 'Adds extra tabs to the Plugins screen (Systeem, Webshop, Marketing, ...) so a long plugin list stays organized. Groups and plugin assignments are configured here, not hardcoded.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-category',
	'default_enabled' => false,
	'boot'            => 'szm_as_plugin_groups_boot',
	'settings'        => array(
		'group_labels' => '',
		'assignments'  => array(),
	),
	'tab_title'       => __( 'Plugin Groups', 'szm-admin-suite' ),
	'render'          => 'szm_as_plugin_groups_render',
	'sanitize'        => 'szm_as_plugin_groups_sanitize',
) );

/**
 * Ordered list of group slugs => label, derived from the newline-separated
 * `group_labels` setting. Order of the lines is the order the tabs appear
 * in. Slugs come from sanitize_title(), de-duplicated with a numeric
 * suffix on collision (e.g. two lines that both sanitize to "seo").
 */
function szm_as_plugin_groups_get_groups( $settings = null ) {
	$settings = null !== $settings ? $settings : szm_as_plugin_groups_settings();
	$lines    = preg_split( '/\r\n|\r|\n/', (string) ( $settings['group_labels'] ?? '' ) );

	$groups = array();
	foreach ( $lines as $line ) {
		$label = trim( $line );
		if ( '' === $label ) {
			continue;
		}
		$slug = sanitize_title( $label );
		if ( '' === $slug ) {
			continue;
		}
		$base  = $slug;
		$i     = 2;
		while ( isset( $groups[ $slug ] ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		$groups[ $slug ] = $label;
	}
	return $groups;
}

/**
 * This module's own settings section, merged with defaults (mirrors how
 * every other module receives $settings in its boot/render callback, but
 * plugin_action_links/wp_redirect fire outside that flow so this fetches it
 * directly).
 */
function szm_as_plugin_groups_settings() {
	$all = SZM_Admin_Suite::instance()->get_settings();
	return isset( $all['plugin-groups'] ) ? (array) $all['plugin-groups'] : array();
}

/**
 * plugin_file => group slug, for plugins that have been explicitly
 * assigned. A plugin absent from this map is "Ungrouped".
 */
function szm_as_plugin_groups_get_assignments( $settings = null ) {
	$settings = null !== $settings ? $settings : szm_as_plugin_groups_settings();
	return isset( $settings['assignments'] ) ? (array) $settings['assignments'] : array();
}

function szm_as_plugin_groups_boot( $settings = array() ) {
	if ( ! is_admin() ) {
		return;
	}

	$groups = szm_as_plugin_groups_get_groups( $settings );
	if ( empty( $groups ) ) {
		return; // No groups configured yet — leave the Plugins screen untouched.
	}

	add_filter( 'views_plugins', 'szm_as_plugin_groups_views' );
	add_filter( 'all_plugins', 'szm_as_plugin_groups_filter_list' );

	// Sticky tab: keep ?plugin_status=<group> through action links,
	// redirects (activate/deactivate/update), the search box and bulk
	// actions, same as core's own status tabs already do for
	// active/inactive/etc.
	add_filter( 'removable_query_args', function ( $args ) {
		return array_diff( $args, array( 'plugin_status' ) );
	} );
	add_filter( 'plugin_action_links', 'szm_as_plugin_groups_sticky_action_links', 20 );
	add_filter( 'wp_redirect', 'szm_as_plugin_groups_sticky_redirect', 20 );
	add_action( 'admin_footer-plugins.php', 'szm_as_plugin_groups_sticky_js' );
}

/**
 * Extra views (tabs) on the Plugins screen, one per configured group, each
 * showing "(active/total)" and a red dot when any plugin in that group has
 * an update available.
 */
function szm_as_plugin_groups_views( $views ) {
	$groups = szm_as_plugin_groups_get_groups();
	if ( empty( $groups ) ) {
		return $views;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all_plugins    = get_plugins();
	$assignments    = szm_as_plugin_groups_get_assignments();
	$current_status = isset( $_GET['plugin_status'] ) ? sanitize_key( wp_unslash( $_GET['plugin_status'] ) ) : '';
	$updates        = get_site_transient( 'update_plugins' );

	$by_group = array();
	foreach ( $assignments as $plugin_file => $slug ) {
		if ( isset( $groups[ $slug ] ) && isset( $all_plugins[ $plugin_file ] ) ) {
			$by_group[ $slug ][] = $plugin_file;
		}
	}

	$extra_views = array();
	foreach ( $groups as $slug => $label ) {
		$plugin_files = isset( $by_group[ $slug ] ) ? $by_group[ $slug ] : array();
		$total        = count( $plugin_files );
		if ( 0 === $total ) {
			continue;
		}

		$active     = 0;
		$has_update = false;
		foreach ( $plugin_files as $plugin_file ) {
			if ( is_plugin_active( $plugin_file ) ) {
				$active++;
			}
			if ( isset( $updates->response[ $plugin_file ] ) ) {
				$has_update = true;
			}
		}

		$dot = $has_update
			? ' <span style="display:inline-block;width:8px;height:8px;background:#d63638;border-radius:50%;margin-left:2px;" title="' . esc_attr__( 'Update available', 'szm-admin-suite' ) . '"></span>'
			: '';

		$extra_views[ $slug ] = sprintf(
			'<a href="%s" class="%s">%s%s <span class="count">(%d/%d)</span></a>',
			esc_url( add_query_arg( 'plugin_status', $slug, admin_url( 'plugins.php' ) ) ),
			$current_status === $slug ? 'current' : '',
			esc_html( $label ),
			$dot,
			$active,
			$total
		);
	}

	if ( empty( $extra_views ) ) {
		return $views;
	}

	$extra_views['szm-as-sep'] = '<span style="margin:0 10px;color:#ccc;">|</span>';
	return array_merge( $extra_views, $views );
}

/**
 * Restricts the plugin list to the current group when ?plugin_status is one
 * of our group slugs. Core's own statuses (active, inactive, ...) pass
 * straight through untouched.
 */
function szm_as_plugin_groups_filter_list( $plugins ) {
	$status = isset( $_GET['plugin_status'] ) ? sanitize_key( wp_unslash( $_GET['plugin_status'] ) ) : '';
	$groups = szm_as_plugin_groups_get_groups();
	if ( '' === $status || ! isset( $groups[ $status ] ) ) {
		return $plugins;
	}

	$assignments = szm_as_plugin_groups_get_assignments();
	$in_group    = array_keys( array_filter( $assignments, function ( $slug ) use ( $status ) {
		return $slug === $status;
	} ) );

	return array_intersect_key( $plugins, array_flip( $in_group ) );
}

function szm_as_plugin_groups_sticky_action_links( $actions ) {
	if ( empty( $_REQUEST['plugin_status'] ) ) {
		return $actions;
	}
	$status = sanitize_key( wp_unslash( $_REQUEST['plugin_status'] ) );
	if ( ! isset( szm_as_plugin_groups_get_groups()[ $status ] ) ) {
		return $actions;
	}
	foreach ( $actions as $key => $link ) {
		if ( preg_match( '/href=([\'"])(.*?)\1/', $link, $matches ) ) {
			$new_url        = add_query_arg( 'plugin_status', $status, html_entity_decode( $matches[2] ) );
			$actions[ $key ] = str_replace( $matches[2], esc_url( $new_url ), $link );
		}
	}
	return $actions;
}

function szm_as_plugin_groups_sticky_redirect( $location ) {
	if ( false === strpos( $location, 'plugins.php' ) || empty( $_REQUEST['plugin_status'] ) ) {
		return $location;
	}
	$status = sanitize_key( wp_unslash( $_REQUEST['plugin_status'] ) );
	if ( ! isset( szm_as_plugin_groups_get_groups()[ $status ] ) ) {
		return $location;
	}
	return add_query_arg( 'plugin_status', $status, $location );
}

function szm_as_plugin_groups_sticky_js() {
	$status = isset( $_REQUEST['plugin_status'] ) ? sanitize_key( wp_unslash( $_REQUEST['plugin_status'] ) ) : '';
	if ( '' === $status || ! isset( szm_as_plugin_groups_get_groups()[ $status ] ) ) {
		return;
	}
	?>
	<script>
	jQuery(function ($) {
		$('form#bulk-action-selector-top, form#bulk-action-selector-bottom, #search-plugins-filter')
			.append('<input type="hidden" name="plugin_status" value="<?php echo esc_js( $status ); ?>">');
	});
	</script>
	<?php
}

/* ---------------------------------------------------------------------
 * Settings tab
 * ------------------------------------------------------------------ */

function szm_as_plugin_groups_sanitize( $input, $current ) {
	$labels_raw = isset( $input['group_labels'] ) ? (string) wp_unslash( $input['group_labels'] ) : '';
	$labels_raw = implode( "\n", array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $labels_raw ) ), 'strlen' ) );

	$groups = szm_as_plugin_groups_get_groups( array( 'group_labels' => $labels_raw ) );

	$posted      = isset( $input['assignments'] ) && is_array( $input['assignments'] ) ? $input['assignments'] : array();
	$assignments = array();
	foreach ( $posted as $plugin_file => $slug ) {
		$plugin_file = sanitize_text_field( wp_unslash( $plugin_file ) );
		$slug        = sanitize_key( wp_unslash( $slug ) );
		if ( '' !== $slug && isset( $groups[ $slug ] ) ) {
			$assignments[ $plugin_file ] = $slug;
		}
	}

	return array(
		'group_labels' => $labels_raw,
		'assignments'  => $assignments,
	);
}

function szm_as_plugin_groups_render( $settings ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all_plugins = get_plugins();
	$groups      = szm_as_plugin_groups_get_groups( $settings );
	$assignments = szm_as_plugin_groups_get_assignments( $settings );
	ksort( $all_plugins );
	?>
	<h3><?php esc_html_e( 'Groups', 'szm-admin-suite' ); ?></h3>
	<p class="description"><?php esc_html_e( 'One group name per line. The order here is the order the tabs appear in on the Plugins screen.', 'szm-admin-suite' ); ?></p>
	<textarea
		name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[plugin-groups][group_labels]"
		rows="7" cols="40"
		style="font-family:monospace; max-width:100%;"
	><?php echo esc_textarea( isset( $settings['group_labels'] ) ? $settings['group_labels'] : '' ); ?></textarea>

	<h3 style="margin-top:24px;"><?php esc_html_e( 'Plugin assignments', 'szm-admin-suite' ); ?></h3>
	<?php if ( empty( $groups ) ) : ?>
		<p class="description"><?php esc_html_e( 'Add at least one group above, save, then come back here to assign plugins to it.', 'szm-admin-suite' ); ?></p>
	<?php else : ?>
		<p class="description"><?php esc_html_e( 'A plugin left on "Ungrouped" only shows up in core\'s own All/Active/Inactive tabs, not in any group tab.', 'szm-admin-suite' ); ?></p>
		<table class="widefat striped" style="max-width:700px; margin-top:8px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Plugin', 'szm-admin-suite' ); ?></th>
					<th style="width:220px;"><?php esc_html_e( 'Group', 'szm-admin-suite' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $all_plugins as $plugin_file => $data ) :
					$current = isset( $assignments[ $plugin_file ] ) ? $assignments[ $plugin_file ] : '';
					?>
					<tr>
						<td>
							<?php echo esc_html( $data['Name'] ); ?>
							<br><code style="color:#888;"><?php echo esc_html( $plugin_file ); ?></code>
						</td>
						<td>
							<select name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[plugin-groups][assignments][<?php echo esc_attr( $plugin_file ); ?>]">
								<option value="" <?php selected( $current, '' ); ?>><?php esc_html_e( 'Ungrouped', 'szm-admin-suite' ); ?></option>
								<?php foreach ( $groups as $slug => $label ) : ?>
									<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php
}
