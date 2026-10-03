<?php
/**
 * Security: settings, visitor IP detection, storage and log.
 *
 * Settings come from .env (SECURITY_* keys). Data lives in two small tables:
 *   {prefix}ebookstore_sec_ips  one row per IP with counted failures / block
 *   {prefix}ebookstore_sec_log  counted failures, lockouts, unlocks (no passwords)
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_SEC_DB_VERSION   = '1';
const EBOOKSTORE_SEC_ALLOW_OPTION = 'ebookstore_security_allowlist';

/* -------------------------------------------------------------------------
 * Settings
 * ---------------------------------------------------------------------- */

/**
 * All security settings (from .env, with safe defaults).
 *
 * @return array
 */
function ebookstore_sec_settings() {
	static $s = null;
	if ( null !== $s ) {
		return $s;
	}
	$get  = static function ( $key, $default ) {
		$value = function_exists( 'env' ) ? env( $key, $default ) : $default;
		return ( null === $value || '' === $value ) ? $default : $value;
	};
	$list = static function ( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
	};
	$s = array(
		'roles'          => $list( $get( 'SECURITY_PROTECTED_ROLES', 'administrator,shop_manager' ) ),
		'max_attempts'   => max( 1, (int) $get( 'SECURITY_MAX_LOGIN_ATTEMPTS', 5 ) ),
		'lockout_min'    => max( 1, (int) $get( 'SECURITY_LOCKOUT_MINUTES', 120 ) ),
		'allowlist_env'  => $list( $get( 'SECURITY_IP_ALLOWLIST', '' ) ),
		'proxy_header'   => strtoupper( str_replace( '-', '_', trim( (string) $get( 'SECURITY_TRUSTED_PROXY_HEADER', '' ) ) ) ),
		'proxies'        => $list( $get( 'SECURITY_TRUSTED_PROXIES', '' ) ),
		'disable_xmlrpc' => (bool) $get( 'SECURITY_DISABLE_XMLRPC', true ),
		'unlock_min'     => max( 1, (int) $get( 'SECURITY_UNLOCK_LINK_MINUTES', 15 ) ),
		'reset_cooldown' => max( 0, (int) $get( 'SECURITY_RESET_EMAIL_COOLDOWN_SECONDS', 120 ) ),
		'log_days'       => max( 1, (int) $get( 'SECURITY_LOG_RETENTION_DAYS', 30 ) ),
		'hsts'           => (bool) $get( 'SECURITY_HSTS', false ),
		'production'     => 'production' === wp_get_environment_type(),
		// Test only: lets local tests pretend to come from another IP. Ignored unless APP_ENV=local.
		'test_ip'        => 'local' === wp_get_environment_type() && (bool) $get( 'SECURITY_TEST_IP_OVERRIDE', false ),
	);
	return $s;
}

/* -------------------------------------------------------------------------
 * IP helpers
 * ---------------------------------------------------------------------- */

/**
 * Valid IPv4/IPv6 address, normalised (or '' if invalid).
 *
 * @param string $ip Raw value.
 * @return string
 */
function ebookstore_sec_clean_ip( $ip ) {
	$ip = trim( (string) $ip );
	// "[2001:db8::1]:443" / "1.2.3.4:80" → address only.
	if ( preg_match( '/^\[([0-9a-f:.]+)\](?::\d+)?$/i', $ip, $m ) ) {
		$ip = $m[1];
	} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{1,3}){3}):\d+$/', $ip, $m ) ) {
		$ip = $m[1];
	}
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
		return '';
	}
	$packed = inet_pton( $ip );
	return false === $packed ? '' : (string) inet_ntop( $packed );
}

/**
 * Whether an IP matches an address or CIDR range ("1.2.3.0/24", "2001:db8::/32").
 *
 * @param string $ip    IP address.
 * @param string $range Address or CIDR.
 * @return bool
 */
function ebookstore_sec_ip_matches( $ip, $range ) {
	$ip_bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input returns false.
	if ( false === $ip_bin ) {
		return false;
	}
	$parts   = explode( '/', trim( $range ), 2 );
	$net_bin = @inet_pton( $parts[0] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $net_bin || strlen( $net_bin ) !== strlen( $ip_bin ) ) {
		return false;
	}
	$bits = isset( $parts[1] ) ? (int) $parts[1] : strlen( $ip_bin ) * 8;
	$bits = max( 0, min( $bits, strlen( $ip_bin ) * 8 ) );
	$full = intdiv( $bits, 8 );
	if ( substr( $ip_bin, 0, $full ) !== substr( $net_bin, 0, $full ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( ! $rest ) {
		return true;
	}
	$mask = chr( ( 0xff << ( 8 - $rest ) ) & 0xff );
	return ( $ip_bin[ $full ] & $mask ) === ( $net_bin[ $full ] & $mask );
}

/**
 * Whether an IP matches any entry of a list.
 *
 * @param string   $ip   IP address.
 * @param string[] $list Addresses / CIDR ranges.
 * @return bool
 */
function ebookstore_sec_ip_in_list( $ip, array $list ) {
	foreach ( $list as $range ) {
		if ( ebookstore_sec_ip_matches( $ip, $range ) ) {
			return true;
		}
	}
	return false;
}

/**
 * The visitor's IP.
 *
 * REMOTE_ADDR by default. A proxy/CDN header is used only when it is
 * configured (SECURITY_TRUSTED_PROXY_HEADER) and, if SECURITY_TRUSTED_PROXIES
 * is set, only when the request really comes from one of those proxies —
 * otherwise anyone could fake the header.
 *
 * @return string
 */
function ebookstore_sec_ip() {
	static $ip = null;
	if ( null !== $ip ) {
		return $ip;
	}
	$s      = ebookstore_sec_settings();
	$remote = ebookstore_sec_clean_ip( isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as IP.
	$ip     = $remote ? $remote : '0.0.0.0';

	if ( $s['test_ip'] && ! empty( $_SERVER['HTTP_X_EBOOK_TEST_IP'] ) ) {
		$test = ebookstore_sec_clean_ip( wp_unslash( $_SERVER['HTTP_X_EBOOK_TEST_IP'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated as IP.
		if ( $test ) {
			$ip = $test;
			return $ip;
		}
	}

	if ( '' === $s['proxy_header'] ) {
		return $ip;
	}
	if ( $s['proxies'] && ! ebookstore_sec_ip_in_list( $ip, $s['proxies'] ) ) {
		return $ip; // Not from our proxy: the header could be fake.
	}
	$key = 'HTTP_' . preg_replace( '/^HTTP_/', '', $s['proxy_header'] );
	if ( empty( $_SERVER[ $key ] ) ) {
		return $ip;
	}
	// "client, proxy1, proxy2": walk from the right, skip our own proxies.
	$chain = array_reverse( array_map( 'trim', explode( ',', (string) wp_unslash( $_SERVER[ $key ] ) ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each entry validated as IP.
	foreach ( $chain as $candidate ) {
		$candidate = ebookstore_sec_clean_ip( $candidate );
		if ( ! $candidate ) {
			break;
		}
		if ( $s['proxies'] && ebookstore_sec_ip_in_list( $candidate, $s['proxies'] ) ) {
			continue;
		}
		$ip = $candidate;
		break;
	}
	return $ip;
}

/**
 * Allowlist from the admin screen.
 *
 * @return string[]
 */
function ebookstore_sec_allowlist_admin() {
	return array_values( array_filter( (array) get_option( EBOOKSTORE_SEC_ALLOW_OPTION, array() ), 'is_string' ) );
}

/**
 * Whether an IP is never blocked.
 *
 * @param string $ip IP.
 * @return bool
 */
function ebookstore_sec_is_allowlisted( $ip ) {
	$s = ebookstore_sec_settings();
	return ebookstore_sec_ip_in_list( $ip, array_merge( $s['allowlist_env'], ebookstore_sec_allowlist_admin() ) );
}

/* -------------------------------------------------------------------------
 * Accounts
 * ---------------------------------------------------------------------- */

/**
 * Whether a user has a protected role (admin accounts).
 *
 * @param WP_User|false|null $user User.
 * @return bool
 */
function ebookstore_sec_is_protected_user( $user ) {
	if ( ! $user instanceof WP_User ) {
		return false;
	}
	return (bool) array_intersect( (array) $user->roles, ebookstore_sec_settings()['roles'] );
}

/**
 * Find a user by login name or email address.
 *
 * @param string $login Username or email.
 * @return WP_User|false
 */
function ebookstore_sec_find_user( $login ) {
	$login = trim( (string) $login );
	if ( '' === $login ) {
		return false;
	}
	$user = get_user_by( 'login', $login );
	if ( ! $user && is_email( $login ) ) {
		$user = get_user_by( 'email', $login );
	}
	return $user;
}

/**
 * Whether a login attempt with this username "counts": the account does
 * not exist, or it is a protected (admin) account. Customer accounts never count.
 *
 * @param string $login Username or email.
 * @return bool
 */
function ebookstore_sec_login_counts( $login ) {
	$user = ebookstore_sec_find_user( $login );
	return ! $user || ebookstore_sec_is_protected_user( $user );
}

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

/**
 * Table names.
 *
 * @return array{ips: string, log: string}
 */
function ebookstore_sec_tables() {
	global $wpdb;
	return array(
		'ips' => $wpdb->prefix . 'ebookstore_sec_ips',
		'log' => $wpdb->prefix . 'ebookstore_sec_log',
	);
}

/**
 * Create/upgrade the tables (cheap option check on every load).
 */
function ebookstore_sec_install() {
	if ( EBOOKSTORE_SEC_DB_VERSION === get_option( 'ebookstore_security_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t       = ebookstore_sec_tables();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$t['ips']} (
			ip varchar(45) NOT NULL,
			failures int unsigned NOT NULL DEFAULT 0,
			last_failure datetime NULL,
			blocked_until datetime NULL,
			lockouts int unsigned NOT NULL DEFAULT 0,
			last_username varchar(100) NOT NULL DEFAULT '',
			last_route varchar(40) NOT NULL DEFAULT '',
			blocked_at datetime NULL,
			manual tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (ip),
			KEY blocked_until (blocked_until)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE {$t['log']} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			ip varchar(45) NOT NULL DEFAULT '',
			event varchar(30) NOT NULL DEFAULT '',
			route varchar(40) NOT NULL DEFAULT '',
			username varchar(100) NOT NULL DEFAULT '',
			user_agent varchar(255) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY created (created),
			KEY ip (ip)
		) $charset;"
	);
	update_option( 'ebookstore_security_db', EBOOKSTORE_SEC_DB_VERSION, true );
}
add_action( 'plugins_loaded', 'ebookstore_sec_install', 1 );

/**
 * Current UTC time as MySQL datetime.
 *
 * @param int $offset Seconds to add.
 * @return string
 */
function ebookstore_sec_now( $offset = 0 ) {
	return gmdate( 'Y-m-d H:i:s', time() + $offset );
}

/**
 * Row for an IP (or null).
 *
 * @param string $ip IP.
 * @return object|null
 */
function ebookstore_sec_get_ip_row( $ip ) {
	global $wpdb;
	$t = ebookstore_sec_tables();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t['ips']} WHERE ip = %s", $ip ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table name.
}

/**
 * Seconds left in a block (0 = not blocked). Allowlisted IPs are never blocked.
 *
 * @param string $ip IP.
 * @return int
 */
function ebookstore_sec_blocked_seconds( $ip ) {
	if ( ebookstore_sec_is_allowlisted( $ip ) ) {
		return 0;
	}
	$row = ebookstore_sec_get_ip_row( $ip );
	if ( ! $row || ! $row->blocked_until ) {
		return 0;
	}
	return max( 0, strtotime( $row->blocked_until . ' UTC' ) - time() );
}

/**
 * Block an IP.
 *
 * @param string $ip       IP.
 * @param int    $minutes  Duration.
 * @param bool   $manual   Blocked by an admin.
 * @param string $username Last username tried.
 * @param string $route    Route.
 */
function ebookstore_sec_block_ip( $ip, $minutes, $manual = false, $username = '', $route = '' ) {
	global $wpdb;
	$t   = ebookstore_sec_tables();
	$row = ebookstore_sec_get_ip_row( $ip );
	$data = array(
		'ip'            => $ip,
		'blocked_until' => ebookstore_sec_now( max( 1, (int) $minutes ) * MINUTE_IN_SECONDS ),
		'blocked_at'    => ebookstore_sec_now(),
		'lockouts'      => $row ? (int) $row->lockouts + 1 : 1,
		'manual'        => $manual ? 1 : 0,
		'failures'      => $row ? (int) $row->failures : 0,
		'last_username' => $username ? mb_substr( $username, 0, 100 ) : ( $row ? $row->last_username : '' ),
		'last_route'    => $route ? $route : ( $row ? $row->last_route : '' ),
		'last_failure'  => $row ? $row->last_failure : null,
	);
	$wpdb->replace( $t['ips'], $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Remove a block and reset the counter of an IP.
 *
 * @param string $ip IP.
 * @return bool Whether a row existed.
 */
function ebookstore_sec_unblock_ip( $ip ) {
	global $wpdb;
	$t = ebookstore_sec_tables();
	return (bool) $wpdb->delete( $t['ips'], array( 'ip' => $ip ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * Currently blocked IPs.
 *
 * @param string $search Part of an IP or username.
 * @param int    $limit  Rows.
 * @param int    $offset Offset.
 * @return array{rows: object[], total: int}
 */
function ebookstore_sec_blocked_list( $search = '', $limit = 20, $offset = 0 ) {
	global $wpdb;
	$t     = ebookstore_sec_tables();
	$where = $wpdb->prepare( 'blocked_until > %s', ebookstore_sec_now() );
	if ( '' !== $search ) {
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$where .= $wpdb->prepare( ' AND (ip LIKE %s OR last_username LIKE %s)', $like, $like );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name + prepared $where.
	$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t['ips']} WHERE $where" );
	$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t['ips']} WHERE $where ORDER BY blocked_at DESC LIMIT %d OFFSET %d", $limit, $offset ) );
	// phpcs:enable
	return array(
		'rows'  => (array) $rows,
		'total' => $total,
	);
}

/**
 * Write a log entry (never passwords).
 *
 * @param string $event    failed | lockout | blocked | unlock_request | unlock_used | unblock | manual_block | allow | disallow.
 * @param string $ip       IP.
 * @param string $route    Route.
 * @param string $username Username tried.
 * @param string $note     Extra info.
 */
function ebookstore_sec_log( $event, $ip, $route = '', $username = '', $note = '' ) {
	global $wpdb;
	$t  = ebookstore_sec_tables();
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$t['log'],
		array(
			'created'    => ebookstore_sec_now(),
			'ip'         => $ip,
			'event'      => $event,
			'route'      => mb_substr( $route, 0, 40 ),
			'username'   => mb_substr( sanitize_text_field( $username ), 0, 100 ),
			'user_agent' => mb_substr( $ua, 0, 255 ),
			'note'       => mb_substr( sanitize_text_field( $note ), 0, 255 ),
		)
	);
}

/**
 * Route name of the current request (for the log).
 *
 * @return string
 */
function ebookstore_sec_route() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return 'rest-api';
	}
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		return 'xmlrpc';
	}
	$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( sanitize_text_field( wp_unslash( $_SERVER['SCRIPT_NAME'] ) ) ) : '';
	if ( 'wp-login.php' === $script ) {
		return 'wp-login.php';
	}
	if ( isset( $_POST['woocommerce-login-nonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only detects the form.
		return 'my-account';
	}
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	if ( false !== strpos( $uri, '/wp-json/' ) || false !== strpos( $uri, 'rest_route=' ) ) {
		return 'rest-api';
	}
	return is_admin() ? 'wp-admin' : 'other';
}

/* -------------------------------------------------------------------------
 * Daily clean-up
 * ---------------------------------------------------------------------- */

add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'ebookstore_security_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ebookstore_security_cleanup' );
		}
	}
);

add_action(
	'ebookstore_security_cleanup',
	static function () {
		global $wpdb;
		$t = ebookstore_sec_tables();
		$s = ebookstore_sec_settings();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table names.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['log']} WHERE created < %s", ebookstore_sec_now( -$s['log_days'] * DAY_IN_SECONDS ) ) );
		// IP rows that are no longer blocked and have no recent failures.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$t['ips']} WHERE (blocked_until IS NULL OR blocked_until < %s) AND (last_failure IS NULL OR last_failure < %s)",
				ebookstore_sec_now(),
				ebookstore_sec_now( -$s['lockout_min'] * MINUTE_IN_SECONDS )
			)
		);
		// phpcs:enable
	}
);
