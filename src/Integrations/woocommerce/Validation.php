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

declare(strict_types=1);

namespace OnlinePaymentPlatformGateway\Integrations\woocommerce;

use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;

/**
 * Class HTML_Widget
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class Validation {


	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
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

		add_action( 'woocommerce_after_checkout_validation', array( $this, 'checkVendorConfigureOPP' ), 15, 2 );

	}

	/**
	 * Validate checkout if vendor has configured OPP account.
	 *
	 * This function checks whether the vendor has configured the OPP (Online Payment Processing) account
	 * before allowing the checkout process to proceed. If OPP is not configured for the vendor,
	 * an error is added to the specified error object.
	 *
	 * @param array    $data    The checkout data.
	 * @param WP_Error $errors  The error object to which errors are added if OPP is not configured.
	 *
	 * @return void
	 */
	public function checkVendorConfigureOPP( $data, $errors ) {
		if ( ! Helper::isEnabled() || Helper::allowNonConnectedSellers() ) {
			return;
		}

		if ( Helper::getGatewayId() !== $data['payment_method'] ) {
			return;
		}

		foreach ( WC()->cart->get_cart() as $item ) {
			$product_id = $item['data']->get_id();
			$available_vendors[ get_post_field( 'post_author', $product_id ) ][] = $item['data'];
		}

		// If it's subscription product return early.
		$subscription_product = wc_get_product( $product_id );

		if ( $subscription_product && 'product_pack' === $subscription_product->get_type() ) {
			return;
		}

		$vendor_names = array();

		foreach ( array_keys( $available_vendors ) as $vendor_id ) {
			$vendor       = dokan()->vendor->get( $vendor_id );
			$access_token = get_user_meta( $vendor_id, 'opp_merchant_uid', true );

			if ( empty( $access_token ) ) {
				$vendor_products = array();

				foreach ( $available_vendors[ $vendor_id ] as $product ) {
					$vendor_products[] = sprintf( '<a href="%s">%s</a>', $product->get_permalink(), $product->get_name() );
				}

				$vendor_names[ $vendor_id ] = array(
					'name'     => sprintf( '<a href="%s">%s</a>', esc_url( $vendor->get_shop_url() ), $vendor->get_shop_name() ),
					'products' => implode( ', ', $vendor_products ),
				);
			}
		}

		foreach ( $vendor_names as $vendor_id => $data ) {
			$errors->add( 'stipe-not-configured', wp_kses_post(sprintf( /* translators: %1$s: the vendor name, %2$s: the list of products */ __( '<strong>Error!</strong> You cannot complete your purchase until <strong>%1$s</strong> has enabled OPP as a payment gateway. Please remove %2$s to continue.', 'online-payment-platform-gateway' ), $data['name'], $data['products'] ) ));
		}
	}
}
