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
			wp_enqueue_style( 'simple-ldap-login-admin', plugins_url( 'assets/admin.css', SLL_FILE ), array(), SLL_VERSION . '.' . filemtime( dirname( __DIR__ ) . '/assets/admin.css' ) );
			wp_enqueue_script( 'simple-ldap-login-admin', plugins_url( 'assets/admin.js', SLL_FILE ), array(), SLL_VERSION . '.' . filemtime( dirname( __DIR__ ) . '/assets/admin.js' ), true );
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
		$tabs     = $this->tabs();
		?>
		<div class="wrap sll-admin" data-sll-directory-type="<?php echo esc_attr( (string) $this->settings->get( 'directory' ) ); ?>">
			<h1><?php esc_html_e( 'Simple LDAP Login', 'simple-ldap-login' ); ?></h1>
			<?php $this->notice(); ?>
			<?php do_action( 'sll_settings_notices' ); ?>
			<div class="sll-overview">
				<div>
					<p class="sll-eyebrow"><?php echo $this->settings->is_network() ? esc_html__( 'Network settings', 'simple-ldap-login' ) : esc_html__( 'Site settings', 'simple-ldap-login' ); ?></p>
					<h2><?php esc_html_e( 'LDAP login settings', 'simple-ldap-login' ); ?></h2>
					<p><?php esc_html_e( 'Connect to LDAP, then test a login before enabling authentication.', 'simple-ldap-login' ); ?></p>
				</div>

			</div>
			<?php if ( $recovery ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'LDAP login and SSO are disabled by SLL_DISABLE_LDAP in wp-config.php.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! defined( 'SLL_FEATURES_LOADED' ) && ( Settings::is_true( $this->settings->get( 'sso_enabled' ) ) || ! empty( $this->settings->get( 'user_meta_data' ) ) ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'Your saved settings use SSO or custom user fields. Install Simple LDAP Login Pro to use these features. Your settings are kept.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( '' !== (string) $this->settings->get( 'sso_search_user_password' ) && is_wp_error( Secrets::decrypt( (string) $this->settings->get( 'sso_search_user_password' ) ) ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'The saved LDAP search password cannot be read. Enter it again on the Connection tab.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! extension_loaded( 'ldap' ) ) : ?>
				<div class="notice notice-error inline"><p><?php esc_html_e( 'PHP LDAP is missing. Ask your host to install it before enabling LDAP login.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<?php if ( 'plain' === $this->settings->get( 'transport' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( 'LDAP passwords are sent without encryption. Use StartTLS or LDAPS if your server supports it.', 'simple-ldap-login' ); ?></p></div>
			<?php endif; ?>
			<nav class="nav-tab-wrapper sll-tabs" aria-label="<?php esc_attr_e( 'LDAP login settings', 'simple-ldap-login' ); ?>">
				<?php foreach ( $tabs as $key => $label ) : ?>
					<a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', $key, $this->url() ) ); ?>" <?php echo $tab === $key ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<?php if ( has_action( 'sll_settings_page_' . $tab ) ) : ?>
				<?php do_action( 'sll_settings_page_' . $tab, $this ); ?>
			<?php else : ?>
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
					case 'advanced':
						$this->advanced();
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
			<?php endif; ?>
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
		$this->card_start( __( 'LDAP connection', 'simple-ldap-login' ), '' );
		$this->checkbox( 'enabled', __( 'LDAP login', 'simple-ldap-login' ), __( 'Enable LDAP authentication.', 'simple-ldap-login' ) );
		$this->select(
			'directory',
			__( 'Directory type', 'simple-ldap-login' ),
			array(
				'ad' => __( 'Active Directory', 'simple-ldap-login' ),
				'ol' => __( 'OpenLDAP / other LDAP directory', 'simple-ldap-login' ),
			)
		);
		$this->textarea( 'domain_controllers', __( 'LDAP servers', 'simple-ldap-login' ), __( 'One hostname or IP address per line. Servers are tried in order.', 'simple-ldap-login' ) );
		$this->input( 'base_dn', __( 'Base DN', 'simple-ldap-login' ), __( 'Where to search for users. Example: DC=example,DC=org.', 'simple-ldap-login' ) );
		$this->input( 'account_suffix', __( 'Username suffix', 'simple-ldap-login' ), __( 'Added to usernames without a domain. Example: @example.org.', 'simple-ldap-login' ) );
		$this->input( 'login_domain', __( 'Windows domain', 'simple-ldap-login' ), __( 'Short domain name, such as EXAMPLE. Takes priority over the username suffix.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Connection security', 'simple-ldap-login' ), __( 'TLS requires a trusted server certificate.', 'simple-ldap-login' ) );
		$this->select(
			'transport',
			__( 'Connection security', 'simple-ldap-login' ),
			array(
				'starttls' => __( 'StartTLS (recommended)', 'simple-ldap-login' ),
				'ldaps'    => __( 'LDAPS', 'simple-ldap-login' ),
				'plain'    => __( 'Unencrypted LDAP', 'simple-ldap-login' ),
			),
			__( 'Unencrypted LDAP requires permission on the Advanced tab.', 'simple-ldap-login' )
		);
		$this->input( 'ldap_port', __( 'Port', 'simple-ldap-login' ), __( '389 for StartTLS; 636 for LDAPS.', 'simple-ldap-login' ), 'number' );
		$this->checkbox( 'search_sub_ous', __( 'Search subfolders', 'simple-ldap-login' ), __( 'Search all organizational units below the Base DN.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'LDAP search account', 'simple-ldap-login' ), __( 'An LDAP account with read access to users and groups.', 'simple-ldap-login' ) );
		$this->input( 'sso_search_user', __( 'Search account', 'simple-ldap-login' ), __( 'LDAP bind DN or account name.', 'simple-ldap-login' ) );
		$this->input( 'sso_search_user_password', __( 'Search account password', 'simple-ldap-login' ), __( 'Leave blank to keep the saved password.', 'simple-ldap-login' ), 'password' );
		$this->card_end();
	}

	/**
	 * Render access rules.
	 */
	private function access(): void {
		$this->card_start( __( 'Login rules', 'simple-ldap-login' ), '' );
		$this->checkbox( 'high_security', __( 'Require LDAP passwords', 'simple-ldap-login' ), __( 'Block WordPress passwords except for recovery access.', 'simple-ldap-login' ) );
		$this->select(
			'recovery_mode',
			__( 'Local recovery access', 'simple-ldap-login' ),
			array(
				'selected' => __( 'Selected administrators', 'simple-ldap-login' ),
				'legacy'   => is_multisite() ? __( 'All super administrators (legacy)', 'simple-ldap-login' ) : __( 'All administrators (legacy)', 'simple-ldap-login' ),
				'off'      => __( 'Off', 'simple-ldap-login' ),
			),
			__( 'Choose who can use a WordPress password when LDAP passwords are required.', 'simple-ldap-login' )
		);
		$this->recovery_accounts();
		$this->checkbox( 'create_users', __( 'Automatic account creation', 'simple-ldap-login' ), __( 'Create a WordPress account on the first successful LDAP login.', 'simple-ldap-login' ) );
		$roles = array();
		foreach ( wp_roles()->roles as $key => $role ) {
			$roles[ $key ] = translate_user_role( $role['name'] );
		}
		$this->select( 'role', __( 'Default role', 'simple-ldap-login' ), $roles, __( 'For new accounts only.', 'simple-ldap-login' ) );
		$this->textarea( 'groups', __( 'Required groups', 'simple-ldap-login' ), __( 'One group name per line. Users must belong to at least one. Leave blank to allow all LDAP users.', 'simple-ldap-login' ) );
		$this->card_end();
		do_action( 'sll_access_settings', $this );
	}

	/** Render the existing administrator accounts allowed in this settings scope. */
	private function recovery_accounts(): void {
		$query = array( 'blog_id' => get_current_blog_id(), 'capability' => 'manage_options', 'orderby' => 'display_name', 'order' => 'ASC' );
		if ( $this->settings->is_network() ) {
			$query = array( 'blog_id' => 0, 'login__in' => get_super_admins(), 'orderby' => 'display_name', 'order' => 'ASC' );
		}
		$users = isset( $query['login__in'] ) && ! $query['login__in'] ? array() : get_users( $query );
		$ids   = (array) $this->settings->get( 'recovery_user_ids' );
		?>
		<div class="sll-field">
			<label for="sll-recovery_user_ids"><?php esc_html_e( 'Recovery accounts', 'simple-ldap-login' ); ?></label>
			<div>
				<input type="hidden" name="sll_settings[recovery_user_ids][]" value="" />
				<select id="sll-recovery_user_ids" name="sll_settings[recovery_user_ids][]" multiple size="5" aria-describedby="sll-recovery_user_ids-help">
					<?php foreach ( $users as $user ) : ?>
						<?php if ( $this->settings->can_recover( $user ) ) : ?>
							<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( in_array( $user->ID, $ids, true ), true ); ?>><?php echo esc_html( $user->display_name . ' (' . $user->user_login . ')' ); ?></option>
						<?php endif; ?>
					<?php endforeach; ?>
				</select>
				<p class="description" id="sll-recovery_user_ids-help"><?php esc_html_e( 'For Selected administrators only. Use separate local accounts with WordPress passwords. Hold Ctrl or Command to select multiple accounts.', 'simple-ldap-login' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render user data mappings.
	 */
	private function profile(): void {
		do_action( 'sll_profile_settings', $this );
	}

	/** Render settings whose defaults suit most installations. */
	private function advanced(): void {
		$this->card_start( __( 'Connection options', 'simple-ldap-login' ), __( 'Change these only if the default values do not work with your directory.', 'simple-ldap-login' ) );
		$this->input( 'timeout', __( 'LDAP timeout (seconds)', 'simple-ldap-login' ), __( 'Maximum wait for an LDAP operation. Default: 5 seconds.', 'simple-ldap-login' ), 'number' );
		$this->input( 'ol_login', __( 'Login attribute', 'simple-ldap-login' ), __( 'Attribute used to find users. Default: uid.', 'simple-ldap-login' ) );
		$this->checkbox( 'allow_plaintext', __( 'Allow unencrypted LDAP', 'simple-ldap-login' ), __( 'Allow passwords to be sent without encryption.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Group search', 'simple-ldap-login' ), '' );
		$this->input( 'group_base_dn', __( 'Group base DN', 'simple-ldap-login' ), __( 'Leave blank to use the user Base DN.', 'simple-ldap-login' ) );
		$this->input( 'ol_group', __( 'Group name attribute', 'simple-ldap-login' ), __( 'Default: cn.', 'simple-ldap-login' ) );
		$this->input( 'group_uid', __( 'Group member attribute', 'simple-ldap-login' ), __( 'Default: memberUid. Use member or uniqueMember if groups store user DNs.', 'simple-ldap-login' ) );
		$this->card_end();
		$this->card_start( __( 'Profile attributes', 'simple-ldap-login' ), __( 'LDAP attributes copied to new WordPress accounts.', 'simple-ldap-login' ) );
		$this->input( 'user_first_name_attribute', __( 'First name attribute', 'simple-ldap-login' ) );
		$this->input( 'user_last_name_attribute', __( 'Last name attribute', 'simple-ldap-login' ) );
		$this->input( 'user_email_attribute', __( 'Email attribute', 'simple-ldap-login' ) );
		$this->input( 'user_url_attribute', __( 'Website attribute', 'simple-ldap-login' ) );
		$this->card_end();
		do_action( 'sll_advanced_settings', $this );
		$this->card_start( __( 'Data retention', 'simple-ldap-login' ), '' );
		$this->checkbox( 'delete_on_uninstall', __( 'Delete settings when removing the plugin', 'simple-ldap-login' ), __( 'Delete saved settings and LDAP credentials when WordPress deletes the plugin. Keep user accounts.', 'simple-ldap-login' ) );
		$this->card_end();
	}

	/**
	 * Render test and recovery instructions.
	 */
	private function help(): void {
		$this->card_start( __( 'Test LDAP login', 'simple-ldap-login' ), __( 'Save your settings, then run both tests. Keep this administrator session open.', 'simple-ldap-login' ) );
		?>
		<p><?php esc_html_e( 'Tests check the connection, password, and required groups. They do not create accounts or sign users in.', 'simple-ldap-login' ); ?></p>
		<p><?php esc_html_e( 'After both tests pass, enable LDAP and try logging in from another browser.', 'simple-ldap-login' ); ?></p>
		<h3><?php esc_html_e( 'Recovery', 'simple-ldap-login' ); ?></h3>
		<p><?php esc_html_e( 'If you are locked out, rename the simple-ldap-login folder using your hosting file manager. Log in with a WordPress administrator password, then restore the folder and correct the settings.', 'simple-ldap-login' ); ?></p>
		<p><?php esc_html_e( 'For certificate errors, ask your host to configure the trusted LDAP certificate authority.', 'simple-ldap-login' ); ?></p>
		<?php
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
				<h2 id="sll-tests-title"><?php esc_html_e( 'LDAP tests', 'simple-ldap-login' ); ?></h2>
				<p><?php esc_html_e( 'Tests use saved settings.', 'simple-ldap-login' ); ?></p>
			</div>
			<div class="sll-tests">
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
					<?php $this->form_fields( $tab, 'test_connection' ); ?>
					<h3><?php esc_html_e( '1. Test connection', 'simple-ldap-login' ); ?></h3>
					<p><?php esc_html_e( 'Connect to LDAP and test the search account, if set.', 'simple-ldap-login' ); ?></p>
					<?php submit_button( __( 'Test connection', 'simple-ldap-login' ), 'secondary', 'submit', false ); ?>
				</form>
				<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" autocomplete="off">
					<?php $this->form_fields( $tab, 'test_login' ); ?>
					<h3><?php esc_html_e( '2. Test login', 'simple-ldap-login' ); ?></h3>
					<p><?php esc_html_e( 'Check an LDAP username, password, and group membership.', 'simple-ldap-login' ); ?></p>
					<p><label for="sll-test-username"><?php esc_html_e( 'LDAP username', 'simple-ldap-login' ); ?></label><input id="sll-test-username" name="sll_test_username" type="text" autocomplete="off" required /></p>
					<p><label for="sll-test-password"><?php esc_html_e( 'LDAP password', 'simple-ldap-login' ); ?></label><input id="sll-test-password" name="sll_test_password" type="password" autocomplete="new-password" required /></p>
					<?php submit_button( __( 'Test LDAP login', 'simple-ldap-login' ), 'secondary', 'submit', false ); ?>
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
			<div class="sll-card-heading"><h2><?php echo esc_html( $title ); ?></h2><?php if ( '' !== $description ) : ?><p><?php echo esc_html( $description ); ?></p><?php endif; ?></div>
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
	 * Mark fields that apply to one directory type. Keep their saved values.
	 *
	 * @param string $key Setting key.
	 */
	private function directory_field( string $key ): void {
		$types = array( 'account_suffix' => 'ad', 'login_domain' => 'ad', 'search_sub_ous' => 'ol', 'ol_login' => 'ol', 'ol_group' => 'ol', 'group_uid' => 'ol' );
		if ( isset( $types[ $key ] ) ) {
			echo ' data-sll-directory="' . esc_attr( $types[ $key ] ) . '"';
			if ( $types[ $key ] !== $this->settings->get( 'directory' ) ) {
				echo ' hidden';
			}
		}
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
		<div class="sll-field"<?php $this->directory_field( $key ); ?>>
			<label for="sll-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<input class="regular-text" id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" type="<?php echo esc_attr( $type ); ?>" value="<?php echo 'password' === $type ? '' : esc_attr( (string) $this->settings->get( $key ) ); ?>" <?php if ( '' !== $description ) : ?>aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help"<?php endif; ?> autocomplete="<?php echo 'password' === $type ? 'new-password' : 'off'; ?>" />
				<?php if ( '' !== $description ) : ?><p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p><?php endif; ?>
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
		<div class="sll-field"<?php $this->directory_field( $key ); ?>>
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
		$current = 'recovery_mode' === $key ? $this->settings->recovery_mode() : $this->settings->get( $key );
		?>
		<div class="sll-field">
			<label for="sll-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
			<div>
				<select id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" <?php if ( '' !== $description ) : ?>aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help"<?php endif; ?>>
					<?php foreach ( $options as $value => $text ) : ?>
						<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $text ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( '' !== $description ) : ?><p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p><?php endif; ?>
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
				<textarea class="large-text code" id="sll-<?php echo esc_attr( $key ); ?>" name="sll_settings[<?php echo esc_attr( $key ); ?>]" rows="4" <?php if ( '' !== $description ) : ?>aria-describedby="sll-<?php echo esc_attr( $key ); ?>-help"<?php endif; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
				<?php if ( '' !== $description ) : ?><p class="description" id="sll-<?php echo esc_attr( $key ); ?>-help"><?php echo esc_html( $description ); ?></p><?php endif; ?>
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
			'connection_failed' => __( 'Cannot connect to LDAP. Check the server, port, TLS certificate, and search account.', 'simple-ldap-login' ),
			'login_ok'          => __( 'LDAP login test passed. No account was created.', 'simple-ldap-login' ),
			'login_failed'      => __( 'LDAP login test failed. Check the username, password, and required groups.', 'simple-ldap-login' ),
			'error'             => __( 'Could not save settings. Check the values and try again.', 'simple-ldap-login' ),
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
		$tabs = $this->tabs();
		// Keep old Free profile links useful after moving its attributes.
		if ( 'profile' === $tab && ! array_key_exists( 'profile', $tabs ) ) {
			return 'advanced';
		}
		return array_key_exists( $tab, $tabs ) ? $tab : 'connection';
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
				'advanced'   => __( 'Advanced', 'simple-ldap-login' ),
				'help'       => __( 'Troubleshooting', 'simple-ldap-login' ),
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
