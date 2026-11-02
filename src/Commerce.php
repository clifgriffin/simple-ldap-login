<?php
/**
 * PaidCommunities purchase and license controls.
 *
 * @package SimpleLdapLogin
 */

namespace SimpleLdapLogin;

use PaidCommunities\WordPress\Assets\AssetsApi;
use WP_Error;

/** Use the SDK components with checked WordPress handlers. */
final class Commerce {

	/**
	 * Settings source.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Set the settings source.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/** Register controls on the plugin settings screen. */
	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'sll_settings_tabs', array( $this, 'tabs' ), 20 );
		add_action( 'sll_settings_notices', array( $this, 'license_notice' ) );
		add_action( 'sll_settings_page_license', array( $this, 'license' ) );
		add_action( 'admin_post_sll_license', array( $this, 'handle_license' ) );
		add_action( 'wp_ajax_sll_license', array( $this, 'ajax' ) );
	}

	/**
	 * Add license or purchase controls after the feature tabs.
	 *
	 * @param array $tabs Settings tabs.
	 * @return array Settings tabs.
	 */
	public function tabs( array $tabs ): array {
		$last            = array_intersect_key(
			$tabs,
			array(
				'advanced' => true,
				'help'     => true,
				'support'  => true,
			)
		);
		$tabs            = array_diff_key( $tabs, $last );
		$tabs['license'] = isset( $tabs['sso'] ) ? __( 'License', 'simple-ldap-login' ) : '✨ ' . __( 'Go Pro', 'simple-ldap-login' );
		return array_merge( $tabs, $last );
	}

	/** Show the effect of an inactive license on the settings screen. */
	public function license_notice(): void {
		if ( ! defined( 'SLL_FEATURES_LOADED' ) ) {
			return;
		}
		$license = new License( $this->settings );
		$data    = $license->data();
		if ( $license->is_active() && empty( $data['error'] ) ) {
			return;
		}
		if ( $license->is_active() ) {
			$message = __( 'Could not check your license. Features in your last verified plan remain active. Try again on the License tab.', 'simple-ldap-login' );
		} elseif ( 'expired' === ( $data['status'] ?? '' ) ) {
			$message = __( 'Your license has expired. Paid features are off. Renew your license, then select Check license on the License tab.', 'simple-ldap-login' );
		} elseif ( 'active' === ( $data['status'] ?? '' ) ) {
			$message = __( 'Your license plan has not been verified. Select Check license on the License tab. Paid features stay off until the plan is verified.', 'simple-ldap-login' );
		} else {
			$message = __( 'Activate your Pro or Enterprise license on the License tab to use paid features.', 'simple-ldap-login' );
		}
		?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Show purchases in Free and a separate license form in Pro.
	 *
	 * @param Admin $admin Shared form controls.
	 */
	public function license( Admin $admin ): void {
		$license         = new License( $this->settings );
		$data            = $license->data();
		$unverified_plan = 'active' === ( $data['status'] ?? '' ) && ! $license->is_active();
		if ( ! defined( 'SLL_FEATURES_LOADED' ) ) {
			$features = array(
				array(
					'networking',
					__( 'Backup LDAP servers', 'simple-ldap-login' ),
					__( 'Try the next server if a directory server cannot be reached. All servers must use the same directory.', 'simple-ldap-login' ),
				),
				array(
					'lock',
					__( 'Single sign-on (SSO)', 'simple-ldap-login' ),
					__( 'Give staff one less password prompt. Sign in through your web server with Kerberos, NTLM, or other server authentication.', 'simple-ldap-login' ),
				),
				array(
					'groups',
					__( 'Group roles', 'simple-ldap-login' ),
					__( 'Turn directory groups into WordPress roles at each login. Give editors, authors, and other teams the access they need.', 'simple-ldap-login' ),
				),
				array(
					'admin-users',
					__( 'Profile updates', 'simple-ldap-login' ),
					__( 'Keep names, email addresses, and websites current. Copy directory values to existing WordPress profiles at login.', 'simple-ldap-login' ),
				),
				array(
					'id-alt',
					__( 'Custom user fields', 'simple-ldap-login' ),
					__( 'Put phone numbers, departments, and other LDAP fields to work in WordPress. Map text and number fields for your theme or plugins.', 'simple-ldap-login' ),
				),
				array(
					'download',
					__( 'Scheduled account import', 'simple-ldap-login' ),
					__( 'Have accounts ready before the first login. Import directory users each hour, with filters for groups and directory location.', 'simple-ldap-login' ),
				),
				array(
					'shield',
					__( 'Scheduled access removal', 'simple-ldap-login' ),
					__( 'Check managed accounts each hour. Suspend access and end sessions when directory checks confirm removal or lost access. Keep user content.', 'simple-ldap-login' ),
				),
				array(
					'networking',
					__( 'Multisite membership', 'simple-ldap-login' ),
					__( 'Manage access across your WordPress network. Use directory groups to assign site membership and roles, then remove access when groups change.', 'simple-ldap-login' ),
				),
				array(
					'list-view',
					__( 'Audit history and CSV export', 'simple-ldap-login' ),
					__( 'See login results, role changes, imports, and access changes in one place. Set record retention and export the history to CSV.', 'simple-ldap-login' ),
				),
			);
			?>
			<section class="sll-pro" aria-labelledby="sll-pro-title">
				<div class="sll-pro-hero">
					<p class="sll-pro-label"><?php esc_html_e( 'Simple LDAP Login Pro and Enterprise', 'simple-ldap-login' ); ?></p>
					<h2 id="sll-pro-title"><?php esc_html_e( 'Choose your LDAP plan.', 'simple-ldap-login' ); ?></h2>
					<p><?php esc_html_e( 'Pro adds single sign-on and user data updates. Enterprise adds scheduled account management, multisite membership rules, and audit history.', 'simple-ldap-login' ); ?></p>
					<p class="sll-pro-note"><?php esc_html_e( 'Both plans cover one site. Your existing LDAP settings stay in place.', 'simple-ldap-login' ); ?></p>
				</div>
				<?php
				foreach ( array(
					'pro'        => __( 'Pro — $99/year', 'simple-ldap-login' ),
					'enterprise' => __( 'Enterprise — $499/year', 'simple-ldap-login' ),
				) as $plan => $title ) :
					?>
					<div class="sll-pro-included">
						<h2><?php echo esc_html( $title ); ?></h2>
						<p><?php echo 'pro' === $plan ? esc_html__( 'All Free features, plus:', 'simple-ldap-login' ) : esc_html__( 'Everything in Pro, plus:', 'simple-ldap-login' ); ?></p>
					</div>
					<div class="sll-pro-features">
						<?php foreach ( array_slice( $features, 'pro' === $plan ? 0 : 5, 'pro' === $plan ? 5 : 4 ) as $feature ) : ?>
							<div class="sll-pro-feature">
								<span class="dashicons dashicons-<?php echo esc_attr( $feature[0] ); ?>" aria-hidden="true"></span>
								<div>
									<h3><?php echo esc_html( $feature[1] ); ?></h3>
									<p><?php echo esc_html( $feature[2] ); ?></p>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
					<?php if ( ! $unverified_plan && ( ! $license->is_active() || ( 'enterprise' === $plan && ! $license->is_enterprise() ) ) ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( self::checkout_url( $plan ) ); ?>" rel="noreferrer"><?php echo 'pro' === $plan ? esc_html__( 'Buy Pro', 'simple-ldap-login' ) : ( $license->is_active() ? esc_html__( 'Get Enterprise', 'simple-ldap-login' ) : esc_html__( 'Buy Enterprise', 'simple-ldap-login' ) ); ?></a></p>
					<?php endif; ?>
				<?php endforeach; ?>
				<p class="description"><?php esc_html_e( 'Scheduled tasks use WordPress cron and need site traffic or a system task. Single sign-on requires web server authentication. Multisite rules require network activation.', 'simple-ldap-login' ); ?></p>
			</section>
			<?php if ( $unverified_plan ) : ?>
				<p><?php esc_html_e( 'Your saved license needs a plan check. Install Pro, then select Check license on the License tab.', 'simple-ldap-login' ); ?></p>
			<?php endif; ?>
			<p><?php esc_html_e( 'After purchase, replace Free with the Pro ZIP from your receipt. Then activate your license on the License tab in Pro. Your LDAP settings stay in place.', 'simple-ldap-login' ); ?></p>
			<p class="sll-commerce-disclaimer"><?php esc_html_e( 'PaidCommunities handles checkout. It receives your IP address, browser details, and the information you enter at checkout. LDAP credentials and user records are not sent.', 'simple-ldap-login' ); ?></p>
			<?php
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice.
		$result = isset( $_GET['sll_license_result'] ) && is_string( $_GET['sll_license_result'] ) ? sanitize_key( wp_unslash( $_GET['sll_license_result'] ) ) : '';
		if ( in_array( $result, array( 'ok', 'error' ), true ) ) {
			?>
			<div class="notice <?php echo 'ok' === $result ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo 'ok' === $result ? esc_html__( 'License status updated.', 'simple-ldap-login' ) : esc_html__( 'Could not verify the license. Check your key and connection, then try again.', 'simple-ldap-login' ); ?></p></div>
			<?php
		}
		$admin->card_start( __( 'License', 'simple-ldap-login' ), __( 'Enter the license key from your purchase receipt.', 'simple-ldap-login' ) );
		$labels = array(
			'active'   => __( 'Plan not verified', 'simple-ldap-login' ),
			'expired'  => __( 'Expired', 'simple-ldap-login' ),
			'inactive' => __( 'Inactive', 'simple-ldap-login' ),
		);
		?>
		<p><strong><?php esc_html_e( 'License status', 'simple-ldap-login' ); ?>:</strong> <?php echo esc_html( $license->is_active() ? __( 'Active', 'simple-ldap-login' ) : ( $labels[ $data['status'] ?? '' ] ?? __( 'Not activated', 'simple-ldap-login' ) ) ); ?></p>
		<?php if ( $license->is_active() ) : ?>
			<p><strong><?php esc_html_e( 'Current plan', 'simple-ldap-login' ); ?>:</strong> <?php echo $license->is_enterprise() ? esc_html__( 'Enterprise', 'simple-ldap-login' ) : esc_html__( 'Pro', 'simple-ldap-login' ); ?></p>
			<?php if ( ! $license->is_enterprise() ) : ?>
				<p><?php esc_html_e( 'Enterprise adds scheduled account import and access removal, multisite membership rules, and audit history with CSV export.', 'simple-ldap-login' ); ?></p>
				<p><?php esc_html_e( 'Get Enterprise opens checkout for a new license. It does not change or cancel your Pro subscription. Manage that subscription separately through PaidCommunities. After purchase, deactivate this key, choose Enterprise, and activate the new key.', 'simple-ldap-login' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( self::checkout_url( 'enterprise' ) ); ?>" rel="noreferrer"><?php esc_html_e( 'Get Enterprise', 'simple-ldap-login' ); ?></a></p>
			<?php endif; ?>
		<?php endif; ?>
		<div class="sll-field">
			<label class="sll-field-label" for="sll-license-plan"><?php esc_html_e( 'Purchased plan', 'simple-ldap-login' ); ?></label>
			<div>
				<select id="sll-license-plan" name="sll_license_plan" form="sll-license-form" aria-describedby="sll-license-plan-help">
					<option value="pro" <?php selected( $license->plan(), 'pro' ); ?>><?php esc_html_e( 'Pro', 'simple-ldap-login' ); ?></option>
					<option value="enterprise" <?php selected( $license->plan(), 'enterprise' ); ?>><?php esc_html_e( 'Enterprise', 'simple-ldap-login' ); ?></option>
				</select>
				<p class="description" id="sll-license-plan-help"><?php esc_html_e( 'Choose the plan on your purchase receipt. This choice is used only when activating a key. A license check verifies which features you can use.', 'simple-ldap-login' ); ?></p>
			</div>
		</div>
		<div id="sll-license-component" class="sll-license-component" hidden></div>
		<form id="sll-license-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
			<?php wp_nonce_field( 'sll_license' ); ?>
			<input type="hidden" name="action" value="sll_license" />
			<input type="hidden" name="sll_scope" value="<?php echo $this->settings->is_network() ? 'network' : 'site'; ?>" />
			<div id="sll-license-fallback">
			<div class="sll-field">
				<label class="sll-field-label" for="sll-license-key"><?php esc_html_e( 'License key', 'simple-ldap-login' ); ?></label>
				<input class="regular-text" type="password" id="sll-license-key" name="sll_license_key" value="" autocomplete="new-password" />
			</div>
			<p>
				<button class="button button-primary" type="submit" name="sll_license_task" value="activate"><?php esc_html_e( 'Activate license', 'simple-ldap-login' ); ?></button>
				<?php if ( ! empty( $data['domain_id'] ) ) : ?>
					<button class="button" type="submit" name="sll_license_task" value="deactivate"><?php esc_html_e( 'Deactivate license', 'simple-ldap-login' ); ?></button>
				<?php endif; ?>
			</p>
			</div>
			<?php if ( ! empty( $data['domain_id'] ) ) : ?>
				<p><button class="button" type="submit" name="sll_license_task" value="check"><?php esc_html_e( 'Check license', 'simple-ldap-login' ); ?></button></p>
			<?php endif; ?>
		</form>
		<?php
		$admin->card_end();
		?>
		<p class="sll-commerce-disclaimer">
			<?php esc_html_e( 'PaidCommunities handles licenses. Activation sends your license key, site domain, plugin version, and server IP address. License checks send site details. LDAP credentials and user records are not sent.', 'simple-ldap-login' ); ?>
		</p>
		<?php
	}

	/**
	 * Check permissions, then run a supported license action.
	 *
	 * @param bool $ajax Whether the request came from the SDK component.
	 * @return true|WP_Error License result.
	 */
	private function run_task( bool $ajax ) {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method || ! current_user_can( $this->settings->is_network() ? 'manage_network_options' : 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to change these settings.', 'simple-ldap-login' ), '', array( 'response' => 403 ) );
		}
		if ( $ajax ) {
			check_ajax_referer( 'sll_license', 'nonce' );
		} else {
			check_admin_referer( 'sll_license' );
		}
		$scope = isset( $_POST['sll_scope'] ) && is_string( $_POST['sll_scope'] ) ? sanitize_key( wp_unslash( $_POST['sll_scope'] ) ) : '';
		if ( ( $this->settings->is_network() ? 'network' : 'site' ) !== $scope ) {
			wp_die( esc_html__( 'The settings scope has changed. Open the settings page and try again.', 'simple-ldap-login' ), '', array( 'response' => 400 ) );
		}
		$task    = isset( $_POST['sll_license_task'] ) && is_string( $_POST['sll_license_task'] ) ? sanitize_key( wp_unslash( $_POST['sll_license_task'] ) ) : '';
		$license = new License( $this->settings );
		if ( 'activate' === $task ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- License validates the exact key before sending it.
			$key  = isset( $_POST['sll_license_key'] ) && is_string( $_POST['sll_license_key'] ) ? trim( wp_unslash( $_POST['sll_license_key'] ) ) : '';
			$plan = isset( $_POST['sll_license_plan'] ) ? ( is_string( $_POST['sll_license_plan'] ) ? sanitize_key( wp_unslash( $_POST['sll_license_plan'] ) ) : '' ) : 'pro';
			return $license->activate( $key, $plan );
		} elseif ( 'check' === $task ) {
			return $license->refresh();
		} elseif ( 'deactivate' === $task ) {
			return $license->deactivate();
		} else {
			return new WP_Error( 'sll_license_action', __( 'Select a license action.', 'simple-ldap-login' ) );
		}
	}

	/** Handle the form when JavaScript is unavailable. */
	public function handle_license(): void {
		$result = $this->run_task( false );
		$url    = $this->settings->is_network() ? network_admin_url( 'settings.php?page=simple-ldap-login' ) : admin_url( 'options-general.php?page=simple-ldap-login' );
		wp_safe_redirect(
			add_query_arg(
				array(
					'tab'                => 'license',
					'sll_license_result' => is_wp_error( $result ) ? 'error' : 'ok',
				),
				$url
			)
		);
		exit;
	}

	/** Supply the response shape expected by the SDK activation component. */
	public function ajax(): void {
		$result = $this->run_task( true );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => 'license_error',
					'message' => $result->get_error_message(),
				),
				400
			);
		}
		wp_send_json_success(
			array(
				'license' => $this->component_license(),
				'notice'  => array(
					'code'    => 'license_updated',
					'message' => __( 'License status updated.', 'simple-ldap-login' ),
				),
			)
		);
	}

	/**
	 * Return display data without the activation secret.
	 *
	 * @return array SDK component license data.
	 */
	private function component_license(): array {
		$license    = new License( $this->settings );
		$data       = $license->data();
		$site       = untrailingslashit( $this->settings->is_network() ? network_home_url() : home_url() );
		$registered = ! empty( $data['domain_id'] ) && ( $data['site_url'] ?? '' ) === $site;
		return array(
			'status'      => $registered && ( 'active' !== ( $data['status'] ?? '' ) || $license->is_active() ) ? ( $data['status'] ?? 'inactive' ) : 'inactive',
			'registered'  => $registered,
			'license_key' => $registered ? '••••••••' : '',
		);
	}

	/**
	 * Load local SDK components only on the license page.
	 *
	 * @param string $hook Admin screen hook.
	 */
	public function enqueue( string $hook ): void {
		if ( ! defined( 'SLL_FEATURES_LOADED' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( ! str_ends_with( $hook, '_page_simple-ldap-login' ) || 'license' !== $tab || ! current_user_can( $this->settings->is_network() ? 'manage_network_options' : 'manage_options' ) ) {
			return;
		}
		$path   = 'vendor/paidcommunities/wordpress-sdk/';
		$assets = new AssetsApi( dirname( __DIR__ ) . '/' . $path, plugins_url( $path, SLL_FILE ), SLL_VERSION );
		$assets->register_script( 'paidcommunities-wp-api', 'build/api.js' );
		$assets->register_script( 'paidcommunities-wp-components', 'build/components.js' );
		$assets->register_style( 'paidcommunities-wp-components', 'build/styles.css' );
		wp_enqueue_style( 'paidcommunities-wp-components' );
		wp_enqueue_script( 'simple-ldap-login-commerce', plugins_url( 'assets/commerce.js', SLL_FILE ), array( 'wp-element', 'paidcommunities-wp-components' ), SLL_VERSION . '.' . filemtime( dirname( __DIR__ ) . '/assets/commerce.js' ), true );
		$config = array(
			'basename' => plugin_basename( SLL_FILE ),
			'nonce'    => wp_create_nonce( 'sll_license' ),
			'scope'    => $this->settings->is_network() ? 'network' : 'site',
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'license'  => $this->component_license(),
			'i18n'     => array(
				'licenseKey'        => __( 'License key', 'simple-ldap-login' ),
				'activateLicense'   => __( 'Activate license', 'simple-ldap-login' ),
				'deactivateLicense' => __( 'Deactivate license', 'simple-ldap-login' ),
				'activateMsg'       => __( 'Activating license…', 'simple-ldap-login' ),
				'deactivateMsg'     => __( 'Deactivating license…', 'simple-ldap-login' ),
				'license_error'     => __( 'License error', 'simple-ldap-login' ),
				'license_updated'   => __( 'License updated', 'simple-ldap-login' ),
				'licenseError'      => __( 'Could not verify the license. Check your key and connection, then try again.', 'simple-ldap-login' ),
			),
		);
		wp_add_inline_script( 'simple-ldap-login-commerce', 'window.sllCommerce = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}

	/**
	 * Open checkout for the selected annual plan.
	 *
	 * @param string $plan Pro or Enterprise.
	 * @return string Hosted checkout URL.
	 */
	public static function checkout_url( string $plan = 'pro' ): string {
		return 'https://checkout.paidcommunities.com/checkout/' . ( 'enterprise' === $plan ? License::ENTERPRISE_PRODUCT : License::PRODUCT ) . '?plan=' . ( 'enterprise' === $plan ? License::ENTERPRISE_PLAN : License::PRO_PLAN );
	}
}
