<?php
/**
 * Core SZM Admin Suite class: module registry, per-site options, boot and
 * the top-level settings page.
 *
 * The plugin file is intentionally a thin loader. Each module lives in
 * inc/modules/<slug>/ and self-registers through szm_as_register_module().
 * A module only runs when (a) it is enabled in settings and (b) it applies
 * to the current user's roles.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SZM_Admin_Suite {

	private static $instance = null;

	/** @var array Registered modules, keyed by slug. */
	private $modules = array();

	private $modules_loaded = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			// Load modules only after $instance is assigned, so module files
			// calling szm_as_register_module() (which calls instance()) land
			// on THIS instance instead of recursively creating another one.
			self::$instance->load_modules();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'boot' ), 5 );

		// Settings page + self-updater.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'init', array( $this, 'self_update' ), 20 );
	}

	/**
	 * Load every module's module.php. Each file is expected to call
	 * szm_as_register_module() to add itself to the registry.
	 */
	private function load_modules() {
		if ( $this->modules_loaded ) {
			return;
		}
		$this->modules_loaded = true;

		$dirs = glob( SZM_AS_PATH . 'inc/modules/*/module.php' );
		if ( ! is_array( $dirs ) ) {
			return;
		}
		foreach ( $dirs as $file ) {
			include_once $file;
		}
	}

	public function register_module( array $module ) {
		$slug = isset( $module['slug'] ) ? sanitize_key( $module['slug'] ) : '';
		if ( '' === $slug ) {
			return;
		}
		$this->modules[ $slug ] = wp_parse_args( $module, array(
			'name'            => $slug,
			'description'     => '',
			'icon'            => 'dashicons-admin-generic',
			'default_enabled' => false,
			'boot'            => null,
			'settings'        => array(),
			'tab_title'       => '',
			'render'          => null,
			'sanitize'        => null,
		) );
	}

	/**
	 * Load each enabled module's boot callback when it applies to the
	 * current user. Modules that affect the admin UI check is_admin()
	 * themselves inside their boot callback.
	 */
	public function boot() {
		$settings = $this->get_settings();

		foreach ( $this->modules as $slug => $module ) {
			if ( ! $this->module_applies_to_user( $slug, $settings ) ) {
				continue;
			}
			if ( ! empty( $module['boot'] ) && is_callable( $module['boot'] ) ) {
				call_user_func( $module['boot'], $settings[ $slug ] );
			}
		}
	}

	/**
	 * Plugin Update Checker: self-updates through the native Plugins screen
	 * from the public GitHub repo (main branch), same pattern as the
	 * existing SZM Admin Menu Manager plugin.
	 */
	public function self_update() {
		if ( ! file_exists( SZM_AS_PATH . 'inc/libs/plugin-update-checker/plugin-update-checker.php' ) ) {
			return;
		}
		require_once SZM_AS_PATH . 'inc/libs/plugin-update-checker/plugin-update-checker.php';
		$update_checker = \YahnisElsts\PluginUpdateChecker\v5p4\PucFactory::buildUpdateChecker(
			'https://github.com/Yelbow/szm-admin-suite',
			SZM_AS_PATH . 'szm-admin-suite.php',
			'szm-admin-suite'
		);
		$update_checker->setBranch( 'main' );
	}

	/* ---------------------------------------------------------------------
	 * Settings / options
	 * ---------------------------------------------------------------- */

	/**
	 * Default settings. 'modules' holds the master on/off + per-role gate for
	 * every module; each module's own section holds its module-specific
	 * options. Merged from defaults on every read, so settings are never
	 * overwritten by an update.
	 */
	public function default_settings() {
		$modules = array();
		$sections = array();

		foreach ( $this->modules as $slug => $module ) {
			$modules[ $slug ] = array(
				'enabled' => (bool) $module['default_enabled'],
				'roles'   => array(),
			);
			$sections[ $slug ] = $module['settings'];
		}

		return array_merge( array( 'modules' => $modules ), $sections );
	}

	public function get_settings() {
		$saved = get_option( SZM_AS_OPTION, array() );
		return $this->deep_merge( $this->default_settings(), (array) $saved );
	}

	private function deep_merge( array $defaults, array $overrides ) {
		foreach ( $overrides as $key => $value ) {
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) ) {
				$defaults[ $key ] = $this->deep_merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	/**
	 * Whether a module is enabled AND applies to the current user's roles.
	 * Empty roles = applies to everyone (including administrators).
	 */
	public function module_applies_to_user( $slug, $settings = null ) {
		$settings = $settings ? $settings : $this->get_settings();
		$mod      = isset( $settings['modules'][ $slug ] ) ? $settings['modules'][ $slug ] : array();

		if ( empty( $mod['enabled'] ) ) {
			return false;
		}

		$roles = isset( $mod['roles'] ) ? (array) $mod['roles'] : array();
		if ( empty( $roles ) ) {
			return true;
		}

		return (bool) array_intersect( $roles, (array) wp_get_current_user()->roles );
	}

	public function get_modules() {
		return $this->modules;
	}

	/* ---------------------------------------------------------------------
	 * Settings page
	 * ---------------------------------------------------------------- */

	public function register_admin_menu() {
		add_menu_page(
			__( 'Admin Suite', 'szm-admin-suite' ),
			__( 'Admin Suite', 'szm-admin-suite' ),
			'manage_options',
			'szm-admin-suite',
			array( $this, 'render_settings_page' ),
			'dashicons-admin-generic',
			3
		);
	}

	public function register_setting() {
		register_setting( 'szm_as_settings_group', SZM_AS_OPTION, array( $this, 'sanitize_settings' ) );
	}

	public function sanitize_settings( $input ) {
		$current = get_option( SZM_AS_OPTION, array() );
		$merged  = $this->deep_merge( $this->default_settings(), (array) $current );

		$input = (array) $input;

		// Master modules section.
		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			foreach ( $this->modules as $slug => $module ) {
				$row = isset( $input['modules'][ $slug ] ) ? $input['modules'][ $slug ] : array();
				$merged['modules'][ $slug ] = array(
					'enabled' => ! empty( $row['enabled'] ),
					'roles'   => isset( $row['roles'] ) && is_array( $row['roles'] )
						? array_values( array_intersect( array_keys( wp_roles()->roles ), $row['roles'] ) )
						: array(),
				);
			}
		}

		// Per-module sections. Only touch a section when it was actually
		// submitted (each module has its own tab), so saving one tab never
		// wipes another module's settings.
		foreach ( $this->modules as $slug => $module ) {
			if ( ! empty( $module['sanitize'] ) && is_callable( $module['sanitize'] )
				&& isset( $input[ $slug ] ) && is_array( $input[ $slug ] ) ) {
				$current_section = isset( $merged[ $slug ] ) ? $merged[ $slug ] : array();
				$merged[ $slug ] = call_user_func( $module['sanitize'], $input[ $slug ], $current_section );
			}
		}

		return $merged;
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		$current  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'modules';
		$tabs     = array( 'modules' => __( 'Modules', 'szm-admin-suite' ) );

		foreach ( $this->modules as $slug => $module ) {
			if ( ! empty( $module['tab_title'] ) ) {
				$tabs[ $slug ] = $module['tab_title'];
			}
		}

		if ( ! isset( $tabs[ $current ] ) ) {
			$current = 'modules';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Admin Suite', 'szm-admin-suite' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab => $label ) : ?>
					<a class="nav-tab <?php echo $current === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=szm-admin-suite&tab=' . $tab ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php settings_fields( 'szm_as_settings_group' ); ?>

				<?php if ( 'modules' === $current ) : ?>
					<?php $this->render_modules_tab( $settings ); ?>
				<?php elseif ( ! empty( $this->modules[ $current ]['render'] ) ) : ?>
					<?php call_user_func( $this->modules[ $current ]['render'], $settings[ $current ] ); ?>
				<?php endif; ?>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private function render_modules_tab( $settings ) {
		$roles = wp_roles()->roles;
		?>
		<p><?php esc_html_e( 'Turn modules on or off and choose which roles each module applies to. Leave a module\'s roles empty to apply it to everyone, including administrators.', 'szm-admin-suite' ); ?></p>

		<?php foreach ( $this->modules as $slug => $module ) :
			$row = isset( $settings['modules'][ $slug ] ) ? $settings['modules'][ $slug ] : array();
			?>
			<div class="card" style="max-width:none; margin-bottom:16px;">
				<div style="display:flex; align-items:flex-start; gap:12px; flex-wrap:wrap;">
					<span class="dashicons <?php echo esc_attr( $module['icon'] ); ?>" style="font-size:32px; width:32px; height:32px;"></span>
					<div style="flex:1; min-width:260px;">
						<h2 style="margin:0 0 4px;">
							<?php echo esc_html( $module['name'] ); ?>
							<label style="margin-left:12px; font-size:13px; font-weight:400;">
								<input type="checkbox"
									name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[modules][<?php echo esc_attr( $slug ); ?>][enabled]"
									value="1" <?php checked( ! empty( $row['enabled'] ) ); ?> />
								<?php esc_html_e( 'Enabled', 'szm-admin-suite' ); ?>
							</label>
						</h2>
						<p class="description" style="margin-top:4px;"><?php echo esc_html( $module['description'] ); ?></p>

						<div style="margin-top:10px;">
							<strong><?php esc_html_e( 'Apply to roles:', 'szm-admin-suite' ); ?></strong>
							<label style="display:inline-flex; align-items:center; margin-left:10px;">
								<input type="checkbox"
									name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[modules][<?php echo esc_attr( $slug ); ?>][roles][]"
									value="" <?php checked( empty( $row['roles'] ) ); ?> />
								<?php esc_html_e( 'All roles', 'szm-admin-suite' ); ?>
							</label>
							<?php foreach ( $roles as $role_slug => $role ) : ?>
								<label style="display:inline-flex; align-items:center; margin-left:10px;">
									<input type="checkbox"
										name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[modules][<?php echo esc_attr( $slug ); ?>][roles][]"
										value="<?php echo esc_attr( $role_slug ); ?>"
										<?php checked( in_array( $role_slug, (array) $row['roles'], true ) ); ?> />
									<?php echo esc_html( translate_user_role( $role['name'] ) ); ?>
								</label>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}
}
