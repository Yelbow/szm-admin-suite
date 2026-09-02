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
 *
 * Also (optionally, default on): strips non-essential admin notices —
 * mainly the promo/upsell banners plugins print on their own initiative
 * ("X Pro is here", cross-sell CTAs) — before they render. Real errors, WP
 * core's own update/security nags, and this plugin's own notices are always
 * kept; see szm_as_declutter_keep_notice().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

szm_as_register_module( array(
	'slug'            => 'declutter',
	'name'            => __( 'Declutter', 'szm-admin-suite' ),
	'description'     => __( 'Keeps new dashboard widgets (including ones added by newly installed plugins) unchecked in Screen Options by default, and strips non-essential plugin promo/upsell notices. Site Health, Welcome, Plugin Recommendations, real errors, and WP core\'s own update nags always stay visible.', 'szm-admin-suite' ),
	'icon'            => 'dashicons-visibility',
	'default_enabled' => true,
	'boot'            => 'szm_as_declutter_boot',
	'settings'        => array(
		'always_show'      => array(
			'szm_as_dashboard_welcome',
			'szm_as_dashboard_recommendations',
		),
		'suppress_notices' => true,
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

function szm_as_declutter_boot( $settings = array() ) {
	if ( ! is_admin() ) {
		return;
	}
	// wp-admin/index.php calls wp_dashboard_setup() directly — it is NOT
	// hooked onto 'load-index.php', so a callback there runs before any
	// widget exists. Widgets themselves (core and plugin, e.g. Yoast) are
	// added by hooking the 'wp_dashboard_setup' action fired from inside
	// that function. Hook the same action at the latest possible priority
	// so we run after every one of them, regardless of the priority they used.
	add_action( 'wp_dashboard_setup', 'szm_as_declutter_sync', PHP_INT_MAX );

	// Notice suppression: never during AJAX/REST (no notice markup renders
	// there anyway, and buffering an AJAX response is asking for trouble).
	if ( empty( $settings['suppress_notices'] ) || wp_doing_ajax() ) {
		return;
	}
	// admin_notices and all_admin_notices are two separate do_action() calls
	// in wp-admin/admin-header.php (core notices use the former, "every
	// admin screen" plugin notices often use the latter) — buffer both. On
	// each hook we add our own callback at the earliest possible priority
	// (to open the buffer before any other callback on that hook has
	// printed anything) and again at the latest possible priority (to close
	// it after every other callback has printed its notice), so everything
	// any other plugin prints on that hook lands inside the buffer we then
	// filter, regardless of what priority it used.
	foreach ( array( 'admin_notices', 'all_admin_notices' ) as $hook ) {
		add_action( $hook, 'szm_as_declutter_notice_buffer_start', -9999 );
		add_action( $hook, 'szm_as_declutter_notice_buffer_end', PHP_INT_MAX );
	}
}

function szm_as_declutter_notice_buffer_start() {
	ob_start();
}

function szm_as_declutter_notice_buffer_end() {
	$html = ob_get_clean();
	echo szm_as_declutter_filter_notices( $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- already-rendered admin HTML, filtered below, not user input.
}

/**
 * Strip non-essential notice <div>s out of a chunk of already-rendered
 * admin_notices/all_admin_notices HTML. Uses DOMDocument rather than a
 * hand-rolled regex — notice markup regularly contains its own nested
 * <div>s (buttons, dismiss icons), which a naive regex match would cut off
 * at the first "</div>" instead of the notice's real closing tag.
 */
function szm_as_declutter_filter_notices( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html || ! class_exists( 'DOMDocument' ) ) {
		return $html;
	}

	// DOMDocument needs a full document to parse a fragment reliably
	// (otherwise libxml's HTML5 recovery heuristics can reparent or drop
	// pieces of it); the explicit UTF-8 meta tag stops loadHTML() from
	// mangling multibyte text, since it otherwise assumes Latin-1.
	$wrapped = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>';

	libxml_use_internal_errors( true );
	$dom    = new DOMDocument();
	$loaded = $dom->loadHTML( $wrapped, LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();

	if ( ! $loaded ) {
		return $html; // Malformed markup: fail open rather than risk eating real content.
	}

	$xpath = new DOMXPath( $dom );
	$nodes = $xpath->query(
		"//div[contains(concat(' ', normalize-space(@class), ' '), ' notice ')" .
		" or contains(concat(' ', normalize-space(@class), ' '), ' updated ')" .
		" or contains(concat(' ', normalize-space(@class), ' '), ' error ')]"
	);

	foreach ( $nodes as $node ) {
		// Only ever act on a top-level notice div, never a div that merely
		// has one of these class names nested somewhere inside a notice
		// we're already keeping (or discarding).
		if ( ! $node->parentNode || 'body' !== $node->parentNode->nodeName ) {
			continue;
		}
		$classes = (string) $node->getAttribute( 'class' );
		if ( szm_as_declutter_keep_notice( $classes, $dom->saveHTML( $node ) ) ) {
			continue;
		}
		$node->parentNode->removeChild( $node );
	}

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	$out  = '';
	foreach ( $body->childNodes as $child ) {
		$out .= $dom->saveHTML( $child );
	}
	return $out;
}

/**
 * Safelist: real problems and WP's own essential nags always stay visible,
 * everything else (mainly plugin promo/upsell banners) gets stripped.
 */
function szm_as_declutter_keep_notice( $classes, $notice_html ) {
	// Never hide a real error.
	if ( preg_match( '/\b(error|notice-error)\b/', $classes ) ) {
		return true;
	}
	// WP core's own update/security/translation nags.
	if ( preg_match( '/\b(update-nag|update-message|translation-nag|plugin-update-tr)\b/', $classes ) ) {
		return true;
	}
	// This plugin's own notices, and anything from WordPress core update
	// checks specifically (identifiable by their own markup, not just class).
	if ( false !== strpos( $notice_html, 'szm-as-' ) || false !== strpos( $notice_html, 'szm_as_' ) ) {
		return true;
	}
	return false;
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
	return array(
		'always_show'      => $show,
		// Checkbox: absent from $input entirely when unchecked, so this
		// correctly turns it off rather than falling back to the default.
		'suppress_notices' => ! empty( $input['suppress_notices'] ),
	);
}

function szm_as_declutter_render( $settings ) {
	$known    = get_option( 'szm_as_declutter_known_widgets', array() );
	ksort( $known );
	$show     = isset( $settings['always_show'] ) ? (array) $settings['always_show'] : array();
	$suppress = ! isset( $settings['suppress_notices'] ) || ! empty( $settings['suppress_notices'] );
	?>
	<h3><?php esc_html_e( 'Plugin notices', 'szm-admin-suite' ); ?></h3>
	<label style="display:block; margin-bottom:12px;">
		<input type="checkbox"
			name="<?php echo esc_attr( SZM_AS_OPTION ); ?>[declutter][suppress_notices]"
			value="1"
			<?php checked( $suppress ); ?> />
		<?php esc_html_e( 'Hide non-essential plugin notices (promo/upsell banners) admin-wide', 'szm-admin-suite' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'Real errors and WordPress\'s own update/security notices always stay visible — this only strips banners plugins print on their own initiative (e.g. "X Pro is here").', 'szm-admin-suite' ); ?></p>

	<h3><?php esc_html_e( 'Dashboard widgets', 'szm-admin-suite' ); ?></h3>
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
