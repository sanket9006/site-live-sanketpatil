jQuery( document ).ajaxStop(function() {

    var jwk = flex_microform_params.jwk;
    var form = jQuery("[name='checkout']");
    var payButton = document.getElementById('place_order');
    //var payButton = jQuery("#place_order");
    var microformIFrame = jQuery("#cybersource_cc-card-number iframe");

    // Only load a new microform if it doesn't already exist
    if(microformIFrame.length < 1) {

        // Change payButton type to button from submit to allow createToken
        // callback to execute first before form submission.
        payButton.type = "button";

        // Setup Microform
        FLEX.microform(
            {
                keyId: jwk.kid,
                keystore: jwk,
                container: '#cybersource_cc-card-number',
                styles: {
                    'input': {
                    'font-size': '1.41575em',
                    'font-family': 'Lato, sans-serif'
                    }
                },            
                encryptionType: 'rsaoaep'
            },
            function (setupError, microformInstance) {
                if (setupError) {
                    // An error here will usually cause the CC field to not be
                    // editable. Therefore an alert will be shown, and the customer
                    // notified that the page will be reloaded.
                    //console.log('setup error ' + JSON.stringify(setupError) );
                    alert("Page failed to load securely. Page will reload after this message. If it occurs repeatedly contact website owner.");
                    location.reload(true);
                    return;
                }

                // intercept the form submission and make a tokenize request instead
                payButton.addEventListener('click', function () {

                    // If a different payment method is selected or
                    // if a card on file is being used submit the form now
                    if(jQuery("#payment_method_cybersource_cc").is(":not(':checked')") || 
                       jQuery("#wc-cybersource_cc-payment-token-new").is(":not(':checked')")) {
                        form.submit();
                        return;
                    }

                    // Grab Date and change to format used by CyberSource
                    var expirationDate = jQuery("#cybersource_cc-card-expiry").val();
                    
                    expirationMonth = expirationDate.substring(0,2);
                    expirationYear = expirationDate.substring(5);

                    if(expirationYear.length < 3) {
                        expirationYear = "20" + expirationYear;
                    }

                    // Send in parameters from other parts of your payment form
                    var options = {
                        cardExpirationMonth: expirationMonth,
                        cardExpirationYear: expirationYear
                    };

                    // Make the request to get a token
                    microformInstance.createToken(options, function (err, response) {
                        if (err) {
                            // If a token is not created - the API call to CyberSource
                            // will not work on the server, an error will be logged
                            // and a message shown to the customer.
                            //console.log('Create token error: ' + err);
                            return;
                        }
                        console.log('Token generated: ');
                        console.log(JSON.stringify(response));
                        // Add the data to the form needed on the server side to complete the transaction
                        form.append('<input type="hidden" name="microform_token" id="microform_token" value="' + response.token + '" />');
                        form.append('<input type="hidden" name="microform_masked_pan" id="microform_masked_pan" value="' + response.maskedPan + '" />');
                        form.submit();
                    });
                });
            }
        );
    }
});