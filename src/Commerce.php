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
			)
		);
		$tabs            = array_diff_key( $tabs, $last );
		$tabs['license'] = isset( $tabs['sso'] ) ? __( 'License', 'simple-ldap-login' ) : __( 'Get Pro', 'simple-ldap-login' );
		return array_merge( $tabs, $last );
	}

	/** Show the effect of an inactive license on the settings screen. */
	public function license_notice(): void {
		$license = new License( $this->settings );
		$data    = $license->data();
		if ( ! $data && ! defined( 'SLL_FEATURES_LOADED' ) ) {
			return;
		}
		if ( $license->is_active() && empty( $data['error'] ) ) {
			return;
		}
		if ( $license->is_active() ) {
			$message = __( 'Could not check your license. Pro features remain active. Try again on the License tab.', 'simple-ldap-login' );
		} elseif ( 'expired' === ( $data['status'] ?? '' ) ) {
			$message = __( 'Your Pro license has expired. Single sign-on and custom field updates are off. Renew your license, then select Check license on the License tab.', 'simple-ldap-login' );
		} else {
			$message = __( 'Activate a Pro license on the License tab to use single sign-on and custom user fields.', 'simple-ldap-login' );
		}
		?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Render a separate form so license keys never enter LDAP settings.
	 *
	 * @param Admin $admin Shared form controls.
	 */
	public function license( Admin $admin ): void {
		$license = new License( $this->settings );
		$data    = $license->data();
		if ( ! defined( 'SLL_FEATURES_LOADED' ) ) {
			$admin->card_start( __( 'Simple LDAP Login Pro', 'simple-ldap-login' ), __( 'Give staff fewer login prompts and keep user details in sync with LDAP.', 'simple-ldap-login' ) );
			?>
			<h3><?php esc_html_e( 'Single sign-on (SSO)', 'simple-ldap-login' ); ?></h3>
			<p><?php esc_html_e( 'Let staff open WordPress without another login prompt. Requires authentication on your web server, such as Kerberos or NTLM.', 'simple-ldap-login' ); ?></p>
			<h3><?php esc_html_e( 'Custom user fields', 'simple-ldap-login' ); ?></h3>
			<p><?php esc_html_e( 'Copy phone numbers, departments, and other LDAP fields to WordPress at login. Use these values in your theme or other plugins.', 'simple-ldap-login' ); ?></p>
			<?php
			if ( ! $license->is_active() ) {
				?>
			<p><button class="button button-primary" type="button" id="sll-load-pricing"><?php esc_html_e( 'View plans', 'simple-ldap-login' ); ?></button> <a href="<?php echo esc_url( self::checkout_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open checkout in a new tab', 'simple-ldap-login' ); ?></a></p>
			<p class="description"><?php esc_html_e( 'Plans and checkout use PaidCommunities. It receives your IP address and browser details.', 'simple-ldap-login' ); ?></p>
			<p id="sll-pricing-status" role="status" hidden></p>
			<div id="sll-pricing"></div>
			<?php } ?>
			<p><?php esc_html_e( 'After purchase, install the Pro ZIP from your receipt. Your LDAP settings will stay in place.', 'simple-ldap-login' ); ?></p>
			<?php
			$admin->card_end();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only result notice.
		$result = isset( $_GET['sll_license_result'] ) && is_string( $_GET['sll_license_result'] ) ? sanitize_key( wp_unslash( $_GET['sll_license_result'] ) ) : '';
		if ( in_array( $result, array( 'ok', 'error' ), true ) ) {
			?>
			<div class="notice <?php echo 'ok' === $result ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo 'ok' === $result ? esc_html__( 'License status updated.', 'simple-ldap-login' ) : esc_html__( 'Could not verify the license. Check your key and connection, then try again.', 'simple-ldap-login' ); ?></p></div>
			<?php
		}
		$admin->card_start( __( 'Pro license', 'simple-ldap-login' ), __( 'Enter the license key from your purchase receipt.', 'simple-ldap-login' ) );
		$labels = array(
			'active'   => __( 'Not activated', 'simple-ldap-login' ),
			'expired'  => __( 'Expired', 'simple-ldap-login' ),
			'inactive' => __( 'Inactive', 'simple-ldap-login' ),
		);
		?>
		<p><strong><?php esc_html_e( 'License status', 'simple-ldap-login' ); ?>:</strong> <?php echo esc_html( $license->is_active() ? __( 'Active', 'simple-ldap-login' ) : ( $labels[ $data['status'] ?? '' ] ?? __( 'Not activated', 'simple-ldap-login' ) ) ); ?></p>
		<details>
			<summary><?php esc_html_e( 'Data shared with PaidCommunities', 'simple-ldap-login' ); ?></summary>
			<p><?php esc_html_e( 'Activation sends your license key, site domain, plugin version, and server IP address to PaidCommunities. License checks send the same site details.', 'simple-ldap-login' ); ?></p>
		</details>
		<div id="sll-license-component" class="sll-license-component" hidden></div>
		<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
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
			$key = isset( $_POST['sll_license_key'] ) && is_string( $_POST['sll_license_key'] ) ? trim( wp_unslash( $_POST['sll_license_key'] ) ) : '';
			return $license->activate( $key );
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
		$data       = ( new License( $this->settings ) )->data();
		$site       = untrailingslashit( $this->settings->is_network() ? network_home_url() : home_url() );
		$registered = ! empty( $data['domain_id'] ) && ( $data['site_url'] ?? '' ) === $site;
		return array(
			'status'      => $registered ? ( $data['status'] ?? 'inactive' ) : 'inactive',
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
		wp_enqueue_script( 'simple-ldap-login-commerce', plugins_url( 'assets/commerce.js', SLL_FILE ), array( 'wp-element', 'paidcommunities-wp-components' ), SLL_VERSION, true );
		$config = array(
			'basename' => plugin_basename( SLL_FILE ),
			'nonce'    => wp_create_nonce( 'sll_license' ),
			'scope'    => $this->settings->is_network() ? 'network' : 'site',
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'product'  => License::PRODUCT,
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
				'pricingTitle'      => __( 'Pro plans', 'simple-ldap-login' ),
				'pricingLoading'    => __( 'Loading plans…', 'simple-ldap-login' ),
				'pricingError'      => __( 'Could not load plans. Open checkout in a new tab.', 'simple-ldap-login' ),
			),
		);
		wp_add_inline_script( 'simple-ldap-login-commerce', 'window.sllCommerce = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
	}

	/**
	 * Use the product pricing URL used by the official purchase SDK.
	 *
	 * @return string Hosted pricing URL.
	 */
	public static function checkout_url(): string {
		return 'https://checkout.paidcommunities.com/product_pricing/' . License::PRODUCT;
	}
}
