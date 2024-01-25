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

namespace OnlinePaymentPlatformGateway\App\General;

use OnlinePaymentPlatformGateway\Common\Abstracts\Base;

/**
 * Class Shortcodes
 *
 * @package OnlinePaymentPlatformGateway\App\General
 * @since 1.0.0
 */
class Shortcodes extends Base {
	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		/**
		 * This general class is always being instantiated as requested in the Bootstrap class
		 *
		 * @see Bootstrap::__construct
		 *
		 * Add plugin code here
		 */

		add_shortcode( 'foobar', array( $this, 'foobarFunc' ) );
	}

	/**
	 * Shortcode example
	 *
	 * @param array $atts Parameters.
	 * @return string
	 * @since 1.0.0
	 */
	public function foobarFunc( array $atts ): string {
		shortcode_atts(
			array(
				'foo' => 'something',
				'bar' => 'something else',
			),
			$atts
		);
		return '<span class="foo">foo = ' . $atts['foo'] . '</span>' .
			'<span class="bar">foo = ' . $atts['bar'] . '</span>';
	}
}
