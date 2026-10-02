<?php
/**
 * Exchange rate for the secondary currency (e.g. 1 USD = X GBP).
 *
 * - Automatic: daily update from the European Central Bank (ECB) reference
 *   rates (official, free, no API key). Google has no public exchange-rate API.
 * - Manual: rate entered in WooCommerce → Exchange Rate.
 * - Fallback: if automatic updates fail for EBOOKSTORE_FX_MAX_AGE, the manual
 *   rate is used, then STORE_GBP_EXCHANGE_RATE from .env.
 *
 * CURCY (WooCommerce Multi Currency) is free-version only, which has no
 * automatic rate updates — hence this small module.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_FX_OPTION   = 'ebookstore_fx';
const EBOOKSTORE_FX_SOURCE   = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
const EBOOKSTORE_FX_MAX_AGE  = 4 * DAY_IN_SECONDS;  // ECB does not publish on weekends/holidays.
const EBOOKSTORE_FX_MAX_JUMP = 0.15;                // Reject a daily change above 15% (bad data).
const EBOOKSTORE_FX_CRON     = 'ebookstore_fx_daily_update';
const EBOOKSTORE_FX_PAGE     = 'ebookstore-exchange-rate';

/**
 * Base and secondary currency codes from .env.
 *
 * @return string[] array( base, secondary )
 */
function ebookstore_fx_currencies() {
	return array(
		strtoupper( (string) env( 'STORE_BASE_CURRENCY', 'USD' ) ),
		strtoupper( (string) env( 'STORE_SECONDARY_CURRENCY', 'GBP' ) ),
	);
}

/**
 * Stored settings and state.
 *
 * @return array
 */
function ebookstore_fx_settings() {
	return wp_parse_args(
		(array) get_option( EBOOKSTORE_FX_OPTION, array() ),
		array(
			'mode'         => 'auto',   // auto | manual
			'manual_rate'  => '',
			'auto_rate'    => '',
			'auto_date'    => '',       // ECB publication date (Y-m-d)
			'auto_updated' => 0,        // Last successful update (timestamp)
			'auto_checked' => 0,        // Last attempt (timestamp)
			'last_error'   => '',
		)
	);
}

/**
 * The rate in use right now and where it comes from.
 *
 * @return array{rate: float, source: string}
 */
function ebookstore_fx_current() {
	$s = ebookstore_fx_settings();

	$auto_ok = (float) $s['auto_rate'] > 0 && ( time() - (int) $s['auto_updated'] ) < EBOOKSTORE_FX_MAX_AGE;
	if ( 'auto' === $s['mode'] && $auto_ok ) {
		return array(
			'rate'   => (float) $s['auto_rate'],
			'source' => 'ecb',
		);
	}
	if ( (float) $s['manual_rate'] > 0 ) {
		return array(
			'rate'   => (float) $s['manual_rate'],
			'source' => 'manual',
		);
	}
	return array(
		'rate'   => max( 0.0, (float) env( 'STORE_GBP_EXCHANGE_RATE', 0 ) ),
		'source' => 'env',
	);
}

/**
 * Download today's ECB rates and compute 1 base = X secondary.
 *
 * @return array{rate: float, date: string}|WP_Error
 */
function ebookstore_fx_fetch_ecb() {
	list( $base, $second ) = ebookstore_fx_currencies();

	$response = wp_remote_get( EBOOKSTORE_FX_SOURCE, array( 'timeout' => 15 ) );
	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return new WP_Error( 'ebookstore_fx_http', sprintf( 'ECB returned HTTP %d.', $code ) );
	}

	$previous = libxml_use_internal_errors( true );
	$xml      = simplexml_load_string( wp_remote_retrieve_body( $response ), 'SimpleXMLElement', LIBXML_NONET );
	libxml_use_internal_errors( $previous );
	if ( false === $xml ) {
		return new WP_Error( 'ebookstore_fx_xml', 'ECB response is not valid XML.' );
	}

	$rates = array( 'EUR' => 1.0 );
	foreach ( (array) $xml->xpath( '//*[@currency]' ) as $cube ) {
		$rates[ (string) $cube['currency'] ] = (float) $cube['rate'];
	}
	$dates = $xml->xpath( '//*[@time]' );
	$date  = $dates ? (string) $dates[0]['time'] : '';

	if ( empty( $rates[ $base ] ) || empty( $rates[ $second ] ) ) {
		return new WP_Error( 'ebookstore_fx_missing', sprintf( 'ECB data has no %s or %s rate.', $base, $second ) );
	}

	return array(
		'rate' => round( $rates[ $second ] / $rates[ $base ], 6 ),
		'date' => $date,
	);
}

/**
 * Fetch and store the automatic rate (runs daily via WP-Cron and on demand).
 *
 * @return true|WP_Error
 */
function ebookstore_fx_update() {
	$s                 = ebookstore_fx_settings();
	$s['auto_checked'] = time();
	$result            = ebookstore_fx_fetch_ecb();

	if ( ! is_wp_error( $result ) ) {
		$previous = (float) $s['auto_rate'];
		if ( $previous > 0 && abs( $result['rate'] - $previous ) / $previous > EBOOKSTORE_FX_MAX_JUMP ) {
			$result = new WP_Error(
				'ebookstore_fx_jump',
				sprintf( 'Rejected new rate %s: more than %d%% away from %s.', $result['rate'], EBOOKSTORE_FX_MAX_JUMP * 100, $previous )
			);
		}
	}

	if ( is_wp_error( $result ) ) {
		$s['last_error'] = $result->get_error_message();
		update_option( EBOOKSTORE_FX_OPTION, $s, false );
		return $result;
	}

	$s['auto_rate']    = $result['rate'];
	$s['auto_date']    = $result['date'];
	$s['auto_updated'] = time();
	$s['last_error']   = '';
	update_option( EBOOKSTORE_FX_OPTION, $s, false );
	ebookstore_fx_sync_curcy();

	return true;
}
add_action( EBOOKSTORE_FX_CRON, 'ebookstore_fx_update' );

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( EBOOKSTORE_FX_CRON ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', EBOOKSTORE_FX_CRON );
		}
	}
);

/**
 * Keep CURCY's stored rate equal to the rate in use (so its own screens agree).
 */
function ebookstore_fx_sync_curcy() {
	$params = get_option( 'woo_multi_currency_params' );
	if ( ! is_array( $params ) || empty( $params['currency_rate'] ) ) {
		return;
	}
	$current = ebookstore_fx_current();
	if ( $current['rate'] > 0 && ( ! isset( $params['currency_rate'][1] ) || (float) $params['currency_rate'][1] !== $current['rate'] ) ) {
		$params['currency_rate'][1] = $current['rate'];
		update_option( 'woo_multi_currency_params', $params );
	}
}

/* -------------------------------------------------------------------------
 * Admin page: WooCommerce → Exchange Rate
 * ---------------------------------------------------------------------- */

add_action(
	'admin_menu',
	static function () {
		add_submenu_page(
			'woocommerce',
			__( 'Exchange Rate', 'ebook-store' ),
			__( 'Exchange Rate', 'ebook-store' ),
			'manage_woocommerce',
			EBOOKSTORE_FX_PAGE,
			'ebookstore_fx_render_page'
		);
	},
	60
);

/**
 * Render the settings page.
 */
function ebookstore_fx_render_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	list( $base, $second ) = ebookstore_fx_currencies();
	$s                     = ebookstore_fx_settings();
	$current               = ebookstore_fx_current();
	$sources               = array(
		'ecb'    => __( 'European Central Bank (automatic)', 'ebook-store' ),
		'manual' => __( 'Manual rate', 'ebook-store' ),
		'env'    => __( 'Default rate from the .env file', 'ebook-store' ),
	);
	$format                = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only status flag after redirect.
	$notice = isset( $_GET['fx'] ) ? sanitize_key( wp_unslash( $_GET['fx'] ) ) : '';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Exchange Rate', 'ebook-store' ); ?></h1>

		<?php if ( 'saved' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ebook-store' ); ?></p></div>
		<?php elseif ( 'updated' === $notice ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Rate updated from the European Central Bank.', 'ebook-store' ); ?></p></div>
		<?php elseif ( 'invalid' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'Please enter a valid manual rate (a number greater than 0).', 'ebook-store' ); ?></p></div>
		<?php elseif ( 'failed' === $notice ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( sprintf( /* translators: %s: error */ __( 'Update failed: %s', 'ebook-store' ), $s['last_error'] ) ); ?></p></div>
		<?php endif; ?>

		<table class="widefat striped" style="max-width:760px;margin:1em 0 2em">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rate in use now', 'ebook-store' ); ?></th>
					<td><strong><?php echo esc_html( sprintf( '1 %s = %s %s', $base, wc_format_decimal( $current['rate'], 6 ), $second ) ); ?></strong></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Source', 'ebook-store' ); ?></th>
					<td><?php echo esc_html( $sources[ $current['source'] ] ); ?></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Latest automatic rate', 'ebook-store' ); ?></th>
					<td>
						<?php
						if ( (float) $s['auto_rate'] > 0 ) {
							echo esc_html( sprintf( /* translators: 1: rate, 2: ECB date, 3: update time */ __( '%1$s (ECB rate of %2$s, fetched %3$s)', 'ebook-store' ), wc_format_decimal( $s['auto_rate'], 6 ), $s['auto_date'], wp_date( $format, (int) $s['auto_updated'] ) ) );
						} else {
							esc_html_e( 'Not fetched yet.', 'ebook-store' );
						}
						?>
					</td>
				</tr>
				<?php if ( $s['last_error'] ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last update problem', 'ebook-store' ); ?></th>
						<td><?php echo esc_html( $s['last_error'] . ' (' . wp_date( $format, (int) $s['auto_checked'] ) . ')' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ebookstore_fx_save">
			<?php wp_nonce_field( 'ebookstore_fx_save' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode', 'ebook-store' ); ?></th>
					<td>
						<fieldset>
							<label><input type="radio" name="mode" value="auto" <?php checked( 'auto', $s['mode'] ); ?>> <?php esc_html_e( 'Automatic — daily rate from the European Central Bank', 'ebook-store' ); ?></label><br>
							<label><input type="radio" name="mode" value="manual" <?php checked( 'manual', $s['mode'] ); ?>> <?php esc_html_e( 'Manual — always use the rate below', 'ebook-store' ); ?></label>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ebookstore-manual-rate"><?php echo esc_html( sprintf( /* translators: 1: base, 2: secondary */ __( 'Manual rate (1 %1$s = ? %2$s)', 'ebook-store' ), $base, $second ) ); ?></label></th>
					<td>
						<input type="number" id="ebookstore-manual-rate" name="manual_rate" value="<?php echo esc_attr( $s['manual_rate'] ); ?>" step="0.000001" min="0.000001" class="regular-text" placeholder="<?php echo esc_attr( (string) env( 'STORE_GBP_EXCHANGE_RATE', '' ) ); ?>">
						<p class="description"><?php esc_html_e( 'Used in Manual mode, and as a backup in Automatic mode if the bank rate cannot be fetched for 4 days.', 'ebook-store' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save changes', 'ebook-store' ), 'primary', 'save', false ); ?>
			<?php submit_button( __( 'Update from ECB now', 'ebook-store' ), 'secondary', 'update_now', false, array( 'style' => 'margin-left:8px' ) ); ?>
		</form>

		<p class="description" style="margin-top:2em;max-width:760px">
			<?php esc_html_e( 'Prices in the secondary currency are calculated with this rate. Orders keep the amount the customer actually paid. Exchange rates from the European Central Bank are reference rates published on working days around 16:00 CET.', 'ebook-store' ); ?>
		</p>
	</div>
	<?php
}

add_action(
	'admin_post_ebookstore_fx_save',
	static function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'ebook-store' ), 403 );
		}
		check_admin_referer( 'ebookstore_fx_save' );

		$s      = ebookstore_fx_settings();
		$status = 'saved';

		$mode      = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
		$s['mode'] = in_array( $mode, array( 'auto', 'manual' ), true ) ? $mode : 'auto';

		$raw = isset( $_POST['manual_rate'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['manual_rate'] ) ) ) : '';
		if ( '' === $raw ) {
			$s['manual_rate'] = '';
		} elseif ( is_numeric( $raw ) && (float) $raw > 0 && (float) $raw < 100000 ) {
			$s['manual_rate'] = (string) round( (float) $raw, 6 );
		} else {
			$status = 'invalid';
		}

		if ( 'manual' === $s['mode'] && '' === $s['manual_rate'] ) {
			$status = 'invalid'; // Manual mode needs a rate.
		}
		if ( 'invalid' !== $status ) {
			update_option( EBOOKSTORE_FX_OPTION, $s, false );
		}

		if ( isset( $_POST['update_now'] ) && 'invalid' !== $status ) {
			$status = is_wp_error( ebookstore_fx_update() ) ? 'failed' : 'updated';
		}

		ebookstore_fx_sync_curcy();
		wp_safe_redirect( add_query_arg( array( 'page' => EBOOKSTORE_FX_PAGE, 'fx' => $status ), admin_url( 'admin.php' ) ) );
		exit;
	}
);

/**
 * Point admins from CURCY's own rate field to this page.
 */
add_action(
	'admin_notices',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'woo-multi-currency' !== $page ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s <a href="%s">%s</a></p></div>',
			esc_html__( 'The exchange rate is managed on the Exchange Rate page (automatic bank rate or manual rate). The rate field on this page is ignored.', 'ebook-store' ),
			esc_url( admin_url( 'admin.php?page=' . EBOOKSTORE_FX_PAGE ) ),
			esc_html__( 'Open Exchange Rate settings', 'ebook-store' )
		);
	}
);
