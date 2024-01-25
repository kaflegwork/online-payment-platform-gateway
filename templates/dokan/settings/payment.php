<?php
/**
 * Dokan Settings Payment Template
 *
 * @since   2.2.2 Insert action before payment settings form
 *
 * @package dokan
 */

//phpcs:disable
use OnlinePaymentPlatformGateway\Integrations\dokan\Dokan;

$dokan    = new Dokan();
$merchant = new \OnlinePaymentPlatformGateway\Integrations\OPPApi\Merchant();

$has_methods = false;
if ( ! dokan_is_seller_enabled( $current_user ) ) {
	/**
	 *  Loading no permission error template
	 *
	 * @since  2.4
	 */
	dokan_get_template_part(
		'global/dokan-error',
		'',
		array(
			'deleted' => false,
			'message' => __( 'You have no permission to view this page', 'online-payment-platform-gateway' ),
		)
	);
	return;
}

do_action( 'dokan_payment_settings_before_form', $current_user, $profile_info ); ?>

<div class="dokan-payment-settings-summary">
	<h2 id="vendor-dashboard-payment-settings-error"></h2>
	<div class="payment-methods-listing-header">
		<h2> <?php esc_html_e( 'Payment Methods', 'online-payment-platform-gateway' ); ?></h2>
		<div>
			<div id="vendor-dashboard-payment-settings-toggle-dropdown">
				<a id="toggle-vendor-payment-method-drop-down"> <?php esc_html_e( 'Add Payment Method', 'online-payment-platform-gateway' ); ?></a>
				<div id="vendor-payment-method-drop-down-wrapper">
					<div id="vendor-payment-method-drop-down">
						<?php if ( is_array( $unused_methods ) && ! empty( $unused_methods ) ) : ?>
							<ul>
								<?php foreach ( $unused_methods as $method_key => $method ) : ?>
									<li>
										<a href="<?php echo esc_url( dokan_get_navigation_url( 'settings/payment-manage-' . $method_key ) ); ?>">
											<div>
												<img src="<?php echo esc_url( dokan_withdraw_get_method_icon( $method_key ) ); ?>"
													 alt="<?php echo esc_attr( $method_key ); ?>"/>
												<span>
												<?php
												// translators: %s: payment method title
												printf( esc_html__( 'Direct to %s', 'online-payment-platform-gateway' ), apply_filters( 'dokan_payment_method_title', $method['title'], $method ) );
												?>
											</span>
											</div>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php else : ?>
							<div class="no-content">
								<?php esc_html_e( 'There is no payment method to add.', 'online-payment-platform-gateway' ); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php if ( is_array( $methods ) && ! empty( $methods ) ) : ?>
		<ul>
			<?php foreach ( $methods as $method_key => $method ) : ?>
				<li>
					<div>
						<div>
							<img src="<?php echo esc_url( dokan_withdraw_get_method_icon( $method_key ) ); ?>"
								 alt="<?php echo esc_attr( $method_key ); ?>"/>
							<span>
							<?php
							echo esc_html( apply_filters( 'dokan_payment_method_title', $method['title'], $method ) );

							if ( isset( $profile_info['payment'][ $method_key ] ) && ! empty( dokan_withdraw_get_method_additional_info( $method_key ) ) ) {
								?>
								<small><?php echo dokan_withdraw_get_method_additional_info( $method_key ); ?></small>
								<?php
							}
							?>

								<?php

								if ( $method_key === 'online-payment-platform' ) :
									?>
									<?php

									if ( $merchant->checkApi() !== true ) {
										echo $merchant->getAuthorizationKey();
										echo '<br>';
										esc_html_e( 'At the moment, this service is unavailable. Please try again later.', 'online-payment-platform' );
										return; }

									?>

									<br>
									<strong class="opp-text-info"
											title="<?php echo $merchant->getStatusDesc(); ?>"><?php esc_html_e( 'Merchant Status: ', 'online-payment-platform-gateway' ); ?><?php echo $merchant->getInfo(); ?></strong>
									<br>
									<strong class="opp-text-info"
											title="<?php echo $merchant->getComplianceDesc(); ?>"><?php esc_html_e( 'Compliance Status: ', 'online-payment-platform-gateway' ); ?><?php echo $merchant->getInfo( 'compliance_status' ); ?></strong>
									<br>

									<strong class="opp-text-info"
											title="<?php echo $merchant->getBankStatusDesc(); ?>"><?php esc_html_e( 'Bank Account status: ', 'online-payment-platform-gateway' ); ?><?php echo $merchant->getBankInfo(); ?></strong>
								<?php endif; ?>
						</span>

						</div>

						<?php

						if ( $method_key === 'online-payment-platform' ) :


							// $dokan->createBankAccountButton();
							$dokan->updateKycButton();
						endif;
						?>
						<div>
							<a href="<?php echo esc_url( dokan_get_navigation_url( 'settings/payment-manage-' . $method_key . '-edit' ) ); ?>">
								<button class="dokan-btn-theme dokan-btn-sm"><?php esc_html_e( 'Manage', 'online-payment-platform-gateway' ); ?></button>
							</a>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="no-content">
			<?php esc_html_e( 'There is no payment method to show.', 'online-payment-platform-gateway' ); ?>
		</div>
	<?php endif; ?>
</div>

<?php
/**
 * @since 2.2.2 Insert action after social settings form
 */
do_action( 'dokan_payment_settings_after_form', $current_user, $profile_info ); ?>
