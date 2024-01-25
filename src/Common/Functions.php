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

namespace OnlinePaymentPlatformGateway\Common;

use OnlinePaymentPlatformGateway\App\Frontend\Templates;
use OnlinePaymentPlatformGateway\Common\Abstracts\Base;

/**
 * Main function class for external uses
 *
 * @see online_payment_platform_gateway()
 * @package OnlinePaymentPlatformGateway\Common
 */
class Functions extends Base {
	/**
	 * Get plugin data by using online_payment_platform_gateway()->getData()
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public function getData(): array {
		return $this->plugin->data();
	}

	/**
	 * Get the template instantiated class using online_payment_platform_gateway()->templates()
	 *
	 * @return Templates
	 * @since 1.0.0
	 */
	public function templates(): Templates {
		return new Templates();
	}
}
