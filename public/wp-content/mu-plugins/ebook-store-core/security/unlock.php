<?php
/**
 * Security: emergency unlock by email.
 *
 * The blocked page offers "Are you the site owner? Send an unlock link."
 * If the email belongs to a protected (admin) account, a one-time link is
 * sent: random token stored only as a hash, valid SECURITY_UNLOCK_LINK_MINUTES,
 * single use, unblocks only the IP that asked for it. The reply is always the
 * same, so the form never reveals which emails are admin accounts.
 * Max 3 requests per IP per hour; every request and use is logged.
 *
 * @package EbookStore
 */

defined( 'ABSPATH' ) || exit;

const EBOOKSTORE_SEC_UNLOCK_MAX_PER_HOUR = 3;

/**
 * The unlock request form (escaped HTML) shown on the blocked page.
 *
 * @return string
 */
function ebookstore_sec_unlock_form_html() {
	ob_start();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="ebookstore_unlock_request">
		<?php wp_nonce_field( 'ebookstore_unlock_request', '_ebookstore_unlock_nonce' ); ?>
		<label for="ebookstore-unlock-email"><?php esc_html_e( 'Are you the site owner? Send an unlock link.', 'ebook-store' ); ?></label>
		<input type="email" id="ebookstore-unlock-email" name="email" required maxlength="100" autocomplete="email" placeholder="<?php esc_attr_e( 'Your admin email address', 'ebook-store' ); ?>">
		<button type="submit"><?php esc_html_e( 'Send unlock link', 'ebook-store' ); ?></button>
	</form>
	<?php
	return (string) ob_get_clean();
}

/**
 * Handle an unlock request (works logged in or not; admin-post.php is never blocked).
 */
function ebookstore_sec_handle_unlock_request() {
	$neutral = __( 'If this email belongs to an administrator, an unlock link has been sent. Please check your inbox.', 'ebook-store' );
	$ip      = ebookstore_sec_ip();

	nocache_headers();
	header( 'Cache-Control: no-store' );

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- verified by wp_verify_nonce.
	if ( ! isset( $_POST['_ebookstore_unlock_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_ebookstore_unlock_nonce'] ), 'ebookstore_unlock_request' ) ) {
		status_header( 400 );
		ebookstore_sec_render_page( __( 'Please try again', 'ebook-store' ), '<p>' . esc_html__( 'The form expired. Please go back, reload the page and try again.', 'ebook-store' ) . '</p>' );
		exit;
	}

	// Rate limit: max 3 requests per IP per hour.
	$key   = 'ebookstore_unlock_rq_' . md5( $ip );
	$count = (int) get_transient( $key );
	if ( $count >= EBOOKSTORE_SEC_UNLOCK_MAX_PER_HOUR ) {
		ebookstore_sec_log( 'unlock_request', $ip, 'unlock', '', 'refused: too many requests' );
		status_header( 429 );
		ebookstore_sec_render_page( __( 'Please try again later', 'ebook-store' ), '<p>' . esc_html__( 'Too many unlock requests from your network. Please try again in an hour.', 'ebook-store' ) . '</p>' );
		exit;
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );

	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
	$email = mb_substr( $email, 0, 100 );
	$user  = is_email( $email ) ? get_user_by( 'email', $email ) : false;

	if ( $user && ebookstore_sec_is_protected_user( $user ) ) {
		$s     = ebookstore_sec_settings();
		$token = wp_generate_password( 40, false, false );
		set_transient(
			'ebookstore_unlock_' . hash( 'sha256', $token ),
			array(
				'ip'      => $ip,
				'user_id' => (int) $user->ID,
			),
			$s['unlock_min'] * MINUTE_IN_SECONDS
		);
		$link = add_query_arg( 'ebookstore-unlock', rawurlencode( $token ), home_url( '/' ) );
		/* translators: 1: site name, 2: IP, 3: link, 4: minutes */
		$body = sprintf( __( "Hello,\n\nSomeone asked to unblock the admin login of %1\$s for the network %2\$s.\n\nIf this was you, open this link (valid for %4\$d minutes, works once):\n%3\$s\n\nIf it was not you, ignore this email. Nothing changes.", 'ebook-store' ), get_bloginfo( 'name' ), $ip, $link, $s['unlock_min'] );
		/* translators: %s: site name */
		wp_mail( $user->user_email, sprintf( __( '[%s] Admin unlock link', 'ebook-store' ), get_bloginfo( 'name' ) ), $body );
		ebookstore_sec_log( 'unlock_request', $ip, 'unlock', $user->user_login, 'link sent' );
	} else {
		ebookstore_sec_log( 'unlock_request', $ip, 'unlock', $email, 'no admin account (no email sent)' );
	}

	ebookstore_sec_render_page( __( 'Check your email', 'ebook-store' ), '<p>' . esc_html( $neutral ) . '</p>' );
	exit;
}
add_action( 'admin_post_nopriv_ebookstore_unlock_request', 'ebookstore_sec_handle_unlock_request' );
add_action( 'admin_post_ebookstore_unlock_request', 'ebookstore_sec_handle_unlock_request' );

// The unlock link: /?ebookstore-unlock=TOKEN (single use).
add_action(
	'init',
	static function () {
		if ( empty( $_GET['ebookstore-unlock'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token itself is the secret.
			return;
		}
		$token = preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_GET['ebookstore-unlock'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- reduced to [A-Za-z0-9].
		$key   = 'ebookstore_unlock_' . hash( 'sha256', $token );
		$data  = '' !== $token ? get_transient( $key ) : false;

		nocache_headers();
		header( 'Cache-Control: no-store' );

		if ( ! is_array( $data ) || empty( $data['ip'] ) ) {
			ebookstore_sec_log( 'unlock_used', ebookstore_sec_ip(), 'unlock', '', 'invalid or expired link' );
			status_header( 410 );
			ebookstore_sec_render_page( __( 'Link not valid', 'ebook-store' ), '<p>' . esc_html__( 'This unlock link is invalid, was already used or has expired. You can ask for a new one on the blocked page.', 'ebook-store' ) . '</p>' );
			exit;
		}
		delete_transient( $key ); // Single use.
		ebookstore_sec_unblock_ip( $data['ip'] );
		$user = get_userdata( (int) $data['user_id'] );
		ebookstore_sec_log( 'unlock_used', $data['ip'], 'unlock', $user ? $user->user_login : '', 'IP unblocked by email link' );

		ebookstore_sec_render_page(
			__( 'Unblocked', 'ebook-store' ),
			/* translators: %s: IP address */
			'<p>' . esc_html( sprintf( __( 'The network %s is unblocked. You can log in again now.', 'ebook-store' ), $data['ip'] ) ) . '</p><p><a href="' . esc_url( wp_login_url() ) . '">' . esc_html__( 'Go to the login page', 'ebook-store' ) . '</a></p>'
		);
		exit;
	},
	1
);
