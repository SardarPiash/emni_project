<?php
/**
 * Demo catalogue for local testing and client previews (WP-CLI only).
 *
 *   wp ebookstore seed-demo                  Create ~50 demo eBooks (safe to run twice).
 *   wp ebookstore remove-demo [--yes]        Delete ONLY demo eBooks, their covers,
 *                     [--include-ordered]    PDFs and empty demo categories. Orders are never touched.
 *
 * Every demo item is tagged: products/attachments with post meta `_ebookstore_demo = 1`,
 * categories with term meta `_ebookstore_demo = 1`. Demo sales counts are fake — remove the
 * demo data (or get the client's approval) before going live.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

const EBOOKSTORE_DEMO_META = '_ebookstore_demo';

/**
 * Category => cover colours (background, accent). All backgrounds are dark enough for white text.
 *
 * @return array
 */
function ebookstore_demo_categories() {
	return array(
		'Business'     => array( '#1F3A5F', '#C9A227' ),
		'Productivity' => array( '#2F5D50', '#E0C36A' ),
		'Technology'   => array( '#152842', '#5FB3D9' ),
		'Fiction'      => array( '#4A3F6B', '#E8B4B8' ),
		'Wellness'     => array( '#2E6B5E', '#F2D5A0' ),
		'Finance'      => array( '#3F4A2A', '#D9C27A' ),
		'Cooking'      => array( '#7A3B2E', '#F4C27A' ),
		'Travel'       => array( '#1D5C7A', '#F2B880' ),
	);
}

/**
 * Generic chapter titles per category (used in the description and the demo PDF).
 *
 * @param string $category Category name.
 * @return string[]
 */
function ebookstore_demo_chapters( $category ) {
	$map = array(
		'Business'     => array( 'Getting Started', 'Know Your Customer', 'Making a Plan', 'Growing Step by Step' ),
		'Productivity' => array( 'Why It Matters', 'Small First Steps', 'Building the Routine', 'Staying on Track' ),
		'Technology'   => array( 'The Basics', 'Your First Project', 'Common Mistakes', 'Next Steps' ),
		'Fiction'      => array( 'Chapter One', 'Chapter Two', 'Chapter Three', 'Chapter Four' ),
		'Wellness'     => array( 'Understanding Your Body', 'Simple Daily Practice', 'Building Consistency', 'Long-Term Wellbeing' ),
		'Finance'      => array( 'Where Your Money Goes', 'Making a Plan', 'Avoiding Common Traps', 'Building for the Future' ),
		'Cooking'      => array( 'Kitchen Basics', 'Everyday Recipes', 'Cooking for Friends', 'Tips and Variations' ),
		'Travel'       => array( 'Planning Your Trip', 'Packing Smart', 'On the Road', 'Coming Home' ),
	);
	return $map[ $category ] ?? array( 'Introduction', 'Part One', 'Part Two', 'Summary' );
}

/* -------------------------------------------------------------------------
 * Generated assets (original covers + small demo PDFs)
 * ---------------------------------------------------------------------- */

/**
 * First readable font file from a list.
 *
 * @param string[] $names File names.
 * @return string|null
 */
function ebookstore_demo_font( array $names ) {
	foreach ( array( 'C:/Windows/Fonts', '/usr/share/fonts/truetype/dejavu', '/usr/share/fonts/dejavu' ) as $dir ) {
		foreach ( $names as $name ) {
			if ( is_readable( "$dir/$name" ) ) {
				return "$dir/$name";
			}
		}
	}
	return null;
}

/**
 * Allocate a colour from "#RRGGBB" (optional alpha 0–127).
 */
function ebookstore_demo_color( $img, $hex, $alpha = 0 ) {
	$hex = ltrim( $hex, '#' );
	return imagecolorallocatealpha( $img, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), $alpha );
}

/**
 * Draw word-wrapped TTF text; returns the y below the last line.
 */
function ebookstore_demo_text( $img, $text, $font, $size, $color, $x, $y, $max_width, $align = 'left', $line_gap = 1.28 ) {
	if ( ! $font ) {
		imagestring( $img, 5, $x, $y, $text, $color );
		return $y + 28;
	}
	$lines = array();
	$line  = '';
	foreach ( explode( ' ', $text ) as $word ) {
		$try = trim( "$line $word" );
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( $box[2] - $box[0] > $max_width && '' !== $line ) {
			$lines[] = $line;
			$line    = $word;
		} else {
			$line = $try;
		}
	}
	$lines[] = $line;
	foreach ( $lines as $l ) {
		$box = imagettfbbox( $size, 0, $font, $l );
		$w   = $box[2] - $box[0];
		$lx  = 'center' === $align ? (int) ( $x + ( $max_width - $w ) / 2 ) : $x;
		$y  += (int) ( $size * $line_gap );
		imagettftext( $img, $size, 0, $lx, $y, $color, $font, $l );
	}
	return $y;
}

/**
 * Create an 800×1200 (2:3) WebP cover in one of three layouts.
 *
 * @param array  $book   Book data.
 * @param int    $index  Index (chooses the layout).
 * @param string $file   Output path.
 */
function ebookstore_demo_make_cover( array $book, $index, $file ) {
	$cats            = ebookstore_demo_categories();
	list( $bg, $ac ) = $cats[ $book['category'] ] ?? array( '#1F3A5F', '#C9A227' );

	$serif = ebookstore_demo_font( array( 'georgiab.ttf', 'DejaVuSerif-Bold.ttf' ) );
	$sans  = ebookstore_demo_font( array( 'segoeui.ttf', 'DejaVuSans.ttf' ) );
	$bold  = ebookstore_demo_font( array( 'segoeuib.ttf', 'DejaVuSans-Bold.ttf' ) );

	$w   = 800;
	$h   = 1200;
	$img = imagecreatetruecolor( $w, $h );
	imagealphablending( $img, true );
	$c_bg     = ebookstore_demo_color( $img, $bg );
	$c_accent = ebookstore_demo_color( $img, $ac );
	$c_white  = ebookstore_demo_color( $img, '#FFFFFF' );
	$c_paper  = ebookstore_demo_color( $img, '#FAF7F2' );
	$c_soft   = ebookstore_demo_color( $img, '#FFFFFF', 112 );
	imagefilledrectangle( $img, 0, 0, $w, $h, $c_bg );

	$category = strtoupper( $book['category'] );
	switch ( $index % 3 ) {
		case 0: // Frame.
			imagesetthickness( $img, 3 );
			imagerectangle( $img, 40, 40, $w - 41, $h - 41, $c_accent );
			imagefilledrectangle( $img, 120, 330, $w - 121, 335, $c_accent );
			imagefilledrectangle( $img, 120, 820, $w - 121, 825, $c_accent );
			ebookstore_demo_text( $img, $category, $bold, 22, $c_accent, 100, 200, 600, 'center' );
			ebookstore_demo_text( $img, $book['title'], $serif, 56, $c_white, 100, 400, 600, 'center', 1.32 );
			ebookstore_demo_text( $img, $book['author'], $sans, 28, $c_paper, 100, 880, 600, 'center' );
			break;

		case 1: // Accent band + large left-aligned title.
			imagefilledrectangle( $img, 0, 0, $w, 150, $c_accent );
			ebookstore_demo_text( $img, $category, $bold, 24, ebookstore_demo_color( $img, $bg ), 70, 50, 660 );
			for ( $i = 0; $i < 6; $i++ ) {
				imagefilledrectangle( $img, 70 + $i * 34, 230, 86 + $i * 34, 246, $i % 2 ? $c_soft : $c_accent );
			}
			ebookstore_demo_text( $img, $book['title'], $serif, 64, $c_white, 70, 330, 660, 'left', 1.25 );
			imagefilledrectangle( $img, 70, 1010, 190, 1016, $c_accent );
			ebookstore_demo_text( $img, $book['author'], $sans, 30, $c_paper, 70, 1030, 660 );
			break;

		default: // Large soft circle + centred title.
			imagefilledellipse( $img, (int) ( $w * 0.72 ), (int) ( $h * 0.3 ), 640, 640, $c_soft );
			imagefilledellipse( $img, (int) ( $w * 0.72 ), (int) ( $h * 0.3 ), 200, 200, $c_accent );
			ebookstore_demo_text( $img, $book['title'], $serif, 58, $c_white, 90, 620, 620, 'center', 1.3 );
			imagefilledrectangle( $img, 360, 920, 440, 926, $c_accent );
			ebookstore_demo_text( $img, $book['author'], $sans, 28, $c_paper, 90, 950, 620, 'center' );
			ebookstore_demo_text( $img, $category, $bold, 20, $c_accent, 90, 1080, 620, 'center' );
			break;
	}

	imagewebp( $img, $file, 80 );
	imagedestroy( $img );
}

/**
 * Create a small multi-page demo PDF.
 *
 * @param array  $book Book data.
 * @param string $file Output path.
 */
function ebookstore_demo_make_pdf( array $book, $file ) {
	$esc   = static fn( $s ) => str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s );
	$pages = array(
		array(
			array( 'F2', 28, 72, 600, $book['title'] ),
			array( 'F1', 16, 72, 560, 'by ' . $book['author'] ),
			array( 'F1', 12, 72, 120, 'Demo eBook for testing the eBook Store. Not for sale.' ),
		),
	);
	foreach ( ebookstore_demo_chapters( $book['category'] ) as $i => $chapter ) {
		$lines = array( array( 'F2', 20, 72, 700, ( $i + 1 ) . '. ' . $chapter ) );
		$y     = 660;
		foreach ( array_merge( explode( "\n", wordwrap( $book['description'], 85 ) ), array( '', 'This placeholder text stands in for the real chapter content.' ) ) as $text ) {
			$lines[] = array( 'F1', 12, 72, $y, $text );
			$y      -= 18;
		}
		$pages[] = $lines;
	}
	$objects = array( '<< /Type /Catalog /Pages 2 0 R >>', '', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>', '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>' );
	$kids    = array();
	foreach ( $pages as $lines ) {
		$stream = '';
		foreach ( $lines as $l ) {
			$stream .= sprintf( "BT /%s %d Tf %d %d Td (%s) Tj ET\n", $l[0], $l[1], $l[2], $l[3], $esc( $l[4] ) );
		}
		$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream';
		$objects[] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents ' . count( $objects ) . ' 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>';
		$kids[]    = count( $objects ) . ' 0 R';
	}
	$objects[1] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';
	$pdf        = "%PDF-1.4\n";
	$offsets    = array();
	foreach ( $objects as $n => $obj ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $n + 1 ) . " 0 obj\n$obj\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $offsets as $o ) {
		$pdf .= sprintf( "%010d 00000 n \n", $o );
	}
	$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
	file_put_contents( $file, $pdf );
}

/* -------------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------------- */

/**
 * Get or create a demo category; tags it as demo.
 *
 * @param string $name Name.
 * @return int Term ID.
 */
function ebookstore_demo_category_id( $name ) {
	$term = term_exists( $name, 'product_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'product_cat' );
		if ( is_wp_error( $term ) ) {
			WP_CLI::error( $term->get_error_message() );
		}
	}
	$id = (int) $term['term_id'];
	update_term_meta( $id, EBOOKSTORE_DEMO_META, '1' );
	return $id;
}

/**
 * Absolute paths of a product's downloadable files inside the uploads folder.
 *
 * @param WC_Product $product Product.
 * @return string[]
 */
function ebookstore_demo_file_paths( $product ) {
	$uploads = wp_upload_dir();
	$paths   = array();
	foreach ( $product->get_downloads() as $download ) {
		$file = $download->get_file();
		if ( 0 === strpos( $file, $uploads['baseurl'] ) ) {
			$paths[] = $uploads['basedir'] . substr( $file, strlen( $uploads['baseurl'] ) );
		}
	}
	return $paths;
}

/**
 * Deterministic "random" number for repeatable demo data.
 */
function ebookstore_demo_num( $seed, $min, $max ) {
	return $min + ( abs( crc32( (string) $seed ) ) % ( $max - $min + 1 ) );
}

/* -------------------------------------------------------------------------
 * wp ebookstore seed-demo
 * ---------------------------------------------------------------------- */

/**
 * Create the demo catalogue.
 *
 * ## OPTIONS
 *
 * [--force]
 * : Allow running when APP_ENV=production (not recommended).
 *
 * @param array $args       Positional args.
 * @param array $assoc_args Flags.
 */
function ebookstore_cli_seed_demo( $args, $assoc_args ) {
	if ( 'production' === env( 'APP_ENV', 'local' ) && empty( $assoc_args['force'] ) ) {
		WP_CLI::error( 'APP_ENV is "production". Demo data (with fake sales counts) must not be added to a live store. Use --force only if you really mean it.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$src     = dirname( ABSPATH ) . '/sample-content';
	$books   = json_decode( (string) file_get_contents( "$src/demo-books.json" ), true );
	$uploads = wp_upload_dir();
	$pdf_dir = $uploads['basedir'] . '/woocommerce_uploads/demo';
	wp_mkdir_p( $pdf_dir );
	if ( ! is_array( $books ) ) {
		WP_CLI::error( 'sample-content/demo-books.json is missing or invalid.' );
	}

	$cat_ids = array();
	foreach ( array_keys( ebookstore_demo_categories() ) as $name ) {
		$cat_ids[ $name ] = ebookstore_demo_category_id( $name );
	}

	// 1. The 6 original sample eBooks become demo items too (approved in Phase 8).
	$originals = json_decode( (string) file_get_contents( "$src/books.json" ), true );
	$move      = array( 'Writing' => 'Fiction' );
	foreach ( (array) $originals as $book ) {
		$post = get_page_by_path( $book['slug'], OBJECT, 'product' );
		if ( ! $post ) {
			continue;
		}
		$product = wc_get_product( $post->ID );
		if ( isset( $move[ $book['category'] ] ) ) {
			$product->set_category_ids( array( $cat_ids[ $move[ $book['category'] ] ] ) );
		}
		$product->update_meta_data( EBOOKSTORE_DEMO_META, '1' );
		$product->update_meta_data( '_ebookstore_demo_files', ebookstore_demo_file_paths( $product ) );
		if ( $product->get_image_id() ) {
			update_post_meta( $product->get_image_id(), EBOOKSTORE_DEMO_META, '1' );
		}
		$product->save();
		WP_CLI::log( 'Tagged existing sample as demo: ' . $book['title'] );
	}
	// Old sample category that is now empty.
	$writing = get_term_by( 'name', 'Writing', 'product_cat' );
	if ( $writing ) {
		$count = (int) ( new WP_Query( array( 'post_type' => 'product', 'post_status' => 'any', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => $writing->term_id ) ), 'fields' => 'ids', 'posts_per_page' => 1 ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		if ( 0 === $count ) {
			wp_delete_term( $writing->term_id, 'product_cat' );
			WP_CLI::log( 'Removed empty sample category: Writing' );
		}
	}

	// 2. New demo eBooks.
	$created = 0;
	$tmp_dir = get_temp_dir();
	foreach ( $books as $i => $book ) {
		if ( get_page_by_path( $book['slug'], OBJECT, 'product' ) ) {
			WP_CLI::log( 'Skipped (exists): ' . $book['title'] );
			continue;
		}

		// Cover.
		$cover = trailingslashit( $tmp_dir ) . $book['slug'] . '-cover.webp';
		ebookstore_demo_make_cover( $book, $i, $cover );
		$image_id = media_handle_sideload(
			array(
				'name'     => $book['slug'] . '-cover.webp',
				'tmp_name' => $cover,
			),
			0,
			$book['title'] . ' cover'
		);
		if ( is_wp_error( $image_id ) ) {
			WP_CLI::warning( 'Cover failed for ' . $book['title'] . ': ' . $image_id->get_error_message() );
			$image_id = 0;
		} else {
			update_post_meta( $image_id, EBOOKSTORE_DEMO_META, '1' );
			update_post_meta( $image_id, '_wp_attachment_image_alt', $book['title'] . ' by ' . $book['author'] . ' - eBook cover' );
		}

		// PDF in the protected downloads folder, with an unguessable name.
		$pdf_name = $book['slug'] . '-' . wp_generate_password( 12, false ) . '.pdf';
		ebookstore_demo_make_pdf( $book, "$pdf_dir/$pdf_name" );
		$download = new WC_Product_Download();
		$download->set_id( wp_generate_uuid4() );
		$download->set_name( $book['title'] . ' (PDF)' );
		$download->set_file( $uploads['baseurl'] . '/woocommerce_uploads/demo/' . $pdf_name );

		$chapters = '';
		foreach ( ebookstore_demo_chapters( $book['category'] ) as $chapter ) {
			$chapters .= '<li>' . esc_html( $chapter ) . '</li>';
		}
		$heading = 'Fiction' === $book['category'] ? 'Inside this book' : 'What you will learn';

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
		$product->set_description(
			'<p>' . esc_html( $book['description'] ) . '</p>'
			. '<h3>' . esc_html( $heading ) . '</h3><ul>' . $chapters . '</ul>'
			. '<h3>Details</h3><ul><li><strong>Author:</strong> ' . esc_html( $book['author'] ) . '</li><li><strong>Format:</strong> PDF</li><li><strong>Delivery:</strong> Instant download after payment, plus a download link by email</li></ul>'
		);
		$product->set_category_ids( array( $cat_ids[ $book['category'] ] ?? ebookstore_demo_category_id( $book['category'] ) ) );
		$product->set_downloads( array( $download ) );
		if ( $image_id ) {
			$product->set_image_id( $image_id );
		}
		$product->set_featured( 0 === $i % 5 );
		$product->set_date_created( time() - ebookstore_demo_num( $book['slug'] . 'date', 1, 240 ) * DAY_IN_SECONDS );
		$product->set_total_sales( ebookstore_demo_num( $book['slug'] . 'sales', 3, 480 ) );
		$product->update_meta_data( '_ebook_author', $book['author'] );
		$product->update_meta_data( EBOOKSTORE_DEMO_META, '1' );
		$product->update_meta_data( '_ebookstore_demo_files', array( "$pdf_dir/$pdf_name" ) );
		$id = $product->save();

		++$created;
		WP_CLI::log( sprintf( 'Created #%d %s (%s, $%s%s)', $id, $book['title'], $book['category'], $book['price'], $product->is_featured() ? ', featured' : '' ) );
	}

	delete_transient( 'ebookstore_bestseller_ids' );
	wc_delete_product_transients();
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'product', true );
	}

	$total = count(
		get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => EBOOKSTORE_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		)
	);
	WP_CLI::success( "Created $created new demo eBooks. Demo eBooks in the store: $total." );
	WP_CLI::warning( 'Demo sales counts are fake. Remove demo data (wp ebookstore remove-demo) or get the client\'s approval before going live.' );
}

/* -------------------------------------------------------------------------
 * wp ebookstore remove-demo
 * ---------------------------------------------------------------------- */

/**
 * Delete only demo eBooks, their covers and PDF files, and empty demo categories.
 * Orders are never touched.
 *
 * ## OPTIONS
 *
 * [--include-ordered]
 * : Also delete demo eBooks that appear in orders (the orders keep their line items).
 *
 * [--yes]
 * : Do not ask for confirmation.
 *
 * @param array $args       Positional args.
 * @param array $assoc_args Flags.
 */
function ebookstore_cli_remove_demo( $args, $assoc_args ) {
	global $wpdb;

	$ids = get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => EBOOKSTORE_DEMO_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		)
	);
	if ( ! $ids ) {
		WP_CLI::success( 'No demo eBooks found.' );
		return;
	}
	WP_CLI::confirm( sprintf( 'Delete %d demo eBooks with their covers and PDF files? Real products and all orders are kept.', count( $ids ) ), $assoc_args );

	$include_ordered = ! empty( $assoc_args['include-ordered'] );
	$protected_dir   = realpath( wp_upload_dir()['basedir'] . '/woocommerce_uploads' );
	$deleted         = 0;
	$skipped         = array();

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || '1' !== (string) $product->get_meta( EBOOKSTORE_DEMO_META ) ) {
			continue; // Safety: only demo-tagged products.
		}
		// Order line items of ANY status (the analytics lookup table skips failed/pending orders).
		$in_orders = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT oi.order_id)
				FROM {$wpdb->prefix}woocommerce_order_items oi
				JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = oi.order_item_id
				WHERE oi.order_item_type = 'line_item' AND im.meta_key = '_product_id' AND im.meta_value = %d",
				$id
			)
		);
		if ( $in_orders && ! $include_ordered ) {
			$skipped[] = $product->get_name() . " ($in_orders orders)";
			continue;
		}

		// Cover and gallery images that are tagged as demo.
		foreach ( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) as $attachment_id ) {
			if ( '1' === (string) get_post_meta( $attachment_id, EBOOKSTORE_DEMO_META, true ) ) {
				wp_delete_attachment( $attachment_id, true );
			}
		}
		// PDF files — only inside the protected downloads folder.
		foreach ( (array) $product->get_meta( '_ebookstore_demo_files' ) as $file ) {
			$real = realpath( (string) $file );
			if ( $real && $protected_dir && 0 === strpos( $real, $protected_dir ) && is_file( $real ) ) {
				wp_delete_file( $real );
			}
		}
		$product->delete( true );
		++$deleted;
	}

	// Empty demo categories.
	$removed_terms = 0;
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'meta_key' => EBOOKSTORE_DEMO_META, 'meta_value' => '1' ) ) as $term ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$count = (int) ( new WP_Query( array( 'post_type' => 'product', 'post_status' => 'any', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => $term->term_id ) ), 'fields' => 'ids', 'posts_per_page' => 1 ) ) )->found_posts; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		if ( 0 === $count ) {
			wp_delete_term( $term->term_id, 'product_cat' );
			++$removed_terms;
		}
	}

	delete_transient( 'ebookstore_bestseller_ids' );
	wc_delete_product_transients();
	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'product', true );
	}

	WP_CLI::success( "Deleted $deleted demo eBooks and $removed_terms empty demo categories. Orders were not touched." );
	if ( $skipped ) {
		WP_CLI::warning( 'Kept ' . count( $skipped ) . ' demo eBooks that appear in orders (use --include-ordered to delete them too): ' . implode( '; ', $skipped ) );
	}
}

WP_CLI::add_command( 'ebookstore seed-demo', 'ebookstore_cli_seed_demo', array( 'shortdesc' => 'Create the demo catalogue (~50 eBooks). Safe to run twice. Local/preview use only.' ) );
WP_CLI::add_command( 'ebookstore remove-demo', 'ebookstore_cli_remove_demo', array( 'shortdesc' => 'Delete only demo eBooks, their covers/PDFs and empty demo categories. Never touches orders.' ) );
