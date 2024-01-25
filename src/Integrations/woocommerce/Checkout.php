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

namespace OnlinePaymentPlatformGateway\Integrations\woocommerce;

use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;

/**
 * Class HTML_Widget
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class Checkout {



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

        add_action( 'woocommerce_checkout_after_terms_and_conditions', array( $this, 'oppFields' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'processFields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'saveFields' ) );

    }


    /**
     * Add OPP (Online Payment Processing) fields to the checkout form.
     *
     * This function is responsible for adding the checkbox field related to OPP terms and policies
     * to the checkout form if the chosen payment method is OPP.
     *
     * @return void
     */
    public function oppFields() {
        // Check if the chosen payment method is OPP.
        // Commented out for now as the field is added regardless of the payment method.
        // Uncomment the following lines if you want to conditionally show the field based on the payment method.
        // if ( isset( $_POST['payment_method'] ) && $_POST['payment_method'] === Helper::getGatewayId() ) { !

        // Add the 'agree_term_condition_opp' checkbox field to the checkout form.

        // Ensure WooCommerce is active.
        if ( class_exists( 'WooCommerce' ) ) {
            woocommerce_form_field(
                'agree_term_condition_opp',
                array(
                    'type'     => 'checkbox',
                    'label'    => sprintf(
                    /* translators: %s: the plugin name*/
                        esc_html__( 'I have read and agree to the OPP %s', 'online-payment-platform-gateway' ),
                        sprintf( '<a href="%s" target="_blank">%s</a>', esc_url( 'https://onlinepaymentplatform.com/terms-policies' ), esc_html__( ' Terms & Policies', 'online-payment-platform-gateway' ) )
                    ),
                    'required' => true,
                )
            );

        }

        // Uncomment the following line if you want to conditionally show the field based on the payment method.
    }

	public function processFields() {
		global $woocommerce;
		if ( ! $_POST['agree_term_condition_opp'] && $_POST['payment_method'] === Helper::getGatewayId()) {
			wc_add_notice( __( 'Please read and accept the terms and conditions of OPP.', 'online-payment-platform-gateway' ), 'error' );
		}
	}

    /**
     * Save additional fields related to OPP (Online Payment Processing) during order creation.
     *
     * This function is triggered when an order is created. It checks if the terms and conditions for OPP are agreed upon
     * and if the payment method selected is OPP. If conditions are met, it updates the order meta with the agreed terms.
     *
     * @param int $order_id The ID of the order being processed.
     *
     * @return void
     */
    public function saveFields( $order_id ) {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( isset( $_POST['agree_term_condition_opp'] ) && ! empty( $_POST['payment_method'] ) === Helper::getGatewayId() ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            update_post_meta( $order_id, 'agree_term_condition_opp', sanitize_text_field( wp_unslash( $_POST['agree_term_condition_opp'] ) ) );
        }
    }
}
