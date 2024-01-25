<?php
/**
 * Online Payment Platform Gateway
 *
 * @package   online-payment-platform-gateway
 * @author    Ganga Kafle <kafleg.work@gmail.com>
 * @copyright 2023 Online Payment Platform Gateway
 * @license   MIT
 * @link      https://the-dev-company.com
 *
 * Plugin Name:     Online Payment Platform Gateway
 * Plugin URI:      https://the-dev-company.com
 * Description:      Online payment platform (OPP) payment gateway for woocommerce.
 * Version:         1.0.0
 * Author:          Ganga Kafle
 * Author URI:      https://the-dev-company.com
 * Text Domain:     online-payment-platform-gateway
 * Plugin slug:     online-payment-platform
 * Domain Path:     /languages
 * Requires PHP:    7.1
 * Requires WP:     5.5.0
 * Namespace:       OnlinePaymentPlatformGateway
 */

declare( strict_types = 1 );

/**
 * Define the default root file of the plugin
 *
 * @since 1.0.0
 */
const ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE = __FILE__;

/**
 * Load PSR4 autoloader
 *
 * @since 1.0.0
 */
$online_payment_platform_gateway_autoloader = require plugin_dir_path( ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ) . 'vendor/autoload.php';
require plugin_dir_path( ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ) . 'src/Helpers.php';

/**
 * Setup hooks (activation, deactivation, uninstall)
 *
 * @since 1.0.0
 */
register_activation_hook( __FILE__, array( 'OnlinePaymentPlatformGateway\Config\Setup', 'activation' ) );
register_deactivation_hook( __FILE__, array( 'OnlinePaymentPlatformGateway\Config\Setup', 'deactivation' ) );
register_uninstall_hook( __FILE__, array( 'OnlinePaymentPlatformGateway\Config\Setup', 'uninstall' ) );

/**
 * Bootstrap the plugin
 *
 * @since 1.0.0
 */
if ( ! class_exists( '\OnlinePaymentPlatformGateway\Bootstrap' ) ) {
	wp_die( esc_html__( 'Online Payment Platform Gateway is unable to find the Bootstrap class.', 'online-payment-platform-gateway' ) );
}

add_action(
	'plugins_loaded',
	static function () use ( $online_payment_platform_gateway_autoloader ) {
		/**
		 * Callback function for initializing the Home Improvement Companion Bootstrap class.
		 *
		 * @see \OnlinePaymentPlatformGateway\Bootstrap
		 */
		try {
			new \OnlinePaymentPlatformGateway\Bootstrap( $online_payment_platform_gateway_autoloader );
		} catch ( Exception $e ) {
			wp_die( esc_html__( 'Online Payment Platform Gateway is unable to run the Bootstrap class.', 'online-payment-platform-gateway' ) );
		}
	}
);

/**
 * Create a main function for external uses
 *
 * @return \OnlinePaymentPlatformGateway\Common\Functions
 * @since 1.0.0
 */
function online_payment_platform_gateway(): \OnlinePaymentPlatformGateway\Common\Functions {
	return new \OnlinePaymentPlatformGateway\Common\Functions();
}
