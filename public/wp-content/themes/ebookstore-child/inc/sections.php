<?php
/**
 * Home page sections (settings from the mu-plugin: Home Page → Sections).
 *
 * @package EbookStoreChild
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the enabled home page sections in order. The first section gets the
 * id "ebooks" (target of the hero's "Browse eBooks" button).
 *
 * @return bool Whether any section was printed.
 */
function ebookstore_child_home_sections() {
	if ( ! function_exists( 'ebookstore_home_sections' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
		return false;
	}
	$shop    = wc_get_page_permalink( 'shop' );
	$printed = false;

	foreach ( ebookstore_home_sections() as $key => $section ) {
		if ( '1' !== (string) $section['enabled'] ) {
			continue;
		}
		$id = $printed ? 'home-' . $key : 'ebooks';

		ob_start();
		switch ( $key ) {
			case 'bestsellers':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'orderby', 'popularity', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" best_selling="true"]', (int) $section['count'] ),
					)
				);
				break;
			case 'featured':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'ebook_list', 'featured', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" visibility="featured" orderby="date" order="DESC"]', (int) $section['count'] ),
					)
				);
				break;
			case 'new':
				get_template_part(
					'template-parts/sections/products',
					null,
					array(
						'id'        => $id,
						'title'     => $section['title'],
						'link'      => add_query_arg( 'orderby', 'date', $shop ),
						'link_text' => __( 'View all', 'ebookstore-child' ),
						'shortcode' => sprintf( '[products limit="%d" columns="4" orderby="date" order="DESC"]', (int) $section['count'] ),
					)
				);
				break;
			case 'categories':
				get_template_part(
					'template-parts/sections/categories',
					null,
					array(
						'id'    => $id,
						'title' => $section['title'],
						'count' => (int) $section['count'],
						'terms' => isset( $section['terms'] ) ? (array) $section['terms'] : array(),
					)
				);
				break;
			case 'promo':
				get_template_part(
					'template-parts/sections/promo',
					null,
					array(
						'id'     => $id,
						'title'  => $section['title'],
						'text'   => $section['text'],
						'button' => $section['button'],
					)
				);
				break;
		}
		$html = trim( (string) ob_get_clean() );
		if ( '' !== $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the templates.
			$printed = true;
		}
	}
	return $printed;
}
