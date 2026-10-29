<?php
/**
 * Settings screens and explicit connection tests.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

defined( 'ABSPATH' ) || exit;

/**
 * Use the same settings flow for a site and a network.
 */
final class Admin {

	/**
	 * Settings store.
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
	 * Settings screen hook.
	 *
	 * @var string
	 */
	private string $screen = '';

	/**
	 * Set the shared services.
	 *
	 * @param Settings    $settings Settings store.
	 * @param Ldap_Client $client Directory client.
	 */
	public function __construct( Settings $settings, Ldap_Client $client ) {
		$this->settings = $settings;
		$this->client   = $client;
	}

	/**
	 * Register the admin hooks.
	 */
	public function register(): void {
		add_action( $this->settings->is_network() ? 'network_admin_menu' : 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_sll_save', array( $this, 'handle_post' ) );
	}

	/**
	 * Add a settings page.
	 */
	public function menu(): void {
		$this->screen = add_submenu_page(
			$this->settings->is_network() ? 'settings.php' : 'options-general.php',
			__( 'Simple LDAP Login', 'simple-ldap-login' ),
			__( 'Simple LDAP Login', 'simple-ldap-login' ),
			$this->capability(),
			'simple-ldap-login',
			array( $this, 'render' )
		);
	}

	/**
	 * Load styles only on this screen.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue( string $hook ): void {
		if ( $this->screen && $this->screen === $hook ) {
			wp_enqueue_style( 'simple-ldap-login-admin', plugins_url( 'assets/admin.css', SLL_FILE ), array(), SLL_VERSION );
		}
	}

	/**
	 * Process a save or a test. Never create a user or a login session.
	 */
	public function handle_post(): void {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method || ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'simple-ldap-login' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'sll_save_settings' );

		$scope          = isset( $_POST['sll_scope'] ) && is_string( $_POST['sll_scope'] ) ? sanitize_key( wp_unslash( $_POST['sll_scope'] ) ) : '';
		$expected_scope = $this->settings->is_network() ? 'network' : 'site';
		if ( $scope !== $expected_scope ) {
			wp_die( esc_html__( 'The settings scope has changed. Open the settings page and try again.', 'simple-ldap-login' ), '', array( 'response' => 400 ) );
		}

		$tab    = $this->tab( $_POST );
		$task   = isset( $_POST['sll_task'] ) && is_string( $_POST['sll_task'] ) ? sanitize_key( wp_unslash( $_POST['sll_task'] ) ) : '';
		$status = 'error';

		if ( 'save' === $task ) {
			// Settings validates each supported field. Passwords must retain their exact bytes.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validation is in Settings::save().
			$input  = isset( $_POST['sll_settings'] ) && is_array( $_POST['sll_settings'] ) ? wp_unslash( $_POST['sll_settings'] ) : array();
			$result = $this->settings->save( $input );
			if ( is_wp_error( $result ) ) {
				set_transient( $this->message_key(), $result->get_error_message(), MINUTE_IN_SECONDS );
			} else {
				$status = 'saved';
			}
		} elseif ( 'test_connection' === $task ) {
			$status = is_wp_error( $this->client->test_connection() ) ? 'connection_failed' : 'connection_ok';
		} elseif ( 'test_login' === $task ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Ldap_Client validates and escapes directory login names.
			$username = isset( $_POST['sll_test_username'] ) && is_string( $_POST['sll_test_username'] ) ? trim( wp_unslash( $_POST['sll_test_username'] ) ) : '';
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Passwords must not be changed by text sanitization.
			$password = isset( $_POST['sll_test_password'] ) && is_string( $_POST['sll_test_password'] ) ? wp_unslash( $_POST['sll_test_password'] ) : '';
			$status   = is_wp_error( $this->client->authenticate( $username, $password ) ) ? 'login_failed' : 'login_ok';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'        => $tab,
					'sll_status' => $status,
				),
				$this->url()
			)
		);
		exit;
	}

	/**
	 * Render the settings page.
	 */
	public function render(): void {
		if ( ! current_user_can( $this->capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to view these settings.', 'simple-ldap-login' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
		$tab      = $this->tab( $_GET );
		$recovery = defined( 'SLL_DISABLE_LDAP' ) && SLL_DISABLE_LDAP;
		$enabled  = Settings::is_true( $this->settings->get( 'enabled' ) ) && ! $recovery;
		$tabs     = $this->tabs();
		?>
		<div class="wrap sll-admin">
			<h1><?php esc_html_e( 'Simple LDAP Login', 'simple-ldap-login' ); ?></h1>
			<?php $this->notice(); ?>
			<div class="sll-overview">
				<div>
					<p class="sll-eyebrow"><?php echo $this->settings->is_network() ? esc_html__( 'Network settings', 'simple-ldap-login' ) : esc_html__( 'Site settings', 'simple-ldap-login' ); ?></p>
					<h2><?php esc_html_e( 'LDAP settings', 'simple-ldap-login' ); ?></h2>
					<p><?php esc_html_e( 'Set up directory access and test a login before you enable LDAP.', 'simple-ldap-login' ); ?></p>
				</div>
				<div class="sll-status" role="status">
					<span class="sll-dot <?php echo $enabled ? 'sll-dot-on' : ''; ?>" aria-hidden="true"></span>
					<?php echo $enabled ? esc_html__( 'LDAP login is on', 'simple-ldap-login' ) : esc_html__( 'LDAP login is off', 'simple-ldap-login' ); ?>
				</div>
			</div>
			<?php if ( $recovery ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'SLL_DISABLE_LDAP is active. LDAP login is off for recovery. Remove this constant from wp-config.php when the settings are ready. Saved settings are kept.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( $this->settings->has_legacy_access() && ( ! defined( 'SLL_FEATURES_LOADED' ) || ! SLL_FEATURES_LOADED ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This package does not contain server sign-on or custom user data mappings. This installation can use the Legacy package at no charge. Install the Legacy package to keep those features. Saved settings are kept.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( defined( 'SLL_EDITION' ) && 'transition' === SLL_EDITION ) : ?>
				<div class="notice notice-info inline"><p><?php esc_html_e( 'Server sign-on and custom user data mappings remain available without payment during this transition. This installation can use the Legacy package at no charge when it is available.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( '' !== (string) $this->settings->get( 'sso_search_user_password' ) && is_wp_error( Secrets::decrypt( (string) $this->settings->get( 'sso_search_user_password' ) ) ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The saved search account password cannot be read. Enter it again on the Connection tab and save the settings. Searches that use this account need a valid password.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! extension_loaded( 'ldap' ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The PHP LDAP extension is not installed. Ask your host to install it before you enable LDAP login.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'plain' === $this->settings->get( 'transport' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'This connection sends passwords without encryption. Select StartTLS or LDAPS after your directory server is ready.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper sll-tabs" aria-label="<?php esc_attr_e( 'LDAP settings', 'simple-ldap-login' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $this->url() ) ); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="sll-settings-form">
				<?php $this->form_fields( $tab, 'save' ); ?>
				<?php
				switch ( $tab ) {
					case 'access':
						$this->access();
						break;
					case 'profile':
						$this->profile();
						break;
					case 'help':
						$this->help();
						break;
					default:
						if ( 'connection' === $tab ) {
							$this->connection();
						} else {
							do_action( 'sll_settings_tab_' . $tab, $this );
						}
				}
				?>
				<div class="sll-save-bar">
					<?php submit_button( __( 'Save settings', 'simple-ldap-login' ), 'primary', 'submit', false ); ?>
					<span><?php esc_html_e( 'Settings on other tabs are kept.', 'simple-ldap-login' ); ?></span>
				</div>
			</form>
			<?php
			if ( 'help' === $tab || 'connection' === $tab ) {
				$this->tests( $tab ); }
			?>
		</div>
		<?php
	}

	/**
	 * Render connection settings.
	 */
	private function connection(): void {
		$this->card_start( __( 'Directory connection', 'simple-ldap-login' ), __( 'Use the details from your directory administrator. Save these settings before you run a test.', 'simple-ldap-login' ) );
		$this->checkbox( 'enabled', __( 'Enable LDAP login', 'simple-ldap-login' ), __( 'Use your directory to check WordPress logins.', 'simple-ldap-login' ) );
		$this->select(
			'directory',
			__( 'Directory type', 'simple-ldap-login' ),
			array(
				'ad' => __( 'Active Directory', 'simple-ldap-login' ),
				'ol' => __( 'OpenLDAP / other LDAP directory', 'simple-ldap-login' ),
			)
		);
		$this->textarea( 'domain_controllers', __( 'Directory servers', 'simple-ldap-login' ), __( 'Enter one host name or IP address per line. The plugin tries these servers in order. Do not include a URL scheme or a path.', 'simple-ldap-login' ) );
		$this->input( 'base_dn', __( 'Base DN', 'simple-ldap-login' ), __( 'Search base, for example: DC=example,DC=org.', 'simple-ldap-login' ) );
		$this->input( 'account_suffix', __( 'Account suffix', 'simple-ldap-login' ), __( 'Active Directory example: @example.org. Leave this blank when the full login name is used.', 'simple-ldap-login' ) );
		$this->input( 'login_domain', __( 'Login domain', 'simple-ldap-login' ), __( 'Optional domain for DOMAIN\\username logins.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Transport and search', 'simple-ldap-login' ), __( 'Use an encrypted connection with a trusted server certificate. Certificate checks cannot be disabled here.', 'simple-ldap-login' ) );
		$this->select(
			'transport',
			__( 'Connection security', 'simple-ldap-login' ),
			array(
				'starttls' => __( 'StartTLS (recommended)', 'simple-ldap-login' ),
				'ldaps'    => __( 'LDAPS', 'simple-ldap-login' ),
				'plain'    => __( 'Plain LDAP (no encryption)', 'simple-ldap-login' ),
			)
		);
		$this->checkbox( 'allow_plaintext', __( 'Allow plain LDAP', 'simple-ldap-login' ), __( 'I understand that plain LDAP sends passwords without encryption. Use it only on a trusted network.', 'simple-ldap-login' ) );
		$this->input( 'ldap_port', __( 'Port', 'simple-ldap-login' ), __( 'Usually 389 for StartTLS and 636 for LDAPS.', 'simple-ldap-login' ), 'number' );
		$this->input( 'timeout', __( 'Connection timeout', 'simple-ldap-login' ), __( 'Maximum time in seconds for a directory operation.', 'simple-ldap-login' ), 'number' );
		$this->input( 'ol_login', __( 'Login attribute', 'simple-ldap-login' ), __( 'OpenLDAP usually uses uid. Active Directory uses sAMAccountName.', 'simple-ldap-login' ) );
		$this->checkbox( 'search_sub_ous', __( 'Search child organizational units', 'simple-ldap-login' ), __( 'Include directory entries below the base DN.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Directory search account', 'simple-ldap-login' ), __( 'Use an account with read access only. Directory searches can use this account.', 'simple-ldap-login' ) );
		$this->input( 'sso_search_user', __( 'Search account', 'simple-ldap-login' ), __( 'Enter the bind DN or the full Active Directory account name.', 'simple-ldap-login' ) );
		$this->input( 'sso_search_user_password', __( 'Search account password', 'simple-ldap-login' ), __( 'Leave this blank to keep the saved password. Saved passwords are never displayed.', 'simple-ldap-login' ), 'password' );
		$this->card_end();
	}

	/**
	 * Render access rules.
	 */
	private function access(): void {
		$this->card_start( __( 'Login rules', 'simple-ldap-login' ), __( 'Choose who can log in and whether the plugin creates WordPress accounts.', 'simple-ldap-login' ) );
		$this->checkbox( 'high_security', __( 'Require LDAP authentication', 'simple-ldap-login' ), __( 'Reject local passwords for other users. Local administrators retain a recovery login.', 'simple-ldap-login' ) );
		$this->checkbox( 'create_users', __( 'Create WordPress accounts', 'simple-ldap-login' ), __( 'Create an account after the directory password and group checks pass.', 'simple-ldap-login' ) );
		$roles = array();
		foreach ( wp_roles()->roles as $key => $role ) {
			$roles[ $key ] = translate_user_role( $role['name'] );
		}
		$this->select( 'role', __( 'New account role', 'simple-ldap-login' ), $roles, __( 'This role applies to new accounts. Existing account roles are kept.', 'simple-ldap-login' ) );
		$this->textarea( 'groups', __( 'Required groups', 'simple-ldap-login' ), __( 'Enter one group per line. A user must belong to at least one group. Leave this blank to allow all valid directory users.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Group search', 'simple-ldap-login' ), __( 'Change these fields only when your directory requires a different group search.', 'simple-ldap-login' ) );
		$this->input( 'group_base_dn', __( 'Group base DN', 'simple-ldap-login' ), __( 'Optional search base for groups. Leave this blank to use the base DN.', 'simple-ldap-login' ) );
		$this->input( 'ol_group', __( 'Group name attribute', 'simple-ldap-login' ), __( 'Usually cn.', 'simple-ldap-login' ) );
		$this->input( 'group_uid', __( 'Group member attribute', 'simple-ldap-login' ), __( 'OpenLDAP example: memberUid. Use member or uniqueMember for groups that store user DNs.', 'simple-ldap-login' ) );
		$this->card_end();
	}

	/**
	 * Render user data mappings.
	 */
	private function profile(): void {
		$this->card_start( __( 'Profile attributes', 'simple-ldap-login' ), __( 'Choose the LDAP attributes for new WordPress accounts.', 'simple-ldap-login' ) );
		$this->input( 'user_first_name_attribute', __( 'First name attribute', 'simple-ldap-login' ) );
		$this->input( 'user_last_name_attribute', __( 'Last name attribute', 'simple-ldap-login' ) );
		$this->input( 'user_email_attribute', __( 'Email attribute', 'simple-ldap-login' ) );
		$this->input( 'user_url_attribute', __( 'Website attribute', 'simple-ldap-login' ) );
		$this->card_end();
		do_action( 'sll_profile_settings', $this );
	}

	/**
	 * Render test instructions and data retention.
	 */
	private function help(): void {
		$this->card_start( __( 'Test before you enable LDAP', 'simple-ldap-login' ), __( 'Keep this administrator session open. Save the connection settings, test the connection, then test a directory login below.', 'simple-ldap-login' ) );
		?>
		<p><?php esc_html_e( 'The login test checks the directory password and required groups. It does not create a WordPress account, change a profile, or start a login session.', 'simple-ldap-login' ); ?></p>
		<p><?php esc_html_e( 'After the tests pass, use a second browser to test the normal WordPress login. Keep a separate local administrator account for recovery.', 'simple-ldap-login' ); ?></p>
		<h3><?php esc_html_e( 'Recovery', 'simple-ldap-login' ); ?></h3>
		<p><?php esc_html_e( 'If you cannot log in, use your hosting file manager to rename the simple-ldap-login plugin folder. Then log in with a local administrator account. Rename the folder back and correct the settings before you activate the plugin again.', 'simple-ldap-login' ); ?></p>
		<p><?php esc_html_e( 'LDAP protocol version 3 is required. Configure trusted certificate authorities on the server for StartTLS and LDAPS.', 'simple-ldap-login' ); ?></p>
		<?php
		$this->card_end();
		$this->card_start( __( 'Data retention', 'simple-ldap-login' ), __( 'Deactivation keeps your settings. Choose what happens when you delete the plugin.', 'simple-ldap-login' ) );
		$this->checkbox( 'delete_on_uninstall', __( 'Delete settings on uninstall', 'simple-ldap-login' ), __( 'Remove plugin settings and saved credentials when the plugin is deleted. WordPress user accounts are kept.', 'simple-ldap-login' ) );
		$this->card_end();
	}

	/**
	 * Render explicit tests with no calls on page load.
	 *
	 * @param string $tab Current tab.
	 */
	private function tests( string $tab ): void {
		?>
		<section class="sll-card" aria-labelledby="sll-tests-title">
			<div class="sll-card-heading">
				<h2 id="sll-tests-title"><?php esc_html_e( 'Check your connection', 'simple-ldap-login' ); ?></h2>
				<p><?php esc_html_e( 'These tests use your saved settings and run only when you select a test button.', 'simple-ldap-login' ); ?></p>
			</div>
			<div class="sll-tests">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<?php $this->form_fields( $tab, 'test_connection' ); ?>
					<h3><?php esc_html_e( '1. Directory connection', 'simple-ldap-login' ); ?></h3>
					<p><?php esc_html_e( 'Check the connection and the search account, if configured.', 'simple-ldap-login' ); ?></p>
					<?php submit_button( __( 'Test connection', 'simple-ldap-login' ), 'secondary', 'submit', false ); ?>
				</form>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" autocomplete="off">
					<?php $this->form_fields( $tab, 'test_login' ); ?>
					<h3><?php esc_html_e( '2. Directory login', 'simple-ldap-login' ); ?></h3>
					<p><?php esc_html_e( 'Check a directory password and the required groups.', 'simple-ldap-login' ); ?></p>
					<p><label for="sll-test-username"><?php esc_html_e( 'Directory username', 'simple-ldap-login' ); ?></label><input id="sll-test-username" name="sll_test_username" type="text" autocomplete="off" required /></p>
					<p><label for="sll-test-password"><?php esc_html_e( 'Directory password', 'simple-ldap-login' ); ?></label><input id="sll-test-password" name="sll_test_password" type="password" autocomplete="new-password" required /></p>
					<?php submit_button( __( 'Test directory login', 'simple-ldap-login' ), 'secondary', 'submit', false ); ?>
				</form>
			</div>
		</section>
		<?php
	}

	/**
	 * Start a settings card.
	 *
	 * @param string $title Card heading.
	 * @param string $description Card description.
	 */
	public function card_start( string $title, string $description ): void {
		?>
		<section class="sll-card">
			<div class="sll-card-heading"><h2><?php echo esc_html( $title ); ?></h2><p><?php echo esc_html( $description ); ?></p></div>
			<div class="sll-fields">
		<?php
	}

	/**
	 * Close a settings card.
	 */
	public function card_end(): void {
		?>
		</div></section>
		<?php
	}

	/**
	 * Render an input.
	 *
	 * @param string $key Setting key.
	 * @param string $label Label.
	 * @param string $description Help text.
	 * @param string $type Input type.
	 */
	public function input( string $key, string $label, string $description = '', string $type = 'text' ): void {
		?>
		<div class="sll-field">
			<label for="sll-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<input class="regular-text" id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $type ); ?>" value="<?php echo 'password' === $type ? '' : esc_attr( (string) $this->settings->get( $key ) ); ?>" aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help" autocomplete="<?php echo 'password' === $type ? 'new-password' : 'off'; ?>" />
				<p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a checkbox with an explicit false value.
	 *
	 * @param string $key Setting key.
	 * @param string $label Label.
	 * @param string $description Help text.
	 */
	public function checkbox( string $key, string $label, string $description ): void {
		?>
		<div class="sll-field">
			<span class="sll-field-label" id="sll-<?php echo esc_attr( $key ); ?>-label"><?php echo esc_html( $label ); ?></span>
			<div>
				<input type="hidden" name="sll_settings[<?php echo esc_attr( $key ); ?>]" value="0" />
				<label class="sll-check" id="sll-<?php echo esc_attr( $key ); ?>-help" for="sll-<?php echo esc_attr( $key ); ?>"><input id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" type="checkbox" value="1" aria-labelledby="sll-<?php echo esc_attr( $key ); ?>-label" aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help" <?php checked( Settings::is_true( $this->settings->get( $key ) ) ); ?> /> <?php echo esc_html( $description ); ?></label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a select.
	 *
	 * @param string $key Setting key.
	 * @param string $label Label.
	 * @param array  $options Allowed choices.
	 * @param string $description Help text.
	 */
	private function select( string $key, string $label, array $options, string $description = '' ): void {
		?>
		<div class="sll-field">
			<label for="sll-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<select id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help">
					<?php foreach ( $options as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $this->settings->get( $key ), $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a list or metadata textarea.
	 *
	 * @param string $key Setting key.
	 * @param string $label Label.
	 * @param string $description Help text.
	 */
	public function textarea( string $key, string $label, string $description ): void {
		$value = $this->settings->get( $key );
		if ( is_array( $value ) ) {
			$value = implode( "\n", array_map( static fn( $line ) => is_array( $line ) ? implode( ':', $line ) : (string) $line, $value ) );
		}
		?>
		<div class="sll-field">
			<label for="sll-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<textarea class="large-text code" id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" rows="4" aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_textarea( (string) $value ); ?></textarea>
				<p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Add fixed context and a nonce to a form.
	 *
	 * @param string $tab Tab name.
	 * @param string $task Task name.
	 */
	private function form_fields( string $tab, string $task ): void {
		wp_nonce_field( 'sll_save_settings' );
		?>
		<input type="hidden" name="action" value="sll_save" />
		<input type="hidden" name="sll_scope" value="<?php echo $this->settings->is_network() ? 'network' : 'site'; ?>" />
		<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
		<input type="hidden" name="sll_task" value="<?php echo esc_attr( $task ); ?>" />
		<?php
	}

	/**
	 * Display a fixed result. Raw directory errors must not reach the screen.
	 */
	private function notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result code; no state changes.
		$status   = isset( $_GET['sll_status'] ) && is_string( $_GET['sll_status'] ) ? sanitize_key( wp_unslash( $_GET['sll_status'] ) ) : '';
		$messages = array(
			'saved'             => __( 'Settings saved.', 'simple-ldap-login' ),
			'connection_ok'     => __( 'Connection test passed.', 'simple-ldap-login' ),
			'connection_failed' => __( 'Connection test failed. Check the saved host, port, encryption, certificate trust, and search account.', 'simple-ldap-login' ),
			'login_ok'          => __( 'Directory login and group checks passed. No WordPress account or session was created.', 'simple-ldap-login' ),
			'login_failed'      => __( 'Login test failed. Check the saved settings, directory credentials, and required groups.', 'simple-ldap-login' ),
			'error'             => __( 'The settings could not be saved. Check the fields and try again.', 'simple-ldap-login' ),
		);
		if ( ! isset( $messages[ $status ] ) ) {
			return;
		}
		$message = $messages[ $status ];
		if ( 'error' === $status ) {
			$validation = get_transient( $this->message_key() );
			delete_transient( $this->message_key() );
			if ( is_string( $validation ) && '' !== $validation ) {
				$message = $validation;
			}
		}
		$success = in_array( $status, array( 'saved', 'connection_ok', 'login_ok' ), true );
		?>
		<div class="notice <?php echo $success ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Get an allowed tab.
	 *
	 * @param array $request Request values.
	 * @return string
	 */
	private function tab( array $request ): string {
		$tab = isset( $request['tab'] ) && is_string( $request['tab'] ) ? sanitize_key( wp_unslash( $request['tab'] ) ) : '';
		return array_key_exists( $tab, $this->tabs() ) ? $tab : 'connection';
	}

	/**
	 * Get the settings tabs registered by this package.
	 *
	 * @return array Tab names and labels.
	 */
	private function tabs(): array {
		return apply_filters(
			'sll_settings_tabs',
			array(
				'connection' => __( 'Connection', 'simple-ldap-login' ),
				'access'     => __( 'Access', 'simple-ldap-login' ),
				'profile'    => __( 'User data', 'simple-ldap-login' ),
				'help'       => __( 'Tests and help', 'simple-ldap-login' ),
			)
		);
	}

	/**
	 * Get the settings page URL.
	 *
	 * @return string
	 */
	private function url(): string {
		return $this->settings->is_network() ? network_admin_url( 'settings.php?page=simple-ldap-login' ) : admin_url( 'options-general.php?page=simple-ldap-login' );
	}

	/**
	 * Get the required capability.
	 *
	 * @return string
	 */
	private function capability(): string {
		return $this->settings->is_network() ? 'manage_network_options' : 'manage_options';
	}

	/**
	 * Keep validation messages private to the current administrator.
	 *
	 * @return string
	 */
	private function message_key(): string {
		return 'sll_admin_message_' . get_current_user_id();
	}
}
