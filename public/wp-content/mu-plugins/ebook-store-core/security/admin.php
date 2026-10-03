<?php
/**
 * Security: admin screen (WP admin → Security), dashboard widget and the
 * IP-detection warning. Administrators only (manage_options).
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_SEC_PAGE = 'ebookstore-security';
const EBOOKSTORE_SEC_CAP  = 'manage_options';

/**
 * URL of the Security screen.
 *
 * @param array $args Query args.
 * @return string
 */
function ebookstore_sec_admin_url( $args = array() ) {
	return add_query_arg( array_merge( array( 'page' => EBOOKSTORE_SEC_PAGE ), $args ), admin_url( 'admin.php' ) );
}

add_action(
	'admin_menu',
	static function () {
		add_menu_page(
			__( 'Security', 'ebook-store' ),
			__( 'Security', 'ebook-store' ),
			EBOOKSTORE_SEC_CAP,
			EBOOKSTORE_SEC_PAGE,
			'ebookstore_sec_render_admin',
			'dashicons-shield',
			81
		);
	}
);

/* -------------------------------------------------------------------------
 * IP-detection warnings
 * ---------------------------------------------------------------------- */

/**
 * Warnings about IP detection (empty = all good).
 *
 * @return string[]
 */
function ebookstore_sec_detection_warnings() {
	global $wpdb;
	$s        = ebookstore_sec_settings();
	$ip       = ebookstore_sec_ip();
	$server   = isset( $_SERVER['SERVER_ADDR'] ) ? ebookstore_sec_clean_ip( wp_unslash( $_SERVER['SERVER_ADDR'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as IP.
	$warnings = array();

	if ( $s['production'] ) {
		if ( $server && $ip === $server ) {
			/* translators: %s: IP */
			$warnings[] = sprintf( __( 'Your IP is detected as %s, which is this server\'s own address. A proxy or CDN is in front of the site: set SECURITY_TRUSTED_PROXY_HEADER (and SECURITY_TRUSTED_PROXIES) in .env, otherwise one block would affect everyone.', 'ebook-store' ), $ip );
		}
		if ( ebookstore_sec_ip_matches( $ip, '127.0.0.0/8' ) || '::1' === $ip ) {
			$warnings[] = __( 'Your IP is detected as localhost. On the live site this usually means a proxy hides the real visitor IP: set SECURITY_TRUSTED_PROXY_HEADER in .env. Also remove 127.0.0.1 and ::1 from SECURITY_IP_ALLOWLIST, or every visitor would be allowlisted.', 'ebook-store' );
		}
	}
	if ( $s['proxy_header'] && ! $s['proxies'] ) {
		$warnings[] = __( 'SECURITY_TRUSTED_PROXY_HEADER is set without SECURITY_TRUSTED_PROXIES. The header is trusted from any sender, so it could be faked. Add your proxy/CDN addresses to SECURITY_TRUSTED_PROXIES.', 'ebook-store' );
	}
	$t   = ebookstore_sec_tables();
	$top = $wpdb->get_row( $wpdb->prepare( "SELECT ip, COUNT(*) AS n FROM {$t['log']} WHERE event = 'lockout' AND created > %s GROUP BY ip ORDER BY n DESC LIMIT 1", ebookstore_sec_now( -DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
	if ( $top && (int) $top->n >= 5 ) {
		/* translators: 1: IP, 2: count */
		$warnings[] = sprintf( __( 'The IP %1$s was blocked %2$d times in 24 hours. If that is your server or CDN address, IP detection is wrong — see SECURITY_TRUSTED_PROXY_HEADER in .env.', 'ebook-store' ), $top->ip, (int) $top->n );
	}
	return $warnings;
}

add_action(
	'admin_notices',
	static function () {
		if ( ! current_user_can( EBOOKSTORE_SEC_CAP ) ) {
			return;
		}
		foreach ( ebookstore_sec_detection_warnings() as $warning ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Security:', 'ebook-store' ) . '</strong> ' . esc_html( $warning ) . '</p></div>';
		}
	}
);

/* -------------------------------------------------------------------------
 * Dashboard widget
 * ---------------------------------------------------------------------- */

add_action(
	'wp_dashboard_setup',
	static function () {
		if ( ! current_user_can( EBOOKSTORE_SEC_CAP ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'ebookstore_security',
			__( 'Security', 'ebook-store' ),
			static function () {
				$blocked = ebookstore_sec_blocked_list( '', 1 )['total'];
				echo '<p>';
				/* translators: %d: number of blocked IPs */
				echo esc_html( sprintf( _n( '%d IP is blocked from the admin area right now.', '%d IPs are blocked from the admin area right now.', $blocked, 'ebook-store' ), $blocked ) );
				echo '</p>';
				foreach ( ebookstore_sec_detection_warnings() as $warning ) {
					echo '<p style="color:#b32d2e">' . esc_html( $warning ) . '</p>';
				}
				echo '<p><a class="button" href="' . esc_url( ebookstore_sec_admin_url() ) . '">' . esc_html__( 'Open Security', 'ebook-store' ) . '</a></p>';
			}
		);
	}
);

/* -------------------------------------------------------------------------
 * Screen
 * ---------------------------------------------------------------------- */

/**
 * Render the Security screen.
 */
function ebookstore_sec_render_admin() {
	if ( ! current_user_can( EBOOKSTORE_SEC_CAP ) ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'blocked';
	$notice = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
	$error  = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';
	// phpcs:enable
	$my_ip  = ebookstore_sec_ip();
	$tabs   = array(
		'blocked'   => __( 'Blocked IPs', 'ebook-store' ),
		'allowlist' => __( 'Allowlist', 'ebook-store' ),
		'log'       => __( 'Activity log', 'ebook-store' ),
	);
	$tab    = isset( $tabs[ $tab ] ) ? $tab : 'blocked';
	$notes  = array(
		'unblocked'   => __( 'IP unblocked.', 'ebook-store' ),
		'unblocked_n' => __( 'Selected IPs unblocked.', 'ebook-store' ),
		'cleared'     => __( 'All blocks removed.', 'ebook-store' ),
		'blocked'     => __( 'IP blocked.', 'ebook-store' ),
		'allowed'     => __( 'IP added to the allowlist.', 'ebook-store' ),
		'disallowed'  => __( 'IP removed from the allowlist.', 'ebook-store' ),
		'log_cleared' => __( 'Activity log cleared.', 'ebook-store' ),
	);
	$errors = array(
		'invalid_ip' => __( 'Please enter a valid IPv4 or IPv6 address.', 'ebook-store' ),
		'allowlisted' => __( 'This IP is on the allowlist, so it cannot be blocked. Remove it from the allowlist first.', 'ebook-store' ),
	);
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Security', 'ebook-store' ); ?></h1>
		<p>
			<?php
			/* translators: %s: IP */
			printf( esc_html__( 'Your current IP: %s', 'ebook-store' ), '<code style="background:#fcf0c3">' . esc_html( $my_ip ) . '</code>' );
			?>
		</p>
		<?php if ( isset( $notes[ $notice ] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notes[ $notice ] ); ?></p></div>
		<?php endif; ?>
		<?php if ( isset( $errors[ $error ] ) ) : ?>
			<div class="notice notice-error is-dismissible"><p><?php echo esc_html( $errors[ $error ] ); ?></p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $key => $label ) : ?>
				<a class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url( ebookstore_sec_admin_url( array( 'tab' => $key ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php
		if ( 'allowlist' === $tab ) {
			ebookstore_sec_render_allowlist( $my_ip );
		} elseif ( 'log' === $tab ) {
			ebookstore_sec_render_log();
		} else {
			ebookstore_sec_render_blocked( $my_ip );
		}
		?>
	</div>
	<script>
	// Confirm dangerous actions; extra warning when the admin's own IP is involved.
	document.addEventListener( 'submit', function ( event ) {
		var form = event.target, msg = form.getAttribute( 'data-confirm' ), ipField = form.querySelector( '[name="ip"]' );
		var own = <?php echo wp_json_encode( $my_ip ); ?>;
		if ( ipField && form.hasAttribute( 'data-own-check' ) && ipField.value.trim() === own ) {
			msg = <?php echo wp_json_encode( __( 'This is YOUR current IP. You may lock yourself out of the admin area. Continue?', 'ebook-store' ) ); ?>;
		}
		if ( msg && ! window.confirm( msg ) ) {
			event.preventDefault();
		}
	} );
	</script>
	<?php
}

/**
 * Hidden fields for an action form.
 *
 * @param string $action Action name.
 */
function ebookstore_sec_form_fields( $action ) {
	echo '<input type="hidden" name="action" value="ebookstore_sec_' . esc_attr( $action ) . '">';
	wp_nonce_field( 'ebookstore_sec_' . $action );
}

/**
 * Human "time left".
 *
 * @param string $until UTC datetime.
 * @return string
 */
function ebookstore_sec_time_left( $until ) {
	$seconds = max( 0, strtotime( $until . ' UTC' ) - time() );
	return $seconds ? human_time_diff( time(), time() + $seconds ) : '—';
}

/**
 * Local date/time for a UTC datetime.
 *
 * @param string|null $utc UTC datetime.
 * @return string
 */
function ebookstore_sec_local_time( $utc ) {
	return $utc ? get_date_from_gmt( $utc, 'Y-m-d H:i' ) : '—';
}

/**
 * Pagination links.
 *
 * @param int    $total    Rows.
 * @param int    $per_page Per page.
 * @param int    $paged    Current page.
 * @param string $tab      Tab.
 * @param array  $extra    Extra query args.
 */
function ebookstore_sec_pagination( $total, $per_page, $paged, $tab, $extra = array() ) {
	$pages = (int) ceil( $total / $per_page );
	if ( $pages < 2 ) {
		return;
	}
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo wp_kses_post(
		paginate_links(
			array(
				'base'      => add_query_arg( 'paged', '%#%', ebookstore_sec_admin_url( array_merge( array( 'tab' => $tab ), $extra ) ) ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			)
		)
	);
	echo '</div></div>';
}

/**
 * Blocked IPs tab.
 *
 * @param string $my_ip Admin's IP.
 */
function ebookstore_sec_render_blocked( $my_ip ) {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
	$search = isset( $_GET['s'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['s'] ) ), 0, 100 ) : '';
	$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	$per   = 20;
	$list  = ebookstore_sec_blocked_list( $search, $per, ( $paged - 1 ) * $per );
	$s     = ebookstore_sec_settings();
	?>
	<div class="notice notice-info inline" style="margin:16px 0"><p><?php esc_html_e( 'Blocked IPs cannot open the admin area or log in to admin accounts. Customers on the same IP are not affected.', 'ebook-store' ); ?></p></div>

	<form method="get" style="margin:12px 0">
		<input type="hidden" name="page" value="<?php echo esc_attr( EBOOKSTORE_SEC_PAGE ); ?>">
		<input type="hidden" name="tab" value="blocked">
		<label class="screen-reader-text" for="ebookstore-sec-search"><?php esc_html_e( 'Search blocked IPs', 'ebook-store' ); ?></label>
		<input type="search" id="ebookstore-sec-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'IP or username', 'ebook-store' ); ?>">
		<button class="button"><?php esc_html_e( 'Search', 'ebook-store' ); ?></button>
	</form>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php ebookstore_sec_form_fields( 'unblock_selected' ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<td class="check-column"><label class="screen-reader-text" for="ebookstore-sec-all"><?php esc_html_e( 'Select all', 'ebook-store' ); ?></label><input type="checkbox" id="ebookstore-sec-all" onclick="document.querySelectorAll('.ebookstore-sec-cb').forEach(function(c){c.checked=this.checked}.bind(this))"></td>
					<th scope="col"><?php esc_html_e( 'IP', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last username tried', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Attempts', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Route', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Blocked at', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Time left', 'ebook-store' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'ebook-store' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $list['rows'] ) : ?>
					<tr><td colspan="8"><?php esc_html_e( 'No IP is blocked right now.', 'ebook-store' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $list['rows'] as $row ) : ?>
					<?php $own = $row->ip === $my_ip; ?>
					<tr<?php echo $own ? ' style="background:#fcf0c3"' : ''; ?>>
						<th scope="row" class="check-column"><input type="checkbox" class="ebookstore-sec-cb" name="ips[]" value="<?php echo esc_attr( $row->ip ); ?>" aria-label="<?php echo esc_attr( $row->ip ); ?>"></th>
						<td><code><?php echo esc_html( $row->ip ); ?></code><?php echo $own ? ' <strong>' . esc_html__( '(you)', 'ebook-store' ) . '</strong>' : ''; ?><?php echo $row->manual ? ' <em>' . esc_html__( 'manual', 'ebook-store' ) . '</em>' : ''; ?></td>
						<td><?php echo esc_html( $row->last_username ? $row->last_username : '—' ); ?></td>
						<td><?php echo esc_html( (string) $row->failures ); ?></td>
						<td><?php echo esc_html( $row->last_route ? $row->last_route : '—' ); ?></td>
						<td><?php echo esc_html( ebookstore_sec_local_time( $row->blocked_at ) ); ?></td>
						<td><?php echo esc_html( ebookstore_sec_time_left( $row->blocked_until ) ); ?></td>
						<td>
							<button class="button button-small" form="ebookstore-sec-unblock-<?php echo esc_attr( md5( $row->ip ) ); ?>"><?php esc_html_e( 'Unblock', 'ebook-store' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( $list['rows'] ) : ?>
			<p><button class="button"><?php esc_html_e( 'Unblock selected', 'ebook-store' ); ?></button></p>
		<?php endif; ?>
	</form>
	<?php foreach ( $list['rows'] as $row ) : ?>
		<form id="ebookstore-sec-unblock-<?php echo esc_attr( md5( $row->ip ) ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php ebookstore_sec_form_fields( 'unblock' ); ?>
			<input type="hidden" name="ip" value="<?php echo esc_attr( $row->ip ); ?>">
		</form>
	<?php endforeach; ?>
	<?php ebookstore_sec_pagination( $list['total'], $per, $paged, 'blocked', $search ? array( 's' => $search ) : array() ); ?>

	<?php if ( $list['total'] ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php esc_attr_e( 'Remove ALL blocks?', 'ebook-store' ); ?>">
			<?php ebookstore_sec_form_fields( 'unblock_all' ); ?>
			<p><button class="button button-link-delete"><?php esc_html_e( 'Unblock all', 'ebook-store' ); ?></button></p>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Block an IP manually', 'ebook-store' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-own-check>
		<?php ebookstore_sec_form_fields( 'block' ); ?>
		<label for="ebookstore-sec-block-ip"><?php esc_html_e( 'IP address', 'ebook-store' ); ?></label>
		<input type="text" id="ebookstore-sec-block-ip" name="ip" required maxlength="45" class="regular-text code">
		<label for="ebookstore-sec-block-min"><?php esc_html_e( 'Minutes', 'ebook-store' ); ?></label>
		<input type="number" id="ebookstore-sec-block-min" name="minutes" min="1" max="525600" value="<?php echo esc_attr( (string) $s['lockout_min'] ); ?>" class="small-text">
		<button class="button button-primary"><?php esc_html_e( 'Block', 'ebook-store' ); ?></button>
	</form>
	<?php
}

/**
 * Allowlist tab.
 *
 * @param string $my_ip Admin's IP.
 */
function ebookstore_sec_render_allowlist( $my_ip ) {
	$s     = ebookstore_sec_settings();
	$admin = ebookstore_sec_allowlist_admin();
	?>
	<p><?php esc_html_e( 'IPs on the allowlist are never blocked. Use it for your own office or home connection (only if it has a fixed IP).', 'ebook-store' ); ?></p>
	<table class="widefat striped" style="max-width:720px">
		<thead><tr><th scope="col"><?php esc_html_e( 'IP or range', 'ebook-store' ); ?></th><th scope="col"><?php esc_html_e( 'Source', 'ebook-store' ); ?></th><th scope="col"><?php esc_html_e( 'Action', 'ebook-store' ); ?></th></tr></thead>
		<tbody>
			<?php foreach ( $s['allowlist_env'] as $entry ) : ?>
				<tr><td><code><?php echo esc_html( $entry ); ?></code></td><td><?php esc_html_e( '.env (SECURITY_IP_ALLOWLIST) — read only', 'ebook-store' ); ?></td><td>—</td></tr>
			<?php endforeach; ?>
			<?php foreach ( $admin as $entry ) : ?>
				<?php $own = ebookstore_sec_ip_matches( $my_ip, $entry ); ?>
				<tr<?php echo $own ? ' style="background:#fcf0c3"' : ''; ?>>
					<td><code><?php echo esc_html( $entry ); ?></code><?php echo $own ? ' <strong>' . esc_html__( '(you)', 'ebook-store' ) . '</strong>' : ''; ?></td>
					<td><?php esc_html_e( 'Added here', 'ebook-store' ); ?></td>
					<td>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php echo esc_attr( $own ? __( 'This entry covers YOUR current IP. Remove it anyway?', 'ebook-store' ) : __( 'Remove this IP from the allowlist?', 'ebook-store' ) ); ?>">
							<?php ebookstore_sec_form_fields( 'disallow' ); ?>
							<input type="hidden" name="ip" value="<?php echo esc_attr( $entry ); ?>">
							<button class="button button-small"><?php esc_html_e( 'Remove', 'ebook-store' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			<?php if ( ! $s['allowlist_env'] && ! $admin ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'The allowlist is empty.', 'ebook-store' ); ?></td></tr>
			<?php endif; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Add an IP', 'ebook-store' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php ebookstore_sec_form_fields( 'allow' ); ?>
		<label for="ebookstore-sec-allow-ip"><?php esc_html_e( 'IP address', 'ebook-store' ); ?></label>
		<input type="text" id="ebookstore-sec-allow-ip" name="ip" required maxlength="45" class="regular-text code">
		<button class="button button-primary"><?php esc_html_e( 'Add', 'ebook-store' ); ?></button>
	</form>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px">
		<?php ebookstore_sec_form_fields( 'allow' ); ?>
		<input type="hidden" name="ip" value="<?php echo esc_attr( $my_ip ); ?>">
		<button class="button">
			<?php
			/* translators: %s: IP */
			echo esc_html( sprintf( __( 'Add my current IP (%s)', 'ebook-store' ), $my_ip ) );
			?>
		</button>
	</form>
	<?php
}

/**
 * Activity log tab.
 */
function ebookstore_sec_render_log() {
	global $wpdb;
	$t = ebookstore_sec_tables();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view parameters.
	$ip_filter = isset( $_GET['ip'] ) ? ebookstore_sec_clean_ip( wp_unslash( $_GET['ip'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as IP.
	$date      = isset( $_GET['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) wp_unslash( $_GET['date'] ) ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '';
	$paged     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	// phpcs:enable
	$per   = 50;
	$where = '1=1';
	if ( $ip_filter ) {
		$where .= $wpdb->prepare( ' AND ip = %s', $ip_filter );
	}
	if ( $date ) {
		// The date is the site's local date: convert its start/end to UTC.
		$where .= $wpdb->prepare( ' AND created >= %s AND created < %s', get_gmt_from_date( $date . ' 00:00:00' ), get_gmt_from_date( $date . ' 23:59:59' ) );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name + prepared $where.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['log']} WHERE $where" );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['log']} WHERE $where ORDER BY id DESC LIMIT %d OFFSET %d", $per, ( $paged - 1 ) * $per ) );
	// phpcs:enable
	$labels = array(
		'failed'         => __( 'Failed login (counted)', 'ebook-store' ),
		'lockout'        => __( 'IP blocked', 'ebook-store' ),
		'blocked'        => __( 'Refused (IP blocked)', 'ebook-store' ),
		'unlock_request' => __( 'Unlock link requested', 'ebook-store' ),
		'unlock_used'    => __( 'Unlock link opened', 'ebook-store' ),
		'unblock'        => __( 'Unblocked by admin', 'ebook-store' ),
		'manual_block'   => __( 'Blocked by admin', 'ebook-store' ),
		'allow'          => __( 'Added to allowlist', 'ebook-store' ),
		'disallow'       => __( 'Removed from allowlist', 'ebook-store' ),
	);
	?>
	<p><?php esc_html_e( 'Counted failed logins (admin accounts and unknown usernames), blocks and unlocks. Passwords are never logged. Customer logins are not logged.', 'ebook-store' ); ?></p>
	<form method="get" style="margin:12px 0">
		<input type="hidden" name="page" value="<?php echo esc_attr( EBOOKSTORE_SEC_PAGE ); ?>">
		<input type="hidden" name="tab" value="log">
		<label for="ebookstore-sec-log-ip"><?php esc_html_e( 'IP', 'ebook-store' ); ?></label>
		<input type="text" id="ebookstore-sec-log-ip" name="ip" value="<?php echo esc_attr( $ip_filter ); ?>" class="code" maxlength="45">
		<label for="ebookstore-sec-log-date"><?php esc_html_e( 'Date', 'ebook-store' ); ?></label>
		<input type="date" id="ebookstore-sec-log-date" name="date" value="<?php echo esc_attr( $date ); ?>">
		<button class="button"><?php esc_html_e( 'Filter', 'ebook-store' ); ?></button>
	</form>
	<table class="widefat striped">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Time', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Event', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'IP', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Route', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Username tried', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Details', 'ebook-store' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Browser', 'ebook-store' ); ?></th>
		</tr></thead>
		<tbody>
			<?php if ( ! $rows ) : ?>
				<tr><td colspan="7"><?php esc_html_e( 'No entries.', 'ebook-store' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( (array) $rows as $row ) : ?>
				<tr>
					<td><?php echo esc_html( ebookstore_sec_local_time( $row->created ) ); ?></td>
					<td><?php echo esc_html( $labels[ $row->event ] ?? $row->event ); ?></td>
					<td><code><?php echo esc_html( $row->ip ); ?></code></td>
					<td><?php echo esc_html( $row->route ); ?></td>
					<td><?php echo esc_html( $row->username ); ?></td>
					<td><?php echo esc_html( $row->note ); ?></td>
					<td><span title="<?php echo esc_attr( $row->user_agent ); ?>"><?php echo esc_html( mb_strimwidth( $row->user_agent, 0, 40, '…' ) ); ?></span></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
	ebookstore_sec_pagination( $total, $per, $paged, 'log', array_filter( array( 'ip' => $ip_filter, 'date' => $date ) ) );
	if ( $total ) :
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-confirm="<?php esc_attr_e( 'Delete the whole activity log?', 'ebook-store' ); ?>">
			<?php ebookstore_sec_form_fields( 'clear_log' ); ?>
			<p><button class="button button-link-delete"><?php esc_html_e( 'Clear log', 'ebook-store' ); ?></button></p>
		</form>
		<?php
	endif;
}

/* -------------------------------------------------------------------------
 * Actions (admin-post.php, capability + nonce on every one)
 * ---------------------------------------------------------------------- */

/**
 * Check capability + nonce for an action.
 *
 * @param string $action Action name.
 */
function ebookstore_sec_verify( $action ) {
	if ( ! current_user_can( EBOOKSTORE_SEC_CAP ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'ebook-store' ), 403 );
	}
	check_admin_referer( 'ebookstore_sec_' . $action );
}

/**
 * Posted IP (validated) or redirect with an error.
 *
 * @param string $tab Tab to return to.
 * @return string
 */
function ebookstore_sec_posted_ip( $tab ) {
	$ip = isset( $_POST['ip'] ) ? ebookstore_sec_clean_ip( wp_unslash( $_POST['ip'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce checked by caller; validated as IP.
	if ( ! $ip ) {
		wp_safe_redirect( ebookstore_sec_admin_url( array( 'tab' => $tab, 'error' => 'invalid_ip' ) ) );
		exit;
	}
	return $ip;
}

/**
 * Redirect back with a notice.
 *
 * @param string $tab  Tab.
 * @param string $done Notice key.
 */
function ebookstore_sec_back( $tab, $done ) {
	wp_safe_redirect( ebookstore_sec_admin_url( array( 'tab' => $tab, 'done' => $done ) ) );
	exit;
}

add_action(
	'admin_post_ebookstore_sec_unblock',
	static function () {
		ebookstore_sec_verify( 'unblock' );
		$ip = ebookstore_sec_posted_ip( 'blocked' );
		ebookstore_sec_unblock_ip( $ip );
		ebookstore_sec_log( 'unblock', $ip, 'admin', wp_get_current_user()->user_login );
		ebookstore_sec_back( 'blocked', 'unblocked' );
	}
);

add_action(
	'admin_post_ebookstore_sec_unblock_selected',
	static function () {
		ebookstore_sec_verify( 'unblock_selected' );
		$ips = isset( $_POST['ips'] ) && is_array( $_POST['ips'] ) ? array_filter( array_map( 'ebookstore_sec_clean_ip', wp_unslash( $_POST['ips'] ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as IPs.
		foreach ( $ips as $ip ) {
			ebookstore_sec_unblock_ip( $ip );
			ebookstore_sec_log( 'unblock', $ip, 'admin', wp_get_current_user()->user_login );
		}
		ebookstore_sec_back( 'blocked', 'unblocked_n' );
	}
);

add_action(
	'admin_post_ebookstore_sec_unblock_all',
	static function () {
		global $wpdb;
		ebookstore_sec_verify( 'unblock_all' );
		$t = ebookstore_sec_tables();
		$wpdb->query( "DELETE FROM {$t['ips']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, no input.
		ebookstore_sec_log( 'unblock', '*', 'admin', wp_get_current_user()->user_login, 'all IPs' );
		ebookstore_sec_back( 'blocked', 'cleared' );
	}
);

add_action(
	'admin_post_ebookstore_sec_block',
	static function () {
		ebookstore_sec_verify( 'block' );
		$ip      = ebookstore_sec_posted_ip( 'blocked' );
		$minutes = isset( $_POST['minutes'] ) ? min( 525600, max( 1, absint( $_POST['minutes'] ) ) ) : ebookstore_sec_settings()['lockout_min']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above.
		if ( ebookstore_sec_is_allowlisted( $ip ) ) {
			wp_safe_redirect( ebookstore_sec_admin_url( array( 'tab' => 'blocked', 'error' => 'allowlisted' ) ) );
			exit;
		}
		ebookstore_sec_block_ip( $ip, $minutes, true, '', 'manual' );
		ebookstore_sec_log( 'manual_block', $ip, 'admin', wp_get_current_user()->user_login, sprintf( '%d minutes', $minutes ) );
		ebookstore_sec_back( 'blocked', 'blocked' );
	}
);

add_action(
	'admin_post_ebookstore_sec_allow',
	static function () {
		ebookstore_sec_verify( 'allow' );
		$ip   = ebookstore_sec_posted_ip( 'allowlist' );
		$list = ebookstore_sec_allowlist_admin();
		if ( ! in_array( $ip, $list, true ) ) {
			$list[] = $ip;
			update_option( EBOOKSTORE_SEC_ALLOW_OPTION, array_slice( $list, 0, 200 ), false );
		}
		ebookstore_sec_unblock_ip( $ip );
		ebookstore_sec_log( 'allow', $ip, 'admin', wp_get_current_user()->user_login );
		ebookstore_sec_back( 'allowlist', 'allowed' );
	}
);

add_action(
	'admin_post_ebookstore_sec_disallow',
	static function () {
		ebookstore_sec_verify( 'disallow' );
		$ip = ebookstore_sec_posted_ip( 'allowlist' );
		update_option( EBOOKSTORE_SEC_ALLOW_OPTION, array_values( array_diff( ebookstore_sec_allowlist_admin(), array( $ip ) ) ), false );
		ebookstore_sec_log( 'disallow', $ip, 'admin', wp_get_current_user()->user_login );
		ebookstore_sec_back( 'allowlist', 'disallowed' );
	}
);

add_action(
	'admin_post_ebookstore_sec_clear_log',
	static function () {
		global $wpdb;
		ebookstore_sec_verify( 'clear_log' );
		$t = ebookstore_sec_tables();
		$wpdb->query( "TRUNCATE TABLE {$t['log']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- custom table, no input.
		ebookstore_sec_back( 'log', 'log_cleared' );
	}
);
