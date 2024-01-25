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

namespace OnlinePaymentPlatformGateway\Common\Utils;

use OnlinePaymentPlatformGateway\Config\Plugin;

/**
 * Utility to show prettified wp_die errors, write debug logs as
 * string or array and to deactivate plugin and print a notice
 *
 * @package HomeImprovementCompanion\Config
 * @since 1.0.0
 */
class Errors {

	/**
	 * Get the plugin data in static form
	 *
	 * @return array
	 * @since 1.0.0
	 */
	public static function getPluginData(): array {
		return Plugin::init()->data();
	}

	/**
	 * Prettified version of the wp_die error function.
	 *
	 * @param string $message   The error message to be displayed.
	 * @param string $subtitle  Optional. A specified title of the error.
	 * @param string $source    Optional. The file source of the error.
	 * @param string $exception Optional. The exception or error details.
	 * @param string $title     Optional. A general title of the error.
	 * @since 1.0.0
	 */
	public static function wpDie( $message = '', $subtitle = '', $source = '', $exception = '', $title = '' ) {
		if ( $message ) {
			$plugin = self::getPluginData();
			$title  = $title ? $title : $plugin['name'] . ' ' . $plugin['version'] . ' ' . __( '&rsaquo; Fatal Error', 'online-payment-platform-gateway' );
			self::writeLog(
				array(
					'title'     => $title . ' - ' . $subtitle,
					'message'   => $message,
					'source'    => $source,
					'exception' => $exception,
				)
			);
			$source   = $source ? '<code>' .
				sprintf(  /* translators: %s: file path */
					__( 'Error source: %s', 'online-payment-platform-gateway' ),
					$source
				) . '</code><BR><BR>' : '';
			$footer   = $source . '<a href="' . $plugin['uri'] . '">' . $plugin['uri'] . '</a>';
			$message  = '<p>' . $message . '</p>';
			$message .= $exception ? '<p><strong>Exception: </strong><BR>' . $exception . '</p>' : '';
			$message  = "<h1>{$title}<br><small>{$subtitle}</small></h1>{$message}<hr><p>{$footer}</p>";
			wp_die( $message, $title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			wp_die();
		}
	}

	/**
	 * Deactivates the plugin and displays an error notice in the WordPress back-end.
	 *
	 * @param string $message   The error message to be displayed.
	 * @param string $subtitle  Optional. A specified title of the error.
	 * @param string $source    Optional. The file source of the error.
	 * @param string $exception Optional. The exception or error details.
	 * @param string $title     Optional. A general title of the error.
	 * @since 1.0.0
	 */
	public static function pluginDie( $message = '', $subtitle = '', $source = '', $exception = '', $title = '' ) {
		if ( $message ) {
			$plugin = self::getPluginData();
			$title  = $title ? $title : $plugin['name'] . ' ' . $plugin['version'] . ' ' . __( '&rsaquo; Plugin Disabled', 'online-payment-platform-gateway' );
			self::writeLog(
				array(
					'title'     => $title . ' - ' . $subtitle,
					'message'   => $message,
					'source'    => $source,
					'exception' => $exception,
				)
			);
			$source = $source ? '<small>' .
				sprintf( /* translators: %s: file path */
					__( 'Error source: %s', 'online-payment-platform-gateway' ),
					$source
				) . '</small> - ' : '';
			$footer = $source . '<a href="' . $plugin['uri'] . '"><small>' . $plugin['uri'] . '</small></a>';
			$error  = "<strong><h3>{$title}</h3>{$subtitle}</strong><p>{$message}</p><hr><p>{$footer}</p>";
			global $online_payment_platform_gateway_die_notice;
			$online_payment_platform_gateway_die_notice = $error;
			add_action(
				'admin_notices',
				static function () {
					global $online_payment_platform_gateway_die_notice;
					echo wp_kses_post(
						sprintf(
							'<div class="notice notice-error"><p>%s</p></div>',
							$online_payment_platform_gateway_die_notice
						)
					);
				}
			);
		}
		add_action(
			'admin_init',
			static function () {
				deactivate_plugins( plugin_basename( ONLINE_PAYMENT_PLATFORM_GATEWAY_PLUGIN_FILE ) ); // phpcs:disable ImportDetection.Imports.RequireImports.Symbol -- this constant is global
			}
		);
	}

	/**
	 * Writes a log if WP_DEBUG is enabled.
	 *
	 * @param mixed $log The data to be logged. It can be a string, array, object, or any other type of data.
	 * @since 1.0.0
	 */
	public static function writeLog( $log ) {
		if ( true === WP_DEBUG ) {
			if ( is_array( $log ) || is_object( $log ) ) {
                error_log( print_r( $log, true ) ); // phpcs:ignore
			} else {
				error_log( $log ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}
	}
}
