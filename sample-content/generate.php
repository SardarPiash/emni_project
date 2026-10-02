<?php
/**
 * Generate sample eBooks for local testing: one PDF + one 2:3 cover per book.
 *
 * Usage (from the project root):  php sample-content/generate.php
 * Output: sample-content/pdf/*.pdf and sample-content/covers/*.jpg
 *
 * Book data lives in books.json (shared with create-products.php).
 *
 * @package EbookStore
 */

$dir   = __DIR__;
$books = json_decode( file_get_contents( $dir . '/books.json' ), true );

@mkdir( $dir . '/pdf' );
@mkdir( $dir . '/covers' );

// Fonts: Windows system fonts if present, otherwise GD's built-in font.
$font_dirs  = array( 'C:/Windows/Fonts', '/usr/share/fonts/truetype/dejavu' );
$serif_bold = find_font( $font_dirs, array( 'georgiab.ttf', 'DejaVuSerif-Bold.ttf' ) );
$sans       = find_font( $font_dirs, array( 'segoeui.ttf', 'DejaVuSans.ttf' ) );
$sans_bold  = find_font( $font_dirs, array( 'segoeuib.ttf', 'DejaVuSans-Bold.ttf' ) );

foreach ( $books as $book ) {
	make_cover( $book, $dir . '/covers/' . $book['slug'] . '.jpg', $serif_bold, $sans, $sans_bold );
	make_pdf( $book, $dir . '/pdf/' . $book['slug'] . '.pdf' );
	echo 'Generated: ' . $book['slug'] . "\n";
}

/**
 * Return the first font file that exists.
 */
function find_font( array $dirs, array $names ) {
	foreach ( $dirs as $d ) {
		foreach ( $names as $n ) {
			if ( is_readable( "$d/$n" ) ) {
				return "$d/$n";
			}
		}
	}
	return null;
}

/**
 * Convert "#RRGGBB" to a GD color.
 */
function hex_color( $img, $hex ) {
	$hex = ltrim( $hex, '#' );
	return imagecolorallocate( $img, hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) );
}

/**
 * Draw centered, word-wrapped TTF text. Returns the y position below the text.
 */
function draw_centered( $img, $text, $font, $size, $color, $y, $max_width, $line_gap = 1.3 ) {
	$width = imagesx( $img );
	if ( ! $font ) {
		imagestring( $img, 5, (int) ( ( $width - strlen( $text ) * 9 ) / 2 ), $y, $text, $color );
		return $y + 30;
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
		$x   = (int) ( ( $width - ( $box[2] - $box[0] ) ) / 2 );
		$y  += (int) ( $size * $line_gap );
		imagettftext( $img, $size, 0, $x, $y, $color, $font, $l );
	}
	return $y;
}

/**
 * Create an 800x1200 (2:3) JPG cover.
 */
function make_cover( $book, $file, $serif_bold, $sans, $sans_bold ) {
	$w   = 800;
	$h   = 1200;
	$img = imagecreatetruecolor( $w, $h );

	$bg    = hex_color( $img, $book['color'] );
	$paper = hex_color( $img, '#FAF7F2' );
	$gold  = hex_color( $img, '#C9A227' );
	$white = hex_color( $img, '#FFFFFF' );

	imagefilledrectangle( $img, 0, 0, $w, $h, $bg );

	// Inner frame and gold rules.
	imagesetthickness( $img, 3 );
	imagerectangle( $img, 40, 40, $w - 41, $h - 41, $gold );
	imagefilledrectangle( $img, 120, 330, $w - 121, 335, $gold );
	imagefilledrectangle( $img, 120, 820, $w - 121, 825, $gold );

	draw_centered( $img, strtoupper( $book['category'] ), $sans_bold, 22, $gold, 200, 600 );
	draw_centered( $img, $book['title'], $serif_bold, 58, $white, 400, 620, 1.35 );
	draw_centered( $img, $book['author'], $sans, 28, $paper, 880, 600 );
	draw_centered( $img, 'SAMPLE EBOOK', $sans_bold, 18, $gold, 1080, 600 );

	imagejpeg( $img, $file, 85 );
	imagedestroy( $img );
}

/**
 * Escape text for a PDF string literal.
 */
function pdf_text( $s ) {
	return str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), $s );
}

/**
 * Create a small, valid multi-page PDF (Letter size, Helvetica).
 */
function make_pdf( $book, $file ) {
	$pages = array();

	// Title page.
	$pages[] = array(
		array( 'F2', 30, 72, 600, $book['title'] ),
		array( 'F1', 16, 72, 560, 'by ' . $book['author'] ),
		array( 'F1', 12, 72, 120, 'Sample eBook generated for testing the eBook Store. Not for sale.' ),
	);

	// Content pages.
	foreach ( $book['chapters'] as $i => $chapter ) {
		$lines   = array( array( 'F2', 20, 72, 700, 'Chapter ' . ( $i + 1 ) . ': ' . $chapter ) );
		$y       = 660;
		$wrapped = explode( "\n", wordwrap( $book['description'], 85 ) );
		foreach ( array_merge( $wrapped, array( '', 'This placeholder text stands in for the real chapter content.' ) ) as $text ) {
			$lines[] = array( 'F1', 12, 72, $y, $text );
			$y      -= 18;
		}
		$pages[] = $lines;
	}

	$objects   = array();
	$objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
	$objects[] = ''; // Pages — filled below.
	$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
	$objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

	$kids = array();
	foreach ( $pages as $lines ) {
		$stream = '';
		foreach ( $lines as $l ) {
			$stream .= sprintf( "BT /%s %d Tf %d %d Td (%s) Tj ET\n", $l[0], $l[1], $l[2], $l[3], pdf_text( $l[4] ) );
		}
		$objects[] = '<< /Length ' . strlen( $stream ) . " >>\nstream\n" . $stream . 'endstream';
		$content   = count( $objects );
		$objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents $content 0 R /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> >>";
		$kids[]    = count( $objects ) . ' 0 R';
	}
	$objects[1] = '<< /Type /Pages /Kids [' . implode( ' ', $kids ) . '] /Count ' . count( $kids ) . ' >>';

	$pdf     = "%PDF-1.4\n";
	$offsets = array();
	foreach ( $objects as $n => $obj ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $n + 1 ) . " 0 obj\n" . $obj . "\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $offsets as $o ) {
		$pdf .= sprintf( "%010d 00000 n \n", $o );
	}
	$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";

	file_put_contents( $file, $pdf );
}
