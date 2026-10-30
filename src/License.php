<?php
/**
 * Store the license status outside the login request.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use WP_Error;

/** Only a verified active license enables Pro features. */
final class License {

	/** PaidCommunities product ID. */
	public const PRODUCT = 'prd_9QyrKxQpvZGrcCW8';
	/** Separate from the LDAP settings and their public compatibility API. */
	public const OPTION = 'sll_pro_license';
	/** Background check hook. */
	public const CHECK = 'sll_pro_license_check';

	/**
	 * Settings scope.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Set the license scope.
	 *
	 * @param Settings $settings Shared settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/** Register checks without making an HTTP request. */
	public function register(): void {
		add_action( self::CHECK, array( $this, 'refresh' ) );
		if ( ( ! $this->settings->is_network() || is_main_site() ) && ! empty( $this->data()['domain_id'] ) && ! wp_next_scheduled( self::CHECK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::CHECK );
		}
	}

	/**
	 * Stop checks when the plugin is deactivated. Keep the activation record.
	 *
	 * @param bool $network Whether deactivation applies to the network.
	 */
	public static function stop_checks( bool $network = false ): void {
		if ( $network && is_multisite() ) {
			switch_to_blog( get_main_site_id() );
		}
		wp_clear_scheduled_hook( self::CHECK );
		if ( $network && is_multisite() ) {
			restore_current_blog();
		}
	}

	/**
	 * Read local state only. This method is safe to use during login.
	 *
	 * @return bool Whether Pro features can run.
	 */
	public function is_active(): bool {
		$data = $this->data();
		return 'active' === ( $data['status'] ?? '' ) && ! empty( $data['secret'] ) && ! empty( $data['domain_id'] ) && $this->site_url() === ( $data['site_url'] ?? '' );
	}

	/**
	 * Read the record in the same scope as the LDAP settings.
	 *
	 * @return array License record.
	 */
	public function data(): array {
		$data = $this->settings->is_network() ? get_site_option( self::OPTION, array() ) : get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Activate only after an administrator submits a key.
	 *
	 * @param string $key License key. Never store the original key.
	 * @return true|WP_Error Activation result.
	 */
	public function activate( string $key ) {
		if ( '' === $key || strlen( $key ) > 512 || preg_match( '/[\x00-\x20\x7f]/', $key ) ) {
			return $this->error();
		}
		try {
			$client = new PaidCommunities_Client();
			$domain = $client->domains->register(
				array(
					'key'        => $key,
					'domain'     => wp_parse_url( $this->site_url(), PHP_URL_HOST ),
					'version'    => SLL_VERSION,
					'product_id' => self::PRODUCT,
				)
			);
			if ( ! is_string( $domain->id ) || ! preg_match( '/^dom_[a-zA-Z0-9]+$/D', $domain->id ) || ! is_string( $domain->secret ) || '' === $domain->secret ) {
				return $this->error();
			}
			// Registration alone does not establish the current license status.
			$client->setSecret( $domain->secret );
			$status = $this->status( $client );
			$secret = Secrets::encrypt( $domain->secret );
			if ( is_wp_error( $secret ) ) {
				return $this->error();
			}
			$saved = $this->save(
				array(
					'domain_id'  => $domain->id,
					'secret'     => $secret,
					'site_url'   => $this->site_url(),
					'status'     => $status,
					'checked_at' => time(),
					'error'      => false,
				)
			);
			if ( ! $saved ) {
				return $this->error();
			}
			$this->register();
			return true;
		} catch ( \Throwable $error ) {
			return $this->error();
		}
	}

	/**
	 * Keep the last verified status when the service cannot be reached.
	 *
	 * @return true|WP_Error Check result.
	 */
	public function refresh() {
		$data = $this->data();
		if ( empty( $data['domain_id'] ) || $this->site_url() !== ( $data['site_url'] ?? '' ) ) {
			return $this->error();
		}
		try {
			$secret = Secrets::decrypt( $data['secret'] ?? '' );
			if ( is_wp_error( $secret ) || '' === $secret ) {
				return $this->error();
			}
			$data['status']     = $this->status( new PaidCommunities_Client( PaidCommunities_Client::PRODUCTION, $secret ) );
			$data['checked_at'] = time();
			$data['error']      = false;
			return $this->save( $data ) ? true : $this->error();
		} catch ( \Throwable $error ) {
			$data['error'] = true;
			$this->save( $data );
			return $this->error();
		}
	}

	/**
	 * Remove this site's activation only after the service accepts it.
	 *
	 * @return true|WP_Error Deactivation result.
	 */
	public function deactivate() {
		$data = $this->data();
		try {
			$secret = Secrets::decrypt( $data['secret'] ?? '' );
			if ( is_wp_error( $secret ) || '' === $secret || empty( $data['domain_id'] ) || $this->site_url() !== ( $data['site_url'] ?? '' ) ) {
				return $this->error();
			}
			$client = new PaidCommunities_Client( PaidCommunities_Client::PRODUCTION, $secret );
			$client->domains->delete( $data['domain_id'] );
			if ( ! $this->save( array() ) ) {
				return $this->error();
			}
			wp_clear_scheduled_hook( self::CHECK );
			return true;
		} catch ( \Throwable $error ) {
			return $this->error();
		}
	}

	/**
	 * Ask the SDK update endpoint for its authenticated license status.
	 *
	 * @param PaidCommunities_Client $client Authenticated SDK client.
	 * @throws \RuntimeException When no valid status is returned.
	 * @return string License status.
	 */
	private function status( PaidCommunities_Client $client ): string {
		$response = $client->plugins->updateCheck(
			array(
				'version'    => SLL_VERSION,
				'product_id' => self::PRODUCT,
			)
		);
		$status   = $response->license->status ?? null;
		$products = $response->license->product_ids ?? array();
		$products = is_array( $products ) ? $products : array();
		if ( isset( $response->license->product_id ) ) {
			$products[] = $response->license->product_id;
		}
		if ( $products && ! in_array( self::PRODUCT, $products, true ) ) {
			throw new \RuntimeException( 'The license is for another product.' );
		}
		if ( ! in_array( $status, array( 'active', 'inactive', 'expired' ), true ) ) {
			throw new \RuntimeException( 'The license status is invalid.' );
		}
		return $status;
	}

	/**
	 * Bind stored activation data to the configured site, not the Host header.
	 *
	 * @return string Site address.
	 */
	private function site_url(): string {
		return untrailingslashit( $this->settings->is_network() ? network_home_url() : home_url() );
	}

	/**
	 * Save without autoloading the activation secret.
	 *
	 * @param array $data License record.
	 * @return bool Whether the record was saved.
	 */
	private function save( array $data ): bool {
		$this->settings->is_network() ? update_site_option( self::OPTION, $data ) : update_option( self::OPTION, $data, false );
		return $this->data() === $data;
	}

	/**
	 * Return a safe error without remote content or credentials.
	 *
	 * @return WP_Error License error.
	 */
	private function error(): WP_Error {
		return new WP_Error( 'sll_license_error', __( 'Could not verify the license. Check your key and connection, then try again.', 'simple-ldap-login' ) );
	}
}
