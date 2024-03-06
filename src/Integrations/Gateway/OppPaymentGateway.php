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

namespace OnlinePaymentPlatformGateway\Integrations\Gateway;

use OnlinePaymentPlatformGateway\Config\Plugin;
use OnlinePaymentPlatformGateway\Integrations\OPPApi\Transaction;
use WC_Order;
use WC_Payment_Gateway;
use WeDevs\Dokan\Exceptions\DokanException;
use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;
use WP_Error;

/**
 * Class HTML_Widget
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class OppPaymentGateway extends WC_Payment_Gateway {


	/**
	 * Transaction instance.
	 *
	 * @var Transaction
	 */
	private $transaction;

	/**
	 * Plugin configuration.
	 *
	 * @var array
	 */
	private $plugin = array();

	/**
	 * Logger instance.
	 *
	 * @var mixed
	 */
	private $logger;

	/**
	 * Transaction escrow flag.
	 *
	 * @var bool
	 */
	public $transaction_escrow;

	/**
	 * Transaction escrow period.
	 *
	 * @var int
	 */
	public $transaction_escrow_period;

	/**
	 * Test mode flag.
	 *
	 * @var bool
	 */
	public $test_mode;

	/**
	 * API key.
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * Debug flag.
	 *
	 * @var bool
	 */
	public $debug;


	/**
	 * Initialize the class.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		/**
		 * Integration classes instantiates before anything else
		 *
		 * @see Bootstrap::__construct
		 *
		 * Widget is registered via the app/general/widgets class, but it is also
		 * possible to register from this class
		 * @see Widgets
		 */
		$this->hooks();
	}

	/**
	 * Constructor for the OnlinePaymentPlatformGateway class.
	 */
	public function __construct() {
		$this->plugin = Plugin::init();
		// LOAD THE WC LOGGER
		$this->logger = wc_get_logger();

		$this->transaction = new Transaction();

		// Setup general properties.
		$this->id                 = 'online-payment-platform-gateway';
		$this->method_title       = esc_html__( 'Online Payment Platform Gateway', 'online-payment-platform-gateway' );
		$this->method_description = esc_html__( 'WooCommerce Payment Gateway for Online Payment Platform (OPP)', 'online-payment-platform-gateway' );
		$this->icon               = ''; // URL of the icon that will be displayed on the checkout page near your gateway name
		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = array(
			'products',
			'refunds',
		);

		// Load the settings.
		$this->formFields();
		$this->init_settings();

		// Get settings.
		$this->title                     = $this->get_option( 'title' );
		$this->description               = $this->get_option( 'description' );
		$this->enabled                   = $this->get_option( 'enabled' );
		$this->transaction_escrow        = 'yes' === $this->get_option( 'transaction_escrow', 'no' );
		$this->transaction_escrow_period = $this->get_option( 'transaction_escrow_period' );
		$this->test_mode                 = 'yes' === $this->get_option( 'test_mode' );
		$this->api_key                   = $this->test_mode ? $this->get_option( 'test_api_key' ) : $this->get_option( 'live_api_key' );
		$this->defaul_merchant_uid       = $this->test_mode ? $this->get_option( 'test_default_merchant_uid' ) : $this->get_option( 'live_default_merchant_uid' );
		$this->debug                     = 'yes' === $this->get_option( 'debug_enabled', 'no' );

	}

	/**
	 * Init all the hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_api_webhook_opp_gateway', array( $this, 'webhook' ) );

		add_action( 'init', array( $this, 'checkResponse' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_page' ), 10, 2 );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'addPendingStatus' ), 10, 1 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'emailPendingStatusNote' ), 10, 1 );

	}


	/**
	 * Display a note in the order email for pending payment status.
	 *
	 * @param WC_Order $order The order object.
	 *
	 * @return string
	 */
	public function emailPendingStatusNote( $order ) {
		$opp_payment_status = get_post_meta( $order->get_id(), 'opp_transaction_status', true );
		if ( $opp_payment_status !== 'pending' ) {
			return '';
		}
		return printf(
			'<p><strong>%s</strong> %s</p>',
			esc_html__( 'Payment status note: ', 'online-payment-platform-gateway' ),
			esc_html__( ' Please be aware that the payment is still in the status "pending".', 'online-payment-platform-gateway' )
		);

	}

	/**
	 * Add a note about pending payment status on the order received page.
	 *
	 * @param int $order_id The order ID.
	 *
	 * @return void
	 */
	public function addPendingStatus( $order_id ) {
		$opp_payment_status = get_post_meta( $order_id, 'opp_transaction_status', true );
		if ( $opp_payment_status !== 'pending' ) {
			return;
		}
		?>
		<ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
			<li class="woocommerce-order-overview__payment-method method-status">
				<?php esc_html_e( 'Payment Status:', 'online-payment-platform-gateway' ); ?>
				<strong><?php esc_html_e( 'Pending', 'online-payment-platform-gateway' ); ?></strong>
			</li>
		</ul>
		<?php
	}

	/**
	 * Modify the Thank You page text based on the Online Payment Platform (OPP) transaction status.
	 *
	 * @param string   $thankyou_text The default Thank You page text.
	 * @param WC_Order $order The WooCommerce order object.
	 * @return string The modified Thank You page text.
	 */
	public function thankyou_page( $thankyou_text, $order ) {
		$opp_payment_status = get_post_meta( $order->get_id(), 'opp_transaction_status', true );
		if ( $opp_payment_status !== 'pending' ) {
			return $thankyou_text;
		}
		$thankyou_text .= esc_html__( ' Please be aware of that the payment is still in the status "pending".', 'online-payment-platform-gateway' );

		return $thankyou_text;
	}

	/**
	 * Webhook callback handler for processing OPP gateway events.
	 * This method generates and logs the webhook URL for verification.
	 * Logs the generated URL and the received webhook request for debugging purposes.
	 * Responds with a 200 OK status and 'callback' message to acknowledge the webhook.
	 *
	 * For check siteurl/wc-api/webhook_opp_gateway
	 */
	public function webhook() {
		// Log or echo the generated URL for verification
		$webhook_url = WC()->api_request_url( 'webhook_opp_gateway' );

		header( 'HTTP/1.1 200 OK' );
		echo 'callback';
		die();
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function formFields(): void {
		$this->form_fields = array(
			'enabled' => array(
				'title'       => esc_html__( 'Enable/Disable', 'online-payment-platform-gateway' ),
				'label'       => esc_html__( 'Enable Stripe', 'online-payment-platform-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title' => array(
				'title'       => esc_html__( 'Title', 'online-payment-platform-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'This controls the title which the user sees during checkout.', 'online-payment-platform-gateway' ),
				'default'     => esc_html__( 'Online Payment Platform (OPP)', 'online-payment-platform-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => esc_html__( 'Description', 'online-payment-platform-gateway' ),
				'type'        => 'textarea',
				'description' => esc_html__( 'This controls the description which the user sees during checkout.', 'online-payment-platform-gateway' ),
				'default'     => 'Pay with your payment gateway.',
				'desc_tip'    => true,
			),
			'display_notice_to_non_connected_sellers' => array(
				'title'       => esc_html__( 'Display Notice to Connect Seller', 'online-payment-platform-gateway' ),
				'label'       => esc_html__( 'If checked, non-connected sellers will receive announcement notice to connect their OPP account. ', 'online-payment-platform-gateway' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'If checked, non-connected sellers will receive announcement notice to connect their OPP account once in a week.', 'online-payment-platform-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'debug_enabled' => array(
				'title'       => esc_html__( 'Debug Enabled', 'online-payment-platform-gateway' ),
				'label'       => 'Debug Enabled',
				'type'        => 'checkbox',
				'description' => esc_html__( 'When enabled, valuable debugging information will be captured and stored in the WooCommerce logs.', 'online-payment-platform-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'test_mode' => array(
				'title'    => esc_html__( 'Test mode', 'online-payment-platform-gateway' ),
				'label'    => esc_html__( 'Enable Test Mode', 'online-payment-platform-gateway' ),
				'type'     => 'checkbox',
				'default'  => 'yes',
				'desc_tip' => true,
			),
			'transaction_escrow' => array(
				'title'       => esc_html__( 'Transaction Escrow', 'online-payment-platform-gateway' ),
				'label'       => esc_html__( 'OPP allows secure fund holding in escrow, ensuring controlled payment release to the seller at the end of the escrow period or as directed by the partner.', 'online-payment-platform-gateway' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'If checked, OPP provides the option to put money in escrow. By doing this, you can somewhat give a guaranteed feeling to buyer and seller and in the meantime have more control over when the money is released to the seller. OPP will put the money in the settlement of the merchant, only when the escrow period ends, or is manually ended by you as a partner.', 'online-payment-platform-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'transaction_escrow_period' => array(
				'title'       => esc_html__( 'Transaction Escrow Period (period in days)', 'online-payment-platform-gateway' ),
				'type'        => 'number',
				'description' => esc_html__( 'Escrow period in days.', 'online-payment-platform-gateway' ),
				'default'     => 14,
				'desc_tip'    => true,
			),
			'allow_non_connected_sellers' => array(
				'title'       => esc_html__( 'Non-connected sellers', 'online-payment-platform-gateway' ),
				'label'       => esc_html__( 'Allow ordering products from non-connected sellers (For this merchant Uid is required)', 'online-payment-platform-gateway' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'If this is enable, customers can order products from non-connected sellers. The payment will send to admin account.', 'online-payment-platform-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'live_api_key' => array(
				'title' => esc_html__( 'Live API Key', 'online-payment-platform-gateway' ),
				'type'  => 'text',
			),
			'live_default_merchant_uid' => array(
				'title'       => esc_html__( 'Live Merchant Uid (Partner Merchant Uid)', 'online-payment-platform-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'If vendor not linked to OPP account, payments will be directed to this Partner Merchant Uid.', 'online-payment-platform-gateway' ),
			),
			'test_api_key' => array(
				'title' => esc_html__( 'Sandbox API Key', 'online-payment-platform-gateway' ),
				'type'  => 'text',
			),
			'test_default_merchant_uid' => array(
				'title'       => esc_html__( 'Sanbox Merchant Uid (Partner Merchant Uid)', 'online-payment-platform-gateway' ),
				'type'        => 'text',
				'description' => esc_html__( 'If vendor not linked to OPP account, payments will be directed to this Partner Merchant Uid.', 'online-payment-platform-gateway' ),
			),

		);
	}


	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it
	 */
	public function payment_fields() {
		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// display the description with <p> tags etc.
			echo wp_kses_post( wpautop( $this->description ) );
		}

		// Add this action hook if you want your custom payment gateway to support it
		// do_action( 'woocommerce_opp_form_start', $this->id );

		$option_keys = array_keys( $this->transaction->paymentOptions() );

		woocommerce_form_field(
			'opp_payment_method',
			array(
				'type'     => 'radio',
				'class'    => array( 'opp_transaction_type form-row-wide' ),
				'label'    => esc_html__( 'Payment Method', 'online-payment-platform-gateway' ),
				'required' => true,
				'options'  => $this->transaction->paymentOptions(),
				'default'  => 'ideal',
			),
			reset( $option_keys )
		);

	}

	/**
	 * Process payment for the order
	 *
	 * @param int $order_id The order ID.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		// Verify nonce
		$nonce_value = isset( $_POST['woocommerce-process-checkout-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce-process-checkout-nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
			// Nonce verification failed, handle accordingly (e.g., show an error message)
			wc_add_notice( __( 'Security check failed. Please try again.', 'online-payment-platform-gateway' ), 'error' );
			return false;
		}

		if ( isset( $_POST['opp_payment_method'] ) ) {
			$payment_method = sanitize_text_field( wp_unslash( $_POST['opp_payment_method'] ) );
			$result         = $this->multiTransactionData( $order, $order_id, $payment_method );
		}

		if ( isset( $payment_method ) && $result['success'] && is_object( $result['data'] ) && $result['data']->status === 'created' ) {
			// Mark as on-hold (we're awaiting the payment)
			update_post_meta( $order_id, 'opp_order_multi_transaction_id', $result['data']->uid );

			$order->update_status( 'pending', sprintf( /* translators: %1$s: the plugin name */ esc_html__( '%s Awaiting payment!', 'online-payment-platform-gateway' ), $this->title ) );

			$all_orders = Helper::getAllOrdersToProcessed( $order );

			$has_suborder = $order->get_meta( 'has_sub_order' );
			if ( $has_suborder ) {
				$order->add_order_note( sprintf( /* translators: %1$s: the plugin name, %2$s: the multi transaction iD */ esc_html__( '%1$s Multi Transaction Created! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $result['data']->uid ) );
			}
			foreach ( $result['data']->transactions as $transaction ) {

				foreach ( $all_orders as $tmp_order ) {
					// return if $tmp_order not instance of WC_Order
					if ( ! $tmp_order instanceof \WC_Order ) {
						continue;
					}

					$tmp_order_id = $tmp_order->get_id();
					$seller_id    = dokan_get_seller_id_by_order( $tmp_order_id );
					// Get store information using the vendor ID
					$store_info = dokan_get_store_info( $seller_id );

					if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
						continue;
					}
					$tmp_order->add_order_note( sprintf( /* translators: %1$s: the plugin name, %2$s: the  transaction iD */ esc_html__( '%1$s payment Created for %2$s and vendor %3$s! Transaction ID: %4$s', 'online-payment-platform-gateway' ), $this->title, $tmp_order_id, '<a href="' . dokan_get_store_url( $seller_id ) . '">' . $store_info['store_name'] . '</a>', $transaction->uid ) );

					update_post_meta( $tmp_order_id, Helper::getOrderTransactionIdKey(), $transaction->uid );
				}
			}

			return array(
				'result'   => 'success',
				'redirect' => $result['data']->redirect_url,
			);
		} else {
			$order->add_order_note( esc_html__( 'Processing Response: Payment processing failed.', 'online-payment-platform-gateway' ), 'error' );
			wc_add_notice( esc_html__( 'Processing Response: Payment processing failed. Please try again.', 'online-payment-platform-gateway' ), 'error' );

			return false;
		}
	}

	/**
	 * Prepare data for a multi-transaction and initiate the transaction creation.
	 *
	 * This method gathers order details, constructs the initial data array,
	 * and processes each order to prepare transaction data for each seller.
	 * It handles escrow settings, sets merchant UID, and calculates partner fees.
	 *
	 * @param WC_Order $order The WooCommerce order object.
	 * @param int      $order_id The ID of the WooCommerce order.
	 * @param string   $payment_method The method of OPP payment.
	 * @return array|WP_Error The result of the transaction creation.
	 */
	protected function multiTransactionData( $order, $order_id, $payment_method ) {
		// Gather order details
		$all_orders         = Helper::getAllOrdersToProcessed( $order );
		$billing_email      = $order->get_billing_email();
		$billing_first_name = $order->get_billing_first_name();
		$billing_last_name  = $order->get_billing_last_name();

		// Prepare return URL
		$return_url = add_query_arg(
			array(
				'order'            => $order->get_id(),
				'opppayment'       => true,
				'_wpnonce'         => wp_create_nonce( 'opp_payment_' . $order->get_id() ),
				'env'              => $this->test_mode ? 'sandbox' : 'production',
			),
			$this->get_return_url( $order )
		);

		// Construct initial data array
		$data = array(
			'checkout'       => false,
			'payment_method' => $payment_method,
			'total_price'    => Helper::toCents( $order->get_subtotal() ),
			'shipping_costs' => Helper::toCents( $order->get_shipping_total() ),
			'partner_fee'    => Helper::toCents( Helper::getPartnerFee( $order->get_subtotal() ) ),
			'currency'       => get_woocommerce_currency(),
			'metadata'       => array(
				'order_id'       => (string) $order_id,
				'payment_method' => $this->transaction->paymentOptions( $payment_method ),
				'customer_name'  => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
				'customer_email' => sanitize_email( $billing_email ),
			),
			'return_url'     => $return_url,
			'notify_url'     => WC()->api_request_url( 'webhook_opp_gateway' ),
		);

		// Process each order
		foreach ( $all_orders as $tmp_order ) {
			$tmp_order_id = $tmp_order->get_id();
			$seller_id    = dokan_get_seller_id_by_order( $tmp_order_id );

			// Prepare transaction data
			$data['transactions'][ $seller_id ] = array(
				'buyer_name_first'   => opp_limit_characters( $order->get_billing_first_name(), 45 ),
				'buyer_name_last'    => opp_limit_characters( $order->get_billing_last_name(), 45 ),
				'buyer_emailaddress' => $order->get_billing_email(),
				'metadata'           => array(
					'order_id'       => (string) $tmp_order_id,
					'payment_method' => $this->transaction->paymentOptions( $payment_method ),
					'vendor_id'      => (string) $seller_id,
					'customer_name'  => sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name ),
					'customer_email' => sanitize_email( $billing_email ),
				),
			);

			// Handle escrow
			if ( $this->transaction_escrow === true ) {
				$data['transactions'][ $seller_id ]['escrow']        = true;
				$data['transactions'][ $seller_id ]['escrow_period'] = $this->transaction_escrow_period;
			}

			// Set merchant UID
			$merchant_uid = get_user_meta( $seller_id, 'opp_merchant_uid', true );

			$data['transactions'][ $seller_id ]['merchant_uid'] = ! empty( $merchant_uid ) ? $merchant_uid : $this->defaul_merchant_uid;

			// Process products for the seller
			$partner_fee        = 0;
			$products           = array();
			$product_metadata   = array();
			$seller_total_price = 0;
			$i                  = 0;

			foreach ( $order->get_items() as $item_id => $item_data ) {
				$author = get_post_field( 'post_author', $item_data['product_id'] );
				if ( intval( $seller_id ) === intval( $author ) ) {
					$product      = $item_data->get_product();
					$product_id   = $product->get_id();
					$product_type = get_post_meta( $product_id, 'select_product_service', true ) ? get_post_meta( $product_id, 'select_product_service', true ) : 'product';

					// Calculate partner fee and prepare product data
					$partner_fee = $item_data->get_quantity() * ( $partner_fee + Helper::getPartnerFee( $product->get_price() ) );

					$products[ $i ] = array(
						'name'     => $product->get_name(),
						'code'     => $product->get_sku(),
						'quantity' => $item_data->get_quantity(),
						'price'    => Helper::toCents( $product->get_price() ),
					);

					// Fetching category names
					$category_names = array();
					$categories     = get_the_terms( $product_id, 'product_cat' );
					if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
						foreach ( $categories as $category ) {
							$category_names[] = $category->name;
						}
					}

					// Fetching tag names
					$tag_names = array();
					$tags      = get_the_terms( $product_id, 'product_tag' );
					if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
						foreach ( $tags as $tag ) {
							$tag_names[] = $tag->name;
						}
					}

					// Prepare product metadata
					$product_metadata[ $i ] = array(
						'name'         => $product->get_name(),
						'code'         => $product->get_sku(),
						'product_type' => $product_type,
						'quantity'     => $item_data->get_quantity(),
						'price'        => Helper::toCents( $product->get_price() ),
						'id'           => strval( $product_id ),
						'link'         => $product->get_permalink(),
						'categories'   => implode( ', ', $category_names ),
						'tag'          => implode( ', ', $tag_names ),
					);

					$seller_total_price += $item_data->get_total();
					$i++;
				}
			}

			// Set transaction details for the seller
			$data['transactions'][ $seller_id ]['total_price']                 = Helper::toCents( $seller_total_price );
			$data['transactions'][ $seller_id ]['metadata']['product_details'] = wp_json_encode( $product_metadata );
			$data['transactions'][ $seller_id ]['partner_fee']                 = Helper::toCents( $partner_fee );
			$data['transactions'][ $seller_id ]['products']                    = $products;
		}
		// Perform the transaction creation and return
		return $this->transaction->create( $data, true );
	}




	/**
	 * Check the response from the online payment platform and update order status accordingly.
	 */
	public function checkResponse() {

		if ( isset( $_REQUEST['opppayment'] ) ) {
			global $woocommerce;
			$nonce_value = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			$order_id    = null;

			if ( isset( $_GET['order'] ) ) {
				$order_id = absint( wp_unslash( $_GET['order'] ) );
			}

			if ( ! wp_verify_nonce( $nonce_value, 'opp_payment_' . $order_id ) ) {
				return;
			}

			$order = wc_get_order( $order_id );

			if ( ! $order || $order_id === 0 || $order_id === '' ) {
				return;
			}

			if ( ! isset( $_GET['key'] ) || $order->get_order_key() !== sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) {
				return;
			}

			if ( $order->has_status( 'completed' ) || $order->has_status( 'processing' ) ) {
				return;
			}

			$opp_multi_transaction_id = get_post_meta( $order_id, 'opp_order_multi_transaction_id', true );
			$result                   = $this->transaction->retrieve( $opp_multi_transaction_id, true );
			update_post_meta( $order_id, 'opp_transaction_status', $result['data']->status );

			$all_orders   = Helper::getAllOrdersToProcessed( $order );
			$has_suborder = $order->get_meta( 'has_sub_order' );

			if ( ! $result['success'] ) {
				wc_add_notice( esc_html__( 'Your order has been failed.', 'online-payment-platform-gateway' ), 'error' );

				foreach ( $result['data']->transactions as $transaction ) {
					foreach ( $all_orders as $tmp_order ) {
						// return if $tmp_order not instance of WC_Order
						if ( ! $tmp_order instanceof \WC_Order ) {
							continue;
						}

						$tmp_order_id = $tmp_order->get_id();
						if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
							continue;
						}

						$tmp_order->update_status( 'failed', sprintf( /* translators: %1$s: the plugin name, %2$s: the transaction iD */ esc_html__( '%1$s payment failed! Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $transaction->uid ) );
					}
				}

				if ( $has_suborder ) {
					$tmp_order->update_status( 'failed', sprintf( /* translators: %1$s: the plugin name, %2$s: the transaction iD */ esc_html__( '%1$s payment failed! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $opp_multi_transaction_id ) );
				}

				$wc_order = new WC_Order( $order_id );
				wp_safe_redirect( $wc_order->get_checkout_payment_url( true ) );
				exit;
			}

			if ( $result['data']->status === 'cancelled' ) {

				wc_add_notice( esc_html__( 'Your order has been cancelled.', 'online-payment-platform-gateway' ), 'notice' );

				foreach ( $result['data']->transactions as $transaction ) {
					foreach ( $all_orders as $tmp_order ) {
						// return if $tmp_order not instance of WC_Order
						if ( ! $tmp_order instanceof \WC_Order ) {
							continue;
						}

						$tmp_order_id = $tmp_order->get_id();
						if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
							continue;
						}

						$tmp_order->update_status( /* translators: %1$s: the plugin name, %2$s: the  transaction iD */ 'cancelled', sprintf( esc_html__( '%1$s payment cancelled by user! Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $transaction->uid ) );
					}
				}

				if ( $has_suborder ) {
					$order->update_status( 'cancelled', sprintf( /* translators: %1$s: the plugin name, %2$s: the multi transaction iD */ esc_html__( '%1$s payment cancelled by user! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $opp_multi_transaction_id ) );
				}

				$wc_order = new WC_Order( $order_id );
				wp_safe_redirect( $wc_order->get_cancel_order_url() );
				exit;
			} elseif ( $result['data']->status === 'pending' ) {

				foreach ( $result['data']->transactions as $transaction ) {
					foreach ( $all_orders as $tmp_order ) {
						// return if $tmp_order not instance of WC_Order
						if ( ! $tmp_order instanceof \WC_Order ) {
							continue;
						}

						$tmp_order_id = $tmp_order->get_id();
						if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
							continue;
						}

						$tmp_order->update_status( 'on-hold', sprintf( /* translators: %1$s: the plugin name, %2$s: the transaction iD */ esc_html__( '%1$s payment is pending! Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $transaction->uid ) );
					}
				}

				if ( $has_suborder ) {
					$order->update_status( 'on-hold', sprintf( /* translators: %1$s: the plugin name, %2$s: the multi transaction iD */ esc_html__( '%1$s payment is pending! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $opp_multi_transaction_id ) );
				}
			} elseif ( $result['data']->status === 'completed' ) {
				// Payment complete
				$order->payment_complete( $opp_multi_transaction_id );

				foreach ( $result['data']->transactions as $transaction ) {
					$all_withdraws = array();
					foreach ( $all_orders as $tmp_order ) {
						// return if $tmp_order not instance of WC_Order
						if ( ! $tmp_order instanceof \WC_Order ) {
							continue;
						}

						$tmp_order_id       = $tmp_order->get_id();
						$vendor_id          = dokan_get_seller_id_by_order( $tmp_order_id );
						$vendor_raw_earning = dokan()->commission->get_earning_by_order( $tmp_order, 'seller' );
						if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
							continue;
						}

						$tmp_order->add_order_note( sprintf( /* translators: %1$s: the plugin name, %2$s: the transaction iD */ esc_html__( '%1$s payment approved! Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $transaction->uid ) );

						$tmp_order->update_meta_data( '_opp_transaction_uid', $transaction->uid );
						$tmp_order->update_meta_data( '_opp_multi_transaction_uid', $opp_multi_transaction_id );
						$tmp_order->update_meta_data( '_opp_transaction', $transaction );
						$tmp_order->update_meta_data( '_opp_payment_details', $transaction->payment_details );

						// set transaction id
						$tmp_order->set_transaction_id( $transaction->uid );
						$tmp_order->save();

						$withdraw_data = array(
							'user_id'  => $vendor_id,
							'amount'   => $vendor_raw_earning,
							'order_id' => $tmp_order_id,
						);

						$all_withdraws[] = $withdraw_data;
					}

					$this->insert_into_vendor_balance( $all_withdraws );
					$this->process_seller_withdraws( $all_withdraws );
				}

				if ( $has_suborder ) {
					$order->add_order_note( sprintf( /* translators: %1$s: the plugin name, %2$s: the multi transaction iD */ esc_html__( '%1$s payment approved! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $opp_multi_transaction_id ) );
				}

				// Remove cart
				$woocommerce->cart->empty_cart();
				$order->save();
			} else {

				wc_add_notice( esc_html__( 'Your order has been failed.', 'online-payment-platform-gateway' ), 'error' );

				foreach ( $result['data']->transactions as $transaction ) {
					foreach ( $all_orders as $tmp_order ) {
						// return if $tmp_order not instance of WC_Order
						if ( ! $tmp_order instanceof \WC_Order ) {
							continue;
						}

						$tmp_order_id = $tmp_order->get_id();
						if ( $tmp_order_id !== (int) $transaction->metadata[0]->value ) {
							continue;
						}

						$tmp_order->update_status(
							'failed',
							sprintf(/* translators: %1$s: the plugin name, %2$s: the transaction iD */
								esc_html__( '%1$s payment failed! Transaction ID: %2$s', 'online-payment-platform-gateway' ),
								$this->title,
								$transaction->uid
							)
						);
					}
				}

				if ( $has_suborder ) {
					$order->update_status( 'failed', sprintf( /* translators: %1$s: the plugin name, %2$s: the multi transaction iD */ esc_html__( '%1$s payment failed! Multi Transaction ID: %2$s', 'online-payment-platform-gateway' ), $this->title, $opp_multi_transaction_id ) );
				}

				$wc_order = new WC_Order( $order_id );
				wp_safe_redirect( $wc_order->get_checkout_payment_url() );
				exit;
			}
		}}


	/**
	 * Insert withdrawal data into the vendor balance table.
	 *
	 * @param array $all_withdraws Withdrawal data.
	 */
	public function insert_into_vendor_balance( $all_withdraws ) {
		if ( ! $all_withdraws ) {
			return;
		}

		global $wpdb;

		foreach ( $all_withdraws as $withdraw ) {

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$wpdb->prefix . 'dokan_vendor_balance',
				array(
					'vendor_id'     => $withdraw['user_id'],
					'trn_id'        => $withdraw['order_id'],
					'trn_type'      => 'dokan_withdraw',
					'perticulars'   => 'Paid Via OPP',
					'debit'         => 0,
					'credit'        => $withdraw['amount'],
					'status'        => 'approved',
					'trn_date'      => current_time( 'mysql' ),
					'balance_date'  => current_time( 'mysql' ),
				),
				array(
					'%d',
					'%d',
					'%s',
					'%s',
					'%f',
					'%f',
					'%s',
					'%s',
					'%s',
				)
			);
		}
	}


	/**
	 * Automatically process seller withdrawals for sellers per order.
	 *
	 * @param array $all_withdraws Withdrawal data.
	 */
	public function process_seller_withdraws( $all_withdraws ) {
		if ( ! $all_withdraws ) {
			return;
		}

		$ip = dokan_get_client_ip();

		foreach ( $all_withdraws as $withdraw_data ) {

			$data = array(
				'date'   => current_time( 'mysql' ),
				'status' => 1,
				'method' => Helper::getGatewayId(),
				'notes'  => sprintf( /* translators: %1$d: the order id, %2$s: the plugin name */ esc_html__( 'Order %1$d payment Auto paid via %2$s', 'online-payment-platform-gateway' ), $withdraw_data['order_id'], Helper::getGatewayTitle() ),
				'ip'     => $ip,
			);

			$data = array_merge( $data, $withdraw_data );
			dokan()->withdraw->insert_withdraw( $data );
		}
	}

}
