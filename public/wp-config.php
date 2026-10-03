<?php
/**
 * WordPress configuration — every value comes from ../.env
 *
 * Layout (same locally and on Hostinger):
 *   <project or home dir>/.env
 *   <project or home dir>/config/env-loader.php
 *   <project or home dir>/public(_html)/wp-config.php   <- this file
 *
 * @package EbookStore
 */

$ebookstore_root = dirname( __DIR__ );

require_once $ebookstore_root . '/config/env-loader.php';

if ( ! ebookstore_load_env( $ebookstore_root . '/.env' ) && false === getenv( 'DB_NAME' ) ) {
	header( 'HTTP/1.1 503 Service Unavailable' );
	exit( 'Configuration file missing.' );
}

// ---------- Database ----------
define( 'DB_NAME', env( 'DB_NAME', '' ) );
define( 'DB_USER', env( 'DB_USER', '' ) );
define( 'DB_PASSWORD', env( 'DB_PASSWORD', '' ) );
define( 'DB_HOST', env( 'DB_HOST', '127.0.0.1' ) );
define( 'DB_CHARSET', env( 'DB_CHARSET', 'utf8mb4' ) );
define( 'DB_COLLATE', env( 'DB_COLLATE', 'utf8mb4_unicode_ci' ) );

$table_prefix = env( 'DB_PREFIX', 'wp_' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

// ---------- Security keys and salts ----------
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $ebookstore_key ) {
	define( $ebookstore_key, (string) env( $ebookstore_key, '' ) );
}

// ---------- URLs and environment ----------
define( 'WP_HOME', env( 'WP_HOME' ) );
define( 'WP_SITEURL', env( 'WP_SITEURL', env( 'WP_HOME' ) ) );
define( 'WP_ENVIRONMENT_TYPE', 'production' === env( 'APP_ENV' ) ? 'production' : 'local' );

// ---------- Debugging ----------
define( 'WP_DEBUG', (bool) env( 'WP_DEBUG', false ) );
// The log file lives OUTSIDE the web root (../logs/debug.log), so it can never be downloaded.
if ( env( 'WP_DEBUG_LOG', false ) ) {
	if ( ! is_dir( $ebookstore_root . '/logs' ) ) {
		@mkdir( $ebookstore_root . '/logs', 0750 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}
	define( 'WP_DEBUG_LOG', $ebookstore_root . '/logs/debug.log' );
} else {
	define( 'WP_DEBUG_LOG', false );
}
define( 'WP_DEBUG_DISPLAY', (bool) env( 'WP_DEBUG_DISPLAY', false ) );
define( 'SCRIPT_DEBUG', false );

// ---------- Security ----------
define( 'DISALLOW_FILE_EDIT', (bool) env( 'DISALLOW_FILE_EDIT', true ) );
if ( 'production' === WP_ENVIRONMENT_TYPE ) {
	// Live site: admin and login only over HTTPS.
	define( 'FORCE_SSL_ADMIN', true );
	// Live site: refuse to run with missing security keys (they protect logins and cookies).
	foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $ebookstore_key ) {
		if ( strlen( constant( $ebookstore_key ) ) < 32 ) {
			header( 'HTTP/1.1 503 Service Unavailable' );
			exit( 'Site configuration error. Please contact the site owner.' );
		}
	}
}

// ---------- Email: WP Mail SMTP constants (plugin installed in Phase 6) ----------
define( 'WPMS_ON', true );
define( 'WPMS_MAILER', 'smtp' );
define( 'WPMS_SMTP_HOST', env( 'SMTP_HOST', '' ) );
define( 'WPMS_SMTP_PORT', (int) env( 'SMTP_PORT', 587 ) );
define( 'WPMS_SSL', 'none' === env( 'SMTP_ENCRYPTION' ) ? '' : (string) env( 'SMTP_ENCRYPTION', '' ) ); // '' | 'ssl' | 'tls'
define( 'WPMS_SMTP_AUTH', (bool) env( 'SMTP_AUTH', true ) );
define( 'WPMS_SMTP_AUTOTLS', true );
define( 'WPMS_SMTP_USER', (string) env( 'SMTP_USER', '' ) );
define( 'WPMS_SMTP_PASS', (string) env( 'SMTP_PASSWORD', '' ) );
define( 'WPMS_MAIL_FROM', (string) env( 'MAIL_FROM_EMAIL', '' ) );
define( 'WPMS_MAIL_FROM_FORCE', true );
define( 'WPMS_MAIL_FROM_NAME', (string) env( 'MAIL_FROM_NAME', '' ) );
define( 'WPMS_MAIL_FROM_NAME_FORCE', true );
define( 'WPMS_SET_RETURN_PATH', true );

unset( $ebookstore_root, $ebookstore_key );

/* That's all, stop editing! Happy publishing. */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once ABSPATH . 'wp-settings.php';
