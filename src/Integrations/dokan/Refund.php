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

use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;
use OnlinePaymentPlatformGateway\Integrations\OPPApi\Transaction;
use WeDevs\DokanPro\Modules\MangoPay\Support\Meta;

/**
 * Class Refund
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class Refund {

	/**
	 * Refund constructor.
	 *
	 * Initializes the class and hooks.
	 */
	public function init() {
		$this->hooks();
	}

	/**
	 * Init all the hooks
	 *
	 * Hooks for refund processing.
	 */
	private function hooks() {
		add_action( 'dokan_refund_request_created', array( $this, 'process_refund' ) );
		add_filter( 'dokan_refund_approve_vendor_refund_amount', array( $this, 'vendor_refund_amount' ), 10, 3 );
		add_action( 'dokan_refund_approve_before_insert', array( $this, 'add_vendor_withdraw_entry' ), 10, 3 );
		// add_action( 'dokan_refund_approve_before_insert', [ $this, 'update_gateway_fee' ], 10, 3 );
		add_filter( 'dokan_excluded_gateways_from_auto_process_api_refund', array( $this, 'exclude_from_auto_process_api_refund' ) );
	}

	/**
	 * Process refund request.
	 *
	 * Processes the refund request for the Online Payment Platform Gateway. It checks if the provided refund object is
	 * an instance of \WeDevs\DokanPro\Refund\Refund and if the gateway is ready. If the refund is approvable, it retrieves
	 * the necessary information, such as the merchant ID and transaction ID. It then handles automatic and manual refunds,
	 * communicates with the Online Payment Platform API to create a refund, and updates order notes accordingly. Finally,
	 * it tries to approve the refund and logs any errors encountered during the process.
	 *
	 * @param \WeDevs\DokanPro\Refund\Refund $refund The refund object.
	 *
	 * @return \WeDevs\DokanPro\Refund\Refund|\WP_Error Returns the processed refund object or a WP_Error in case of an error.
	 *
	 * @throws \Exception Throws an exception if there is an issue with the refund processing.
	 */
	public function process_refund( $refund ) {

		// get code editor suggestion on refund object
		if ( ! $refund instanceof \WeDevs\DokanPro\Refund\Refund ) {
			return;
		}

		// check if gateway is ready
		if ( ! Helper::isReady() ) {
			return;
		}

		// check if refund is approvable
		if ( ! dokan_pro()->refund->is_approvable( $refund->get_order_id() ) ) {
			dokan_log( sprintf( '%1$s: This refund is not allowed to approve, Refund ID: %2$s, Order ID: %3$s', Helper::getGatewayTitle(), $refund->get_id(), $refund->get_order_id() ) );
			return;
		}

		$order = wc_get_order( $refund->get_order_id() );

		// return if $order is not instance of WC_Order
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( Helper::getGatewayId() !== $order->get_payment_method() ) {
			return;
		}

		// check for merchant id
		$seller_id   = $refund->get_seller_id();
		$merchant_id = Helper::getVendorMerchantID( $seller_id );

		if ( ! $merchant_id ) {
			$order->add_order_note(
				sprintf(
				/* translators: 1) Gateway Title 2) Refund ID 3) Order ID */
					__( '%1$s Error: Automatic refund is not possible for this order. Reason: No Opp Merchant id is found. Refund id: %2$s, Order ID: %3$s', 'online-payment-platform-gateway' ),
					Helper::getGatewayTitle(),
					$refund->get_id(),
					$refund->get_order_id()
				)
			);
			return;
		}

		if ( $refund->is_manual() ) {
			echo 'manual';
			$refund = $refund->approve();

			if ( is_wp_error( $refund ) ) {
				dokan_log( $refund->get_error_message(), 'error' );
			}
			return;
		}

		// Check if transaction id exists
		$transaction_id = Helper::getOrderTransactionID( $order->get_id() );

		if ( ! $transaction_id ) {
			$order->add_order_note(
				sprintf(
				/* translators: 1) Gateway Title 2) Refund ID 3) Order ID */
					__( '[%1$s] Error: Automatic refund is not possible for this order. Reason: No Opp transaction id is found. Refund id: %2$s, Order ID: %3$s', 'online-payment-platform-gateway' ),
					Helper::getGatewayTitle(),
					$refund->get_id(),
					$refund->get_order_id()
				)
			);
			return $refund->cancel();
		}

		$args = array(
			'amount'             => Helper::toCents( $refund->get_refund_amount() ),
			'message'            => $refund->get_refund_reason(),
			'internal_reason'    => 'internal: ' . $transaction_id . ' ' . $refund->get_refund_reason(),
			'payout_description' => 'payout description: ' . $refund->get_refund_reason(),
		);

		$opp_refund = ( new \OnlinePaymentPlatformGateway\Integrations\OPPApi\Transaction() )->createRefund( $transaction_id, $args );

		if ( ! $opp_refund['success'] ) {
			$order->add_order_note(
				sprintf(
				// translators: 1) Payment Gateway id, 2) API error message
					__( '%1$s: API Refund Error: %2$s', 'online-payment-platform-gateway' ),
					Helper::getGatewayTitle(),
					$opp_refund['data']['error']['message']
				)
			);

			// cancel refund request
			return $refund->cancel();
		}

		$order->add_order_note(
			sprintf(
			/* translators: 1) gateway title, 2) refund amount, 3) refund id, 4) refund reason */
				__( '[%1$s]. Refunded %2$s. Refund ID: %3$s.%4$s', 'online-payment-platform-gateway' ),
				Helper::getGatewayTitle(),
				wc_price( $refund->get_refund_amount(), array( 'currency' => $order->get_currency() ) ),
				$opp_refund['data']->uid,
				/* translators: refund reason */
				! empty( $refund->get_refund_reason() ) ? sprintf( __( 'Reason 1 - %s', 'online-payment-platform-gateway' ), $refund->get_refund_reason() ) : ''
			)
		);

		$refund_ids = $order->get_meta( 'refund_ids', true );
		if ( is_array( $refund_ids ) ) {
			$refund_ids[] = $opp_refund['data']->uid;
		} else {
			$refund_ids = array( $opp_refund['data']->uid );
		}
		$order->update_meta_data( 'opp_refund_ids', $refund_ids );

		$order->update_meta_data( 'opp_last_refund_id', $opp_refund['data']->uid );

		// save metadata
		$order->save();

		$args[ Helper::getGatewayId() ] = true;
		$args['opp_refund_id']          = $opp_refund['data']->uid;

		// Try to approve the refund.
		$refund = $refund->approve( $args );

		if ( is_wp_error( $refund ) ) {
			dokan_log( $refund->get_error_message(), 'error' );
		}

	}

	/**
	 * Recalculate gateway fee after a refund.
	 *
	 * This method recalculates the gateway fee after processing a refund through the Online Payment Platform Gateway.
	 * It ensures that the Dokan PayPal Marketplace payment processing fee is updated accordingly, considering any reversed
	 * gateway fees. If the remaining refund amount is zero, it sets the processing fee to 0 to prevent double deduction.
	 *
	 * @param \WeDevs\DokanPro\Refund\Refund $refund          The refund object.
	 * @param array                          $args            An array of arguments provided for the refund.
	 * @param float                          $vendor_refund   The vendor refund amount.
	 *
	 * @return void
	 */
	public function update_gateway_fee( $refund, $args, $vendor_refund ) {
		$order = wc_get_order( $refund->get_order_id() );

		// return if $order is not instance of WC_Order
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// return if not paid with dokan paypal marketplace payment gateway
		if ( Helper::getGatewayId() !== $order->get_payment_method() ) {
			return;
		}

		$order             = wc_get_order( $refund->get_order_id() );
		$dokan_gateway_fee = (float) $order->get_meta( '_dokan_paypal_payment_processing_fee', true );

		if ( $args['reversed_gateway_fee'] > 0 ) {
			$dokan_gateway_fee -= $args['reversed_gateway_fee'];
		}

		/*
		 * If there is no remaining amount then its full refund and we are updating the processing fee to 0.
		 * because seller is already paid the processing fee from his account. if we keep this then it will deducted twice.
		 */
		if ( $order->get_remaining_refund_amount() <= 0 ) {
			$dokan_gateway_fee = 0;
		}

		$order->update_meta_data( 'dokan_gateway_fee', $dokan_gateway_fee );
		$order->save();
	}

	/**
	 * Withdraw entry for automatic refund as debit.
	 *
	 * This method adds a vendor withdraw entry for automatic refunds processed through the Online Payment Platform Gateway.
	 * It records essential information in the Dokan vendor balance table, such as vendor ID, transaction ID, transaction type,
	 * particulars, debit amount, credit amount, status, transaction date, and balance date.
	 *
	 * @param \WeDevs\DokanPro\Refund\Refund $refund         The refund object.
	 * @param array                          $args           An array of arguments provided for the refund.
	 * @param float                          $vendor_refund  The vendor refund amount.
	 *
	 * @return void
	 */
	public function add_vendor_withdraw_entry( $refund, $args, $vendor_refund ) {

		$order = wc_get_order( $refund->get_order_id() );

		// return if $order is not instance of WC_Order
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		// return if not paid with dokan paypal marketplace payment gateway
		if ( Helper::getGatewayId() !== $order->get_payment_method() ) {
			return;
		}

		global $wpdb;

		$wpdb->insert(
			$wpdb->dokan_vendor_balance,
			array(
				'vendor_id'    => $refund->get_seller_id(),
				'trn_id'       => $refund->get_order_id(),
				'trn_type'     => 'dokan_refund',
				'perticulars'  => maybe_serialize( $args ),
				'debit'        => $vendor_refund,
				'credit'       => 0,
				'status'       => 'wc-completed', // see: Dokan_Vendor->get_balance() method
				'trn_date'     => current_time( 'mysql' ),
				'balance_date' => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%f',
				'%f',
				'%s',
				'%s',
				'%s',
			)
		);
	}

	/**
	 * Set vendor refund amount as refund amount.
	 *
	 * This method determines the vendor refund amount based on the provided arguments. If the Online Payment Platform Gateway
	 * is involved in the refund and the necessary data, such as 'opp_refund_id' and the gateway identifier, are available,
	 * it calculates and returns the vendor refund amount. Otherwise, it returns the original vendor refund amount.
	 *
	 * @param float  $vendor_refund The original vendor refund amount.
	 * @param array  $args          An array of arguments provided for the refund.
	 * @param object $refund       The refund object.
	 *
	 * @return float The vendor refund amount to be processed.
	 */
	public function vendor_refund_amount( $vendor_refund, $args, $refund ) {

		if ( isset( $args[ Helper::getGatewayId() ], $args['opp_refund_id'] ) && $args[ Helper::getGatewayId() ] ) {
			return wc_format_decimal( $args['amount'] ) / 100;
		}

		return $vendor_refund;
	}

	/**
	 * Excludes marketplace from auto process API refund.
	 *
	 * This method is responsible for excluding the Online Payment Platform Gateway from the automatic processing of API refunds.
	 *
	 * @param array $gateways The array of gateways to be excluded from auto processing API refunds.
	 *
	 * @return array The updated array of gateways after excluding the Online Payment Platform Gateway.
	 */
	public function exclude_from_auto_process_api_refund( $gateways ) {
		$gateways[ Helper::getGatewayId() ] = Helper::getGatewayTitle();
		return $gateways;
	}
}
