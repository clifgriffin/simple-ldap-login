<?php
/**
 * Read, validate, and migrate plugin settings.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

/** Keep settings in the original site or network option. */
final class Settings {

	/** Old option names and their current settings keys. */
	private const LEGACY_MAP = array(
		'account_suffix'     => 'account_suffix',
		'base_dn'            => 'base_dn',
		'domain_controllers' => 'domain_controllers',
		'group'              => 'groups',
		'account_type'       => 'role',
		'ol_login'           => 'ol_login',
		'use_tls'            => 'use_tls',
		'directory_type'     => 'directory',
		'login_mode'         => 'create_users',
		'security_mode'      => 'high_security',
	);

	/**
	 * Explicit scope during network activation.
	 *
	 * @var bool|null
	 */
	private ?bool $network;

	/**
	 * Set a scope override only when WordPress activates the plugin.
	 *
	 * @param bool|null $network Network activation flag, or detect current scope.
	 */
	public function __construct( ?bool $network = null ) {
		$this->network = $network;
	}

	/**
	 * Get defaults for a new installation.
	 *
	 * @return array
	 */
	public static function defaults(): array {
		return array(
			'version'                   => '200',
			'schema_version'            => 3,
			'enabled'                   => false,
			'directory'                 => 'ad',
			'account_suffix'            => '',
			'base_dn'                   => '',
			'domain_controllers'        => array(),
			'transport'                 => 'starttls',
			'allow_plaintext'           => false,
			'use_tls'                   => true,
			'ldap_port'                 => 389,
			'ldap_version'              => 3,
			'timeout'                   => 5,
			'ol_login'                  => 'uid',
			'ol_group'                  => 'cn',
			'groups'                    => array(),
			'group_base_dn'             => '',
			'group_uid'                 => 'memberUid',
			'search_sub_ous'            => false,
			'login_domain'              => '',
			'high_security'             => true,
			'recovery_mode'             => 'selected',
			'recovery_user_ids'         => array(),
			'create_users'              => false,
			'role'                      => 'subscriber',
			'sso_enabled'               => false,
			'sso_search_user'           => '',
			'sso_search_user_password'  => '',
			'user_first_name_attribute' => 'givenname',
			'user_last_name_attribute'  => 'sn',
			'user_email_attribute'      => 'mail',
			'user_url_attribute'        => 'wwwhomepage',
			'user_meta_data'            => array(),
			'meta_data_suffix_ldap'     => 'ldap',
			'meta_data_suffix_wp'       => 'wp',
			'delete_on_uninstall'       => false,
		);
	}

	/**
	 * Read a setting and retain the original setting filter.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $fallback Default for an unknown key.
	 * @return mixed
	 */
	public function get( string $key, $fallback = false ) {
		$settings = $this->all();
		$value    = array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
		return apply_filters( 'sll_get_setting', $value, $key );
	}

	/**
	 * Read all settings without filters.
	 *
	 * @return array
	 */
	public function all(): array {
		$stored   = $this->read( 'sll_settings', null );
		$settings = array_replace( self::defaults(), is_array( $stored ) ? $stored : array() );
		if ( is_array( $stored ) && 3 > (int) ( $stored['schema_version'] ?? 0 ) && ! array_key_exists( 'recovery_mode', $stored ) ) {
			$settings['recovery_mode'] = 'legacy';
		}
		return $settings;
	}

	/**
	 * Check the plugin activation scope.
	 *
	 * @return bool
	 */
	public function is_network(): bool {
		if ( ! is_multisite() ) {
			return false;
		}
		if ( null !== $this->network ) {
			return $this->network;
		}
		$file   = defined( 'SLL_FILE' ) ? SLL_FILE : dirname( __DIR__ ) . '/Simple-LDAP-Login.php';
		$active = get_site_option( 'active_sitewide_plugins', array() );
		return is_array( $active ) && isset( $active[ plugin_basename( $file ) ] );
	}

	/**
	 * Read the recovery policy. An invalid policy grants no exception.
	 *
	 * @return string Recovery mode.
	 */
	public function recovery_mode(): string {
		$mode = $this->get( 'recovery_mode' );
		return in_array( $mode, array( 'selected', 'legacy', 'off' ), true ) ? $mode : 'off';
	}

	/**
	 * Check administrator access in this settings scope.
	 *
	 * @param \WP_User $user Existing WordPress user.
	 * @return bool Whether the account can be selected for local recovery.
	 */
	public function can_recover( \WP_User $user ): bool {
		if ( $user->ID < 1 || ! empty( $user->spam ) || ! empty( $user->deleted ) ) {
			return false;
		}
		if ( $this->is_network() ) {
			return is_super_admin( $user->ID );
		}
		return ( ! is_multisite() || is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) && user_can( $user, 'manage_options' );
	}

	/**
	 * Check selected recovery access without granting it from a role alone.
	 *
	 * @param \WP_User $user Existing WordPress user.
	 * @return bool Whether the account must use local authentication.
	 */
	public function is_recovery_account( \WP_User $user ): bool {
		$ids = $this->get( 'recovery_user_ids' );
		return 'selected' === $this->recovery_mode() && is_array( $ids ) && in_array( $user->ID, $ids, true ) && $this->can_recover( $user );
	}

	/**
	 * Read a legacy boolean.
	 *
	 * @param mixed $value Setting value.
	 * @return bool
	 */
	public static function is_true( $value ): bool {
		return true === $value || ( is_scalar( $value ) && in_array( strtolower( (string) $value ), array( 'yes', 'y', 'true', '1', 'on', 'open' ), true ) );
	}

	/**
	 * Validate an LDAP attribute description.
	 *
	 * @param mixed $value Attribute name.
	 * @return bool
	 */
	public static function validate_attribute( $value ): bool {
		return is_string( $value ) && 1 === preg_match( '/^(?:[a-zA-Z][a-zA-Z0-9-]*|[0-9]+(?:\.[0-9]+)+)(?:;[a-zA-Z0-9-]+)*$/D', $value );
	}

	/**
	 * Block metadata keys that can change account security.
	 *
	 * @param string $key Final metadata key, with its suffix.
	 * @return bool
	 */
	public static function safe_meta_key( string $key ): bool {
		if ( ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D', $key ) ) {
			return false;
		}
		$key = strtolower( $key );
		if ( preg_match( '/(?:^|_)(?:capabilities|user_level)$/D', $key ) ) {
			return false;
		}
		return ! in_array( $key, array( 'session_tokens', 'application_passwords', 'user_pass', 'user_login', 'user_email', 'user_activation_key', 'primary_blog', 'source_domain', 'use_ssl', 'user_status' ), true );
	}

	/**
	 * Validate and save one complete settings change.
	 *
	 * The caller must check the administrator capability and nonce first.
	 *
	 * @param array $input Unslashed form values. Missing keys are retained.
	 * @return true|\WP_Error
	 */
	public function save( array $input ) {
		$current  = $this->all();
		$settings = $current;
		$defaults = self::defaults();
		$booleans = array( 'enabled', 'allow_plaintext', 'use_tls', 'search_sub_ous', 'high_security', 'create_users', 'sso_enabled', 'delete_on_uninstall' );
		$attrs    = array( 'ol_login', 'ol_group', 'group_uid', 'user_first_name_attribute', 'user_last_name_attribute', 'user_email_attribute', 'user_url_attribute' );

		foreach ( $input as $key => $value ) {
			$custom = apply_filters( 'sll_validate_setting', null, $key, $value, $this );
			if ( is_wp_error( $custom ) ) {
				return $custom;
			}
			if ( null !== $custom ) {
				$settings[ $key ] = $custom;
				continue;
			}
			if ( ! array_key_exists( $key, $defaults ) || in_array( $key, array( 'version', 'schema_version' ), true ) ) {
				return $this->invalid( $key );
			}
			if ( in_array( $key, $booleans, true ) ) {
				if ( ! is_scalar( $value ) || ! in_array( strtolower( (string) $value ), array( '', '0', '1', 'true', 'false', 'yes', 'no', 'y', 'n', 'on', 'off', 'open', 'closed' ), true ) ) {
					return $this->invalid( $key );
				}
				$settings[ $key ] = self::is_true( $value );
			} elseif ( in_array( $key, array( 'domain_controllers', 'groups' ), true ) ) {
				$list = $this->list_value( $value );
				if ( is_wp_error( $list ) ) {
					return $this->invalid( $key );
				}
				$settings[ $key ] = $list;
			} elseif ( 'recovery_user_ids' === $key ) {
				if ( ! is_array( $value ) ) {
					return $this->invalid( $key );
				}
				$ids = array();
				foreach ( $value as $id ) {
					if ( '' === $id ) {
						continue;
					}
					if ( ( ! is_int( $id ) && ! is_string( $id ) ) || false === filter_var( $id, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 1 ) ) ) ) {
						return $this->invalid( $key );
					}
					$ids[] = (int) $id;
				}
				$settings[ $key ] = array_values( array_unique( $ids ) );
			} elseif ( 'user_meta_data' === $key ) {
				$mappings = $this->mappings( $value );
				if ( is_wp_error( $mappings ) ) {
					return $mappings;
				}
				$settings[ $key ] = $mappings;
			} elseif ( in_array( $key, array( 'ldap_port', 'ldap_version', 'timeout' ), true ) ) {
				$number = filter_var( $value, FILTER_VALIDATE_INT );
				$min    = 1;
				$max    = 'ldap_port' === $key ? 65535 : 30;
				if ( false === $number || $number < $min || $number > $max || ( 'ldap_version' === $key && 3 !== $number ) ) {
					return $this->invalid( $key );
				}
				$settings[ $key ] = $number;
			} elseif ( 'sso_search_user_password' === $key ) {
				if ( ! is_string( $value ) || false !== strpos( $value, "\0" ) ) {
					return $this->invalid( $key );
				}
				if ( '' !== $value ) {
					$encrypted = Secrets::encrypt( $value );
					if ( is_wp_error( $encrypted ) ) {
						return $encrypted;
					}
					$settings[ $key ] = $encrypted;
				}
			} else {
				if ( ! is_string( $value ) || preg_match( '/[\x00-\x1f\x7f]/', $value ) ) {
					return $this->invalid( $key );
				}
				$value = trim( $value );
				if ( in_array( $key, $attrs, true ) && ! self::validate_attribute( $value ) ) {
					return $this->invalid( $key );
				}
				if ( in_array( $key, array( 'meta_data_suffix_ldap', 'meta_data_suffix_wp' ), true ) && ! preg_match( '/^[a-zA-Z0-9_-]*$/D', $value ) ) {
					return $this->invalid( $key );
				}
				$settings[ $key ] = $value;
			}
		}

		if ( ! in_array( $settings['recovery_mode'], array( 'selected', 'legacy', 'off' ), true ) ) {
			return $this->invalid( 'recovery_mode' );
		}
		$recovery_changed = array_key_exists( 'recovery_mode', $input ) || array_key_exists( 'recovery_user_ids', $input );
		foreach ( array( 'enabled', 'sso_enabled' ) as $key ) {
			$recovery_changed = $recovery_changed || ( self::is_true( $settings[ $key ] ) && ! self::is_true( $current[ $key ] ) );
		}
		if ( array_key_exists( 'recovery_user_ids', $input ) || ( $recovery_changed && 'selected' === $settings['recovery_mode'] ) ) {
			if ( ! is_array( $settings['recovery_user_ids'] ) ) {
				return $this->invalid( 'recovery_user_ids' );
			}
			foreach ( $settings['recovery_user_ids'] as $id ) {
				$user = is_int( $id ) && $id > 0 ? get_user_by( 'id', $id ) : false;
				if ( ! $user instanceof \WP_User || ! $this->can_recover( $user ) ) {
					return $this->invalid( 'recovery_user_ids' );
				}
			}
			if ( 'selected' === $settings['recovery_mode'] && ( self::is_true( $settings['enabled'] ) || self::is_true( $settings['sso_enabled'] ) ) && empty( $settings['recovery_user_ids'] ) ) {
				return new \WP_Error( 'sll_recovery_required', __( 'Select a recovery account on the Access tab, or turn local recovery access Off.', 'simple-ldap-login' ) );
			}
		}

		if ( ! in_array( $settings['directory'], array( 'ad', 'ol' ), true ) || ! in_array( $settings['transport'], array( 'plain', 'starttls', 'ldaps' ), true ) ) {
			return $this->invalid( 'directory / transport' );
		}
		if ( 'plain' === $settings['transport'] && ! self::is_true( $settings['allow_plaintext'] ) ) {
			return new \WP_Error( 'sll_plaintext', __( 'Enable Allow unencrypted LDAP on the Advanced tab, or choose StartTLS or LDAPS.', 'simple-ldap-login' ) );
		}
		if ( isset( $input['use_tls'] ) && ! isset( $input['transport'] ) ) {
			$settings['transport'] = $settings['use_tls'] ? 'starttls' : 'plain';
			if ( 'plain' === $settings['transport'] && ! self::is_true( $settings['allow_plaintext'] ) ) {
				return $this->invalid( 'allow_plaintext' );
			}
		}
		$settings['use_tls'] = 'starttls' === $settings['transport'];
		if ( ! get_role( $settings['role'] ) ) {
			return $this->invalid( 'role' );
		}
		foreach ( $settings['domain_controllers'] as $host ) {
			if ( ! self::valid_host( $host ) ) {
				return $this->invalid( 'domain_controllers' );
			}
		}
		foreach ( $settings['user_meta_data'] as $mapping ) {
			if ( ! is_array( $mapping ) || count( $mapping ) < 2 || ! self::validate_attribute( $mapping[0] ) ) {
				return $this->invalid( 'user_meta_data' );
			}
			foreach ( array( 'meta_data_suffix_ldap', 'meta_data_suffix_wp' ) as $suffix ) {
				if ( ! self::safe_meta_key( $mapping[1] . '_' . $settings[ $suffix ] ) ) {
					return new \WP_Error( 'sll_meta_key', __( 'A custom field mapping uses a protected or invalid WordPress user meta key.', 'simple-ldap-login' ) );
				}
			}
		}
		if ( self::is_true( $settings['enabled'] ) && ( '' === $settings['base_dn'] || empty( $settings['domain_controllers'] ) ) ) {
			return new \WP_Error( 'sll_connection_required', __( 'Enter a Base DN and at least one LDAP server before enabling LDAP login.', 'simple-ldap-login' ) );
		}
		if ( ! $this->write( 'sll_settings', $settings ) && $this->read( 'sll_settings', array() ) !== $settings ) {
			return new \WP_Error( 'sll_save_failed', __( 'WordPress could not save the settings. Try again.', 'simple-ldap-login' ) );
		}
		return true;
	}

	/**
	 * Migrate once without deleting the original data.
	 *
	 * @return void
	 */
	public function migrate(): void {
		$stored = $this->read( 'sll_settings', null );
		if ( is_array( $stored ) && isset( $stored['schema_version'] ) && 2 <= (int) $stored['schema_version'] ) {
			if ( 3 > (int) $stored['schema_version'] ) {
				if ( ! array_key_exists( 'recovery_mode', $stored ) ) {
					$stored['recovery_mode'] = 'legacy';
				}
				if ( ! array_key_exists( 'recovery_user_ids', $stored ) ) {
					$stored['recovery_user_ids'] = array();
				}
				$stored['schema_version']    = 3;
				$this->write( 'sll_settings', $stored );
			}
			return;
		}
		if ( null !== $stored && false !== $stored && ! is_array( $stored ) ) {
			return;
		}
		$legacy = is_array( $stored ) ? $stored : array();
		$old    = array();
		foreach ( self::LEGACY_MAP as $old_key => $new_key ) {
			$value = $this->read( 'simpleldap_' . $old_key, null );
			if ( null !== $value ) {
				$old[ 'simpleldap_' . $old_key ] = $value;
				if ( ! array_key_exists( $new_key, $legacy ) ) {
					if ( 'directory_type' === $old_key ) {
						$value = 'directory_ad' === $value ? 'ad' : 'ol';
					} elseif ( 'login_mode' === $old_key ) {
						$value = in_array( $value, array( 'mode_create_all', 'mode_create_group' ), true );
					} elseif ( 'security_mode' === $old_key ) {
						$value = 'security_high' === $value;
					}
					$legacy[ $new_key ] = $value;
				}
			}
		}
		$has_legacy = is_array( $stored ) || ! empty( $old );
		if ( $has_legacy && null === $this->read( 'sll_settings_backup_v1', null ) ) {
			$backup = is_array( $stored ) ? $stored : $old;
			if ( ! $this->write( 'sll_settings_backup_v1', $backup ) ) {
				return;
			}
		}
		$settings = array_replace( self::defaults(), $legacy );
		if ( $has_legacy ) {
			if ( ! array_key_exists( 'recovery_mode', $legacy ) ) {
				$settings['recovery_mode'] = 'legacy';
			}
			if ( ! array_key_exists( 'enabled', $legacy ) && ! empty( $old ) ) {
				$settings['enabled'] = true;
			}
			if ( ! array_key_exists( 'role', $legacy ) ) {
				$settings['role'] = 'contributor';
			}
			if ( ! array_key_exists( 'transport', $legacy ) ) {
				$settings['transport']       = self::is_true( $legacy['use_tls'] ?? false ) ? 'starttls' : 'plain';
				$settings['allow_plaintext'] = 'plain' === $settings['transport'];
			}
		}
		foreach ( array( 'enabled', 'allow_plaintext', 'use_tls', 'search_sub_ous', 'high_security', 'create_users', 'sso_enabled', 'delete_on_uninstall' ) as $key ) {
			$settings[ $key ] = self::is_true( $settings[ $key ] );
		}
		foreach ( array( 'domain_controllers', 'groups' ) as $key ) {
			$list = $this->list_value( $settings[ $key ] );
			if ( is_wp_error( $list ) ) {
				return;
			}
			$settings[ $key ] = $list;
		}
		if ( false === $settings['user_meta_data'] || null === $settings['user_meta_data'] ) {
			$settings['user_meta_data'] = array();
		}
		$password = $settings['sso_search_user_password'];
		if ( is_string( $password ) && '' !== $password && 0 !== strpos( $password, 'v2:' ) ) {
			$plain = Secrets::decrypt( $password );
			if ( ! is_wp_error( $plain ) ) {
				$encrypted = Secrets::encrypt( $plain );
				if ( ! is_wp_error( $encrypted ) ) {
					$settings['sso_search_user_password'] = $encrypted;
				}
			}
		}
		$settings['use_tls']        = 'starttls' === $settings['transport'];
		$settings['version']        = '200';
		$settings['schema_version'] = 3;
		$this->write( 'sll_settings', $settings );
	}

	/**
	 * Parse a list from an array or form field.
	 *
	 * @param mixed $value List value.
	 * @return array|\WP_Error
	 */
	private function list_value( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[;\r\n]+/', $value );
		}
		if ( false === $value || null === $value ) {
			return array();
		}
		if ( ! is_array( $value ) ) {
			return $this->invalid( 'list' );
		}
		$list = array();
		foreach ( $value as $item ) {
			if ( ! is_string( $item ) || preg_match( '/[\x00-\x1f\x7f]/', $item ) ) {
				return $this->invalid( 'list' );
			}
			$item = trim( $item );
			if ( '' !== $item ) {
				$list[] = $item;
			}
		}
		return array_values( array_unique( $list ) );
	}

	/**
	 * Parse metadata mappings.
	 *
	 * @param mixed $value Mapping value.
	 * @return array|\WP_Error
	 */
	private function mappings( $value ) {
		if ( is_string( $value ) ) {
			$value = preg_split( '/[;\r\n]+/', $value, -1, PREG_SPLIT_NO_EMPTY );
			$value = array_map(
				static function ( $line ) {
					return explode( ':', trim( $line ) );
				},
				$value
			);
		}
		if ( ! is_array( $value ) ) {
			return $this->invalid( 'user_meta_data' );
		}
		$mappings = array();
		foreach ( $value as $mapping ) {
			if ( ! is_array( $mapping ) || count( $mapping ) < 2 || count( $mapping ) > 3 ) {
				return $this->invalid( 'user_meta_data' );
			}
			foreach ( $mapping as $part ) {
				if ( ! is_string( $part ) ) {
					return $this->invalid( 'user_meta_data' );
				}
			}
			$mapping = array_values( array_map( 'trim', $mapping ) );
			if ( ! self::validate_attribute( $mapping[0] ) || ! preg_match( '/^[a-zA-Z0-9][a-zA-Z0-9_-]*$/D', $mapping[1] ) || ( isset( $mapping[2] ) && ! in_array( $mapping[2], array( 'string', 'number' ), true ) ) ) {
				return $this->invalid( 'user_meta_data' );
			}
			$mapping[2] = $mapping[2] ?? 'string';
			$mappings[] = $mapping;
		}
		return $mappings;
	}

	/**
	 * Check a host or LDAP URL. Prevent URLs with credentials or paths.
	 *
	 * @param string $host Directory server.
	 * @return bool
	 */
	private static function valid_host( string $host ): bool {
		$url = strpos( $host, '://' ) === false ? 'ldap://' . $host : $host;
		$uri = wp_parse_url( $url );
		return is_array( $uri ) && isset( $uri['scheme'], $uri['host'] ) && in_array( $uri['scheme'], array( 'ldap', 'ldaps' ), true ) && ! isset( $uri['user'] ) && ! isset( $uri['pass'] ) && ! isset( $uri['query'] ) && ! isset( $uri['fragment'] ) && ( ! isset( $uri['path'] ) || '' === $uri['path'] || '/' === $uri['path'] ) && ! preg_match( '/[\s\\\\]/', $uri['host'] );
	}

	/**
	 * Read an option in the correct scope.
	 *
	 * @param string $key Option key.
	 * @param mixed  $fallback Default value.
	 * @return mixed
	 */
	private function read( string $key, $fallback ) {
		return $this->is_network() ? get_site_option( $key, $fallback ) : get_option( $key, $fallback );
	}

	/**
	 * Write an option without autoloading service account secrets.
	 *
	 * @param string $key Option key.
	 * @param array  $value Option value.
	 * @return bool
	 */
	private function write( string $key, array $value ): bool {
		return $this->is_network() ? update_site_option( $key, $value ) : update_option( $key, $value, false );
	}

	/**
	 * Create a validation error.
	 *
	 * @param string $key Setting key.
	 * @return \WP_Error
	 */
	private function invalid( string $key ): \WP_Error {
		$labels = array(
			'directory' => __( 'Directory type', 'simple-ldap-login' ),
			'transport' => __( 'Connection security', 'simple-ldap-login' ),
			'domain_controllers' => __( 'LDAP servers', 'simple-ldap-login' ),
			'base_dn' => __( 'Base DN', 'simple-ldap-login' ),
			'account_suffix' => __( 'Username suffix', 'simple-ldap-login' ),
			'login_domain' => __( 'Windows domain', 'simple-ldap-login' ),
			'ldap_port' => __( 'Port', 'simple-ldap-login' ),
			'ldap_version' => __( 'LDAP protocol version', 'simple-ldap-login' ),
			'timeout' => __( 'LDAP timeout', 'simple-ldap-login' ),
			'ol_login' => __( 'Login attribute', 'simple-ldap-login' ),
			'ol_group' => __( 'Group name attribute', 'simple-ldap-login' ),
			'group_uid' => __( 'Group member attribute', 'simple-ldap-login' ),
			'groups' => __( 'Required groups', 'simple-ldap-login' ),
			'group_base_dn' => __( 'Group base DN', 'simple-ldap-login' ),
			'role' => __( 'Default role', 'simple-ldap-login' ),
			'recovery_mode' => __( 'Local recovery access', 'simple-ldap-login' ),
			'recovery_user_ids' => __( 'Recovery accounts', 'simple-ldap-login' ),
			'sso_search_user_password' => __( 'Search account password', 'simple-ldap-login' ),
			'user_meta_data' => __( 'Field mappings', 'simple-ldap-login' ),
			'meta_data_suffix_ldap' => __( 'LDAP value suffix', 'simple-ldap-login' ),
			'meta_data_suffix_wp' => __( 'WordPress value suffix', 'simple-ldap-login' ),
			'user_first_name_attribute' => __( 'First name attribute', 'simple-ldap-login' ),
			'user_last_name_attribute' => __( 'Last name attribute', 'simple-ldap-login' ),
			'user_email_attribute' => __( 'Email attribute', 'simple-ldap-login' ),
			'user_url_attribute' => __( 'Website attribute', 'simple-ldap-login' ),
		);
		$message = __( 'Check the submitted settings. No changes were saved.', 'simple-ldap-login' );
		if ( isset( $labels[ $key ] ) ) {
			/* translators: %s: setting label. */
			$message = sprintf( __( 'Check %s. No changes were saved.', 'simple-ldap-login' ), $labels[ $key ] );
		}
		return new \WP_Error( 'sll_invalid_setting', $message );
	}
}
