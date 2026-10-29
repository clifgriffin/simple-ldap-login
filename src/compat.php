<?php
/**
 * Public settings API from version 1.
 *
 * @package SimpleLdapLogin
 */

use SimpleLdapLogin\Authenticator;
use SimpleLdapLogin\Settings;

defined( 'ABSPATH' ) || exit;

/** Retains the public settings access used by existing integrations. */
class SimpleLDAPLogin {

	/**
	 * Current instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;
	/**
	 * Legacy hook prefix.
	 *
	 * @var string
	 */
	public string $prefix = 'sll_';
	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $store;
	/**
	 * Login handler.
	 *
	 * @var Authenticator|null
	 */
	private ?Authenticator $auth;

	/**
	 * Set the compatibility services.
	 *
	 * @param Settings|null      $settings Settings source.
	 * @param Authenticator|null $auth     Login handler.
	 */
	public function __construct( ?Settings $settings = null, ?Authenticator $auth = null ) {
		$this->store    = $settings ?? new Settings();
		$this->auth     = $auth;
		self::$instance = $this;
	}

	/**
	 * Get the compatibility instance.
	 *
	 * @return self Instance.
	 */
	public static function getInstance(): self { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Public legacy API.
		return self::$instance ?? new self();
	}

	/**
	 * Return the original option name.
	 *
	 * @return string Option name.
	 */
	public static function get_field_settings_s(): string {
		return 'sll_settings';
	}

	/**
	 * Read a filtered setting.
	 *
	 * @param string|false $option Setting key.
	 * @return mixed Setting value.
	 */
	public function get_setting( $option = false ) {
		return false === $option ? false : $this->store->get( $option );
	}

	/**
	 * Validate and save one setting.
	 *
	 * @param string|false $option Setting key.
	 * @param mixed        $value  New value.
	 * @return true|WP_Error|false Save result.
	 */
	public function set_setting( $option, $value ) {
		return false === $option ? false : $this->store->save( array( $option => $value ) );
	}

	/**
	 * Add a setting if it has no value.
	 *
	 * @param string $option Setting key.
	 * @param mixed  $value  Default value.
	 * @return mixed Save result.
	 */
	public function add_setting( $option, $value ) {
		return array_key_exists( $option, $this->store->all() ) ? false : $this->set_setting( $option, $value );
	}

	/**
	 * Read the settings array.
	 *
	 * @return array Settings.
	 */
	public function get_settings_obj(): array {
		return $this->store->all();
	}

	/**
	 * Save settings through the current validation rules.
	 *
	 * @param array $settings Settings to save.
	 * @return true|WP_Error Result.
	 */
	public function set_settings_obj( array $settings ) {
		$current = $this->store->all();
		$allowed = Settings::defaults();
		unset( $settings['version'], $settings['schema_version'] );
		foreach ( $settings as $key => $value ) {
			if ( ! array_key_exists( $key, $allowed ) || $value === $current[ $key ] ) {
				unset( $settings[ $key ] );
			}
		}
		return $this->store->save( $settings );
	}

	/**
	 * Return the settings scope.
	 *
	 * @return bool Whether the plugin is network active.
	 */
	public function is_network_version(): bool {
		return $this->store->is_network();
	}

	/**
	 * Use the current login flow.
	 *
	 * @param mixed  $user     Previous result.
	 * @param string $username Login name.
	 * @param string $password Password.
	 * @return mixed Login result.
	 */
	public function authenticate( $user, $username, $password ) {
		return $this->auth ? $this->auth->authenticate( $user, $username, $password ) : $user;
	}
}

// phpcs:disable Universal.Files.SeparateFunctionsFromOO -- Retain the small public API in one compatibility file.
if ( ! function_exists( 'str_true' ) ) {
	/**
	 * Read legacy boolean strings.
	 *
	 * @param mixed $value Value to test.
	 * @param array $true_values Legacy accepted values.
	 * @return bool Whether the value is true.
	 */
	function str_true( $value, $true_values = array( 'yes', 'y', 'true', '1', 'on', 'open' ) ): bool {
		return is_bool( $value ) ? $value : ( is_scalar( $value ) && in_array( strtolower( (string) $value ), $true_values, true ) );
	}
}
