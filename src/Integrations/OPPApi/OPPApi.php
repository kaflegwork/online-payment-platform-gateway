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

declare( strict_types=1 );

namespace OnlinePaymentPlatformGateway\Integrations\OPPApi;

use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;

/**
 * Class Api
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Example
 * @since 1.0.0
 */
class OPPApi {

	/**
	 * The sandbox API endpoint URL.
	 *
	 * @const string
	 */
	const ENDPOINT_SANDBOX = 'https://api-sandbox.onlinebetaalplatform.nl/v1/';

	/**
	 * The production API endpoint URL.
	 *
	 * @const string
	 */
	const ENDPOINT_PRODUCTION = 'https://api.onlinebetaalplatform.nl/v1/';


	/**
	 * Whether to log the detailed request/response info.
	 *
	 * @var bool
	 */
	protected $is_logging_enabled = true;

	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		/**
		 * Add integration code here.
		 * Integration classes instantiates before anything else
		 *
		 * @see Bootstrap::__construct
		 */


        
	}

    /**
     * The current API endpoint URL.
     *
     * @var string
     */
    private  function apiEndpoint(){
        if(!Helper::isTestMode()){
           return self::ENDPOINT_PRODUCTION;
        }
        return self::ENDPOINT_SANDBOX;
    }


	/**
	 * Get headers data for cURL request.
	 *
	 * @return array
	 */
	public function header() {
		$headers =
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->getAuthorizationKey(),
					'Content-Type'  => 'application/json',
				),
			);
		return $headers;
	}

	/**
	 * Get base64 encoded authorization data.
	 *
	 * @return string
	 */
	public function getAuthorizationKey() {
		return Helper::getSecretKey();
	}

	/**
	 * Check the API status.
	 *
	 * @return bool|string True if the API is accessible, otherwise, the error message.
	 */
	public function checkApi() {
		$result = $this->get( 'transactions?perpage=1' );
		if ( $result['success'] ) {
			return true;
		} else {
			return $result['data']['error-message'];

		}
	}

	/**
	 * Make a POST request to the API.
	 *
	 * @param string $endpoint The API endpoint.
	 * @param array  $data     The data to be sent in the request.
	 *
	 * @return array The API response.
	 */
	public function post( $endpoint, $data ): array {

		$args         = $this->header();
		$args['body'] = wp_json_encode( $data );

		$response = wp_remote_post( $this->apiEndpoint() . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'status' => 'error',
					'error'  => array(
						'code'       => $response->get_error_codes(),
						'message'    => $response->get_error_message(),
					),
				),
			);
		} else {
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body );

			if ( $response_code === 200 ) {
				return array(
					'success' => true,
					'data'    => $response_data,
				);
			} else {
				return array(
					'success' => false,
					'data'    => array(
						'status' => 'error',
						'error'  => array(
							'code'       => $response_code,
							'message'    => $response_data->error->message,
							'parameters' => $response_data->error->parameters,
						),
					),
				);
			}
		}
	}

	/**
	 * Make a GET request to the API.
	 *
	 * @param string $endpoint The API endpoint.
	 *
	 * @return array The API response.
	 */
	public function get( $endpoint ): array {

		$args     = $this->header();
		$response = wp_remote_get( $this->apiEndpoint() . $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'data'    => array(
					'status' => 'error',
					'error'  => array(
						'code'       => $response->get_error_codes(),
						'message'    => $response->get_error_message(),
					),
				),
			);
		} else {
			$response_code = wp_remote_retrieve_response_code( $response );
			$response_body = wp_remote_retrieve_body( $response );
			$response_data = json_decode( $response_body );

			if ( $response_code === 200 ) {
				return array(
					'success' => true,
					'data'    => $response_data,
				);
			} else {
				return array(
					'success' => false,
					'data'    => array(
						'status'        => 'error',
						'error'         => $response_code,
						'error-message' => $response_data->error->message,
					),
				);
			}
		}
	}

	/**
	 * Get payment options.
	 *
	 * @param string|null $payment_type The specific payment type to retrieve options for.
	 *
	 * @return array|string Array of payment options or a specific option if $payment_type is provided.
	 */
	public function paymentOptions( $payment_type = null ) {
		$payment_options = array(
			'ideal'          => __( 'iDEAL', 'online-payment-platform-gateway' ),
			'bcmc'           => __( 'Bancontact/Mister Cash', 'online-payment-platform-gateway' ),
			'sepa'           => __( 'SEPA Bank Transfer', 'online-payment-platform-gateway' ),
			'paypal-pc'      => __( 'PayPal', 'online-payment-platform-gateway' ),
			'creditcard'     => __( 'Creditcard', 'online-payment-platform-gateway' ),
			'sofort-banking' => __( 'Sofort', 'online-payment-platform-gateway' ),
			'mybank'         => __( 'MyBank', 'online-payment-platform-gateway' ),
			'giropay'        => __( 'GiroPay', 'online-payment-platform-gateway' ),
		);
		if ( empty( $payment_type ) ) {
			return $payment_options; }
		return $payment_options[ $payment_type ];
	}

}
