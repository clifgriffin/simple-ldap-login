<?php
/**
 * Plugin hooks.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

/** Starts the plugin without an LDAP connection. */
final class Plugin {

	/** Register hooks after other plugins load. */
	public static function boot(): void {
		$settings = new Settings();
		$settings->migrate();
		$client = new Ldap_Client( $settings );
		$auth   = new Authenticator( $settings, $client );
		$auth->register();
		if ( is_admin() ) {
			( new Admin( $settings, $client ) )->register();
		}

		$GLOBALS['SimpleLDAPLogin'] = new \SimpleLDAPLogin( $settings, $auth );
		/**
		 * Register add-on hooks after the free plugin starts.
		 *
		 * @param Settings      $settings Settings in the current scope.
		 * @param Authenticator $auth     Login handler.
		 */
		do_action( 'sll_loaded', $settings, $auth );
	}

	/**
	 * Set defaults or migrate settings in the activation scope.
	 *
	 * @param bool $network_wide Whether WordPress activates the plugin for a network.
	 */
	public static function activate( bool $network_wide = false ): void {
		( new Settings( $network_wide ) )->migrate();
	}
}
