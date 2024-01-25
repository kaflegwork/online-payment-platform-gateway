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

/**
 * This array is being used in ../Boostrap.php to instantiate the classes
 *
 * @package OnlinePaymentPlatformGateway\Config
 * @since 1.0.0
 */
final class Classes {

	/**
	 * Init the classes inside these folders based on type of request.
	 *
	 * @see Requester for all the type of requests or to add your own
	 */
	public static function get(): array {
		if ( ! ( new Requirements() )->isRequirePluginActivated() ) {
			return array();
		}
		// phpcs:disable
		// ignore for readable array values one a single line
		return [
			[ 'init' => 'Integrations' ],
			[ 'init' => 'App\\General' ],
			[ 'init' => 'App\\Frontend', 'on_request' => 'frontend' ],
			[ 'init' => 'App\\Backend', 'on_request' => 'backend' ],
            [ 'init' => 'App\\Rest', 'on_request' => 'rest' ],
		];
		// phpcs:enable
	}
}
