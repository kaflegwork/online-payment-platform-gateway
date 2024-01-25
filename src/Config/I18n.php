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

namespace OnlinePaymentPlatformGateway\Config;

use OnlinePaymentPlatformGateway\Common\Abstracts\Base;

/**
 * Internationalization and localization definitions
 *
 * @package OnlinePaymentPlatformGateway\Config
 * @since 1.0.0
 */
final class I18n extends Base {
	/**
	 * Load the plugin text domain for translation
	 *
	 * @docs https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#loading-text-domain
	 *
	 * @since 1.0.0
	 */
	public function load() {
		load_plugin_textdomain(
			$this->plugin->textDomain(),
			false,
			dirname( plugin_basename( ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ) ) . '/languages' // phpcs:disable ImportDetection.Imports.RequireImports.Symbol -- this constant is global
		);
	}
}
