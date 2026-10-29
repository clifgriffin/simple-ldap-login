<?php
/**
 * LDAP connections and directory reads.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Ldap;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Model;
use LdapRecord\Models\OpenLDAP\Entry;
use LdapRecord\Query\EscapedValue;
use WP_Error;

/** Uses LdapRecord with no persistent connection or password cache. */
class Ldap_Client {

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	protected Settings $settings;

	/**
	 * Set the settings source.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Check a password and access groups. Do not change WordPress data.
	 *
	 * @param string $username Login name.
	 * @param string $password Exact password, without slash removal.
	 * @return array|WP_Error Directory entry or error.
	 */
	public function authenticate( string $username, string $password ) {
		if ( '' === trim( $username ) || '' === $password || str_contains( $username . $password, "\0" ) ) {
			return $this->error();
		}
		return $this->with_connection(
			function ( Connection $connection ) use ( $username, $password ) {
				$ad        = 'ad' === $this->settings->get( 'directory' );
				$base      = (string) $this->settings->get( 'base_dn' );
				$attribute = (string) $this->settings->get( 'ol_login' );
				if ( '' === $base || ( ! $ad && ! Settings::validate_attribute( $attribute ) ) ) {
					return $this->error( 'sll_configuration' );
				}
				$dn = '';
				if ( ! $ad && Settings::is_true( $this->settings->get( 'search_sub_ous' ) ) ) {
					$bound = $this->bind_service( $connection, false );
					if ( is_wp_error( $bound ) ) {
						return $bound;
					}
					$found = $this->find_user( $connection, $username );
					if ( is_wp_error( $found ) ) {
						return $found;
					}
					$dn = $found->getDn();
				}
				$bind_name = $ad ? $this->bind_name( $username ) : ( $dn ? $dn : $attribute . '=' . ( new EscapedValue( $username ) )->forDn() . ',' . $base );
				$bound     = $this->bind( $connection, $bind_name, $password );
				if ( is_wp_error( $bound ) || ! apply_filters( 'sll_ldap_auth', true ) ) {
					return is_wp_error( $bound ) ? $bound : $this->error();
				}
				$entry = $this->find_user( $connection, $username, $ad ? null : $bind_name );
				if ( is_wp_error( $entry ) ) {
					return $entry;
				}
				// The password must belong to the exact entry used by WordPress.
				if ( $ad ) {
					$bound = $this->bind( $connection, $entry->getDn(), $password );
					if ( is_wp_error( $bound ) ) {
						return $bound;
					}
				}
				return $this->allowed_entry( $connection, $entry, $username );
			}
		);
	}

	/**
	 * Open a connection and test the search account, if set.
	 *
	 * @return true|WP_Error Test result.
	 */
	public function test_connection() {
		return $this->with_connection(
			function ( Connection $connection ) {
				$bound = $this->bind_service( $connection, false );
				if ( is_wp_error( $bound ) ) {
					return $bound;
				}
				$connection->query()->in( '' )->read()->whereHas( 'objectClass' )->limit( 2 )->get( array( 'namingContexts' ) );
				return 0 === $connection->getLdapConnection()->errNo() ? true : $this->error( 'sll_search' );
			}
		);
	}

	/**
	 * Try each configured server. Always close its connection.
	 *
	 * @param callable $callback Work to do on the connection.
	 * @return mixed|WP_Error Operation result.
	 */
	protected function with_connection( callable $callback ) {
		if ( ! extension_loaded( 'ldap' ) ) {
			return $this->error( 'sll_ldap_missing' );
		}
		$transport = $this->settings->get( 'transport' );
		if ( ! in_array( $transport, array( 'plain', 'starttls', 'ldaps' ), true ) || ( 'plain' === $transport && ! Settings::is_true( $this->settings->get( 'allow_plaintext' ) ) ) ) {
			return $this->error( 'sll_transport' );
		}
		$result = $this->error( 'sll_connection' );
		foreach ( (array) $this->settings->get( 'domain_controllers' ) as $host ) {
			$connection = null;
			$name       = '';
			try {
				$connection = $this->connect( (string) $host, $transport );
				if ( is_wp_error( $connection ) ) {
					$result     = $connection;
					$connection = null;
					continue;
				}
				$name = 'sll-' . spl_object_id( $connection );
				Container::addConnection( $connection, $name );
				$result = $callback( $connection );
			} catch ( \Throwable $exception ) {
				$result = $this->error( 'sll_connection' );
			} finally {
				if ( $connection instanceof Connection ) {
					$connection->disconnect();
				}
				if ( '' !== $name ) {
					Container::removeConnection( $name );
				}
			}
			if ( ! is_wp_error( $result ) || in_array( $result->get_error_code(), array( 'sll_credentials', 'sll_groups' ), true ) ) {
				return $result;
			}
		}
		return $result;
	}

	/**
	 * Make a library connection. Tests can supply the library's transport fake.
	 *
	 * @param array $configuration Connection settings.
	 * @return Connection Connection.
	 */
	protected function create_connection( array $configuration ): Connection {
		return new Connection(
			$configuration,
			new class() extends Ldap {
				/** Keep the configured port; the library otherwise changes 389 to 636 for LDAPS. */
				protected function makeConnectionUris( array|string $hosts, string|int $port ): string {
					return implode( ' ', $this->assembleHostUris( $hosts, $port ) );
				}
			}
		);
	}

	/**
	 * Set time limits and require valid TLS certificates.
	 *
	 * @param string $host      Host name or LDAP URI.
	 * @param string $transport Transport mode.
	 * @return Connection|WP_Error Connection or error.
	 */
	private function connect( string $host, string $transport ) {
		$parts = wp_parse_url( str_contains( $host, '://' ) ? $host : 'ldap://' . $host );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'], array( 'ldap', 'ldaps' ), true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || ( ! empty( $parts['path'] ) && '/' !== $parts['path'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return $this->error( 'sll_configuration' );
		}
		$tls  = 'ldaps' === $transport || 'ldaps' === $parts['scheme'];
		$port = $parts['port'] ?? (int) $this->settings->get( 'ldap_port' );
		if ( $port < 1 || $port > 65535 ) {
			return $this->error( 'sll_configuration' );
		}
		$timeout = max( 1, min( 30, (int) $this->settings->get( 'timeout' ) ) );
		$options = array(
			LDAP_OPT_TIMEOUT   => $timeout,
			LDAP_OPT_TIMELIMIT => $timeout,
		);
		$secure  = $tls || 'starttls' === $transport;
		if ( $secure ) {
			$options[ LDAP_OPT_X_TLS_REQUIRE_CERT ] = LDAP_OPT_X_TLS_DEMAND;
		}
		$connection = $this->create_connection(
			array(
				'hosts'            => array( $parts['host'] ),
				'port'             => $port,
				'base_dn'          => (string) $this->settings->get( 'base_dn' ),
				'username'         => null,
				'password'         => null,
				'timeout'          => $timeout,
				'follow_referrals' => false,
				'use_tls'          => $tls,
				'use_starttls'     => ! $tls && 'starttls' === $transport,
				'options'          => $options,
			)
		);
		try {
			// Apply the certificate policy before a connection can send credentials.
			if ( $secure && ! $connection->getLdapConnection()->setOption( LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND ) ) {
				return $this->error( 'sll_tls' );
			}
			$connection->initialize();
			foreach ( $options as $option => $value ) {
				if ( ! $connection->getLdapConnection()->setOption( $option, $value ) ) {
					$connection->disconnect();
					return $this->error( 'sll_configuration' );
				}
			}
		} catch ( \Throwable $exception ) {
			$connection->disconnect();
			return $this->error( $secure ? 'sll_tls' : 'sll_connection' );
		}
		return $connection;
	}

	/**
	 * Bind through the library and preserve the exact password bytes.
	 *
	 * @param Connection  $connection LDAP connection.
	 * @param string|null $name       Bind name, or null for anonymous access.
	 * @param string|null $password   Password, or null for anonymous access.
	 * @return true|WP_Error Bind result.
	 */
	protected function bind( Connection $connection, ?string $name, ?string $password ) {
		// LdapRecord 4.1 decodes HTML entities and treats '0' as null in Ldap::bind().
		// Encode only these cases so its single decode restores the original bytes.
		$password = null === $password ? null : ( '0' === $password ? '&#48;' : str_replace( '&', '&amp;', $password ) );
		$connection->getConfiguration()->set( 'username', $name );
		$connection->getConfiguration()->set( 'password', $password );
		try {
			$connection->auth()->bind( $name, $password );
			return true;
		} catch ( BindException $exception ) {
			return $this->error( 49 === $exception->getDetailedError()?->getErrorCode() ? 'sll_credentials' : 'sll_bind' );
		} catch ( \Throwable $exception ) {
			$ldap = $connection->getLdapConnection();
			return $this->error( ( $ldap->isUsingTLS() || $ldap->isUsingStartTLS() ) && ! $ldap->isSecure() ? 'sll_tls' : 'sll_connection' );
		}
	}

	/**
	 * Bind a search account; allow anonymous lookup only outside SSO.
	 *
	 * @param Connection $connection LDAP connection.
	 * @param bool       $required   Require a nonempty search account and secret.
	 * @return true|WP_Error Bind result.
	 */
	protected function bind_service( Connection $connection, bool $required ) {
		$name = (string) $this->settings->get( 'sso_search_user' );
		if ( '' === $name ) {
			return $required ? $this->error( 'sll_service_account' ) : $this->bind( $connection, null, null );
		}
		$password = Secrets::decrypt( (string) $this->settings->get( 'sso_search_user_password' ) );
		if ( is_wp_error( $password ) || '' === $password ) {
			return $this->error( 'sll_service_account' );
		}
		$name   = 'ad' === $this->settings->get( 'directory' ) ? $this->bind_name( $name ) : $name;
		$result = $this->bind( $connection, $name, $password );
		return is_wp_error( $result ) ? $this->error( 'sll_service_account' ) : true;
	}

	/**
	 * Build a bind name without adding a second domain.
	 *
	 * @param string $username Login name.
	 * @return string Bind name.
	 */
	private function bind_name( string $username ): string {
		if ( str_contains( $username, '@' ) || str_contains( $username, '\\' ) || str_contains( $username, '=' ) ) {
			return $username;
		}
		$domain = (string) $this->settings->get( 'login_domain' );
		return $domain ? $domain . '\\' . $username : $username . $this->settings->get( 'account_suffix' );
	}

	/**
	 * Find one enabled account with library models and escaped query values.
	 *
	 * @param Connection  $connection LDAP connection.
	 * @param string      $username   Login name.
	 * @param string|null $dn         Use a base-scope read at this DN, if set.
	 * @return Model|WP_Error User model or error.
	 */
	protected function find_user( Connection $connection, string $username, ?string $dn = null ) {
		$ad        = 'ad' === $this->settings->get( 'directory' );
		$attribute = $ad ? ( str_contains( $username, '@' ) ? 'userPrincipalName' : 'sAMAccountName' ) : $this->settings->get( 'ol_login' );
		$base      = (string) $this->settings->get( 'base_dn' );
		if ( '' === $base || ! Settings::validate_attribute( $attribute ) ) {
			return $this->error( 'sll_configuration' );
		}
		$model = $ad ? new User() : new Entry();
		$model->setConnection( 'sll-' . spl_object_id( $connection ) );
		$query = $model->newQuery()->in( $dn ?? $base )->limit( 2 );
		if ( $ad ) {
			$query->whereEnabled();
		}
		if ( null !== $dn ) {
			$query->read()->whereHas( 'objectClass' );
		} else {
			$query->whereEquals( $attribute, $this->login_name( $username ) );
		}
		$found = $query->select( $this->attributes() )->get();
		if ( 0 !== $connection->getLdapConnection()->errNo() || 1 !== $found->count() || ! $found->first()->getDn() ) {
			return $this->error();
		}
		return $found->first();
	}

	/**
	 * Remove a domain prefix from a login name for the selected schema.
	 *
	 * @param string $username Login name.
	 * @return string Search value.
	 */
	protected function login_name( string $username ): string {
		return 'ad' === $this->settings->get( 'directory' ) && str_contains( $username, '\\' ) ? substr( $username, strrpos( $username, '\\' ) + 1 ) : $username;
	}

	/**
	 * Collect only configured profile attributes and model relation attributes.
	 *
	 * @return array Attribute names.
	 */
	protected function attributes(): array {
		$attributes = array( 'cn', 'objectclass' );
		if ( 'ad' === $this->settings->get( 'directory' ) ) {
			$attributes = array_merge( $attributes, array( 'samaccountname', 'userprincipalname', 'primarygroupid', 'objectsid' ) );
		}
		foreach ( array( 'ol_login', 'user_first_name_attribute', 'user_last_name_attribute', 'user_email_attribute', 'user_url_attribute' ) as $key ) {
			$attributes[] = $this->settings->get( $key );
		}
		/**
		 * Add attributes required by installed directory modules.
		 *
		 * @param array $attributes Attribute names.
		 */
		$filtered = apply_filters( 'sll_ldap_attributes', $attributes );
		$attributes = is_array( $filtered ) ? $filtered : $attributes;
		return array_values( array_unique( array_filter( $attributes, array( Settings::class, 'validate_attribute' ) ) ) );
	}

	/**
	 * Check access and return the existing entry-array format for WordPress hooks.
	 *
	 * @param Connection $connection LDAP connection.
	 * @param Model      $entry      User model.
	 * @param string     $username   Login name.
	 * @return array|WP_Error Directory entry or error.
	 */
	protected function allowed_entry( Connection $connection, Model $entry, string $username ) {
		if ( ! apply_filters( 'sll_user_has_groups', $this->has_groups( $connection, $entry, $this->login_name( $username ) ) ) ) {
			return $this->error( 'sll_groups' );
		}
		return array_merge( $entry->getAttributes(), array( 'dn' => $entry->getDn() ) );
	}

	/**
	 * Check any required group. The AD model owns nested and primary group rules.
	 *
	 * @param Connection $connection LDAP connection.
	 * @param Model      $entry      User model.
	 * @param string     $username   Login name.
	 * @return bool Access result.
	 */
	private function has_groups( Connection $connection, Model $entry, string $username ): bool {
		$groups = array_filter( (array) $this->settings->get( 'groups' ), 'strlen' );
		if ( ! $groups ) {
			return true;
		}
		$base = (string) ( $this->settings->get( 'group_base_dn' ) ? $this->settings->get( 'group_base_dn' ) : $this->settings->get( 'base_dn' ) );
		if ( $entry instanceof User ) {
			$attributes  = array( 'cn', 'samaccountname', 'objectclass' );
			$memberships = $entry->groups()->recursive()->get( $attributes );
			$primary     = $entry->primaryGroup()->first( $attributes );
			if ( $primary instanceof Group ) {
				$memberships = $memberships->merge( $primary->groups()->recursive()->get( $attributes ) );
			}
			$base_parts  = array_map( 'strtolower', DistinguishedName::make( $base )->components() );
			if ( ! $base_parts || 0 !== $connection->getLdapConnection()->errNo() ) {
				return false;
			}
			foreach ( $memberships as $group ) {
				$parts = array_map( 'strtolower', DistinguishedName::make( $group->getDn() )->components() );
				if ( array_slice( $parts, -count( $base_parts ) ) !== $base_parts ) {
					continue;
				}
				$names = array_merge( array( $group->getDn() ), $group->getAttribute( 'cn' ) ?? array(), $group->getAttribute( 'samaccountname' ) ?? array() );
				if ( array_intersect( array_map( 'strtolower', $groups ), array_map( 'strtolower', $names ) ) ) {
					return true;
				}
			}
			return false;
		}
		$attribute = $this->settings->get( 'ol_group' );
		$uid       = $this->settings->get( 'group_uid' );
		if ( ! Settings::validate_attribute( $attribute ) || ! Settings::validate_attribute( $uid ) ) {
			return false;
		}
		foreach ( $groups as $group ) {
			$query = $connection->query()->in( $base )->limit( 2 )->whereEquals( $attribute, $group );
			$query->orFilter(
				function ( $members ) use ( $entry, $username, $uid ) {
					$members->andFilter( fn( $filter ) => $filter->whereEquals( 'objectClass', 'groupOfUniqueNames' )->whereEquals( 'uniqueMember', $entry->getDn() ) );
					$members->andFilter( fn( $filter ) => $filter->whereEquals( 'objectClass', 'groupOfNames' )->whereEquals( 'member', $entry->getDn() ) );
					$members->whereEquals( $uid, in_array( strtolower( $uid ), array( 'member', 'uniquemember' ), true ) ? $entry->getDn() : $username );
				}
			);
			$found = $query->get( array( 'cn' ) );
			if ( 0 === $connection->getLdapConnection()->errNo() && count( $found ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Return a fixed public error without directory details.
	 *
	 * @param string $code Error code.
	 * @return WP_Error Error.
	 */
	protected function error( string $code = 'sll_credentials' ): WP_Error {
		return new WP_Error( $code, __( 'LDAP could not verify this request. Check the connection, account, and access settings.', 'simple-ldap-login' ) );
	}
}
