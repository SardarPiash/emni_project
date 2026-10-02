<?php
/**
 * Tiny .env loader — no Composer needed.
 *
 * Supports: comments (#), blank lines, optional "export " prefix,
 * single/double-quoted values, inline comments after unquoted values
 * (" # ..."), empty values (also "KEY=  # comment"), and Windows (CRLF) or Linux (LF) line endings.
 *
 * Variables already set by the server are never overwritten.
 *
 * @package EbookStore
 */

if ( ! function_exists( 'ebookstore_load_env' ) ) {
	/**
	 * Parse a .env file and register its variables.
	 *
	 * @param string $file Absolute path to the .env file.
	 * @return bool True if the file was read.
	 */
	function ebookstore_load_env( $file ) {
		if ( ! is_readable( $file ) ) {
			return false;
		}

		$lines = file( $file, FILE_IGNORE_NEW_LINES );
		if ( false === $lines ) {
			return false;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line ); // Also strips a trailing "\r" from CRLF files.

			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}

			if ( 0 === strpos( $line, 'export ' ) ) {
				$line = ltrim( substr( $line, 7 ) );
			}

			$pos = strpos( $line, '=' );
			if ( false === $pos ) {
				continue;
			}

			$key = trim( substr( $line, 0, $pos ) );
			if ( ! preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
				continue;
			}

			$value = ltrim( substr( $line, $pos + 1 ) );

			if ( '' !== $value && '"' === $value[0] && preg_match( '/^"((?:[^"\\\\]|\\\\.)*)"/', $value, $m ) ) {
				$value = strtr( $m[1], array( '\\n' => "\n", '\\"' => '"', '\\\\' => '\\' ) );
			} elseif ( '' !== $value && "'" === $value[0] && preg_match( "/^'([^']*)'/", $value, $m ) ) {
				$value = $m[1];
			} else {
				$value = rtrim( preg_replace( '/(^|\s+)#.*$/', '', $value ) );
			}

			// Never overwrite values provided by the server environment.
			if ( false !== getenv( $key ) || isset( $_ENV[ $key ] ) ) {
				continue;
			}

			if ( function_exists( 'putenv' ) ) {
				putenv( $key . '=' . $value );
			}
			$_ENV[ $key ] = $value;
		}

		return true;
	}
}

if ( ! function_exists( 'env' ) ) {
	/**
	 * Read an environment variable.
	 *
	 * "true"/"false"/"null" (any case) become true/false/null.
	 * An empty value is returned as an empty string; the default is
	 * used only when the key does not exist at all.
	 *
	 * @param string $key     Variable name.
	 * @param mixed  $default Value if the key is not set.
	 * @return mixed
	 */
	function env( $key, $default = null ) {
		if ( array_key_exists( $key, $_ENV ) ) {
			$value = $_ENV[ $key ];
		} else {
			$value = getenv( $key );
			if ( false === $value ) {
				return $default;
			}
		}

		switch ( strtolower( $value ) ) {
			case 'true':
				return true;
			case 'false':
				return false;
			case 'null':
				return null;
		}

		return $value;
	}
}
