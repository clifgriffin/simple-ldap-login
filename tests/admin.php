<?php
/** Standalone admin regression checks. Run: php tests/admin.php. */

namespace SimpleLdapLogin {

	/** Keep screen tests independent of WordPress and a live directory. */
	class Settings {
		public bool $network = false;
		public bool $enabled = false;
		public bool $sso = false;
		public array $mappings = array( array( 'telephoneNumber', 'phone', 'string' ) );
		public string $directory = 'ad';
		public string $recovery = 'selected';
		public array $recovery_ids = array( 1 );
		public array $saved = array();
		public function get( $key ) { return $this->all()[ $key ] ?? ''; }
		public function all() {
			return array( 'directory' => $this->directory, 'enabled' => $this->enabled, 'transport' => 'starttls', 'sso_search_user_password' => 'never-render-this-secret', 'domain_controllers' => array( 'dc.example.org' ), 'groups' => array( 'staff' ), 'sso_enabled' => $this->sso, 'user_meta_data' => $this->mappings, 'recovery_mode' => $this->recovery, 'recovery_user_ids' => $this->recovery_ids );
		}
		public function is_network() { return $this->network; }
		public function recovery_mode() { return $this->recovery; }
		public function can_recover( \WP_User $user ) { return $this->network ? $user->super : $user->admin && 1 === $user->blog; }
		public static function is_true( $value ) { return true === $value || '1' === $value; }
		public function save( $values ) { $this->saved = $values; return true; }
	}

	/** Count every explicit test call. */
	class Ldap_Client {
		public int $calls = 0;
		public array $credentials = array();
		public function authenticate( $username, $password ) { ++$this->calls; $this->credentials = array( $username, $password ); return array( 'dn' => 'uid=test' ); }
		public function test_connection() { ++$this->calls; return true; }
	}

	/** Read only the stored secret format in these screen checks. */
	class Secrets {
		public static function decrypt( $value ) { return empty( $GLOBALS['sll_admin_broken_secret'] ) ? 'test-secret' : new \WP_Error(); }
	}
}

namespace {

	define( 'ABSPATH', '/tmp/' );
	define( 'SLL_FILE', '/simple-ldap-login/Simple-LDAP-Login.php' );
	define( 'SLL_VERSION', 'test' );
	define( 'MINUTE_IN_SECONDS', 60 );
	$GLOBALS['sll_admin_allowed'] = true;
	$GLOBALS['sll_admin_checks'] = 0;
	$GLOBALS['sll_admin_hooks'] = array();

	class AdminTestStop extends RuntimeException {}
	class WP_Error {}
	class WP_User {
		public function __construct( public int $ID, public string $user_login, public string $display_name, public bool $admin = true, public bool $super = false, public int $blog = 1 ) {}
	}
	$GLOBALS['sll_admin_users'] = array(
		new WP_User( 1, 'site-admin', 'Site <Admin>' ),
		new WP_User( 2, 'network-admin', 'Network Admin', true, true ),
		new WP_User( 3, 'member', 'Site Member', false ),
		new WP_User( 4, 'other-site-admin', 'Other Site Admin', true, false, 2 ),
	);
	function get_current_blog_id() { return 1; }
	function is_multisite() { return $GLOBALS['sll_admin_multisite'] ?? false; }
	function get_super_admins() { return $GLOBALS['sll_admin_super_admins'] ?? array( 'network-admin' ); }
	function get_users( $query ) {
		$GLOBALS['sll_admin_user_queries'] = ( $GLOBALS['sll_admin_user_queries'] ?? 0 ) + 1;
		$GLOBALS['sll_admin_user_query'] = $query;
		return array_values( array_filter( $GLOBALS['sll_admin_users'], static function ( $user ) use ( $query ) {
			return ( 0 === $query['blog_id'] || $query['blog_id'] === $user->blog ) && ( ! isset( $query['capability'] ) || $user->admin ) && ( ! isset( $query['login__in'] ) || in_array( $user->user_login, $query['login__in'], true ) );
		} ) );
	}
	function __( $s, $d = '' ) { return $s; }
	function add_filter( $name, $callback, $priority = 10, $accepted = 1 ) { $GLOBALS['sll_admin_hooks'][ $name ][ $priority ][] = array( $callback, $accepted ); }
	function add_action( $name, $callback, $priority = 10, $accepted = 1 ) { add_filter( $name, $callback, $priority, $accepted ); }
	function has_action( $name ) { return ! empty( $GLOBALS['sll_admin_hooks'][ $name ] ); }
	function get_option( $name, $default = false ) { return $GLOBALS['sll_admin_options'][ $name ] ?? $default; }
	function get_site_option( $name, $default = false ) { return $GLOBALS['sll_admin_network_options'][ $name ] ?? $default; }
	function home_url() { return 'https://example.test'; }
	function network_home_url() { return 'https://network.test'; }
	function untrailingslashit( $value ) { return rtrim( $value, '/' ); }
	function plugin_basename( $file ) { return 'simple-ldap-login/' . basename( $file ); }
	function plugins_url( $path, $file ) { return '/wp-content/plugins/simple-ldap-login/' . $path; }
	function wp_create_nonce( $action ) { return 'test-nonce'; }
	function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
	function wp_register_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['sll_commerce_scripts'][ $handle ] = $url; }
	function wp_register_style( $handle, $url, $deps, $version ) { $GLOBALS['sll_commerce_styles'][ $handle ] = $url; }
	function wp_enqueue_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['sll_commerce_scripts'][ $handle ] = $url; }
	function wp_enqueue_style( $handle ) {}
	function wp_add_inline_script( $handle, $script, $position ) { $GLOBALS['sll_commerce_config'] = $script; }
	function apply_filters( $name, $value ) {
		$priorities = $GLOBALS['sll_admin_hooks'][ $name ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $entry ) { $value = call_user_func( $entry[0], $value ); } }
		return $value;
	}
	function do_action( $name, ...$args ) {
		$priorities = $GLOBALS['sll_admin_hooks'][ $name ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) { foreach ( $callbacks as $entry ) { call_user_func_array( $entry[0], array_slice( $args, 0, $entry[1] ) ); } }
	}
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return esc_html( $s ); }
	function esc_url( $s ) { return esc_html( $s ); }
	function esc_textarea( $s ) { return esc_html( $s ); }
	function esc_html__( $s, $d = '' ) { return esc_html( $s ); }
	function esc_attr__( $s, $d = '' ) { return esc_attr( $s ); }
	function esc_html_e( $s, $d = '' ) { echo esc_html( $s ); }
	function esc_attr_e( $s, $d = '' ) { echo esc_attr( $s ); }
	function admin_url( $path = '' ) { return '/wp-admin/' . $path; }
	function network_admin_url( $path = '' ) { return '/wp-admin/network/' . $path; }
	function add_query_arg( $key, $value, $url = null ) {
		if ( is_array( $key ) ) { return $value . '&' . http_build_query( $key ); }
		return $url . ( false !== strpos( $url, '?' ) ? '&' : '?' ) . rawurlencode( $key ) . '=' . rawurlencode( $value );
	}
	function wp_nonce_field( $action ) { echo '<input name="_wpnonce" value="test-nonce" />'; }
	function check_admin_referer( $action ) { if ( 'test-nonce' !== ( $_POST['_wpnonce'] ?? '' ) ) { throw new AdminTestStop( 'nonce' ); } }
	function checked( $v ) { echo $v ? 'checked="checked"' : ''; }
	function selected( $a, $b ) { echo $a === $b ? 'selected="selected"' : ''; }
	function submit_button( $s, $c, $n, $p ) { echo '<button type="submit">' . esc_html( $s ) . '</button>'; }
	function current_user_can( $c ) { $GLOBALS['sll_admin_capability'] = $c; return $GLOBALS['sll_admin_allowed']; }
	function wp_roles() { return (object) array( 'roles' => array( 'subscriber' => array( 'name' => 'Subscriber' ) ) ); }
	function translate_user_role( $s ) { return $s; }
	function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_]/', '', strtolower( $s ) ); }
	function sanitize_text_field( $s ) { return trim( strip_tags( $s ) ); }
	function wp_unslash( $s ) { return is_array( $s ) ? array_map( 'wp_unslash', $s ) : stripslashes( $s ); }
	function get_current_user_id() { return 1; }
	function get_transient( $key ) { return false; }
	function delete_transient( $key ) {}
	function is_wp_error( $value ) { return $value instanceof WP_Error; }
	function wp_die( $message, $title, $options ) { throw new AdminTestStop( 'denied:' . $options['response'] ); }
	function wp_safe_redirect( $url ) { throw new AdminTestStop( $url ); }
	function admin_check( $condition, $label ) {
		if ( ! $condition ) { throw new RuntimeException( 'FAIL: ' . $label ); }
		++$GLOBALS['sll_admin_checks'];
	}
	function admin_post( $admin, $values ) {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST = array_replace( array( '_wpnonce' => 'test-nonce', 'sll_scope' => 'site', 'tab' => 'connection', 'sll_task' => 'save', 'sll_settings' => array() ), $values );
		try { $admin->handle_post(); } catch ( AdminTestStop $stop ) { return $stop->getMessage(); }
		throw new RuntimeException( 'The handler did not redirect or reject the request.' );
	}

	require dirname( __DIR__ ) . '/src/Admin.php';
	require dirname( __DIR__ ) . '/pro/src/Admin.php';
	require dirname( __DIR__ ) . '/src/License.php';
	require dirname( __DIR__ ) . '/src/Commerce.php';
	require dirname( __DIR__ ) . '/vendor/paidcommunities/wordpress-sdk/src/Assets/AssetsApi.php';
	$settings = new SimpleLdapLogin\Settings();
	$client   = new SimpleLdapLogin\Ldap_Client();
	$admin    = new SimpleLdapLogin\Admin( $settings, $client );
	foreach ( array( 'free', 'pro' ) as $edition ) {
		$GLOBALS['sll_admin_hooks'] = array();
		( new SimpleLdapLogin\Commerce( $settings ) )->register();
		if ( 'pro' === $edition ) {
			( new SimpleLdapLogin\Pro\Admin( $settings ) )->register();
		}
		foreach ( array( 'site', 'network' ) as $scope ) {
			$settings->network = 'network' === $scope;
			$GLOBALS['sll_admin_multisite'] = $settings->network;
			foreach ( array( 'connection', 'access', 'profile', 'sso', 'accounts', 'license', 'advanced', 'help' ) as $tab ) {
				$_GET = array( 'tab' => $tab );
				ob_start(); $admin->render(); $html = ob_get_clean();
				admin_check( false === strpos( $html, 'never-render-this-secret' ), 'Saved password is absent from ' . $edition . '/' . $scope . '/' . $tab );
				admin_check( false !== strpos( $html, 'name="sll_scope" value="' . $scope . '"' ) && false !== strpos( $html, 'name="_wpnonce"' ), 'Forms have a nonce and correct scope: ' . $edition . '/' . $scope . '/' . $tab );
				admin_check( false !== strpos( $html, 'aria-current="page"' ), 'The current tab has an accessible label: ' . $edition . '/' . $scope . '/' . $tab );
				admin_check( ( 'pro' === $edition ) === ( false !== strpos( $html, 'tab=sso' ) ), 'Only packages with SSO add its tab.' );
				admin_check( ( 'pro' === $edition && 'access' === $tab ) === str_contains( $html, 'sll_settings[role_rules]' ), 'Group role rules appear only on Pro Access.' );
				admin_check( ( 'pro' === $edition && 'profile' === $tab ) === str_contains( $html, 'sll_settings[profile_updates]' ), 'Profile updates appear only on Pro User data.' );
				admin_check( ( 'pro' === $edition && 'accounts' === $tab ) === str_contains( $html, 'sll_settings[scheduled_import]' ), 'Scheduled import appears only on Pro Accounts.' );
				if ( 'profile' === $tab ) {
					admin_check( ( 'free' === $edition ) === str_contains( $html, 'sll_settings[user_email_attribute]' ), 'Old Free profile links open Advanced; Pro profile shows mappings.' );
					admin_check( 'pro' === $edition || str_contains( $html, 'name="tab" value="advanced"' ), 'Old Free profile links retain a valid save destination.' );
					admin_check( ( 'pro' === $edition ) === ( false !== strpos( $html, 'sll_settings[user_meta_data]' ) ), 'Only packages with metadata mappings show their controls.' );
				}
				if ( 'sso' === $tab ) {
					admin_check( ( 'pro' === $edition ) === ( false !== strpos( $html, 'sll_settings[sso_enabled]' ) ), 'Free has no SSO controls, even with a direct tab request.' );
					admin_check( 'pro' === $edition || false !== strpos( $html, 'name="tab" value="connection"' ), 'Free sends an unknown SSO tab to Connection.' );
				}
				if ( 'connection' === $tab ) {
					admin_check( false !== strpos( $html, 'sll_settings[sso_search_user_password]' ), 'Both packages can configure a search account.' );
				}
				$advanced_fields = array( 'timeout', 'allow_plaintext', 'ol_login', 'group_base_dn', 'ol_group', 'group_uid', 'user_first_name_attribute', 'user_last_name_attribute', 'user_email_attribute', 'user_url_attribute', 'delete_on_uninstall' );
				$on_advanced = 'advanced' === $tab || ( 'free' === $edition && 'profile' === $tab );
				$visible_advanced = array_filter( $advanced_fields, static fn( $field ) => str_contains( $html, 'sll_settings[' . $field . ']' ) );
				admin_check( $visible_advanced === ( $on_advanced ? $advanced_fields : array() ), 'Default-based settings appear only on Advanced in ' . $edition . '/' . $scope . '/' . $tab );
				foreach ( array( 'meta_data_suffix_ldap', 'meta_data_suffix_wp' ) as $field ) {
					admin_check( ( 'pro' === $edition && 'advanced' === $tab ) === str_contains( $html, 'sll_settings[' . $field . ']' ), 'Metadata suffixes appear only on Pro Advanced.' );
				}
				admin_check( ( 'pro' === $edition ) === str_contains( $html, 'tab=profile' ), 'Only the full package needs a User data tab.' );
				if ( 'access' === $tab ) {
					admin_check( str_contains( $html, 'sll_settings[recovery_mode]' ) && str_contains( $html, 'Selected administrators' ) && str_contains( $html, is_multisite() ? 'All super administrators (legacy)' : 'All administrators (legacy)' ), 'Both packages show the recovery modes in each scope.' );
					admin_check( str_contains( $html, 'name="sll_settings[recovery_user_ids][]" value=""' ) && str_contains( $html, 'multiple size="5"' ), 'The recovery picker supports multiple accounts and an empty selection.' );
					admin_check( str_contains( $html, 'Network Admin (network-admin)' ) && ! str_contains( $html, 'Site Member (member)' ) && ! str_contains( $html, 'Other Site Admin (other-site-admin)' ), 'The picker shows only eligible accounts in its scope.' );
					admin_check( ( 'site' === $scope ) === str_contains( $html, 'Site &lt;Admin&gt; (site-admin)' ), 'Site administrator labels are escaped and absent from network recovery choices.' );
					admin_check( ! str_contains( $html, 'Local administrators retain a recovery login.' ) && str_contains( $html, 'Use separate local accounts with WordPress passwords.' ), 'The access text describes the selected recovery policy.' );
				}
			}
		}
	}
	$pro_admin = new SimpleLdapLogin\Commerce( $settings );
	foreach ( array( false, true ) as $network ) {
		$settings->network = $network;
		$scope = $network ? 'network' : 'site';
		foreach ( array( 'activate', 'check', 'deactivate' ) as $task ) {
			$_SERVER['REQUEST_METHOD'] = 'POST';
			$_POST = array( '_wpnonce' => 'test-nonce', 'sll_scope' => $scope, 'sll_license_task' => $task );
			$GLOBALS['sll_admin_allowed'] = false;
			try { $pro_admin->handle_license(); } catch ( AdminTestStop $stop ) { admin_check( 'denied:403' === $stop->getMessage(), 'License actions require settings permissions.' ); }
			admin_check( ( $network ? 'manage_network_options' : 'manage_options' ) === $GLOBALS['sll_admin_capability'], 'License actions check the correct capability for the scope.' );
			$GLOBALS['sll_admin_allowed'] = true;
			unset( $_POST['_wpnonce'] );
			try { $pro_admin->handle_license(); } catch ( AdminTestStop $stop ) { admin_check( 'nonce' === $stop->getMessage(), 'License actions reject a missing nonce before reading credentials.' ); }
			$_POST['_wpnonce'] = 'test-nonce';
			$_POST['sll_scope'] = $network ? 'site' : 'network';
			try { $pro_admin->handle_license(); } catch ( AdminTestStop $stop ) { admin_check( 'denied:400' === $stop->getMessage(), 'A changed scope cannot alter a license.' ); }
		}
		$target = $network ? 'sll_admin_network_options' : 'sll_admin_options';
		$GLOBALS[ $target ]['sll_pro_license'] = array( 'status' => 'expired', 'secret' => 'never-render-license-secret', 'domain_id' => 'dom_test', 'site_url' => $network ? network_home_url() : home_url() );
		$_GET = array( 'tab' => 'license' );
		ob_start(); $admin->render(); $html = ob_get_clean();
		admin_check( str_contains( $html, 'Your Pro license has expired.' ) && str_contains( $html, 'Check license' ), 'Expired licenses have a clear message and a renewal check.' );
		admin_check( ! str_contains( $html, 'never-render-license-secret' ) && 1 === substr_count( $html, '<form ' ) && ! str_contains( $html, 'Save settings' ), 'The license page has one form and does not reveal the activation secret.' );
		admin_check( str_contains( $html, 'View plans' ), 'Free can show plans without an active license.' );
		$GLOBALS[ $target ]['sll_pro_license']['status'] = 'active';
		ob_start(); $admin->render(); $licensed_html = ob_get_clean();
		admin_check( ! str_contains( $licensed_html, 'View plans' ) && str_contains( $licensed_html, 'install the Pro ZIP' ), 'An active license shows installation instructions instead of another purchase offer.' );
		$GLOBALS[ $target ]['sll_pro_license']['status'] = 'expired';
		$GLOBALS['sll_commerce_scripts'] = array();
		$_GET['tab'] = 'connection';
		$pro_admin->enqueue( 'settings_page_simple-ldap-login' );
		admin_check( ! $GLOBALS['sll_commerce_scripts'], 'Other tabs do not load the SDK components.' );
		$_GET['tab'] = 'license';
		$pro_admin->enqueue( 'dashboard' );
		admin_check( ! $GLOBALS['sll_commerce_scripts'], 'Other admin screens do not load the SDK components.' );
		$pro_admin->enqueue( 'settings_page_simple-ldap-login' );
		admin_check( count( $GLOBALS['sll_commerce_scripts'] ) === 3 && ! str_contains( implode( '', $GLOBALS['sll_commerce_scripts'] ), 'https://' ), 'The license screen loads only local scripts before purchase consent.' );
		$config = json_decode( substr( $GLOBALS['sll_commerce_config'], strlen( 'window.sllCommerce = ' ), -1 ), true );
		admin_check( $scope === $config['scope'] && 'test-nonce' === $config['nonce'] && 'expired' === $config['license']['status'], 'The SDK receives the correct scope, nonce, and saved status.' );
		admin_check( ! str_contains( $GLOBALS['sll_commerce_config'], 'never-render-license-secret' ), 'The SDK configuration does not contain the activation secret.' );
		$GLOBALS[ $target ]['sll_pro_license']['site_url'] = 'https://copied.test';
		$pro_admin->enqueue( 'settings_page_simple-ldap-login' );
		$config = json_decode( substr( $GLOBALS['sll_commerce_config'], strlen( 'window.sllCommerce = ' ), -1 ), true );
		admin_check( ! $config['license']['registered'], 'A copied site can enter its own license key in the SDK component.' );
		unset( $GLOBALS[ $target ]['sll_pro_license'] );
	}
	foreach ( array( 'ad', 'ol' ) as $directory ) {
		$settings->directory = $directory;
		foreach ( array( 'connection', 'advanced' ) as $tab ) {
			$_GET = array( 'tab' => $tab );
			ob_start(); $admin->render(); $html = ob_get_clean();
			preg_match_all( '/<div class="sll-field" data-sll-directory="(ad|ol)"( hidden)?>/', $html, $fields, PREG_SET_ORDER );
			admin_check( count( $fields ) > 0, 'Directory-specific fields have visibility controls.' );
			foreach ( $fields as $field ) {
				admin_check( ( $directory !== $field[1] ) === isset( $field[2] ), 'Only relevant directory fields are visible without JavaScript.' );
			}
		}
	}
	$settings->directory = 'ad';
	admin_check( 0 === $client->calls, 'Rendering all screens makes no LDAP request.' );
	$settings->network = false;
	foreach ( array( 'selected', 'legacy', 'off' ) as $mode ) {
		$settings->recovery = $mode;
		$_GET = array( 'tab' => 'access' );
		ob_start(); $admin->render(); $html = ob_get_clean();
		admin_check( str_contains( $html, 'value="' . $mode . '" selected="selected"' ), 'The recovery control displays the saved ' . $mode . ' mode.' );
		admin_check( str_contains( $html, 'value="1" selected="selected"' ), 'Saved account choices remain visible in ' . $mode . ' mode.' );
	}
	$settings->recovery = 'selected';
	$settings->network = true;
	$GLOBALS['sll_admin_super_admins'] = array();
	$user_queries = $GLOBALS['sll_admin_user_queries'];
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( $user_queries === $GLOBALS['sll_admin_user_queries'] && ! str_contains( $html, 'Network Admin (network-admin)' ), 'An empty network administrator list does not query all users.' );
	unset( $GLOBALS['sll_admin_super_admins'] );
	$settings->network = false;
	$GLOBALS['sll_admin_allowed'] = false;
	admin_check( 'denied:403' === admin_post( $admin, array() ), 'A user without permission cannot save or test.' );
	$GLOBALS['sll_admin_allowed'] = true;
	admin_check( 'nonce' === admin_post( $admin, array( '_wpnonce' => 'wrong' ) ), 'A request without a valid nonce is rejected.' );
	admin_check( 'denied:400' === admin_post( $admin, array( 'sll_scope' => 'network' ) ), 'A request cannot change the settings scope.' );
	admin_check( array() === $settings->saved && 0 === $client->calls, 'Rejected requests do not save settings or call LDAP.' );
	$result = admin_post( $admin, array( 'sll_settings' => array( 'base_dn' => 'dc=example' ) ) );
	admin_check( false !== strpos( $result, 'sll_status=saved' ) && array( 'base_dn' => 'dc=example' ) === $settings->saved, 'A save passes only submitted settings to the store.' );
	$advanced_input = array( 'timeout' => '10', 'allow_plaintext' => '0' );
	$result = admin_post( $admin, array( 'tab' => 'advanced', 'sll_settings' => $advanced_input ) );
	admin_check( $advanced_input === $settings->saved && str_contains( $result, 'tab=advanced' ), 'An Advanced save submits only its fields and returns to Advanced.' );
	$recovery_input = array( 'recovery_mode' => 'selected', 'recovery_user_ids' => array( '', '1', '2' ) );
	admin_post( $admin, array( 'tab' => 'access', 'sll_settings' => $recovery_input ) );
	admin_check( $recovery_input === $settings->saved, 'The handler passes selected recovery IDs to settings validation.' );
	$result = admin_post( $admin, array( 'sll_task' => 'test_connection' ) );
	admin_check( false !== strpos( $result, 'sll_status=connection_ok' ) && 1 === $client->calls, 'An explicit connection test calls LDAP once.' );
	$password = "a\\b'c";
	$result = admin_post( $admin, array( 'sll_task' => 'test_login', 'sll_test_username' => 'alice', 'sll_test_password' => addslashes( $password ) ) );
	admin_check( false !== strpos( $result, 'sll_status=login_ok' ) && array( 'alice', $password ) === $client->credentials, 'A login test retains exact password bytes.' );
	admin_check( false === strpos( $result, $password ), 'The redirect never includes a test password.' );
	$settings->network = true;
	$result = admin_post( $admin, array( 'sll_scope' => 'network' ) );
	admin_check( false !== strpos( $result, '/wp-admin/network/settings.php' ), 'A network save returns to network settings.' );
	$GLOBALS['sll_admin_broken_secret'] = true;
	$_GET = array( 'tab' => 'connection' );
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( false !== strpos( $html, 'The saved LDAP search password cannot be read.' ) && false === strpos( $html, 'never-render-this-secret' ), 'An unreadable secret has a safe repair notice.' );
	$GLOBALS['sll_admin_hooks'] = array();
	$settings->mappings = array();
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( ! str_contains( $html, 'Your saved settings use SSO or custom user fields.' ), 'Free password-only settings do not show the Pro migration notice.' );
	$settings->sso = true;
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( str_contains( $html, 'Install Simple LDAP Login Pro to use these features.' ), 'Free reports saved SSO settings that require Pro.' );
	$settings->sso = false;
	$settings->mappings = array( array( 'telephoneNumber', 'phone', 'string' ) );
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( str_contains( $html, 'Your settings are kept.' ), 'Free reports saved custom fields without deleting their settings.' );
	define( 'SLL_FEATURES_LOADED', true );
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( ! str_contains( $html, 'Your saved settings use SSO or custom user fields.' ), 'Pro does not show the missing-feature notice.' );
	define( 'SLL_DISABLE_LDAP', true );
	$settings->enabled = true;
	ob_start(); $admin->render(); $html = ob_get_clean();
	admin_check( false !== strpos( $html, 'LDAP login and SSO are disabled by SLL_DISABLE_LDAP' ), 'The recovery switch shows the real runtime state.' );
	echo 'Admin checks passed: ' . $GLOBALS['sll_admin_checks'] . ".\n";
}
