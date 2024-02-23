<?php
/**
 * Online Payment Platform Gateway
 *
 * @package   online-payment-platform-gateway
 * @author    Ganga Kafle <kafleg.work@gmail.com>
 * @copyright 2023 Online Payment Platform Gateway
 * @license   MIT
 * @link      https://the-dev-company.com
 */

declare( strict_types=1 );

namespace OnlinePaymentPlatformGateway\Integrations\dokan;

use OnlinePaymentPlatformGateway\Integrations\OPPApi\Merchant;
use OnlinePaymentPlatformGateway\Config\Plugin;


/**
 * Class Dokan
 *
 * @package OnlinePaymentPlatformGateway\Integrations\dokan
 */
class Dokan {

	/**
	 * Plugin configuration.
	 *
	 * @var array $plugin Will be filled with data from the plugin config class.
	 * @see Plugin
	 */
	protected $plugin = array();

	/**
	 * Merchant.
	 *
	 * @var mixed $merchant Represents an instance of a merchant (type may vary).
	 */
	protected $merchant;

	/**
	 * Slug.
	 *
	 * @var string $slug Represents a string identifier.
	 */
	protected $slug;

	/**
	 * Initialize the class.
	 */
	public function init() {
		/**
		 * Integration classes instantiates before anything else
		 *
		 * @see Bootstrap::__construct
		 *
		 * Widget is registered via the app/general/widgets class, but it is also
		 * possible to register from this class
		 * @see Widgets
		 */
	}

	/**
	 * Sets up a new HTML widget instance.
	 */
	public function __construct() {
		$this->plugin = Plugin::init();

		$this->slug = $this->plugin->slug();

		$this->merchant = new Merchant();

		add_filter( 'dokan_withdraw_methods', array( $this, 'registerNewWithdrawMethod' ), 99 );

		add_filter( 'dokan_withdraw_method_icon', array( $this, 'getIcon' ), 10, 2 );

		add_action( 'dokan_store_profile_saved', array( $this, 'saveWithdrawMethodWise' ), 10, 2 );

		add_filter( 'dokan_payment_settings_required_fields', array( $this, 'addCustomWithdrawInPaymentMethodList' ), 10, 2 );

		add_filter( 'dokan_get_seller_active_withdraw_methods', array( $this, 'customMethodInActiveWithdrawMethod' ), 99, 2 );

		add_filter( 'dokan_withdraw_withdrawable_payment_methods', array( $this, 'includeMethodInWithdrawMethodSection' ) );

		add_action( 'admin_print_footer_scripts', array( $this, 'viewAdminWithdraw' ), 99 );

		// add_filter( 'dokan_get_seller_active_withdraw_methods', 'customMethodActiveWithdrawMethod', 99, 2 );

		// Remove payment nav form vendor dashboard
		add_filter( 'dokan_get_dashboard_nav', array( $this, 'dokanNav' ) );

		// remove step payment from setup wizard
		add_filter( 'dokan_seller_wizard_steps', array( $this, 'removePaymentStepFormSetupWizard' ) );
		add_filter( 'dokan_get_template_part', array( $this, 'dokan_get_template_part' ), 10, 3 );

	}

	/**
	 * Dokan Nav function.
	 *
	 * @param array $urls Vendor dashboard URLs.
	 *
	 * @return array
	 */
	public function dokanNav( $urls ) {
		$user_id = get_current_user_id();

		if ( ! dokan_is_seller_enabled( $user_id ) ) {
			unset( $urls['settings']['submenu']['payment'] );
		}

		return $urls;
	}

	/**
	 * Get template part.
	 *
	 * @param string $template Template file.
	 * @param string $slug Template slug.
	 * @param string $name Template name.
	 *
	 * @return string
	 */
	public function dokan_get_template_part( $template, $slug, $name ) {

		$plugin_template = $this->plugin->templatePath() . '/dokan/settings/payment.php';
		if ( $slug === 'settings/payment' && file_exists( $plugin_template ) && empty( $name ) ) {
			$template = $plugin_template;
		}
		return $template;
	}

	/**
	 * Register New Withdraw Method.
	 *
	 * @param array $methods Available withdrawal methods.
	 *
	 * @return array
	 */
	public function registerNewWithdrawMethod( $methods ) {
		$methods[ $this->slug ] = array(
			'title'    => esc_html__( 'Online Payment Platform Gateway', 'online-payment-platform-gateway' ),
			'callback' => array( $this, 'dokanWithdrawMethodCustom' ),
		);

		return $methods;
	}

	/**
	 * Get the Withdrawal method icon.
	 *
	 * @param string $method_icon Method icon URL.
	 * @param string $method_key Method key.
	 *
	 * @return string
	 */
	public function getIcon( $method_icon, $method_key ) {
		if ( $this->slug === $method_key ) {
			$method_icon = $this->plugin->url() . '/assets/public/images/online-payment-platform.png';
		}

		return $method_icon;
	}

	/**
	 * Dokan Withdraw Method Custom callback.
	 *
	 * @param array $store_settings Store settings.
	 *
	 * @return void
	 */
	public function dokanWithdrawMethodCustom( $store_settings ) {
		$email                    = isset( $store_settings['payment'][ $this->slug ]['email'] ) ? esc_attr( $store_settings['payment'][ $this->slug ]['email'] ) : '';
		$coc_nr                   = isset( $store_settings['payment'][ $this->slug ]['coc_nr'] ) ? esc_attr( $store_settings['payment'][ $this->slug ]['coc_nr'] ) : '';
		$agree_term_condition     = isset( $store_settings['payment'][ $this->slug ]['agree_term_condition'] ) ? esc_attr( $store_settings['payment'][ $this->slug ]['agree_term_condition'] ) : 'yes';
		$agree_term_condition_opp = isset( $store_settings['payment'][ $this->slug ]['agree_term_condition_opp'] ) ? esc_attr( $store_settings['payment'][ $this->slug ]['agree_term_condition_opp'] ) : 'yes';

		?>

		<div class="dokan-form-group">
			<div class="dokan-w8">
				<div class="dokan-input-group">
					<span class="dokan-input-group-addon"><?php esc_html_e( 'E-mail', 'online-payment-platform-gateway' ); ?></span>
					<input value="<?php echo esc_attr( $email ); ?>" name="settings[<?php echo esc_attr( $this->slug ); ?>][email]"
						   class="dokan-form-control email" placeholder="you@domain.com" type="text">
				</div>
			</div>
		</div>
		<div class="dokan-form-group">
			<div class="dokan-w8">
				<div class="dokan-input-group">
					<span class="dokan-input-group-addon"><?php esc_html_e( 'CoC Number', 'online-payment-platform-gateway' ); ?></span>
					<input value="<?php echo esc_attr( $coc_nr ); ?>" name="settings[<?php echo esc_attr( $this->slug ); ?>][coc_nr]"
						   class="dokan-form-control" type="text">
				</div>
			</div>
		</div>
		<div class="dokan-form-group">
			<label class="dokan-w3 dokan-control-label"><?php esc_html_e( 'Terms and Conditions', 'online-payment-platform-gateway' ); ?></label>
			<div class="dokan-w5 dokan-text-left">
				<div class="checkbox">
					<label>
						<input type="checkbox" name="settings[<?php echo esc_attr( $this->slug ); ?>][agree_term_condition]"
							   value="yes"<?php checked( $agree_term_condition, 'yes' ); ?>> <?php printf( /* translators: %s: the link */ esc_html__( 'I have read and agree to the website %s', 'online-payment-platform-gateway' ), sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( get_the_permalink( 3 ) ), esc_html__( ' terms and conditions', 'online-payment-platform-gateway' ) ) ); ?>
						<?php
						// echo wp_kses_post( wc_replace_policy_page_link_placeholders( 'I have read and agree to the website [terms] [privacy_policy]' ) );
						?>
					</label>
				</div>
				<div class="checkbox">
					<label>
						<input type="checkbox" name="settings[<?php echo esc_attr( $this->slug ); ?>][agree_term_condition_opp]"
							   value="yes"<?php checked( $agree_term_condition_opp, 'yes' ); ?>> <?php printf( /* translators: %s: the link */  esc_html__( 'I have read and agree to the OPP %s', 'online-payment-platform-gateway' ), sprintf( '<a href="%s">%s</a>', esc_url( 'https://onlinepaymentplatform.com/terms-policies' ), esc_html__( ' Terms & Policies', 'online-payment-platform-gateway' ) ) ); ?>
					</label>
				</div>
			</div>
		</div>
			<?php if ( dokan_is_seller_dashboard() ) : ?>
		<div class="dokan-form-group">

			<div class="dokan-w8">
				<?php // $this->createBankAccountButton(); ?>
				<?php $this->updateKycButton(); ?>
				<input name="dokan_update_payment_settings" type="hidden">
				<button class="ajax_prev disconnect dokan_payment_disconnect_btn dokan-btn dokan-btn-danger <?php echo empty( $email ) ? 'dokan-hide' : ''; ?>"
						type="button" name="settings[<?php echo esc_attr( $this->slug ); ?>][disconnect]">
					<?php esc_attr_e( 'Disconnect', 'online-payment-platform-gateway' ); ?>
				</button>
			</div>
		</div>
				<?php
		endif;

	}

	/**
	 * Save Withdraw Method.
	 *
	 * @param int   $store_id Store ID.
	 * @param array $dokan_settings Dokan settings.
	 *
	 * @return void
	 */
	public function saveWithdrawMethodWise( $store_id, $dokan_settings ) {

		if ( ! $store_id ) {
			return;
		}

		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'dokan_payment_settings_nonce' ) ) {
			return;
		}
		$setting = wp_unslash(  $_POST['settings'] );

		$email                    = sanitize_email( $setting[ $this->slug ]['email'] );
		$coc_nr                   = sanitize_text_field( $setting[ $this->slug ]['coc_nr'] );
		$agree_term_condition     = sanitize_text_field( $setting[ $this->slug ]['agree_term_condition'] );
		$agree_term_condition_opp = sanitize_text_field( $setting[ $this->slug ]['agree_term_condition_opp'] );

		if ( isset( $setting[ $this->slug ] ) && isset( $setting[ $this->slug ]['disconnect'] ) ) {
			$dokan_settings['payment'][ $this->slug ] = array();
			update_user_meta( $store_id, 'dokan_profile_settings', $dokan_settings );
			delete_user_meta( $store_id, 'opp_merchant_uid' );
			delete_user_meta( $store_id, 'opp_bankAccount_uid' );
			delete_user_meta( $store_id, 'opp_email' );

			return true;
		}

		if ( empty( $email ) || ! is_email( $email ) ) {
			wp_send_json_error( esc_html__( 'Invalid email', 'online-payment-platform-gateway' ) );
		} elseif ( empty( $coc_nr ) ) {
			wp_send_json_error( esc_html__( 'Invalid CoC Number', 'online-payment-platform-gateway' ) );
		} elseif ( $agree_term_condition !== 'yes' ) {
			wp_send_json_error( esc_html__( 'Please read and accept the terms and conditions.', 'online-payment-platform-gateway' ) );
		} elseif ( $agree_term_condition_opp !== 'yes' ) {
			wp_send_json_error( esc_html__( 'Please read and accept the terms and conditions of OPP.', 'online-payment-platform-gateway' ) );
		}

		$base_url = esc_url( site_url() . esc_html( wp_unslash(  $_POST['_wp_http_referer'] )  ) );

		if ( strpos( $base_url, '?' ) !== false ) {
			$base_url .= '&';
		} else {
			$base_url .= '?';
		}

		if ( isset( $_POST['settings'][ $this->slug ] ) && isset( $_POST['settings'][ $this->slug ]['email'] ) ) {
			$data = array(
				'emailaddress' => sanitize_email( $email ),
				'legal_name'   => $dokan_settings['store_name'],
				'type'         => 'business',
				'coc_nr'       => $coc_nr,
				'metadata'     => array(
					'store_id' => (string) $store_id,
				),
				'return_url'   => $base_url . 'createMerchant=true&store_id=' . $store_id,
				'notify_url'   => $base_url . 'createMerchant=true&store_id=' . $store_id,
			);

			$result = $this->merchant->create( $data );

			if ( $result['success'] ) {
				$create_bank_account = $this->merchant->createBankAccount( $result['data']->uid );

				if ( ! $create_bank_account['success'] ) {
					wp_send_json_error();
				}

				$dokan_settings['payment'][ $this->slug ] = array(
					'email'                    => sanitize_email( $email ),
					'coc_nr'                   => sanitize_text_field( $coc_nr ),
					'agree_term_condition'     => sanitize_text_field( $agree_term_condition ),
					'agree_term_condition_opp' => sanitize_text_field( $agree_term_condition_opp ),
					'opp_merchant_uid'         => sanitize_text_field( $result['data']->uid ),
					'opp_bank_uid'             => sanitize_text_field( $result['data']->uid ),
				);

				update_user_meta( $store_id, 'dokan_profile_settings', $dokan_settings );
				update_user_meta( $store_id, 'opp_merchant_uid', sanitize_text_field( $result['data']->uid ) );
				update_user_meta( $store_id, 'opp_bankAccount_uid', sanitize_text_field( $create_bank_account['data']->uid ) );
				update_user_meta( $store_id, 'opp_email', sanitize_email( $email ) );

				$success_msg = __( 'Your information has been saved successfully', 'online-payment-platform-gateway' );
				$data        = array(
					'msg'      => $success_msg,
					'redirect' => 'opp',
					'url'      => esc_url( $result['data']->compliance->overview_url ),
				);

				wp_send_json_success( $data );
			} else {

				// print_r($result);

				$error_msg = $result['data']['error']['message'] . '<br>';
				foreach ( $result['data']['error']['parameters'] as $error_key => $error_value ) {
					$append = '';
					if ( $error_key === 'country' ) :
						$append = sprintf(
						/* translators: %s: the link */
							esc_html__(
								'Please update country from <a href="%s">store location</a>.',
								'online-payment-platform-gateway'
							),
							esc_url( dokan_get_navigation_url( 'settings/store' ) . '#dokan-store-pickup-location' )
						);
					endif;
					 $error_msg .= '<strong>' . ucfirst( $error_key ) . ' : </strong> ' . $error_value[0] . ' ' . $append . '<br>';
				}

				wp_send_json_error( print_r( $error_msg, true ) );
			}
		}

	}

	/**
	 * Add Custom Withdraw Method to the Payment Method List
	 *
	 * @param array  $required_fields    An array of required fields.
	 * @param string $payment_method_id The ID of the payment method.
	 * @return array                    Updated array of required fields.
	 */
	public function addCustomWithdrawInPaymentMethodList( $required_fields, $payment_method_id ) {
		if ( $this->slug === $payment_method_id ) {
			$required_fields = array( 'email' );
		}
		return $required_fields;
	}

	/**
	 * Add Custom Withdraw Method to the Payment Method List
	 *
	 * @param array  $active_payment_methods An array of active withdrawal methods.
	 * @param string $vendor_id              The ID of the vendor (store owner).
	 * @return array                        Updated array of active withdrawal methods.
	 */
	public function customMethodInActiveWithdrawMethod( $active_payment_methods, $vendor_id ) {
		$store_info = dokan_get_store_info( $vendor_id );
		if ( isset( $store_info['payment'][ $this->slug ]['value'] ) && $store_info['payment'][ $this->slug ]['value'] !== false ) {
			$active_payment_methods[] = $this->slug;
		}

		return $active_payment_methods;
	}

	/**
	 * Include Method to Available Withdraw Method Section
	 *
	 * @param array $methods An array of available withdraw methods.
	 * @return array Updated array of available withdraw methods.
	 */
	public function includeMethodInWithdrawMethodSection( $methods ) {
		$methods[] = $this->slug;
		return $methods;
	}

	/**
	 * Add details to the Withdrawal Requests
	 */
	public function viewAdminWithdraw() {
		?>
		<script>
			let hooks;

			function getCustomPaymentDetails(details, method, data) {
				if (data[method] !== undefined) {
					if ( <?php echo esc_attr( $this->slug ); ?> ===
					method
				)
					{
						details = data[method].value || '';
					}
				}

				return details;
			}

			dokan.hooks.addFilter('dokan_get_payment_details', 'getCustomPaymentDetails', getCustomPaymentDetails, 33, 3);
		</script>
		<?php
	}

	/**
	 * Remove Payment Step from Setup Wizard
	 *
	 * @param array $steps An array representing the steps in the setup wizard.
	 * @return array Updated array of steps after removing the 'payment' step.
	 */
	public function removePaymentStepFormSetupWizard( $steps ) {
		unset( $steps['payment'] );
		return $steps;
	}

	/**
	 * Update KYC Button
	 */
	public function updateKycButton() {

			$overview_url = $this->merchant->overviewUrl();
		if ( ! $overview_url ) {
			return false;
		}

		?>
			<a class="dokan-btn dokan-btn-success dokan-btn-sm"
			   href="<?php echo esc_url( $overview_url ); ?>">
				<?php esc_html_e( 'Update KYC', 'online-payment-platform-gateway' ); ?>
			</a>
			<?php

	}

	/**
	 * Create Bank Account Button
	 */
	public function createBankAccountButton() {

			$response = $this->merchant->createBankAccount();

		if ( ! $response['success'] ) {
			return false;
		}
		?>
			<a class="dokan-btn dokan-btn-success dokan-btn-sm"
			   href="<?php echo esc_url( $response['data']->verification_url ); ?>">
				<?php esc_html_e( 'Verify Bank Account', 'online-payment-platform-gateway' ); ?>
			</a>
			<?php

	}

}
