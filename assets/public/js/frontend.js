jQuery(function($){

    // Dokan OPP connect redirection
    $( document ).on( "ajaxComplete", function( event, request, settings ) {

        let response = JSON.parse( request.responseText);
        // Check if the response was successful
        if (response.success ) {
            // Get the URL and redirect value
            var redirectValue = response.data.redirect;
            var urlValue = response.data.url;

            // Perform actions based on the values received
            if (redirectValue === 'opp') {
                // Redirect to the received URL
                window.location.href = urlValue;
            } else {
                // Handle other cases or perform other actions
                console.log("Received redirect value is different.");
            }
        }
    } );
    // OPP payment method selected in checkout
    $( document ).on( "ajaxComplete", function( event, request, settings ) {
        // Check if the body has the required class
        if ($('body').hasClass('woocommerce-checkout')) {
            // Function to toggle visibility based on payment method
            function toggleAgreeFieldVisibility() {
                let selectedPaymentMethod = $('input[name="payment_method"]:checked').val();

                if (selectedPaymentMethod !== 'online-payment-platform-gateway') {
                    $('#agree_term_condition_opp_field').hide();
                } else {
                    $('#agree_term_condition_opp_field').show();
                }
            }

            // On change of payment method, toggle visibility
            $('form.checkout').on('change', 'input[name="payment_method"]', function () {
                toggleAgreeFieldVisibility();
            });

            // Initially hide/show based on the default selected payment method on page load
            $(document).ready(function () {
                toggleAgreeFieldVisibility();
            });
        }
    });
})