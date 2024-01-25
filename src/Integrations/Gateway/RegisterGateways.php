<?php
/**
 * Class RegisterGateways
 *
 * This class initializes and registers the custom payment gateway for the Online Payment Platform.
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Gateway
 */

namespace OnlinePaymentPlatformGateway\Integrations\Gateway;

/**
 * Class RegisterGateways
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class RegisterGateways {

	/**
	 * Constructor method
	 *
	 * Initializes the class and sets up necessary hooks.
	 *
	 * @return void
	 */
	public function init() {
		$this->hooks();
	}

	/**
	 * Init all the hooks
	 *
	 * Initializes various hooks needed for registering the payment gateway.
	 *
	 * @return void
	 */
	private function hooks() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'register_gateway' ) );
	}

	/**
	 * Register payment gateway
	 *
	 * Adds the custom payment gateway class to the list of available WooCommerce payment gateways.
	 *
	 * @param array $gateways The array of existing WooCommerce payment gateways.
	 *
	 * @return array The modified array with the addition of the custom payment gateway class.
	 */
	public function register_gateway( $gateways ) {
		$gateways[] = 'OnlinePaymentPlatformGateway\Integrations\Gateway\OppPaymentGateway';

		return $gateways;
	}
}
