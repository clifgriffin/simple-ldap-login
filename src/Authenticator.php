<?php
/**
 * WordPress account authentication and creation.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use WP_Error;
use WP_User;

/** Keeps WordPress security filters in the login path. */
final class Authenticator {

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $settings;
	/**
	 * Directory client.
	 *
	 * @var Ldap_Client
	 */
	private Ldap_Client $client;
	/**
	 * LDAP denial for the current attempt.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $denial = null;

	/**
	 * Set the login dependencies.
	 *
	 * @param Settings    $settings Plugin settings.
	 * @param Ldap_Client $client   Directory client.
	 */
	public function __construct( Settings $settings, Ldap_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/** Register password login hooks. */
	public function register(): void {
		if ( defined( 'SLL_DISABLE_LDAP' ) && SLL_DISABLE_LDAP ) {
			return;
		}
		if ( Settings::is_true( $this->settings->get( 'enabled' ) ) ) {
			add_filter( 'authenticate', array( $this, 'authenticate' ), 10, 3 );
			add_filter( 'authenticate', array( $this, 'enforce_access' ), 99 );
		}
	}

	/**
	 * Check LDAP before the core password handlers run.
	 *
	 * @param WP_User|WP_Error|null $user     Previous result.
	 * @param string                $username Login name.
	 * @param string                $password Exact password.
	 * @return WP_User|WP_Error|null Login result.
	 */
	public function authenticate( $user, string $username, string $password ) {
		$this->denial = null;
		if ( is_wp_error( $user ) ) {
			$this->denial = $user;
			return $user;
		}
		if ( $user instanceof WP_User || '' === $username || '' === $password ) {
			return $user;
		}
		if ( ! Settings::is_true( $this->settings->get( 'enabled' ) ) ) {
			return $user;
		}
		$local = get_user_by( 'login', $username );
		if ( ! $local && is_email( $username ) ) {
			$local = get_user_by( 'email', $username );
		}
		if ( $local && $this->settings->is_recovery_account( $local ) ) {
			// Core checks the local password and keeps user security filters active.
			return $user;
		}
		// Keep the old filter only for the legacy administrator exception.
		$recovery  = 'legacy' === $this->settings->recovery_mode() && (bool) apply_filters( 'sll_force_ldap', $local && ( user_can( $local, 'update_core' ) || is_super_admin( $local->ID ) ) );
		$exclusive = (bool) apply_filters( 'sll_remove_default_authentication_hook', Settings::is_true( $this->settings->get( 'high_security' ) ) && ! $recovery );
		$entry     = $this->client->authenticate( $username, $password );
		if ( is_wp_error( $entry ) ) {
			do_action( 'sll_auth_failure' );
			if ( $exclusive || ( ! $recovery && 'sll_groups' === $entry->get_error_code() ) ) {
				$this->denial = $entry;
			}
			return $entry;
		}
		$result = $this->authenticate_entry( $username, $entry, $password );
		if ( is_wp_error( $result ) ) {
			$this->denial = $result;
		}
		return $result;
	}

	/**
	 * Complete login after a directory module verifies the entry.
	 *
	 * @param string $username Verified login name.
	 * @param array  $entry Verified directory entry.
	 * @param string $password Password or proof for WordPress user checks.
	 * @return WP_User|WP_Error WordPress account or error.
	 */
	public function authenticate_entry( string $username, array $entry, string $password ) {
		$result = $this->provision( $username, $entry );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		// Core skips this check when an earlier handler supplies WP_User.
		$result = apply_filters( 'wp_authenticate_user', $result, $password );
		if ( $result instanceof WP_User ) {
			do_action( 'sll_auth_success', $result );
		}
		return $result;
	}

	/**
	 * Stop core username, email, or application password fallback after denial.
	 *
	 * @param mixed $user Previous login result.
	 * @return mixed Final access result.
	 */
	public function enforce_access( $user ) {
		return $this->denial ?? $user;
	}

	/**
	 * Create a WordPress account only after LDAP and group checks pass.
	 *
	 * @param string $username Login name.
	 * @param array  $entry    Directory entry.
	 * @param bool   $create Allow a scheduled import to create an account.
	 * @return WP_User|WP_Error WordPress account or error.
	 */
	public function provision( string $username, array $entry, bool $create = false ) {
		$user = get_user_by( 'login', $username );
		if ( ! $user && 'ad' === $this->settings->get( 'directory' ) && ! empty( $entry['samaccountname'][0] ) ) {
			// Use the verified account name when a UPN or DOMAIN\name was entered.
			$username = (string) $entry['samaccountname'][0];
			$user     = get_user_by( 'login', $username );
		}
		if ( ! $user ) {
			if ( ! $create && ! Settings::is_true( $this->settings->get( 'create_users' ) ) ) {
				return new WP_Error( 'sll_account', __( 'Your LDAP login is valid, but you need a WordPress account. Contact your site administrator.', 'simple-ldap-login' ) );
			}
			if ( ! validate_username( $username ) || sanitize_user( $username, true ) !== $username ) {
				return new WP_Error( 'sll_username', __( 'Your LDAP username is not a valid WordPress username. Contact your site administrator.', 'simple-ldap-login' ) );
			}
			$role = (string) $this->settings->get( 'role' );
			if ( ! get_role( $role ) ) {
				return new WP_Error( 'sll_role', __( 'The default WordPress role is not available. Contact your site administrator.', 'simple-ldap-login' ) );
			}
			$data = array_merge(
				$this->profile_data( $entry ),
				array(
					'user_login' => $username,
					'user_pass'  => wp_generate_password( 64, true, true ),
					'role'       => $role,
				)
			);
			if ( '' === $data['display_name'] ) {
				$data['display_name'] = $username;
			}
			$data = apply_filters( 'sll_user_data', $data );
			$id   = wp_insert_user( $data );
			if ( is_wp_error( $id ) ) {
				return new WP_Error( 'sll_account', __( 'Could not create your WordPress account. Contact your site administrator.', 'simple-ldap-login' ) );
			}
			$user = get_user_by( 'id', $id );
		}
		if ( $this->settings->is_recovery_account( $user ) ) {
			return new WP_Error( 'sll_local_recovery', __( 'Use your WordPress password for this recovery account.', 'simple-ldap-login' ) );
		}
		/**
		 * Update data after the directory entry resolves to a WordPress account.
		 *
		 * @param WP_User $user WordPress account.
		 * @param array $entry Verified directory entry.
		 */
		do_action( 'sll_account_loaded', $user, $entry );
		return $user;
	}

	/**
	 * Build standard profile values for account creation or updates.
	 *
	 * @param array $entry Verified directory entry.
	 * @return array Safe WordPress profile fields.
	 */
	public function profile_data( array $entry ): array {
		$first = sanitize_text_field( $this->attribute( $entry, 'user_first_name_attribute' ) );
		$last  = sanitize_text_field( $this->attribute( $entry, 'user_last_name_attribute' ) );
		return array(
			'user_email'   => sanitize_email( $this->attribute( $entry, 'user_email_attribute' ) ),
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => trim( $first . ' ' . $last ),
			'user_url'     => esc_url_raw( $this->attribute( $entry, 'user_url_attribute' ), array( 'http', 'https' ) ),
		);
	}

	/**
	 * Read the first value of a configured LDAP attribute.
	 *
	 * @param array  $entry Directory entry.
	 * @param string $key   Attribute setting.
	 * @return string First value or empty text.
	 */
	private function attribute( array $entry, string $key ): string {
		return (string) ( $entry[ strtolower( (string) $this->settings->get( $key ) ) ][0] ?? '' );
	}
}
