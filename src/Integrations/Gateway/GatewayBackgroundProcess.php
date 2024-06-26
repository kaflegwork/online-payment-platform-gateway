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

namespace OnlinePaymentPlatformGateway\Integrations\Gateway;

use OnlinePaymentPlatformGateway\Integrations\OPPApi\Transaction;
use WC_Background_Process;


/**
 * Class HTML_Widget
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class GatewayBackgroundProcess extends WC_Background_Process {
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
		$this->hooks();
		$this->schedule_event();

	}


	/**
	 * Init all the hooks
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'cron_schedules', array( $this, 'addCronInterval' ) );
		add_action( 'opp_payment_check_cron_hook', array( $this, 'cron_check' ) );
		//add_action( 'wp_head', array( $this, 'cron_check' ) );
	}


	/**
	 * Add custom cron interval.
	 *
	 * @param array $schedules Existing cron schedules.
	 * @return array Updated cron schedules.
	 */
	public function addCronInterval( $schedules ) {

		// Adds every 15 minutes to the existing schedules.
		$schedules[ $this->identifier . '_opp_cron_interval' ] = array(
			'interval' => MINUTE_IN_SECONDS * 15,
			'display'  => sprintf( /* translators: %d: the interval  */ __( 'Every %d minutes', 'online-payment-platform-gateway' ), 15 ),
		);

		return $schedules;
	}

	/**
	 * Regenerate all lookup table data.
	 */
	public function cron_check() {

		$orders = wc_get_orders(
			array(
				'limit'         => -1,
				'post_status'   => 'any',
				'meta_key'      => 'opp_transaction_status', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'    => 'pending',  // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'meta_compare'  => '=', // Possible values are
			)
		);

		if ( ! $orders ) {
			return false;
		}

		foreach ( $orders as $order ) {
			if ( $order->has_status( 'completed' ) || $order->has_status( 'processing' ) ) {
				continue;
			}
			$completed  = false;
			$all_orders = Helper::getAllOrdersToProcessed( $order );
			foreach ( $all_orders as $tmp_order ) {
				$transaction_id = Helper::getOrderTransactionID( $tmp_order->get_id() );
				if ( empty( $transaction_id ) ) {
					continue;
				}
				$result = ( new Transaction() )->retrieve( $transaction_id );

				if ( ! $result['success'] || empty( $result['data']->status ) ) {
                    echo "<p>". $tmp_order->get_id() . " => " . $result['data']['error-message'] . "</p>";
					continue;
				}

				$tmp_order_id       = $tmp_order->get_id();
				$vendor_id          = dokan_get_seller_id_by_order( $tmp_order_id );
				$vendor_raw_earning = dokan()->commission->get_earning_by_order( $tmp_order, 'seller' );
				if ( $result['data']->status !== 'reserved' && $result['data']->status !== 'completed' ) {
					continue;
				}
				$completed = true;

				$tmp_order->add_order_note( sprintf( /* translators: %1$s: the plugin name, %2$s: the transaction iD */ esc_html__( '%1$s payment approved! Transaction ID: %2$s', 'online-payment-platform-gateway' ), ( new OppPaymentGateway() )->title, $transaction_id ) );

				$tmp_order->update_meta_data( '_opp_transaction_uid', $result->uid );

				$tmp_order->update_meta_data( '_opp_transaction', $result );
				$tmp_order->update_meta_data( '_opp_payment_details', $result->payment_details );

				// set transaction id
				$tmp_order->set_transaction_id( $result->uid );
				$tmp_order->save();

				$withdraw_data = array(
					'user_id'  => $vendor_id,
					'amount'   => $vendor_raw_earning,
					'order_id' => $tmp_order_id,
				);

				$all_withdraws[] = $withdraw_data;

			}

			if ( $completed ) {
				$order->payment_complete( $order->get_id() );

				$opp_payment_gateway = new OppPaymentGateway();
				$opp_payment_gateway->insert_into_vendor_balance( $all_withdraws );
				$opp_payment_gateway->process_seller_withdraws( $all_withdraws );

				update_post_meta( $order->get_id(), 'opp_transaction_status', $result['data']->status );
				$order->save();
			}
		}

	}

	/**
	 * Schedule event.
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( 'opp_payment_check_cron_hook' ) ) {
			wp_schedule_event( time(), $this->identifier . '_opp_cron_interval', 'opp_payment_check_cron_hook' );
		}
	}

	/**
	 * Task to be performed on each item.
	 *
	 * @param mixed $item Item to process.
	 */
	protected function task( $item ) {
		// TODO: Implement task() method.

		update_option( 'cron_check_here', $item );
	}
}
