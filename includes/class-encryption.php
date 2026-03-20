<?php
/**
 * Encryption helper for storing sensitive data (tokens, secrets).
 * Uses AES-256-GCM (authenticated encryption).
 */

defined( 'ABSPATH' ) || exit;

class WPTS_Encryption {

	/**
	 * Encrypt a value using AES-256-GCM.
	 *
	 * @param string $value Plain text value.
	 * @return string Base64-encoded encrypted string (iv + tag + ciphertext).
	 */
	public static function encrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$key    = self::get_key();
		$iv     = random_bytes( openssl_cipher_iv_length( 'aes-256-gcm' ) );
		$tag    = '';
		$cipher = openssl_encrypt( $value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $cipher ) {
			return '';
		}

		// Pack as: IV (12 bytes) + Auth Tag (16 bytes) + Ciphertext.
		return base64_encode( $iv . $tag . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value encrypted with AES-256-GCM.
	 *
	 * @param string $value Base64-encoded encrypted string.
	 * @return string Decrypted plain text, or empty string on failure.
	 */
	public static function decrypt( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$raw = base64_decode( $value, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $raw ) {
			return '';
		}

		$iv_len = openssl_cipher_iv_length( 'aes-256-gcm' );
		$iv     = substr( $raw, 0, $iv_len );
		$tag    = substr( $raw, $iv_len, 16 );
		$cipher = substr( $raw, $iv_len + 16 );

		if ( empty( $iv ) || empty( $tag ) || empty( $cipher ) ) {
			return '';
		}

		$key   = self::get_key();
		$plain = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		return ( false === $plain ) ? '' : $plain;
	}

	/**
	 * Derive encryption key from WordPress salts.
	 *
	 * @return string 32-byte key.
	 */
	private static function get_key() {
		if ( ! defined( 'AUTH_SALT' ) || strlen( AUTH_SALT ) < 16 ) {
			wp_die(
				esc_html__( 'WP to Social requires AUTH_SALT to be defined in wp-config.php with at least 16 characters.', 'wp-to-social' ),
				esc_html__( 'Configuration Error', 'wp-to-social' ),
				array( 'response' => 500 )
			);
		}

		return hash( 'sha256', 'wpts-v1:' . AUTH_SALT, true );
	}
}
