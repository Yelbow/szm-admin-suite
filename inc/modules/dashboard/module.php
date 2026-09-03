<?php
/**
 * Module: Custom Dashboard.
 *
 * Replaces the default dashboard with a Welcome card and a Plugin
 * Recommendations card. The default Site Health widget is the only core
 * widget that stays (per SPEC). Plugin install/activate happens through
 * admin-ajax with a nonce + manage_options gate.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'dashboard',
	'name'            => __( 'Custom Dashboard', 'szm-admin-suite' ),
	'description'     => __( 'Replaces the default dashboard with a Welcome card and a Plugin Recommendations card. The Site Health widget is kept.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-dashboard',
	'default_enabled' => true,
	'boot'            => 'szm_as_dashboard_boot',
	'settings'        => array(),
) );

/**
 * Curated list of recommended plugins. Keyed by wordpress.org slug.
 * "Yoast Duplicator" maps to the popular "Duplicator" backup plugin.
 * Each entry can carry an optional 'icon' (wordpress.org plugin icon) shown
 * as a duotone tile in the dashboard widget.
 */
function szm_as_dashboard_get_recommendations() {
	return array(
		'wordpress-seo' => array(
			'name'        => __( 'Yoast SEO', 'szm-admin-suite' ),
			'description' => __( 'Search engine optimization: titles, meta, sitemaps.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/wordpress-seo/assets/icon-128x128.gif',
		),
		'duplicator' => array(
			'name'        => __( 'Duplicator', 'szm-admin-suite' ),
			'description' => __( 'Backup and migration — copy or move the site safely.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/duplicator/assets/icon-128x128.png',
		),
		'sucuri-scanner' => array(
			'name'        => __( 'Sucuri Security', 'szm-admin-suite' ),
			'description' => __( 'Security auditing, malware scanning and hardening.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/sucuri-scanner/assets/icon-128x128.png',
		),
		'duplicate-post' => array(
			'name'        => __( 'Duplicate Post', 'szm-admin-suite' ),
			'description' => __( 'Clone posts, pages and custom post types in one click.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/duplicate-post/assets/icon-128x128.png',
		),
		'twentig' => array(
			'name'        => __( 'Twentig', 'szm-admin-suite' ),
			'description' => __( 'Extra options and blocks for the default Twenty themes: colors, spacing, patterns.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/twentig/assets/icon-128x128.png',
		),
		'otter-blocks' => array(
			'name'        => __( 'Otter Blocks', 'szm-admin-suite' ),
			'description' => __( 'Gutenberg page-building blocks: sections, animations, icons and more.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/otter-blocks/assets/icon-128x128.gif',
		),
		'litespeed-cache' => array(
			'name'        => __( 'LiteSpeed Cache', 'szm-admin-suite' ),
			'description' => __( 'All-in-one page cache, image optimization and site speed boost.', 'szm-admin-suite' ),
			'icon'        => 'https://ps.w.org/litespeed-cache/assets/icon-128x128.png',
		),
	);
}

function szm_as_dashboard_boot() {
	if ( ! is_admin() ) {
		return;
	}

	add_action( 'admin_enqueue_scripts', 'szm_as_dashboard_enqueue' );
	add_action( 'wp_dashboard_setup', 'szm_as_dashboard_setup' );
	add_action( 'wp_ajax_szm_as_plugin_action', 'szm_as_dashboard_ajax_plugin_action' );
}

/**
 * Dashboard widget styling (recommendation cards + duotone icon tiles),
 * only loaded on the dashboard screen.
 */
function szm_as_dashboard_enqueue() {
	if ( 'index.php' !== ( isset( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '' ) ) {
		return;
	}
	$css = SZM_AS_PATH . 'inc/modules/dashboard/dashboard.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style(
			'szm-as-dashboard',
			SZM_AS_URL . 'inc/modules/dashboard/dashboard.css',
			array(),
			(string) filemtime( $css )
		);
	}
}

/**
 * Remove default dashboard widgets except Site Health, then add the
 * Welcome + Plugin Recommendations widgets.
 *
 * Site Health is never touched: WP 7.1's add_meta_box() refuses to re-add a
 * core box that was removed ("If a core box was previously removed, don't
 * add"), so removing it and re-adding it would silently fail.
 */
function szm_as_dashboard_setup() {
	$to_remove = array(
		'dashboard_right_now',
		'dashboard_activity',
		'dashboard_quick_press',
		'dashboard_primary',
		'dashboard_secondary',
		'welcome_panel',
		'welcome', // "Welcome to WordPress" widget (older WP).
	);

	foreach ( $to_remove as $id ) {
		remove_meta_box( $id, 'dashboard', 'normal' );
		remove_meta_box( $id, 'dashboard', 'side' );
	}

	wp_add_dashboard_widget(
		'szm_as_dashboard_welcome',
		__( 'Welcome', 'szm-admin-suite' ),
		'szm_as_dashboard_render_welcome'
	);

	wp_add_dashboard_widget(
		'szm_as_dashboard_recommendations',
		__( 'Plugin Recommendations', 'szm-admin-suite' ),
		'szm_as_dashboard_render_recommendations'
	);
}

function szm_as_dashboard_render_welcome() {
	$user      = wp_get_current_user();
	$site_name = get_bloginfo( 'name' );
	$date      = wp_date( get_option( 'date_format' ) );
	?>
	<div class="szm-as-welcome">
		<h2 style="margin-top:0;">
			<?php
			printf(
				/* translators: %1$s: display name, %2$s: site name. */
				esc_html__( 'Welcome back, %1$s.', 'szm-admin-suite' ),
				'<strong>' . esc_html( $user->display_name ) . '</strong>'
			);
			?>
		</h2>
		<p><?php printf( esc_html__( 'You are managing %1$s. Today is %2$s.', 'szm-admin-suite' ), '<strong>' . esc_html( $site_name ) . '</strong>', esc_html( $date ) ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php' ) ); ?>"><?php esc_html_e( 'Write a new post', 'szm-admin-suite' ); ?></a></p>
	</div>
	<?php
}

function szm_as_dashboard_render_recommendations() {
	$recommendations = szm_as_dashboard_get_recommendations();
	if ( empty( $recommendations ) ) {
		echo '<p>' . esc_html__( 'No recommendations yet.', 'szm-admin-suite' ) . '</p>';
		return;
	}

	$nonce = wp_create_nonce( 'szm_as_plugin_action' );
	echo '<ul style="margin:0; list-style:none;">';
	foreach ( $recommendations as $slug => $rec ) {
		$state   = szm_as_dashboard_plugin_state( $slug );
		$initial = mb_strtoupper( mb_substr( (string) $rec['name'], 0, 1 ) );
		$icon    = isset( $rec['icon'] ) ? $rec['icon'] : '';

		echo '<li class="szm-as-reco">';
		echo '<span class="szm-as-reco-icon">';
		echo '<span class="szm-as-reco-initial">' . esc_html( $initial ) . '</span>';
		if ( $icon ) {
			echo '<img src="' . esc_url( $icon ) . '" alt="" loading="lazy" />';
		}
		echo '</span>';
		echo '<span class="szm-as-reco-body">';
		echo '<strong class="szm-as-reco-name">' . esc_html( $rec['name'] ) . '</strong>';
		echo '<span class="szm-as-reco-desc">' . esc_html( $rec['description'] ) . '</span>';
		echo '</span>';
		echo '<span class="szm-as-reco-action">';
		if ( 'active' === $state['status'] ) {
			echo '<span class="description">' . esc_html__( 'Active', 'szm-admin-suite' ) . '</span>';
		} else {
			$label = 'installed' === $state['status'] ? __( 'Activate', 'szm-admin-suite' ) : __( 'Install', 'szm-admin-suite' );
			echo '<button type="button" class="button szm-as-plugin-action" data-slug="' . esc_attr( $slug ) . '" data-action="' . esc_attr( $state['status'] ) . '">' . esc_html( $label ) . '</button>';
		}
		echo '</span></li>';
	}
	echo '</ul>';
	echo '<input type="hidden" id="szm-as-plugin-nonce" value="' . esc_attr( $nonce ) . '" />';
	echo '<p id="szm-as-plugin-status" style="margin-bottom:0; min-height:1.4em;" class="description"></p>';
	?>
	<script>
	(function () {
		var statusEl = document.getElementById( 'szm-as-plugin-status' );
		var nonce    = document.getElementById( 'szm-as-plugin-nonce' ).value;

		// Broken plugin icons fall back to the initial-letter tile.
		[].forEach.call( document.querySelectorAll( '.szm-as-reco-icon img' ), function ( img ) {
			img.addEventListener( 'error', function () { img.remove(); } );
		} );

		function onClick( e ) {
			var btn = e.target.closest( '.szm-as-plugin-action' );
			if ( ! btn ) return;
			btn.disabled = true;
			statusEl.textContent = 'Working…';

			var data = new URLSearchParams();
			data.set( 'action', 'szm_as_plugin_action' );
			data.set( 'nonce', nonce );
			data.set( 'slug', btn.getAttribute( 'data-slug' ) );
			data.set( 'todo', btn.getAttribute( 'data-action' ) );

			fetch( ajaxurl, { method: 'POST', credentials: 'same-origin', body: data } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( res ) {
					if ( res && res.success ) {
						statusEl.textContent = res.data.message || 'Done.';
						btn.outerHTML = '<span class="description">' + ( res.data.after || 'Active' ) + '</span>';
					} else {
						statusEl.textContent = ( res && res.data ) ? res.data : 'Something went wrong.';
						btn.disabled = false;
					}
				} )
				.catch( function () {
					statusEl.textContent = 'Request failed.';
					btn.disabled = false;
				} );
		}

		document.addEventListener( 'click', onClick );
	})();
	</script>
	<?php
}

/**
 * Determine a plugin's state on this site.
 *
 * @return array{status:string, file:string} status: active|installed|missing.
 */
function szm_as_dashboard_plugin_state( $slug ) {
	$file = szm_as_dashboard_plugin_file( $slug );

	if ( $file && is_plugin_active( $file ) ) {
		return array( 'status' => 'active', 'file' => $file );
	}
	if ( $file ) {
		return array( 'status' => 'installed', 'file' => $file );
	}
	return array( 'status' => 'missing', 'file' => '' );
}

/**
 * Find the main plugin file for a slug from the list of installed plugins.
 */
function szm_as_dashboard_plugin_file( $slug ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	foreach ( get_plugins() as $file => $data ) {
		if ( 0 === strpos( $file, $slug . '/' ) ) {
			return $file;
		}
	}
	return '';
}

function szm_as_dashboard_ajax_plugin_action() {
	check_ajax_referer( 'szm_as_plugin_action', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( __( 'You are not allowed to do this.', 'szm-admin-suite' ) );
	}

	$slug = isset( $_POST['slug'] ) ? sanitize_key( $_POST['slug'] ) : '';
	$todo = isset( $_POST['todo'] ) ? sanitize_key( $_POST['todo'] ) : '';
	$recs = szm_as_dashboard_get_recommendations();

	if ( '' === $slug || ! isset( $recs[ $slug ] ) ) {
		wp_send_json_error( __( 'Unknown plugin.', 'szm-admin-suite' ) );
	}

	if ( 'install' === $todo ) {
		szm_as_dashboard_install_plugin( $slug );
	} elseif ( 'activate' === $todo ) {
		szm_as_dashboard_activate_plugin( $slug );
	} else {
		wp_send_json_error( __( 'Invalid action.', 'szm-admin-suite' ) );
	}
}

function szm_as_dashboard_install_plugin( $slug ) {
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';

	$api = plugins_api( 'plugin_information', array(
		'slug'   => $slug,
		'fields' => array( 'sections' => false, 'short_description' => false, 'downloadlink' => true ),
	) );

	if ( is_wp_error( $api ) ) {
		wp_send_json_error( $api->get_error_message() );
	}

	$skin     = new Plugin_Installer_Skin( array( 'nonce' => 'install-plugin_' . $slug ) );
	$upgrader = new Plugin_Upgrader( $skin );
	$result   = $upgrader->install( $api->download_link );

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}
	if ( true !== $result ) {
		wp_send_json_error( __( 'Installation failed.', 'szm-admin-suite' ) );
	}

	$file = szm_as_dashboard_plugin_file( $slug );
	if ( $file && ! is_plugin_active( $file ) ) {
		$activated = activate_plugin( $file );
		if ( is_wp_error( $activated ) ) {
			wp_send_json_success( array(
				'message' => __( 'Installed, but activation failed:', 'szm-admin-suite' ) . ' ' . $activated->get_error_message(),
				'after'   => __( 'Installed', 'szm-admin-suite' ),
			) );
		}
	}
	wp_send_json_success( array(
		'message' => __( 'Installed and activated.', 'szm-admin-suite' ),
		'after'   => __( 'Active', 'szm-admin-suite' ),
	) );
}

function szm_as_dashboard_activate_plugin( $slug ) {
	$file = szm_as_dashboard_plugin_file( $slug );
	if ( ! $file ) {
		wp_send_json_error( __( 'Plugin not found.', 'szm-admin-suite' ) );
	}

	$result = activate_plugin( $file );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( $result->get_error_message() );
	}

	wp_send_json_success( array(
		'message' => __( 'Activated.', 'szm-admin-suite' ),
		'after'   => __( 'Active', 'szm-admin-suite' ),
	) );
}
