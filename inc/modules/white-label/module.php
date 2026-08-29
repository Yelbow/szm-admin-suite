<?php
/**
 * Module: White-label.
 *
 * The client's identity on the wp-admin: logo, footer text and login
 * background. (The Admin Theme module handles colors/typography/layout —
 * this module is strictly identity, per the SPEC theme/white-label split.)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'white-label',
	'name'            => __( 'White-label', 'szm-admin-suite' ),
	'description'     => __( 'Replace the WordPress branding with the client\'s own: logo, admin footer text and login background.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-id',
	'default_enabled' => false,
	'boot'            => 'szm_as_white_label_boot',
	'settings'        => array(
		'logo'        => '',
		'footer_text' => '',
		'login_bg'    => '',
	),
	'tab_title'       => __( 'White-label', 'szm-admin-suite' ),
	'render'          => 'szm_as_white_label_render',
	'sanitize'        => 'szm_as_white_label_sanitize',
) );

function szm_as_white_label_boot( $settings ) {
	$logo    = isset( $settings['logo'] ) ? esc_url_raw( $settings['logo'] ) : '';
	$bg      = isset( $settings['login_bg'] ) ? esc_url_raw( $settings['login_bg'] ) : '';
	$footer  = isset( $settings['footer_text'] ) ? trim( $settings['footer_text'] ) : '';

	if ( '' !== $footer ) {
		add_filter( 'admin_footer_text', function () use ( $footer ) {
			return $footer;
		} );
	}

	if ( '' !== $logo || '' !== $bg ) {
		add_action( 'login_enqueue_scripts', function () use ( $logo, $bg ) {
			$css = '';
			if ( '' !== $bg ) {
				$css .= 'body.login { background:url(' . $bg . ') no-repeat center center / cover; }';
				$css .= 'body.login #loginform, body.login #registerform, body.login #lostpasswordform { background:rgba(255,255,255,0.92); }';
			}
			if ( '' !== $logo ) {
				$css .= 'body.login #login h1 a { background-image:url(' . $logo . '); background-size:contain; background-position:center; width:100%; height:84px; }';
			}
			wp_add_inline_style( 'login', $css );
		} );

		add_filter( 'login_headerurl', function () {
			return home_url( '/' );
		} );
		add_filter( 'login_headertext', function () {
			return get_bloginfo( 'name' );
		} );
	}

	// Media picker on the Admin Suite settings page. Depends on 'media-editor'
	// so wp.media is fully loaded before the handler runs — an inline script
	// in the form body executes too early (the media scripts load in the
	// footer) and would find window.wp.media undefined.
	add_action( 'admin_enqueue_scripts', function ( $hook ) {
		if ( 'toplevel_page_szm-admin-suite' !== $hook ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script(
			'szm-as-white-label',
			SZM_AS_URL . 'inc/modules/white-label/picker.js',
			array( 'media-editor' ),
			SZM_AS_VERSION,
			true
		);
	} );
}

function szm_as_white_label_sanitize( $input, $current ) {
	return array(
		'logo'        => isset( $input['logo'] ) ? esc_url_raw( trim( $input['logo'] ) ) : '',
		'footer_text' => isset( $input['footer_text'] ) ? wp_kses_post( trim( $input['footer_text'] ) ) : '',
		'login_bg'    => isset( $input['login_bg'] ) ? esc_url_raw( trim( $input['login_bg'] ) ) : '',
	);
}

function szm_as_white_label_render( $settings ) {
	$logo   = isset( $settings['logo'] ) ? $settings['logo'] : '';
	$footer = isset( $settings['footer_text'] ) ? $settings['footer_text'] : '';
	$bg     = isset( $settings['login_bg'] ) ? $settings['login_bg'] : '';
	?>
	<p><?php esc_html_e( 'Set the client\'s branding. Leave a field empty to keep the default.', 'szm-admin-suite' ); ?></p>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row">
				<label for="szm-as-wl-logo"><?php esc_html_e( 'Logo', 'szm-admin-suite' ); ?></label>
			</th>
			<td>
				<input type="url" id="szm-as-wl-logo" class="regular-text szm-as-wl-media"
					name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[white-label][logo]"
					value="<?php echo esc_attr( $logo ); ?>" placeholder="https://…/logo.png" />
				<button type="button" class="button szm-as-wl-pick" data-target="szm-as-wl-logo"><?php esc_html_e( 'Choose', 'szm-admin-suite' ); ?></button>
				<p class="description"><?php esc_html_e( 'Shown on the login page.', 'szm-admin-suite' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="szm-as-wl-footer"><?php esc_html_e( 'Admin footer text', 'szm-admin-suite' ); ?></label>
			</th>
			<td>
				<textarea id="szm-as-wl-footer" class="large-text" rows="2"
					name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[white-label][footer_text]"><?php echo esc_textarea( $footer ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Replaces the "Thank you for creating with WordPress" text at the bottom of the admin.', 'szm-admin-suite' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row">
				<label for="szm-as-wl-bg"><?php esc_html_e( 'Login background', 'szm-admin-suite' ); ?></label>
			</th>
			<td>
				<input type="url" id="szm-as-wl-bg" class="regular-text szm-as-wl-media"
					name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[white-label][login_bg]"
					value="<?php echo esc_attr( $bg ); ?>" placeholder="https://…/background.jpg" />
				<button type="button" class="button szm-as-wl-pick" data-target="szm-as-wl-bg"><?php esc_html_e( 'Choose', 'szm-admin-suite' ); ?></button>
				<p class="description"><?php esc_html_e( 'Background image on the login page.', 'szm-admin-suite' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
