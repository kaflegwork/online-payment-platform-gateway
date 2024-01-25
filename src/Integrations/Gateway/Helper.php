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

namespace OnlinePaymentPlatformGateway\Integrations\Gateway;

/**
 * Helper class for Online Payment Platform Gateway integration with WooCommerce.
 */
class Helper {


	/**
	 * Initialize the helper class.
	 */
	public function init() {

	}


	/**
	 * Check if the Online Payment Platform Gateway is enabled.
	 *
	 * @return bool True if the gateway is enabled, false otherwise.
	 */
	public static function isEnabled() {
		$settings = static::getSettings();

		return ! empty( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	/**
	 * Check if the Online Payment Platform Gateway is ready for use.
	 *
	 * @return bool True if the gateway is ready, false otherwise.
	 */
	public static function isReady() {
		if ( ! static::isEnabled() ||
			empty( static::getSecretKey() ) ) {
			return false;
		}

		return true;
	}


	/**
	 * Get the Online Payment Platform Gateway ID.
	 *
	 * @return string The gateway ID.
	 */
	public static function getGatewayId() {
		$gateway = new OppPaymentGateway();
		return $gateway->id;
	}

	/**
	 * Get the settings for the Online Payment Platform Gateway.
	 *
	 * @param string|null $key Optional. Specific setting key to retrieve.
	 * @return mixed|array|null The settings array or a specific setting value if $key is provided.
	 */
	public static function getSettings( $key = null ) {
		$settings = get_option( 'woocommerce_' . static::getGatewayId() . '_settings', array() );
		if ( $key && isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		return $settings;
	}

	/**
	 * Get the title of the Online Payment Platform Gateway.
	 *
	 * @return string The gateway title.
	 */
	public static function getGatewayTitle() {
		$settings = static::getSettings();

		return ! empty( $settings['title'] ) ? $settings['title'] : __( 'Online Payment Platform Gateway', 'online-payment-platform-gateway' );
	}


	/**
	 * Check whether the gateway is in test mode.
	 *
	 * @return bool True if the gateway is in test mode, false otherwise.
	 */
	public static function isTestMode() {
		$settings = static::getSettings();

		return ! empty( $settings['test_mode'] ) && 'yes' === $settings['test_mode'];
	}

	/**
	 * Get the secret key for the Online Payment Platform Gateway.
	 *
	 * @return string The secret key.
	 */
	public static function getSecretKey() {
		$key      = static::isTestMode() ? 'test_api_key' : 'live_api_key';
		$settings = static::getSettings();

		return ! empty( $settings[ $key ] ) ? $settings[ $key ] : '';
	}

	/**
	 * Get the key for the vendor's merchant ID based on test mode.
	 *
	 * @param bool|null $test_mode Optional. Whether to use test mode. Defaults to null (automatic detection).
	 * @return string The merchant ID key.
	 */
	public static function getVendorMerchantIdKey( $test_mode = null ) {
		if ( null === $test_mode ) {
			$test_mode = static::isTestMode();
		}
		return $test_mode ? 'opp_merchant_uid' : 'opp_merchant_uid';
	}

	/**
	 * Get the vendor's merchant ID.
	 *
	 * @param int $seller_id The ID of the seller.
	 * @return string The vendor's merchant ID.
	 */
	public static function getVendorMerchantID( $seller_id ) {
		return get_user_meta( $seller_id, static::getVendorMerchantIdKey(), true );
	}

	/**
	 * Get the key for the order's transaction ID based on test mode.
	 *
	 * @param bool|null $test_mode Optional. Whether to use test mode. Defaults to null (automatic detection).
	 * @return string The transaction ID key.
	 */
	public static function getOrderTransactionIdKey( $test_mode = null ) {
		if ( null === $test_mode ) {
			$test_mode = static::isTestMode();
		}
		return $test_mode ? 'opp_order_transaction_id' : 'opp_order_transaction_id';
	}

	/**
	 * Get the order's transaction ID.
	 *
	 * @param int $order_id The ID of the order.
	 * @return string The order's transaction ID.
	 */
	public static function getOrderTransactionID( $order_id ) {
		return get_post_meta( $order_id, static::getOrderTransactionIdKey(), true );
	}

	/**
	 * Convert a value to cents (integer format).
	 *
	 * @param float $value The value to convert.
	 * @return int The value in cents.
	 */
	public static function toCents( $value ) {
		return wc_format_decimal( $value, 2 ) * 100; /* In cents*/
	}

	/**
	 * Get all orders to be processed based on whether the main order has suborders.
	 *
	 * @param WC_Order $order The main WooCommerce order.
	 * @return array An array of orders to be processed.
	 */
	public static function getAllOrdersToProcessed( $order ) {
		$has_suborder = $order->get_meta( 'has_sub_order' );
		$all_orders   = array();

		if ( $has_suborder ) {
			$sub_orders = dokan()->order->get_child_orders( $order->get_id() );
			foreach ( $sub_orders as $sub_order ) {
				$all_orders[] = $sub_order;
			}
		} else {
			$all_orders[] = $order;
		}

		return $all_orders;
	}

	/**
	 * Get the partner fee based on the product price.
	 *
	 * @param float $price The product price.
	 * @return float The calculated partner fee.
	 */
	public static function getPartnerFee( $price ) {
		if ( class_exists( 'Dokan_Pro' ) ) :
			$partner_fee = dokan_get_option( 'admin_percentage', 'dokan_selling', 10 );
		else :
			$partner_fee = 0;
		endif;

		return $price * $partner_fee / 100;
	}

	/**
	 * Check whether non-connected sellers are allowed.
	 *
	 * @return bool True if non-connected sellers are allowed, false otherwise.
	 */
	public static function allowNonConnectedSellers() {
		$settings = self::getSettings();

		return ! empty( $settings['allow_non_connected_sellers'] ) && 'yes' === $settings['allow_non_connected_sellers'];
	}

}
