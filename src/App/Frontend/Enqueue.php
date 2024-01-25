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

declare( strict_types = 1 );

namespace OnlinePaymentPlatformGateway\App\Frontend;

use OnlinePaymentPlatformGateway\Common\Abstracts\Base;

/**
 * Class Enqueue
 *
 * @package OnlinePaymentPlatformGateway\App\Frontend
 * @since 1.0.0
 */
class Enqueue extends Base {

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		/**
		 * This frontend class is only being instantiated in the frontend as requested in the Bootstrap class
		 *
		 * @see Requester::isFrontend()
		 * @see Bootstrap::__construct
		 *
		 * Add plugin code here
		 */
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
	}

	/**
	 * Enqueue scripts function
	 *
	 * @since 1.0.0
	 */
	public function enqueueScripts() {
		// Enqueue CSS
		foreach (
			array(
				array(
					'deps'    => array(),
					'handle'  => 'online-payment-platform-gateway-frontend-css',
					'media'   => 'all',
					'source'  => plugins_url( '/assets/public/css/frontend.css', ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ), // phpcs:disable ImportDetection.Imports.RequireImports.Symbol -- this constant is global
					'version' => $this->plugin->version(),
				),
			) as $css ) {
			wp_enqueue_style( $css['handle'], $css['source'], $css['deps'], $css['version'], $css['media'] );
		}
		// Enqueue JS
		foreach (
			array(
				array(
					'deps'      => array(),
					'handle'    => 'online-payment-platform-gateway-frontend-js',
					'in_footer' => true,
					'source'    => plugins_url( '/assets/public/js/frontend.js', ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ), // phpcs:disable ImportDetection.Imports.RequireImports.Symbol -- this constant is global
					'version'   => $this->plugin->version(),
				),
			) as $js ) {
			wp_enqueue_script( $js['handle'], $js['source'], $js['deps'], $js['version'], $js['in_footer'] );
		}

		// Send variables to JS
		global $wp_query;

		// localize script and send variables
		wp_localize_script(
			'online-payment-platform-gateway-frontend-js',
			'plugin_frontend_script',
			array(
				'plugin_frontend_url'  => admin_url( 'admin-ajax.php' ),
				'plugin_wp_query_vars' => $wp_query->query_vars,
			)
		);
	}
}
