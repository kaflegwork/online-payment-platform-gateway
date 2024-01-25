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

/**
 * Class Api
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Example
 * @since 1.0.0
 */
class Merchant extends OPPApi {

	/**
	 * Merchant's unique identifier.
	 *
	 * @var string|null
	 */
	protected $merchant_uid;

	/**
	 * Bank account's unique identifier associated with the merchant.
	 *
	 * @var string|null
	 */
	protected $bank_account_uid;

	/**
	 * Initialize the class.
	 *
	 * This constructor checks if Dokan is active and the current user is a Dokan vendor.
	 * If true, it sets the merchant and bank account UIDs based on user meta.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Check if Dokan is active
		if ( class_exists( 'WeDevs_Dokan' ) ) {

			// Check if the current user is a Dokan vendor
			$user_id   = get_current_user_id();
			$is_vendor = dokan_is_user_seller( $user_id );

			if ( $is_vendor ) {
				$this->merchant_uid     = get_user_meta( $user_id, 'opp_merchant_uid', true );
				$this->bank_account_uid = get_user_meta( $user_id, 'opp_bankAccount_uid', true );
			}
		}
	}

	/**
	 * Create a merchant.
	 *
	 * @param array $data The data for creating a merchant.
	 *
	 * @return array The API response.
	 */
	public function create( $data ) {
		$endpoint          = 'merchants';
		$check_email_exist = $this->retrieve( sanitize_email( $data['emailaddress'] ) );

		if ( $check_email_exist['success'] && count( $check_email_exist['data']->data ) === 1 ) {
			return array(
				'success'           => true,
				'check_email_exist' => true,
				'data'              => $check_email_exist['data']->data[0],
			);
		}
		return $this->post( $endpoint, $data );
	}

	/**
	 * Retrieve merchant information.
	 *
	 * @param string|null $emailaddress The email address to filter merchants.
	 *
	 * @return array|bool The API response or false if no merchant UID is available.
	 */
	public function retrieve( $emailaddress = null ) {
		if ( empty( $this->merchant_uid ) && empty( $emailaddress ) ) {
			return false;
		}
		$endpoint = 'merchants/' . $this->merchant_uid;
		if ( ! empty( $emailaddress ) ) {
			$endpoint = 'merchants/?filter[emailaddress]=' . $emailaddress;
		}
		return $this->get( $endpoint );
	}


	/**
	 * Get the overview URL of the merchant's compliance.
	 *
	 * @return string|bool The overview URL or false if data retrieval fails.
	 */
	public function overviewUrl() {
		$get_data = $this->retrieve();

		if ( ! $get_data ) {
			return false;
		}
		return $get_data['data']->compliance->overview_url;

	}


	/**
	 * Create a bank account associated with the merchant.
	 *
	 * @param string|null $merchant_uid The merchant UID.
	 *
	 * @return array The API response.
	 */
	public function createBankAccount( $merchant_uid = null ) {
		$data = array(
			'return_url' => site_url(),
			'notify_url' => site_url(),
		);
		if ( ! empty( $merchant_uid ) ) {
			$this->merchant_uid = $merchant_uid;
		}

		$endpoint = 'merchants/' . $this->merchant_uid . '/bank_accounts';
		return $this->post( $endpoint, $data );

	}

	/**
	 * Retrieve information about the bank account associated with the merchant.
	 *
	 * @return array|bool The API response or false if no merchant or bank account UID is available.
	 */
	public function retrieveBankAccount() {

		if ( empty( $this->merchant_uid ) || empty( $this->bank_account_uid ) ) {
			return false;
		}

		$endpoint = 'merchants/' . $this->merchant_uid . '/bank_accounts/' . $this->bank_account_uid;
		return $this->get( $endpoint );
	}

	/**
	 * Get information about the bank account associated with the merchant.
	 *
	 * @param string $type The type of information to retrieve (status or verification_url).
	 *
	 * @return bool|string The requested information or false if data retrieval fails.
	 */
	public function getBankInfo( $type = 'status' ) {
		$get_data = $this->retrieveBankAccount();

		if ( ! $get_data ) {
			return false;
		}
		$response = $get_data['data']->status;
		if ( $type === 'verification_url' ) {
			$response = $get_data['data']->verification_url;
		}
		return $response;
	}

	/**
	 * Get a description of the bank account status.
	 *
	 * @return bool|string The status description or false if data retrieval fails.
	 */
	public function getBankStatusDesc() {
		if ( ! $this->getBankInfo() ) {
			return false;
		}
		$args = array(
			'new'         => esc_html__( 'The bank_account is created and still needs to be verified by the merchant.', 'online-payment-platform-gateway' ),
			'pending'     => esc_html__( 'The bank_account has been verified by the merchant and is awaiting approval by our compliance department.', 'online-payment-platform-gateway' ),
			'approved'    => esc_html__( 'The bank_account is approved and linked for payouts.', 'online-payment-platform-gateway' ),
			'disapproved' => esc_html__( 'The bank_account is disapproved by our compliance department.', 'online-payment-platform-gateway' ),
		);

		return $args[ $this->getBankInfo() ];
	}

	/**
	 * Get information about the merchant.
	 *
	 * @param string $type The type of information to retrieve (status, compliance_status, or level).
	 *
	 * @return bool|string The requested information or false if data retrieval fails.
	 */
	public function getInfo( $type = 'status' ) {

		$get_data = $this->retrieve();

		if ( ! $get_data['success'] ) {
			return $get_data['data']['error-message'];
		}

		$response = $get_data['data']->status;

		if ( $type === 'compliance_status' ) {
			$response = $get_data['data']->compliance->status;
		}
		if ( $type === 'level' ) {
			$response = $get_data['data']->compliance->level;
		}

		return $response;

	}

	/**
	 * Get a description of the merchant status.
	 *
	 * @return bool|string The status description or false if data retrieval fails.
	 */
	public function getStatusDesc() {
		$args = array(
			'new'        => esc_html__( 'Merchant is created.', 'online-payment-platform-gateway' ),
			'pending'    => esc_html__( 'Merchant is pending to be monitored.', 'online-payment-platform-gateway' ),
			'live'       => esc_html__( '	Merchant is live and ready to be used.', 'online-payment-platform-gateway' ),
			'terminated' => esc_html__( 'Manually terminated by the partner or OPP, merchant cannot receive payouts and no transactions can be created. Can be undone by partner or OPP.', 'online-payment-platform-gateway' ),
			'suspended'  => esc_html__( 'Merchant is temporarily blocked by partner or OPP, no transactions can be made and merchant cannot be paid out. Can be unsuspended by OPP compliance.', 'online-payment-platform-gateway' ),
			'blocked'    => esc_html__( 'Permanent block of the merchant. No payouts can be done and no transactions can be created. Cannot be undone.', 'online-payment-platform-gateway' ),
		);

		return $args[ $this->getInfo() ];
	}

	/**
	 * Get a description of the merchant's compliance status.
	 *
	 * @return bool|string The compliance description or false if data retrieval fails.
	 */
	public function getComplianceDesc() {
		$args = array(
			'unverified' => esc_html__( 'Merchant is unverified and unable to receive payouts. Merchant should meet all unverified ‘compliance requirements’ to get to the ‘verified’ state. Unverified means that no verifications have been done yet, or delivered verifications have been disapproved.', 'online-payment-platform-gateway' ),
			'pending'    => esc_html__( "Merchant compliance status is pending and is unable to receive payouts. Merchant is awaiting the review of the merchant's updated ‘compliance requirements’ by OPP.", 'online-payment-platform-gateway' ),
			'verified'   => esc_html__( 'Merchant compliance status is verified and the merchant is able to receive payouts.', 'online-payment-platform-gateway' ),
		);

		return $args[ $this->getInfo( 'compliance_status' ) ];
	}
}
