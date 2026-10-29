<?php
/**
 * Encrypt service account passwords.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

/** Encrypt stored secrets with authenticated encryption. */
final class Secrets {

	/**
	 * Encrypt a password. Keep the encryption key outside the database.
	 *
	 * @param string $value Password.
	 * @return string|\WP_Error
	 */
	public static function encrypt( string $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return new \WP_Error( 'sll_encryption_unavailable', __( 'PHP OpenSSL is required to save the service account password.', 'simple-ldap-login' ) );
		}
		try {
			$iv         = random_bytes( 12 );
			$tag        = '';
			$ciphertext = openssl_encrypt( $value, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, 'simple-ldap-login:v2' );
			if ( false === $ciphertext ) {
				return self::error();
			}
			return 'v2:' . base64_encode( $iv . $tag . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Store binary encryption data.
		} catch ( \Throwable $error ) {
			return self::error();
		}
	}

	/**
	 * Decrypt current and legacy stored passwords.
	 *
	 * @param string $value Stored password.
	 * @return string|\WP_Error
	 */
	public static function decrypt( string $value ) {
		if ( '' === $value ) {
			return '';
		}
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return self::error();
		}
		try {
			if ( 0 === strpos( $value, 'v2:' ) ) {
				$data = base64_decode( substr( $value, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Read binary encryption data.
				if ( false === $data || strlen( $data ) < 29 ) {
					return self::error();
				}
				$password = openssl_decrypt( substr( $data, 28 ), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr( $data, 0, 12 ), substr( $data, 12, 16 ), 'simple-ldap-login:v2' );
			} else {
				$parts = explode( ';', $value );
				if ( 2 !== count( $parts ) || ! preg_match( '/^[a-f0-9]{32}$/i', $parts[1] ) || false === base64_decode( $parts[0], true ) ) { // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Validate the legacy format.
					return self::error();
				}
				$password = openssl_decrypt( $parts[0], 'AES-256-CBC', gethostname(), 0, hex2bin( $parts[1] ) );
			}
			return false === $password ? self::error() : $password;
		} catch ( \Throwable $error ) {
			return self::error();
		}
	}

	/**
	 * Derive the encryption key.
	 *
	 * @throws \RuntimeException If the key is empty.
	 * @return string
	 */
	private static function key(): string {
		$secret = defined( 'SLL_ENCRYPTION_KEY' ) ? SLL_ENCRYPTION_KEY : wp_salt( 'auth' );
		if ( ! is_string( $secret ) || '' === $secret ) {
			throw new \RuntimeException( 'The encryption key is empty.' );
		}
		return hash( 'sha256', 'simple-ldap-login:' . $secret, true );
	}

	/**
	 * Return a safe error without secret data.
	 *
	 * @return \WP_Error
	 */
	private static function error(): \WP_Error {
		return new \WP_Error( 'sll_secret_error', __( 'The service account password cannot be read or saved. Enter it again. Check PHP OpenSSL and the encryption key.', 'simple-ldap-login' ) );
	}
}
