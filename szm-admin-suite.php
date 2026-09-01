<?php
/**
 * Plugin Name:       SZM Admin Suite
 * Description:       One plugin, many modules — admin theme, custom dashboard, declutter and white-labeling — each toggleable on/off and configurable per role. Managed under the top-level "Admin Suite" menu.
 * Version:           1.0.5
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Studio Zonder Meer
 * License:           GPL-2.0-or-later
 * Text Domain:       szm-admin-suite
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Guard against WordPress ever including this main file twice in one request
// (which would redeclare the function below and double-load every module).
if ( defined( 'SZM_AS_LOADED' ) ) {
	return;
}
define( 'SZM_AS_LOADED', true );

define( 'SZM_AS_VERSION', '1.0.5' );
define( 'SZM_AS_OPTION', 'szm_as_settings' );
define( 'SZM_AS_PATH', plugin_dir_path( __FILE__ ) );
define( 'SZM_AS_URL', plugin_dir_url( __FILE__ ) );

require_once SZM_AS_PATH . 'inc/class-szm-admin-suite.php';
SZM_Admin_Suite::instance();

/**
 * Register a module. Called by each module's own file at include time.
 *
 * @param array $module {
 *     Module descriptor.
 *     @type string   $slug           Unique slug (folder name).
 *     @type string   $name           Display name.
 *     @type string   $description    Short description shown in the UI.
 *     @type string   $icon           Dashicons class, e.g. 'dashicons-art'.
 *     @type bool     $default_enabled Whether the module is on by default.
 *     @type callable $boot           Called on 'init' when enabled and applying to the user.
 *     @type array    $settings       Defaults for this module's own settings section.
 *     @type string   $tab_title      If set, this module gets its own settings tab.
 *     @type callable $render         Renders the module's settings tab (admin-side).
 *     @type callable $sanitize       Sanitizes the module's settings section input.
 * }
 */
function szm_as_register_module( array $module ) {
	SZM_Admin_Suite::instance()->register_module( $module );
}
