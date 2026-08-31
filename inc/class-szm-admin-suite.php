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
	 * Default settings. 'modules' holds the master on/off + the minimum role
	 * (role and up) for every module; each module's own section holds its
	 * module-specific options. Merged from defaults on every read, so settings
	 * are never overwritten by an update.
	 */
	public function default_settings() {
		$modules = array();
		$sections = array();

		foreach ( $this->modules as $slug => $module ) {
			$modules[ $slug ] = array(
				'enabled'  => (bool) $module['default_enabled'],
				'min_role' => '',
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
			// Only recurse into a non-empty array override. An empty array is
			// a real value (e.g. declutter's always_show = "show nothing extra")
			// and must replace the default instead of silently keeping it.
			if ( isset( $defaults[ $key ] ) && is_array( $defaults[ $key ] ) && is_array( $value ) && ! empty( $value ) ) {
				$defaults[ $key ] = $this->deep_merge( $defaults[ $key ], $value );
			} else {
				$defaults[ $key ] = $value;
			}
		}
		return $defaults;
	}

	/**
	 * Whether a module is enabled AND applies to the current user's role.
	 * min_role '' = applies to everyone (including administrators); otherwise
	 * the module applies to the selected role and every role above it.
	 */
	public function module_applies_to_user( $slug, $settings = null ) {
		$settings = $settings ? $settings : $this->get_settings();
		$mod      = isset( $settings['modules'][ $slug ] ) ? $settings['modules'][ $slug ] : array();

		if ( empty( $mod['enabled'] ) ) {
			return false;
		}

		$min_role = isset( $mod['min_role'] ) ? (string) $mod['min_role'] : '';
		if ( '' === $min_role ) {
			return true;
		}

		$min_level = $this->role_level( $min_role );
		if ( $min_level < 0 ) {
			return true; // Unknown role in stored settings: fall back to applying.
		}

		$user = wp_get_current_user();
		// Not logged in (e.g. the login page): no role gate is possible, so
		// appearance modules (theme, white-label) still apply — the login
		// page should match the admin look.
		if ( empty( $user->roles ) ) {
			return true;
		}

		foreach ( (array) $user->roles as $user_role ) {
			if ( $this->role_level( $user_role ) >= $min_level ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Capability level of a role (highest level_N capability), used to order
	 * roles for the "role and up" selector. Returns -1 for unknown roles.
	 */
	private function role_level( $role_slug ) {
		$roles = wp_roles()->roles;
		if ( ! isset( $roles[ $role_slug ] ) ) {
			return -1;
		}
		$level = 0;
		$caps  = isset( $roles[ $role_slug ]['capabilities'] ) ? (array) $roles[ $role_slug ]['capabilities'] : array();
		foreach ( $caps as $cap => $allowed ) {
			if ( $allowed && 0 === strpos( $cap, 'level_' ) ) {
				$level = max( $level, (int) substr( $cap, 6 ) );
			}
		}
		return $level;
	}

	/**
	 * All roles ordered lowest → highest capability level.
	 *
	 * @return array[] List of ['slug' => string, 'name' => string, 'level' => int].
	 */
	private function role_level_ordered() {
		$list = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$list[] = array(
				'slug'  => $slug,
				'name'  => translate_user_role( $role['name'] ),
				'level' => $this->role_level( $slug ),
			);
		}
		usort( $list, function ( $a, $b ) {
			if ( $a['level'] === $b['level'] ) {
				return strcmp( $a['slug'], $b['slug'] );
			}
			return $a['level'] - $b['level'];
		} );
		return $list;
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
				$min_role = isset( $row['min_role'] ) ? sanitize_key( $row['min_role'] ) : '';
				if ( '' !== $min_role && ! isset( wp_roles()->roles[ $min_role ] ) ) {
					$min_role = '';
				}
				$merged['modules'][ $slug ] = array(
					'enabled'  => ! empty( $row['enabled'] ),
					'min_role' => $min_role,
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
		$roles_ordered = $this->role_level_ordered();
		?>
		<p><?php esc_html_e( 'Turn modules on or off and pick a minimum role per module. A module applies to the selected role and every role above it — "All roles" applies it to everyone, including administrators.', 'szm-admin-suite' ); ?></p>

		<?php foreach ( $this->modules as $slug => $module ) :
			$row = isset( $settings['modules'][ $slug ] ) ? $settings['modules'][ $slug ] : array();
			$min_role = isset( $row['min_role'] ) ? $row['min_role'] : '';
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
							<label style="display:inline-flex; align-items:center; gap:8px;">
								<strong><?php esc_html_e( 'Minimum role:', 'szm-admin-suite' ); ?></strong>
								<select name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[modules][<?php echo esc_attr( $slug ); ?>][min_role]">
									<option value="" <?php selected( $min_role, '' ); ?>>
										<?php esc_html_e( 'All roles', 'szm-admin-suite' ); ?>
									</option>
									<?php foreach ( $roles_ordered as $role ) : ?>
										<option value="<?php echo esc_attr( $role['slug'] ); ?>" <?php selected( $min_role, $role['slug'] ); ?>>
											<?php echo esc_html( $role['name'] ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</label>
							<p class="description" style="margin:4px 0 0;">
								<?php esc_html_e( 'Applies to the selected role and every role above it.', 'szm-admin-suite' ); ?>
							</p>
						</div>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
		<?php
	}
}
