<?php
/**
 * Side promo banners (WP admin → Home Page → Side Banners).
 * Each banner is one link: label, title, text, button-style text and, on the
 * right, either the admin's image or 2 book covers from the chosen list.
 *
 * @package EbookStoreChild
 *
 * @var array $args { banners: array[] }
 */

defined( 'ABSPATH' ) || exit;

$ebookstore_banners = isset( $args['banners'] ) ? (array) $args['banners'] : array();
if ( ! $ebookstore_banners ) {
	return;
}

/**
 * Two cover image IDs for a banner, avoiding covers already used.
 *
 * @param string $list  featured | new | bestsellers.
 * @param int[]  $used  Product IDs already shown (updated).
 * @return int[] Attachment IDs.
 */
$ebookstore_covers = static function ( $list, array &$used ) {
	$query = array(
		'status'  => 'publish',
		'limit'   => 2,
		'exclude' => $used,
		'return'  => 'ids',
	);
	if ( 'featured' === $list ) {
		$query['featured'] = true;
		$query['orderby']  = 'date';
		$query['order']    = 'DESC';
	} elseif ( 'bestsellers' === $list ) {
		$query['orderby']  = 'meta_value_num';
		$query['meta_key'] = 'total_sales'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- 2 items.
		$query['order']    = 'DESC';
	} elseif ( 'new' === $list ) {
		$query['orderby'] = 'date';
		$query['order']   = 'DESC';
	} else {
		return array();
	}
	$images = array();
	foreach ( wc_get_products( $query ) as $product_id ) {
		$image = (int) get_post_thumbnail_id( $product_id );
		if ( $image ) {
			$used[]   = (int) $product_id;
			$images[] = $image;
		}
	}
	return $images;
};

$ebookstore_used = array();
?>
<div class="ebook-banners">
	<?php foreach ( $ebookstore_banners as $ebookstore_banner ) : ?>
		<?php
		$ebookstore_tag    = $ebookstore_banner['url'] ? 'a' : 'div';
		$ebookstore_images = $ebookstore_banner['image'] ? array() : $ebookstore_covers( $ebookstore_banner['covers'], $ebookstore_used );
		$ebookstore_class  = 'ebook-banner ebook-banner--' . sanitize_html_class( $ebookstore_banner['style'] )
			. ( $ebookstore_banner['image'] ? ' has-image' : '' )
			. ( $ebookstore_images ? ' has-covers' : '' );
		?>
		<<?php echo esc_attr( $ebookstore_tag ); ?> class="<?php echo esc_attr( $ebookstore_class ); ?>"<?php echo $ebookstore_banner['url'] ? ' href="' . esc_url( $ebookstore_banner['url'] ) . '"' : ''; ?>>
			<?php
			if ( $ebookstore_banner['image'] ) {
				echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
					$ebookstore_banner['image'],
					'large',
					false,
					array(
						'class'    => 'ebook-banner__image',
						'alt'      => '',
						'sizes'    => '(min-width: 1024px) 400px, 100vw',
						'decoding' => 'async',
					)
				);
			}
			?>
			<span class="ebook-banner__body">
				<?php if ( $ebookstore_banner['label'] ) : ?>
					<span class="ebook-banner__label"><?php echo esc_html( $ebookstore_banner['label'] ); ?></span>
				<?php endif; ?>
				<span class="ebook-banner__title"><?php echo esc_html( $ebookstore_banner['title'] ); ?></span>
				<?php if ( $ebookstore_banner['text'] ) : ?>
					<span class="ebook-banner__text"><?php echo esc_html( $ebookstore_banner['text'] ); ?></span>
				<?php endif; ?>
				<?php if ( $ebookstore_banner['url'] && $ebookstore_banner['button'] ) : ?>
					<span class="ebook-banner__cta">
						<?php echo esc_html( $ebookstore_banner['button'] ); ?>
						<?php echo ebookstore_child_icon( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					</span>
				<?php endif; ?>
			</span>
			<?php if ( $ebookstore_images ) : ?>
				<span class="ebook-banner__covers" aria-hidden="true">
					<?php foreach ( $ebookstore_images as $ebookstore_image ) : ?>
						<?php
						echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped markup.
							$ebookstore_image,
							'woocommerce_thumbnail',
							false,
							array(
								'class'    => 'ebook-banner__cover',
								'alt'      => '',
								'sizes'    => '120px',
								'loading'  => 'eager',
								'decoding' => 'async',
							)
						);
						?>
					<?php endforeach; ?>
				</span>
			<?php endif; ?>
		</<?php echo esc_attr( $ebookstore_tag ); ?>>
	<?php endforeach; ?>
</div>
