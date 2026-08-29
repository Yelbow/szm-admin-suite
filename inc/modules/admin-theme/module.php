<?php
/**
 * Module: Admin Theme.
 *
 * Drop-in admin themes. Each theme is a folder in inc/themes/<slug>/ with a
 * small theme.json descriptor and an admin.css. Drop a new folder and it
 * shows up in the picker automatically — no code changes needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'admin-theme',
	'name'            => __( 'Admin Theme', 'szm-admin-suite' ),
	'description'     => __( 'Visually restyle the wp-admin with a drop-in theme (colors, typography, layout). Themes are built as separate folders and switched from the picker.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-art',
	'default_enabled' => false,
	'boot'            => 'szm_as_admin_theme_boot',
	'settings'        => array( 'active_theme' => '' ),
	'tab_title'       => __( 'Admin Theme', 'szm-admin-suite' ),
	'render'          => 'szm_as_admin_theme_render',
	'sanitize'        => 'szm_as_admin_theme_sanitize',
) );

/**
 * List available themes by scanning inc/themes/ for theme.json descriptors.
 */
function szm_as_admin_theme_get_themes() {
	$themes = array();
	$dirs   = glob( SZM_AS_PATH . 'inc/themes/*/theme.json' );
	if ( ! is_array( $dirs ) ) {
		return $themes;
	}

	foreach ( $dirs as $file ) {
		$json = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $json ) ) {
			continue;
		}
		$slug = isset( $json['slug'] ) ? sanitize_key( $json['slug'] ) : basename( dirname( $file ) );
		$themes[ $slug ] = array(
			'slug'        => $slug,
			'name'        => isset( $json['name'] ) ? sanitize_text_field( $json['name'] ) : $slug,
			'description' => isset( $json['description'] ) ? sanitize_text_field( $json['description'] ) : '',
			'path'        => dirname( $file ),
			'css'         => dirname( $file ) . '/admin.css',
		);
	}

	ksort( $themes );
	return $themes;
}

function szm_as_admin_theme_boot( $settings ) {
	$active = isset( $settings['active_theme'] ) ? $settings['active_theme'] : '';
	if ( '' === $active ) {
		return;
	}

	$themes = szm_as_admin_theme_get_themes();
	if ( ! isset( $themes[ $active ] ) ) {
		return;
	}

	add_action( 'admin_enqueue_scripts', function () use ( $themes, $active ) {
		$css = $themes[ $active ]['css'];
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'szm-as-theme-' . $active,
				SZM_AS_URL . 'inc/themes/' . $active . '/admin.css',
				array(),
				(string) filemtime( $css )
			);
		}
	} );

	add_action( 'login_enqueue_scripts', function () use ( $themes, $active ) {
		$css = $themes[ $active ]['css'];
		if ( file_exists( $css ) ) {
			wp_enqueue_style(
				'szm-as-theme-' . $active,
				SZM_AS_URL . 'inc/themes/' . $active . '/admin.css',
				array(),
				(string) filemtime( $css )
			);
		}
	} );
}

function szm_as_admin_theme_sanitize( $input, $current ) {
	$active = isset( $input['active_theme'] ) ? sanitize_key( $input['active_theme'] ) : '';
	$themes = szm_as_admin_theme_get_themes();
	if ( '' !== $active && ! isset( $themes[ $active ] ) ) {
		$active = isset( $current['active_theme'] ) ? $current['active_theme'] : '';
	}
	return array( 'active_theme' => $active );
}

function szm_as_admin_theme_render( $settings ) {
	$themes   = szm_as_admin_theme_get_themes();
	$active   = isset( $settings['active_theme'] ) ? $settings['active_theme'] : '';
	$none_set = '' === $active;
	?>
	<p><?php esc_html_e( 'Choose which admin theme is active. Themes are self-contained folders in the plugin — build new ones with an AI or by hand and drop them in; they appear here automatically.', 'szm-admin-suite' ); ?></p>

	<fieldset>
		<label style="display:block; margin-bottom:10px;">
			<input type="radio" name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[admin-theme][active_theme]"
				value="" <?php checked( $none_set ); ?> />
			<strong><?php esc_html_e( 'No theme (default WordPress look)', 'szm-admin-suite' ); ?></strong>
		</label>

		<?php if ( empty( $themes ) ) : ?>
			<p class="description"><?php esc_html_e( 'No themes found yet. Drop a theme folder into inc/themes/ to get started.', 'szm-admin-suite' ); ?></p>
		<?php endif; ?>

		<?php foreach ( $themes as $slug => $theme ) : ?>
			<label style="display:block; margin-bottom:10px;">
				<input type="radio" name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[admin-theme][active_theme]"
					value="<?php echo esc_attr( $slug ); ?>" <?php checked( $active, $slug ); ?> />
				<strong><?php echo esc_html( $theme['name'] ); ?></strong>
				<?php if ( ! empty( $theme['description'] ) ) : ?>
					<span class="description"> — <?php echo esc_html( $theme['description'] ); ?></span>
				<?php endif; ?>
			</label>
		<?php endforeach; ?>
	</fieldset>
	<?php
}
