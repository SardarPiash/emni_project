<?php
/**
 * Phase 9 assets: component styles and the small UI script.
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	static function () {
		wp_enqueue_style(
			'ebookstore-components',
			get_stylesheet_directory_uri() . '/assets/css/components.css',
			array( 'ebookstore-main' ),
			ebookstore_child_asset_version( 'assets/css/components.css' )
		);

		wp_enqueue_style(
			'ebookstore-header',
			get_stylesheet_directory_uri() . '/assets/css/header.css',
			array( 'ebookstore-components' ),
			ebookstore_child_asset_version( 'assets/css/header.css' )
		);

		wp_enqueue_script(
			'ebookstore-site',
			get_stylesheet_directory_uri() . '/assets/js/site.js',
			array(),
			ebookstore_child_asset_version( 'assets/js/site.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	},
	21
);

// Mark that JavaScript runs, so "reveal" start states never hide content without JS.
add_action(
	'wp_head',
	static function () {
		echo "<script>document.documentElement.classList.add('js');</script>\n";
	},
	1
);
