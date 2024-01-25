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

namespace OnlinePaymentPlatformGateway\Common\Abstracts;

use OnlinePaymentPlatformGateway\Config\Plugin;

/**
 * The Base class which can be extended by other classes to load in default methods
 *
 * @package OnlinePaymentPlatformGateway\Common\Abstracts
 * @since 1.0.0
 */
abstract class Base {
	/**
	 * Data container for plugin configuration.
	 *
	 * @var array : will be filled with data from the plugin config class
	 * @see Plugin
	 */
	protected $plugin = array();

	/**
	 * Base constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->plugin = Plugin::init();
	}
}
