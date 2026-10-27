<?php
/**
 * Module: Editor.
 *
 * Block-editor defaults for chosen roles (e.g. a client's "redacteur"):
 *  - the settings sidebar opens by itself, with the taxonomy panels (the
 *    tick-boxes) unfolded — once per browser session, after that the user decides;
 *  - blocks can be hidden from the block inserter by name or wildcard
 *    (`themeisle-blocks/*`), with `!` exceptions (`!themeisle-blocks/form*`).
 *    Only `supports.inserter` is switched off, so blocks already in content
 *    keep working and stay editable;
 *  - the start-pattern popup ("Choose a pattern") always shows: its
 *    "always show start patterns" tick-box is hidden and forced on.
 *
 * Unlike the other modules this one is limited to the roles ticked in its own
 * tab (default: nobody), because "role and up" would also hit administrators.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'editor',
	'name'            => __( 'Editor', 'szm-admin-suite' ),
	'description'     => __( 'Block-editor defaults for chosen roles: sidebar and tick-box panels open, hide blocks from the inserter, start-pattern popup always on.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-edit-page',
	'default_enabled' => false,
	'boot'            => 'szm_as_editor_boot',
	'settings'        => array(
		'roles'              => array(),
		'sidebar_open'       => true,
		'hidden_blocks'      => '',
		'lock_pattern_popup' => true,
	),
	'tab_title'       => __( 'Editor', 'szm-admin-suite' ),
	'render'          => 'szm_as_editor_render',
	'sanitize'        => 'szm_as_editor_sanitize',
) );

function szm_as_editor_boot( $settings = array() ) {
	if ( ! is_admin() ) {
		return;
	}
	$roles = isset( $settings['roles'] ) ? (array) $settings['roles'] : array();
	$user  = wp_get_current_user();
	if ( ! $roles || ! array_intersect( $roles, (array) $user->roles ) ) {
		return;
	}
	add_action( 'enqueue_block_editor_assets', function () use ( $settings ) {
		$lock   = ! empty( $settings['lock_pattern_popup'] );
		$hidden = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) ( $settings['hidden_blocks'] ?? '' ) ) ) ) );
		wp_enqueue_script( 'szm-as-editor', SZM_AS_URL . 'inc/modules/editor/editor.js', array( 'wp-data', 'wp-dom-ready', 'wp-blocks', 'wp-preferences', 'wp-core-data' ), SZM_AS_VERSION, true );
		wp_add_inline_script( 'szm-as-editor', 'window.szmAsEditor=' . wp_json_encode( array(
			'sidebarOpen'      => ! empty( $settings['sidebar_open'] ),
			'hiddenBlocks'     => $hidden,
			'lockPatternPopup' => $lock,
		) ) . ';', 'before' );
		if ( $lock ) {
			// Emotion's own class wins from a plain selector, hence !important.
			wp_register_style( 'szm-as-editor', false, array(), SZM_AS_VERSION );
			wp_enqueue_style( 'szm-as-editor' );
			wp_add_inline_style( 'szm-as-editor', '.editor-start-page-options__modal__actions{display:none!important}' );
		}
	} );
}

function szm_as_editor_sanitize( $input, $current ) {
	$valid = array_keys( wp_roles()->roles );
	$roles = isset( $input['roles'] ) && is_array( $input['roles'] )
		? array_values( array_intersect( $valid, array_map( 'sanitize_key', $input['roles'] ) ) )
		: array();
	$lines = array_filter( array_map( function ( $l ) {
		return preg_replace( '/[^A-Za-z0-9_\-\/*!]/', '', trim( $l ) );
	}, preg_split( '/\R/', (string) ( $input['hidden_blocks'] ?? '' ) ) ) );
	return array(
		'roles'              => $roles,
		// Checkboxes are absent from $input when unchecked.
		'sidebar_open'       => ! empty( $input['sidebar_open'] ),
		'hidden_blocks'      => implode( "\n", $lines ),
		'lock_pattern_popup' => ! empty( $input['lock_pattern_popup'] ),
	);
}

function szm_as_editor_render( $settings ) {
	$roles = isset( $settings['roles'] ) ? (array) $settings['roles'] : array();
	$name  = esc_attr( SZM_AS_OPTION ) . '[editor]';
	?>
	<h3><?php esc_html_e( 'Applies to roles', 'szm-admin-suite' ); ?></h3>
	<fieldset>
		<?php foreach ( wp_roles()->roles as $slug => $role ) : ?>
			<label style="display:block; margin-bottom:4px;">
				<input type="checkbox" name="<?php echo $name; ?>[roles][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $roles, true ) ); ?> />
				<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<p class="description"><?php esc_html_e( 'Only these roles get the defaults below. Nobody ticked = nothing changes.', 'szm-admin-suite' ); ?></p>

	<h3><?php esc_html_e( 'Sidebar', 'szm-admin-suite' ); ?></h3>
	<label style="display:block; margin-bottom:12px;">
		<input type="checkbox" name="<?php echo $name; ?>[sidebar_open]" value="1" <?php checked( ! empty( $settings['sidebar_open'] ) ); ?> />
		<?php esc_html_e( 'Open the settings sidebar and unfold the taxonomy (tick-box) panels when the editor opens', 'szm-admin-suite' ); ?>
	</label>

	<h3><?php esc_html_e( 'Start-pattern popup', 'szm-admin-suite' ); ?></h3>
	<label style="display:block; margin-bottom:12px;">
		<input type="checkbox" name="<?php echo $name; ?>[lock_pattern_popup]" value="1" <?php checked( ! empty( $settings['lock_pattern_popup'] ) ); ?> />
		<?php esc_html_e( 'Always show the popup: hide its "always show start patterns" tick-box and keep that preference on', 'szm-admin-suite' ); ?>
	</label>

	<h3><?php esc_html_e( 'Hide blocks from the inserter', 'szm-admin-suite' ); ?></h3>
	<textarea name="<?php echo $name; ?>[hidden_blocks]" rows="5" class="large-text code" placeholder="themeisle-blocks/*&#10;!themeisle-blocks/form*"><?php echo esc_textarea( (string) ( $settings['hidden_blocks'] ?? '' ) ); ?></textarea>
	<p class="description"><?php esc_html_e( 'One per line: a block name or a wildcard (themeisle-blocks/*). A line starting with ! is an exception that stays visible. Blocks already in content keep working.', 'szm-admin-suite' ); ?></p>
	<?php
}
