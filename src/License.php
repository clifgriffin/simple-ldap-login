<?php
/**
 * Use the SDK license with encrypted site or network storage.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use PaidCommunities\WordPress\Cache;
use PaidCommunities\WordPress\License as Sdk_License;
use PaidCommunities\WordPress\PluginConfig;
use WP_Error;

/** Keep feature access on the SDK's saved license status. */
final class License extends Sdk_License {

	/** PaidCommunities Pro product ID. */
	public const PRODUCT = 'prd_9QyrKxQpvZGrcCW8';
	/** PaidCommunities Enterprise product ID. */
	public const ENTERPRISE_PRODUCT = 'prd_9R92jB1prxMfJR36';
	/** Annual Pro billing plan. */
	public const PRO_PLAN = 'plan_9R8xkMlq1nmCekmG';
	/** Annual Enterprise billing plan. */
	public const ENTERPRISE_PLAN = 'plan_9R937DcDDy4wMH4x';
	/** Keep the existing option and settings scope. */
	public const OPTION = 'sll_pro_license';
	/** Remove the old custom task; the SDK uses WordPress update checks. */
	public const CHECK = 'sll_pro_license_check';

	/**
	 * Settings scope.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Read the SDK license without a service request.
	 *
	 * @param Settings $settings Settings scope.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
		parent::__construct( self::OPTION );
		$this->read();
	}

	/** Remove a custom license task left by an earlier development build. */
	public function register(): void {
		if ( wp_next_scheduled( self::CHECK ) ) {
			self::stop_checks( $this->settings->is_network() );
		}
	}

	/**
	 * Clear only the old plugin task. WordPress owns its update schedule.
	 *
	 * @param bool $network Whether the plugin is network activated.
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

	/** Use the SDK status without a remote check during login. */
	public function is_active(): bool {
		$this->read();
		return $this->isActive();
	}

	/** Return the installed edition only when its SDK license is active. */
	public function plan(): string {
		if ( ! $this->is_active() ) {
			return '';
		}
		return self::ENTERPRISE_PRODUCT === $this->product() ? 'enterprise' : 'pro';
	}

	/** Require the Enterprise build and an active SDK license. */
	public function is_enterprise(): bool {
		return 'enterprise' === $this->plan();
	}

	/** Read the existing local record in the active settings scope. */
	public function data(): array {
		$data = $this->settings->is_network() ? get_site_option( self::OPTION, array() ) : get_option( self::OPTION, array() );
		return is_array( $data ) ? $data : array();
	}

	/** Load the SDK fields. A copied site or another product cannot reuse them. */
	public function read() {
		$data    = $this->data();
		$product = $data['product_id'] ?? '';
		$id      = $data['domain_id'] ?? '';
		$stored  = $data['secret'] ?? '';
		$valid   = is_string( $id ) && preg_match( '/^dom_[a-zA-Z0-9]+$/D', $id ) && is_string( $stored ) && ( $data['site_url'] ?? '' ) === $this->site_url() && ( '' === $product || $product === $this->product() );
		$secret  = $valid ? Secrets::decrypt( $stored ) : '';
		$valid   = $valid && is_string( $secret ) && '' !== $secret;
		$this->setSecret( $valid ? $secret : '' );
		$this->setDomainId( $valid ? $data['domain_id'] : '' );
		$this->setDomain( $valid ? wp_parse_url( $this->site_url(), PHP_URL_HOST ) : '' );
		$this->setLicenseKey( $valid ? ( $data['license_key'] ?? '••••••••' ) : '' );
		$this->setStatus( $valid && $product === $this->product() ? ( $data['status'] ?? self::INACTIVE ) : self::INACTIVE );
		$this->setCreatedAt( $data['created_at'] ?? '' );
		$this->setLastCheck( $data['checked_at'] ?? '' );
	}

	/**
	 * Save the SDK fields without a second service request.
	 *
	 * @throws \RuntimeException When the scoped record cannot be saved.
	 */
	public function save() {
		$id     = $this->getDomainId();
		$secret = $this->getSecret();
		if ( ! is_string( $id ) || ! preg_match( '/^dom_[a-zA-Z0-9]+$/D', $id ) || ! is_string( $secret ) || '' === $secret ) {
			throw new \RuntimeException( 'The license activation is invalid.' );
		}
		$encrypted = Secrets::encrypt( $secret );
		if ( is_wp_error( $encrypted ) ) {
			throw new \RuntimeException( 'Could not save the license.' );
		}
		$data = array(
			'domain_id'   => $id,
			'secret'      => $encrypted,
			'status'      => $this->getStatus(),
			'product_id'  => $this->product(),
			'site_url'    => $this->site_url(),
			'license_key' => $this->getLicenseKey(),
			'created_at'  => $this->getCreatedAt(),
			'checked_at'  => $this->getLastCheck(),
			'error'       => false,
		);
		$this->settings->is_network() ? update_site_option( self::OPTION, $data ) : update_option( self::OPTION, $data, false );
		if ( $this->data() !== $data ) {
			throw new \RuntimeException( 'Could not save the license.' );
		}
		$this->read();
	}

	/** Retain the SDK's deletion behavior in the correct settings scope. */
	public function delete() {
		$this->settings->is_network() ? delete_site_option( self::OPTION ) : delete_option( self::OPTION );
		self::stop_checks( $this->settings->is_network() );
		$this->read();
	}

	/**
	 * Run a manual check through the SDK's normal update controller.
	 *
	 * @return true|WP_Error SDK check result.
	 */
	public function refresh() {
		$config = $GLOBALS['sll_paidcommunities'] ?? null;
		if ( ! $config instanceof PluginConfig || ! $this->getSecret() ) {
			return $this->error();
		}
		try {
			$cache = new Cache();
			$cache->delete( $cache->generateKey( 'update_plugins', $config->getProductId(), SLL_VERSION ) );
			$result = $config->getUpdateController()->checkPluginUpdates( false, array( 'Version' => SLL_VERSION ), $config->getPluginBasename() );
			$this->read();
			return is_array( $result ) && ( $result['slug'] ?? '' ) === $this->product() ? true : $this->error();
		} catch ( \Throwable $error ) {
			return $this->error();
		}
	}

	/** Return the one product assigned to this build. */
	public function product(): string {
		if ( defined( 'SLL_EDITION' ) && 'enterprise' === SLL_EDITION ) {
			return self::ENTERPRISE_PRODUCT;
		}
		if ( defined( 'SLL_EDITION' ) && 'pro' === SLL_EDITION ) {
			return self::PRODUCT;
		}
		// Free displays retained purchase information but includes no paid features.
		return self::ENTERPRISE_PRODUCT === ( $this->data()['product_id'] ?? '' ) ? self::ENTERPRISE_PRODUCT : self::PRODUCT;
	}

	/** Bind the local record to the configured site address. */
	private function site_url(): string {
		return untrailingslashit( $this->settings->is_network() ? network_home_url() : home_url() );
	}

	/** Return a safe service error for the manual check control. */
	private function error(): WP_Error {
		return new WP_Error( 'sll_license_error', __( 'Could not verify the license. Check your key and connection, then try again.', 'simple-ldap-login' ) );
	}
}
