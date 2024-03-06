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

namespace OnlinePaymentPlatformGateway\Integrations\woocommerce;

use OnlinePaymentPlatformGateway\Integrations\Gateway\Helper;

/**
 * Class HTML_Widget
 *
 * @package OnlinePaymentPlatformGateway\Integrations\Widget
 */
class Product {



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

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'productCustomFields' ) );
		add_action( 'dokan_product_edit_after_title', array( $this, 'productCustomFields' ) );

		add_action( 'woocommerce_process_product_meta', array( $this, 'saveProductCustomFields' ) );
		add_action( 'dokan_process_product_meta', array( $this, 'saveProductCustomFields' ) );

	}

	/**
	 * Display product/service selection dropdown in the admin interface.
	 *
	 * @global int $thepostid The ID of the post being edited.
	 * @global WP_Post  $post The post object.
	 */
	public function productCustomFields() {
		global $thepostid, $post;
		$opp_post_id = empty( $thepostid ) ? $post->ID : $thepostid;
		$value       = get_post_meta( $opp_post_id, 'select_product_service', true ) ? get_post_meta( $opp_post_id, 'select_product_service', true ) : '';

		?>
		<p class="form-field">
			<label for="select_product_service" class="form-label">Select Product/Service</label>
			<select name="select_product_service" id="select_product_service" class="woocommerce_options_panel short">
				<option value="product" <?php selected( $value, 'product' ); ?>>Product</option>
				<option value="service" <?php selected( $value, 'service' ); ?>>Service</option>
			</select>
		</p>
		<?php
	}


	/**
	 * Save product/service selection from the admin interface.
	 *
	 * @param int $product_id The ID of the product being saved.
	 */
	public function saveProductCustomFields( $product_id ) {

		// Check & Validate the woocommerce meta nonce & Dokan product nonce.
		if ( ! isset( $_POST['dokan_edit_product_nonce'] ) && ! isset( $_POST['woocommerce_meta_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_key( $_POST['dokan_edit_product_nonce'] ), 'dokan_edit_product' ) && ! wp_verify_nonce( sanitize_key( $_POST['woocommerce_meta_nonce'] ), 'woocommerce_save_data' ) ) {
			return;
		}

		$selected_methods = isset( $_POST['select_product_service'] ) ? sanitize_text_field( wp_unslash( $_POST['select_product_service'] ) ) : '';

		update_post_meta( $product_id, 'select_product_service', $selected_methods );
	}


}
