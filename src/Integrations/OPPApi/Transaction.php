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

namespace OnlinePaymentPlatformGateway\Integrations\OPPApi;

/**
 * Class Api
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Example
 * @since 1.0.0
 */
class Transaction extends OPPApi {

	/**
	 * Create a new transaction.
	 *
	 * @param array $data   The data for the transaction.
	 * @param bool  $multi  Whether it's a multi-transaction.
	 *
	 * @return array        The result of the transaction creation.
	 */
	public function create( $data, $multi = false ) {
		if ( $multi ) {
			$endpoint = 'multi_transactions';
		} else {
			$endpoint = 'transactions';
		}
		return $this->post( $endpoint, $data );
	}

	/**
	 * Retrieve a transaction.
	 *
	 * @param string $transaction_uid The unique identifier of the transaction.
	 * @param bool   $multi           Whether it's a multi-transaction.
	 *
	 * @return array                  The retrieved transaction data.
	 */
	public function retrieve( $transaction_uid, $multi = false ) {
		if ( $multi ) {
			$endpoint = 'multi_transactions/' . $transaction_uid;
		} else {
			$endpoint = 'transactions/' . $transaction_uid;
		}
		return $this->get( $endpoint );
	}

	/**
	 * Create a refund for a specific transaction.
	 *
	 * @param string $transaction_uid The unique identifier of the transaction.
	 * @param array  $data            The data for the refund.
	 *
	 * @return array                  The result of the refund creation.
	 */
	public function createRefund( $transaction_uid, $data ): array {
		$endpoint = 'transactions/' . $transaction_uid . '/refunds';
		return $this->post( $endpoint, $data );
	}

}
