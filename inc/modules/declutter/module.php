<?php
/**
 * Module: Declutter.
 *
 * Keeps the dashboard from filling up with widgets. Any dashboard widget
 * that isn't explicitly allow-listed (Welcome, Plugin Recommendations, Site
 * Health) starts unchecked in Screen Options — including ones a freshly
 * installed plugin adds on its own (Yoast, Duplicator, Sucuri, etc. all like
 * to drop their own dashboard box). This is a soft default, not a removal:
 * the widget is still there and each user can tick it back on for
 * themselves via Screen Options. Once a user has made a choice about a
 * given widget, we never touch that choice again.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'declutter',
	'name'            => __( 'Declutter', 'szm-admin-suite' ),
	'description'     => __( 'Keeps new dashboard widgets (including ones added by newly installed plugins) unchecked in Screen Options by default. Site Health, Welcome and Plugin Recommendations always stay visible.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-visibility',
	'default_enabled' => true,
	'boot'            => 'szm_as_declutter_boot',
	'settings'        => array(
		'always_show' => array(
			'szm_as_dashboard_welcome',
			'szm_as_dashboard_recommendations',
		),
	),
	'tab_title'       => __( 'Declutter', 'szm-admin-suite' ),
	'render'          => 'szm_as_declutter_render',
	'sanitize'        => 'szm_as_declutter_sanitize',
) );

/**
 * Widget ids that are never subject to hiding, regardless of settings.
 */
function szm_as_declutter_protected() {
	return array( 'dashboard_site_health' );
}

function szm_as_declutter_boot() {
	if ( ! is_admin() ) {
		return;
	}
	// Runs after wp_dashboard_setup() (hooked to load-index.php at the
	// default priority) has registered every widget, core and plugin.
	add_action( 'load-index.php', 'szm_as_declutter_sync', 20 );
}

/**
 * All currently registered dashboard widget ids, id => title.
 */
function szm_as_declutter_get_registered() {
	global $wp_meta_boxes;

	$widgets = array();
	if ( empty( $wp_meta_boxes['dashboard'] ) ) {
		return $widgets;
	}

	foreach ( $wp_meta_boxes['dashboard'] as $contexts ) {
		foreach ( $contexts as $boxes ) {
			foreach ( $boxes as $id => $box ) {
				if ( $id && ! empty( $box['title'] ) ) {
					$widgets[ $id ] = wp_strip_all_tags( $box['title'] );
				}
			}
		}
	}
	return $widgets;
}

/**
 * For the current user: any registered widget they haven't been shown
 * before gets added to their hidden list (unless allow-listed), then gets
 * marked seen so we never override their own choice about it again.
 *
 * Also updates a site-wide "known widgets" option so the settings screen
 * can offer them as always-show choices.
 */
function szm_as_declutter_sync() {
	$registered = szm_as_declutter_get_registered();
	if ( empty( $registered ) ) {
		return;
	}

	// Remember every widget id/title ever seen on this site, for the settings UI.
	$known = get_option( 'szm_as_declutter_known_widgets', array() );
	update_option( 'szm_as_declutter_known_widgets', array_merge( $known, $registered ), false );

	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return;
	}

	$settings    = SZM_Admin_Suite::instance()->get_settings();
	$always_show = isset( $settings['declutter']['always_show'] ) ? (array) $settings['declutter']['always_show'] : array();
	$exempt      = array_merge( $always_show, szm_as_declutter_protected() );

	$seen = (array) get_user_meta( $user_id, 'szm_as_declutter_seen', true );
	$new  = array_diff( array_keys( $registered ), $seen, $exempt );

	if ( $new ) {
		$hidden = get_user_option( 'metaboxhidden_dashboard' );
		$hidden = is_array( $hidden ) ? $hidden : array();
		update_user_option( $user_id, 'metaboxhidden_dashboard', array_values( array_unique( array_merge( $hidden, $new ) ) ) );
	}

	update_user_meta( $user_id, 'szm_as_declutter_seen', array_values( array_unique( array_merge( $seen, array_keys( $registered ) ) ) ) );
}

function szm_as_declutter_sanitize( $input, $current ) {
	$known = get_option( 'szm_as_declutter_known_widgets', array() );
	$valid = array_keys( $known );
	$show  = isset( $input['always_show'] ) && is_array( $input['always_show'] )
		? array_values( array_intersect( $valid, array_map( 'sanitize_key', $input['always_show'] ) ) )
		: array();
	return array( 'always_show' => $show );
}

function szm_as_declutter_render( $settings ) {
	$known = get_option( 'szm_as_declutter_known_widgets', array() );
	ksort( $known );
	$show = isset( $settings['always_show'] ) ? (array) $settings['always_show'] : array();
	?>
	<p><?php esc_html_e( 'Every dashboard widget not ticked here starts unchecked in each user\'s Screen Options — including new ones added later by a freshly installed plugin. Users can still tick any widget back on for themselves; we never override a choice they\'ve already made. Site Health always stays visible.', 'szm-admin-suite' ); ?></p>

	<?php if ( empty( $known ) ) : ?>
		<p class="description"><?php esc_html_e( 'No widgets seen yet — visit the Dashboard once to populate this list.', 'szm-admin-suite' ); ?></p>
	<?php else : ?>
		<fieldset>
			<?php foreach ( $known as $id => $label ) : ?>
				<?php if ( in_array( $id, szm_as_declutter_protected(), true ) ) continue; ?>
				<label style="display:block; margin-bottom:6px;">
					<input type="checkbox"
						name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[declutter][always_show][]"
						value="<?php echo esc_attr( $id ); ?>"
						<?php checked( in_array( $id, $show, true ) ); ?> />
					<?php echo esc_html( $label ); ?>
					<code style="color:#888;"><?php echo esc_html( $id ); ?></code>
				</label>
			<?php endforeach; ?>
		</fieldset>
	<?php endif; ?>
	<?php
}
