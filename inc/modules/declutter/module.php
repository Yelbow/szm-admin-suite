<?php
/**
 * Module: Declutter.
 *
 * Turns off the "dumb" default dashboard widgets. Site Health is always kept
 * (per SPEC). On by default. Hidden widgets are configurable.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'declutter',
	'name'            => __( 'Declutter', 'szm-admin-suite' ),
	'description'     => __( 'Hides the default dashboard widgets (Welcome panel, At a Glance, Activity, Quick Draft, WordPress news). Site Health always stays.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-visibility',
	'default_enabled' => true,
	'boot'            => 'szm_as_declutter_boot',
	'settings'        => array( 'hidden_widgets' => array() ),
	'tab_title'       => __( 'Declutter', 'szm-admin-suite' ),
	'render'          => 'szm_as_declutter_render',
	'sanitize'        => 'szm_as_declutter_sanitize',
) );

/**
 * The default dashboard widgets that can be hidden. Empty hidden_widgets in
 * settings means "hide them all" (the sensible default).
 */
function szm_as_declutter_get_hideable() {
	return array(
		'dashboard_right_now'   => __( 'At a Glance', 'szm-admin-suite' ),
		'dashboard_activity'    => __( 'Activity', 'szm-admin-suite' ),
		'dashboard_quick_press' => __( 'Quick Draft', 'szm-admin-suite' ),
		'dashboard_primary'     => __( 'WordPress Events and News', 'szm-admin-suite' ),
		'dashboard_secondary'   => __( 'Secondary News', 'szm-admin-suite' ),
		'welcome_panel'         => __( 'Welcome panel', 'szm-admin-suite' ),
	);
}

function szm_as_declutter_boot() {
	if ( ! is_admin() ) {
		return;
	}
	add_action( 'wp_dashboard_setup', 'szm_as_declutter_setup', 20 );
}

function szm_as_declutter_setup() {
	$settings = SZM_Admin_Suite::instance()->get_settings();
	$hidden   = isset( $settings['declutter']['hidden_widgets'] ) ? (array) $settings['declutter']['hidden_widgets'] : array();
	$hideable = szm_as_declutter_get_hideable();

	// Empty = hide everything hideable (default).
	if ( empty( $hidden ) ) {
		$hidden = array_keys( $hideable );
	}

	foreach ( $hidden as $id ) {
		remove_meta_box( $id, 'dashboard', 'normal' );
		remove_meta_box( $id, 'dashboard', 'side' );
	}

	// Site Health is never touched.
}

function szm_as_declutter_sanitize( $input, $current ) {
	$hideable = array_keys( szm_as_declutter_get_hideable() );
	$hidden   = isset( $input['hidden_widgets'] ) && is_array( $input['hidden_widgets'] )
		? array_values( array_intersect( $hideable, array_map( 'sanitize_key', $input['hidden_widgets'] ) ) )
		: array();
	return array( 'hidden_widgets' => $hidden );
}

function szm_as_declutter_render( $settings ) {
	$hideable = szm_as_declutter_get_hideable();
	$hidden   = isset( $settings['hidden_widgets'] ) ? (array) $settings['hidden_widgets'] : array();
	?>
	<p><?php esc_html_e( 'Choose which default dashboard widgets to hide. Leave nothing selected to hide all of the defaults below. Site Health is always kept.', 'szm-admin-suite' ); ?></p>

	<label style="display:block; margin-bottom:10px;">
		<input type="checkbox" id="szm-as-declutter-defaults"
			onchange="document.querySelectorAll('#szm-as-declutter-widgets input[type=checkbox]').forEach(function(cb){cb.checked=this.checked}.bind(this));"
			<?php checked( empty( $hidden ) ); ?> />
		<strong><?php esc_html_e( 'Hide all defaults below', 'szm-admin-suite' ); ?></strong>
	</label>

	<fieldset id="szm-as-declutter-widgets" style="margin-left:20px;">
		<?php foreach ( $hideable as $id => $label ) : ?>
			<label style="display:block; margin-bottom:6px;">
				<input type="checkbox"
					name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[declutter][hidden_widgets][]"
					value="<?php echo esc_attr( $id ); ?>"
					<?php checked( empty( $hidden ) || in_array( $id, $hidden, true ) ); ?> />
				<?php echo esc_html( $label ); ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<?php
}
