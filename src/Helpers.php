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

/**
 * Function to limit characters in a string.
 *
 * @param string $string The input string.
 * @param int    $limit  The character limit.
 *
 * @return string The truncated string.
 */
function opp_limit_characters( $string, $limit = 100 ) {
	$str_limit = $limit - 3;
	if ( mb_strlen( $string ) <= $limit ) {
		return $string;
	} else {
		return mb_substr( $string, 0, $str_limit ) . '...';
	}
}

// Register REST API endpoint
add_action( 'rest_api_init', 'opp_api_init' );
/**
 * Initialize REST API endpoint.
 */
function opp_api_init() {
	register_rest_route(
		'myplugin/v1',
		'/myendpoint/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'opp_custom_callback',
			// Here we register our permissions callback. The callback is fired before the main callback to check if the current user can access the endpoint.
			'permission_callback' => 'opp_get_private_data_permissions_check',
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $param, $request, $key ) {
						return is_numeric( $param );
					},
				),
			),
		)
	);
}
/**
 * Callback function for the REST API endpoint.
 *
 * @return WP_REST_Response The REST response object.
 */
function opp_get_private_data_permissions_check() {
	// Restrict endpoint to only users who have the edit_posts capability.
	if ( ! current_user_can( 'edit_posts' ) ) {
		return new WP_Error( 'rest_forbidden', esc_html__( 'OMG you can not view private data.', 'online-payment-platform-gateway' ), array( 'status' => 401 ) );
	}

	// This is a black-listing approach. You could alternatively do this via white-listing, by returning false here and changing the permissions check.
	return true;
}

/**
 * Permissions check function for the REST API endpoint.
 *
 * @param WP_REST_Request $request The REST request object.
 *
 * @return bool|WP_Error True if the user has permission, WP_Error otherwise.
 */
function opp_custom_callback( $request ) {
	$id = $request->get_param( 'id' );

	// Perform logic based on the received ID parameter
	// Example: Fetch data based on the ID from the database
	$data = array( 'message' => 'Received ID: ' . $id );

	return new WP_REST_Response( $data, 200 );
}
