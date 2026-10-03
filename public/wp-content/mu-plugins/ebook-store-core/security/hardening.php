<?php
/**
 * Security: essential hardening.
 *
 * - User list in the REST API only for staff (customers and visitors cannot
 *   read admin login names); ?author=N redirects to the home page.
 * - XML-RPC off (SECURITY_DISABLE_XMLRPC).
 * - No WordPress version in the page source, feeds or asset URLs.
 * - Safe security headers; HSTS only when SECURITY_HSTS=true.
 * - Production: secure login cookie on HTTPS (FORCE_SSL_ADMIN is set in wp-config.php).
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Usernames ---------- */

/**
 * Hide the user endpoints from everyone who cannot edit content (visitors
 * and customers). Staff — and the block editor — keep access.
 *
 * @param array $endpoints REST endpoints.
 * @return array
 */
function ebookstore_hide_rest_users( $endpoints ) {
	if ( ! current_user_can( 'edit_posts' ) ) {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}
	}
	return $endpoints;
}
add_filter( 'rest_endpoints', 'ebookstore_hide_rest_users' );

/**
 * No author archives (the store has no blog). Runs before WordPress'
 * canonical redirect, so "?author=1" cannot reveal a login name either.
 */
function ebookstore_disable_author_archives() {
	if ( is_author() || isset( $_GET['author'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'ebookstore_disable_author_archives', 1 );

// Author names in oEmbed data reveal nothing useful either.
add_filter(
	'oembed_response_data',
	static function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	}
);

/* ---------- XML-RPC ---------- */

if ( ebookstore_sec_settings()['disable_xmlrpc'] ) {
	add_filter( 'xmlrpc_enabled', '__return_false' );
	add_filter( 'xmlrpc_methods', '__return_empty_array' );
	add_filter(
		'wp_headers',
		static function ( $headers ) {
			unset( $headers['X-Pingback'] );
			return $headers;
		}
	);
	remove_action( 'wp_head', 'rsd_link' );
	// Direct requests to xmlrpc.php get a plain 403 (also blocked in .htaccess on Hostinger).
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'XML-RPC is disabled.';
		exit;
	}
}

/* ---------- Version info ---------- */

remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Core assets carry "?ver=<WordPress version>": replace it with a short hash
 * (still changes on every update, so caches stay correct).
 *
 * @param string $src Asset URL.
 * @return string
 */
function ebookstore_sec_hide_version_in_src( $src ) {
	$version = get_bloginfo( 'version' );
	if ( $src && false !== strpos( $src, 'ver=' . $version ) ) {
		$src = str_replace( 'ver=' . $version, 'ver=' . substr( md5( $version . wp_salt( 'nonce' ) ), 0, 8 ), $src );
	}
	return $src;
}
add_filter( 'style_loader_src', 'ebookstore_sec_hide_version_in_src', 20 );
add_filter( 'script_loader_src', 'ebookstore_sec_hide_version_in_src', 20 );

/* ---------- Security headers ---------- */

/**
 * Send the security headers once per request.
 */
function ebookstore_sec_send_headers() {
	static $sent = false;
	if ( $sent || headers_sent() ) {
		return;
	}
	$sent = true;
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	if ( ebookstore_sec_settings()['hsts'] && is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
}
add_action( 'send_headers', 'ebookstore_sec_send_headers' );
add_action( 'admin_init', 'ebookstore_sec_send_headers' );
add_action( 'login_init', 'ebookstore_sec_send_headers' );
add_action( 'rest_api_init', 'ebookstore_sec_send_headers' );

/* ---------- Production cookies ---------- */

add_filter(
	'secure_logged_in_cookie',
	static function ( $secure ) {
		return ( ebookstore_sec_settings()['production'] && is_ssl() ) ? true : $secure;
	}
);
