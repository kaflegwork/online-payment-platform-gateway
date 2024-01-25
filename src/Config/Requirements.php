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

namespace OnlinePaymentPlatformGateway\Config;

use OnlinePaymentPlatformGateway\Common\Abstracts\Base;
use OnlinePaymentPlatformGateway\Common\Utils\Errors;

/**
 * Check if any requirements are needed to run this plugin. We use the
 * "Requirements" package from "MicroPackage" to check if any PHP Extensions,
 * plugins, themes or PHP/WP version are required.
 *
 * @docs https://github.com/micropackage/requirements
 *
 * @package OnlinePaymentPlatformGateway\Config
 * @since 1.0.0
 */
final class Requirements extends Base {

	/**
	 * Specifications for the requirements
	 *
	 * @return array : used to specify the requirements
	 * @since 1.0.0
	 */
	public function specifications(): array {
		return apply_filters(
			'online_payment_platform_gateway_plugin_requirements',
			array(
				'php'            => $this->plugin->requiredPhp(),
				'php_extensions' => array(
					/**
					 * 'mbstring'
					 */
				),
				'wp'             => $this->plugin->requiredWp(),
				'plugins'        => array(
					array(
						'file'    => 'woocommerce/woocommerce.php',
						'name'    => 'WooCommerce',
						'version' => '8.4.0',
					),
					array(
						'file'    => 'dokan-pro/dokan-pro.php',
						'name'    => 'Dokan Pro',
						'version' => '3.9.6',
					),

				),
			)
		);
	}

	/**
	 * Plugin requirements checker
	 *
	 * @since 1.0.0
	 */
	public function check() {
		// We use "Requirements" if the package is required and installed by composer.json
		if ( class_exists( '\Micropackage\Requirements\Requirements' ) ) {
			$this->requirements = new \Micropackage\Requirements\Requirements(
				$this->plugin->name(),
				$this->specifications()
			);
			if ( ! $this->requirements->satisfied() ) {
				// Print notice
				$this->requirements->print_notice();
				// Kill plugin
				Errors::pluginDie();
			}
		} else {
			// Else we do a version check based on version_compare
			$this->versionCompare();
		}
	}

	/**
	 * Compares PHP & WP versions and kills plugin if it's not compatible
	 *
	 * @since 1.0.0
	 */
	public function versionCompare() {
		foreach (
			array(
				// PHP version check
				array(
					'current' => phpversion(),
					'compare' => $this->plugin->requiredPhp(),
					'title'   => __( 'Invalid PHP version', 'online-payment-platform-gateway' ),
					'message' => sprintf( /* translators: %1$1s: required php version, %2$2s: current php version */
						__( 'You must be using PHP %1$1s or greater. You are currently using PHP %2$2s.', 'online-payment-platform-gateway' ),
						$this->plugin->requiredPhp(),
						phpversion()
					),
				),
				// WP version check
				array(
					'current' => get_bloginfo( 'version' ),
					'compare' => $this->plugin->requiredWp(),
					'title'   => __( 'Invalid WordPress version', 'online-payment-platform-gateway' ),
					'message' => sprintf( /* translators: %1$1s: required wordpress version, %2$2s: current wordpress version */
						__( 'You must be using WordPress %1$1s or greater. You are currently using WordPress %2$2s.', 'online-payment-platform-gateway' ),
						$this->plugin->requiredWp(),
						get_bloginfo( 'version' )
					),
				),
			) as $compat_check ) {
			if ( version_compare(
				$compat_check['compare'],
				$compat_check['current'],
				'>='
			) ) {
				// Kill plugin
				Errors::pluginDie(
					$compat_check['message'],
					$compat_check['title'],
					plugin_basename( __FILE__ )
				);
			}
		}
	}

	/**
	 * Check if a set of specified plugins are currently active in the WordPress environment.
	 *
	 * This function iterates through a list of plugin specifications and checks if each plugin is currently active using the `is_plugin_active()` function.
	 *
	 * @return bool Returns `true` if all specified plugins are active; otherwise, returns `false` as soon as one plugin is found to be inactive.
	 */
	public function isRequirePluginActivated() {
		$plugins = $this->specifications()['plugins'];
		foreach ( $plugins as $plugin ) {
			$plugin_path = $plugin['file'];

			if ( ! is_plugin_active( $plugin_path ) ) {
				return false; // Return false as soon as one plugin is not active.
			}
		}
		return true; // All specified plugins are active.
	}
}
