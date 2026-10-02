<?php
/**
 * Create the sample eBook products in WooCommerce.
 *
 * Usage (from the project root, after generate.php):
 *   wp eval-file sample-content/create-products.php
 *
 * Safe to re-run: products whose slug already exists are skipped.
 * Each product is Virtual + Downloadable + Sold individually, with a cover
 * image and its PDF stored in the protected woocommerce_uploads folder.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$src   = dirname( ABSPATH ) . '/sample-content';
$books = json_decode( file_get_contents( $src . '/books.json' ), true );

$uploads = wp_upload_dir();
$subdir  = '/woocommerce_uploads' . $uploads['subdir'];
wp_mkdir_p( $uploads['basedir'] . $subdir );

foreach ( $books as $book ) {
	if ( get_page_by_path( $book['slug'], OBJECT, 'product' ) ) {
		WP_CLI::log( 'Skipped (exists): ' . $book['title'] );
		continue;
	}

	// Category.
	$term = term_exists( $book['category'], 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $book['category'], 'product_cat' );
	}
	$cat_id = (int) $term['term_id'];

	// Cover image -> media library.
	$tmp = wp_tempnam( $book['slug'] . '.jpg' );
	copy( $src . '/covers/' . $book['slug'] . '.jpg', $tmp );
	$image_id = media_handle_sideload(
		array(
			'name'     => $book['slug'] . '-cover.jpg',
			'tmp_name' => $tmp,
		),
		0,
		$book['title'] . ' cover'
	);
	if ( is_wp_error( $image_id ) ) {
		WP_CLI::warning( 'Cover failed for ' . $book['title'] . ': ' . $image_id->get_error_message() );
		$image_id = 0;
	} else {
		update_post_meta( $image_id, '_wp_attachment_image_alt', $book['title'] . ' by ' . $book['author'] . ' - eBook cover' );
	}

	// PDF -> protected woocommerce_uploads folder, with an unguessable file name.
	$pdf_name = $book['slug'] . '-' . wp_generate_password( 12, false ) . '.pdf';
	copy( $src . '/pdf/' . $book['slug'] . '.pdf', $uploads['basedir'] . $subdir . '/' . $pdf_name );
	$pdf_url = $uploads['baseurl'] . $subdir . '/' . $pdf_name;

	$download = new WC_Product_Download();
	$download->set_id( wp_generate_uuid4() );
	$download->set_name( $book['title'] . ' (PDF)' );
	$download->set_file( $pdf_url );

	$chapters = '';
	foreach ( $book['chapters'] as $chapter ) {
		$chapters .= '<li>' . esc_html( $chapter ) . '</li>';
	}

	$description  = '<p>' . esc_html( $book['description'] ) . '</p>';
	$description .= '<h3>What you will learn</h3><ul>' . $chapters . '</ul>';
	$description .= '<h3>Details</h3><ul>'
		. '<li><strong>Author:</strong> ' . esc_html( $book['author'] ) . '</li>'
		. '<li><strong>Format:</strong> PDF</li>'
		. '<li><strong>Delivery:</strong> Instant download after payment, plus a download link by email</li>'
		. '</ul>';

	$product = new WC_Product_Simple();
	$product->set_name( $book['title'] );
	$product->set_slug( $book['slug'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( $book['price'] );
	$product->set_virtual( true );
	$product->set_downloadable( true );
	$product->set_sold_individually( true );
	$product->set_short_description( '<p>' . esc_html( $book['short'] ) . '</p>' );
	$product->set_description( $description );
	$product->set_category_ids( array( $cat_id ) );
	$product->set_downloads( array( $download ) );
	if ( $image_id ) {
		$product->set_image_id( $image_id );
	}
	$product->update_meta_data( '_ebook_author', $book['author'] );
	$id = $product->save();

	WP_CLI::success( sprintf( 'Created #%d: %s ($%s)', $id, $book['title'], $book['price'] ) );
}
