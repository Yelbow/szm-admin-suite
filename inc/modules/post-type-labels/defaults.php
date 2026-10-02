<?php
/**
 * Default config DATA for the Post Type Labels module (not logic).
 *
 * - 'templates': label key => template. Placeholders: {singular}, {plural},
 *   {singular_lc}, {plural_lc} (lowercased). Editable on the module's tab;
 *   a site that saves its own templates replaces this whole list.
 * - 'types': post type => singular/plural. Seeded with the Verras je Lief
 *   renaming (Berichten -> Verhalen). The module itself is OFF by default, so
 *   this only takes effect on a site that switches the module on; a type with
 *   empty singular or plural is left untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'templates' => array(
		'name'               => '{plural}',
		'singular_name'      => '{singular}',
		'menu_name'          => '{plural}',
		'name_admin_bar'     => '{singular}',
		'all_items'          => 'Alle {plural_lc}',
		'add_new'            => 'Nieuw {singular_lc}',
		'add_new_item'       => 'Nieuw {singular_lc} toevoegen',
		'edit_item'          => '{singular} bewerken',
		'new_item'           => 'Nieuw {singular_lc}',
		'view_item'          => '{singular} bekijken',
		'view_items'         => '{plural} bekijken',
		'search_items'       => '{plural} zoeken',
		'not_found'          => 'Geen {plural_lc} gevonden',
		'not_found_in_trash' => 'Geen {plural_lc} in de prullenbak',
	),
	'types'     => array(
		'post' => array(
			'singular' => 'Verhaal',
			'plural'   => 'Verhalen',
		),
	),
);
