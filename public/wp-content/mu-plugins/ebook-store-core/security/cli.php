<?php
/**
 * Security: WP-CLI recovery tools.
 *
 *   wp ebookstore security list-blocked
 *   wp ebookstore security unblock <ip>
 *   wp ebookstore security unblock-all
 *   wp ebookstore security allow <ip>
 *   wp ebookstore security disallow <ip>
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Validated IP argument or a CLI error.
 *
 * @param array $args Positional arguments.
 * @return string
 */
function ebookstore_sec_cli_ip( $args ) {
	$ip = ebookstore_sec_clean_ip( $args[0] ?? '' );
	if ( ! $ip ) {
		WP_CLI::error( 'Please give a valid IPv4 or IPv6 address.' );
	}
	return $ip;
}

WP_CLI::add_command(
	'ebookstore security list-blocked',
	static function () {
		$list = ebookstore_sec_blocked_list( '', 500 );
		if ( ! $list['rows'] ) {
			WP_CLI::success( 'No IP is blocked.' );
			return;
		}
		$items = array_map(
			static function ( $row ) {
				return array(
					'ip'            => $row->ip,
					'last_username' => $row->last_username,
					'attempts'      => $row->failures,
					'route'         => $row->last_route,
					'blocked_at'    => $row->blocked_at . ' UTC',
					'time_left'     => human_time_diff( time(), strtotime( $row->blocked_until . ' UTC' ) ),
					'manual'        => $row->manual ? 'yes' : 'no',
				);
			},
			$list['rows']
		);
		WP_CLI\Utils\format_items( 'table', $items, array_keys( $items[0] ) );
	},
	array( 'shortdesc' => 'List IPs blocked from the admin area.' )
);

WP_CLI::add_command(
	'ebookstore security unblock',
	static function ( $args ) {
		$ip = ebookstore_sec_cli_ip( $args );
		$found = ebookstore_sec_unblock_ip( $ip );
		ebookstore_sec_log( 'unblock', $ip, 'wp-cli' );
		WP_CLI::success( $found ? "Unblocked $ip." : "$ip was not blocked (counter cleared)." );
	},
	array(
		'shortdesc' => 'Unblock one IP.',
		'synopsis'  => array(
			array(
				'type'        => 'positional',
				'name'        => 'ip',
				'description' => 'IP address.',
			),
		),
	)
);

WP_CLI::add_command(
	'ebookstore security unblock-all',
	static function () {
		global $wpdb;
		$t = ebookstore_sec_tables();
		$n = (int) $wpdb->query( "DELETE FROM {$t['ips']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- custom table, no input.
		ebookstore_sec_log( 'unblock', '*', 'wp-cli', '', 'all IPs' );
		WP_CLI::success( "Removed all blocks and counters ($n rows)." );
	},
	array( 'shortdesc' => 'Unblock all IPs.' )
);

WP_CLI::add_command(
	'ebookstore security allow',
	static function ( $args ) {
		$ip   = ebookstore_sec_cli_ip( $args );
		$list = ebookstore_sec_allowlist_admin();
		if ( ! in_array( $ip, $list, true ) ) {
			$list[] = $ip;
			update_option( EBOOKSTORE_SEC_ALLOW_OPTION, $list, false );
		}
		ebookstore_sec_unblock_ip( $ip );
		ebookstore_sec_log( 'allow', $ip, 'wp-cli' );
		WP_CLI::success( "$ip is on the allowlist (never blocked)." );
	},
	array(
		'shortdesc' => 'Add an IP to the allowlist.',
		'synopsis'  => array(
			array(
				'type'        => 'positional',
				'name'        => 'ip',
				'description' => 'IP address.',
			),
		),
	)
);

WP_CLI::add_command(
	'ebookstore security disallow',
	static function ( $args ) {
		$ip = ebookstore_sec_cli_ip( $args );
		update_option( EBOOKSTORE_SEC_ALLOW_OPTION, array_values( array_diff( ebookstore_sec_allowlist_admin(), array( $ip ) ) ), false );
		ebookstore_sec_log( 'disallow', $ip, 'wp-cli' );
		WP_CLI::success( "$ip removed from the allowlist." );
	},
	array(
		'shortdesc' => 'Remove an IP from the allowlist.',
		'synopsis'  => array(
			array(
				'type'        => 'positional',
				'name'        => 'ip',
				'description' => 'IP address.',
			),
		),
	)
);
