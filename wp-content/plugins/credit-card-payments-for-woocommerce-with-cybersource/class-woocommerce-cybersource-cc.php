<?php

include 'class-cybs-soapi-cc.php';
include 'class-cybs-rest-client.php';

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Credit Card Payments for Woocommerce with CyberSource implementation class.
 *
 * @class 		WC_Gateway_CyberSource_CC
 * @extends		WC_Payment_Gateway
 * @version		1.0.2
 */
class WC_Gateway_CyberSource_CC extends WC_Payment_Gateway_CC {

    private $soap_client;
    private $rest_client;
    

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = 'cybersource_cc';
		$this->method_title       = __( 'Credit Cards - CyberSource', 'woocommerce' );
		$this->method_description = __( 'Take credit card payments and use tokens for cards on file using the CyberSource platform. Requires SSL when in live mode. Request a test account here: <a href="https://www.cybersource.com/about/contact_us/" target="_blank">CyberSource</a>', 'woocommerce' );
		$this->has_fields         = true;
        $this->supports           = array(
            'products',
            'tokenization',
        );

        // Initialize the logger
        $this->logger = wc_get_logger();
        $this->logger_context = array( 'source' => $this->id );

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values
        $this->enabled         = $this->get_option( 'enabled' );
        $this->capture         = $this->get_option( 'capture' );
        $this->sandbox         = $this->get_option( 'sandbox' );
        $this->card_on_file    = $this->get_option( 'card_on_file' );
        $this->microform       = $this->get_option( 'microform' );
        $this->title           = $this->get_option( 'title' );
		$this->description     = $this->get_option( 'description' );
		$this->merchantID      = $this->sandbox == 'no' ? $this->get_option( 'live_merchant_id' ) : $this->get_option( 'sandbox_merchant_id' );
        $this->soap_key        = $this->sandbox == 'no' ? $this->get_option( 'live_soap_key' ) : $this->get_option( 'sandbox_soap_key' );
        $this->shared_secret_key = $this->sandbox == 'no' ? $this->get_option( 'live_shared_secret_key' ) : $this->get_option( 'sandbox_shared_secret_key' );
        $this->shared_secret_key_serial_number = $this->sandbox == 'no' ? $this->get_option( 'live_shared_secret_key_serial_number' ) : $this->get_option( 'sandbox_shared_secret_key_serial_number' );
        $this->wsdl_version    = $this->get_option( 'wsdl_version' );
        $this->log_enabled     = $this->get_option( 'log_enabled' );

		// General Hooks
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
        // Microform Hooks only on the checkout page
        if (is_checkout() && ! is_order_received_page() && 'yes' == $this->microform ) {
            add_filter( 'woocommerce_credit_card_form_fields' , array( $this, 'microform_credit_card_fields' ) , 10, 2 );
            add_action( 'wp_enqueue_scripts',  array($this, 'microform_scripts'));
            add_action( 'send_headers',  array($this, 'add_microform_headers'), 1); 
        }
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

        $label = __( 'Enable Logging', 'woocommerce' );
        $description = __( 'Enable the logging of errors.', 'woocommerce' );
        
        if ( defined( 'WC_LOG_DIR' ) ) {
            $log_url = add_query_arg( 'tab', 'logs', add_query_arg( 'page', 'wc-status', admin_url( 'admin.php' ) ) );
            $log_key = 'cybersource_cc-' . sanitize_file_name( wp_hash( 'cybersource_cc' ) ) . '-log';
            $log_url = add_query_arg( 'log_file', $log_key, $log_url );
        
            $label .= ' | ' . sprintf( __( '%1$sView Log%2$s', 'woocommerce' ), '<a href="' . esc_url( $log_url ) . '">', '</a>' );
        }

		$this->form_fields = array(
			'enabled' => array(
				'title'       => __( 'Enable/Disable', 'woocommerce' ),
				'label'       => __( 'Enable Credit Cards payment processing and tokenization using CyberSource', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
                'default'     => 'no'
            ),
            'capture' => array(
                'title'       => __( 'Capture/Settle', 'woocommerce' ),
                'label'       => __( 'Enable Immediate Capture/Settlement', 'woocommerce' ),
                'type'        => 'checkbox',
                'description' => __( 'To enable immediate capture/settlement of funds in addition to authorizing the credit card', 'woocommerce' ),
                'default'     => 'no'
            ),
            'sandbox' => array(
				'title'       => __( 'Sandbox', 'woocommerce' ),
				'label'       => __( 'Enable Sandbox Mode', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Send transations to the CyberSource sandbox using sandbox API keys (real payments will not be taken).', 'woocommerce' ),
				'default'     => 'yes'
            ),
            'card_on_file' => array(
				'title'       => __( 'Card On File', 'woocommerce' ),
				'label'       => __( 'Enable Card On File.', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'This allows customers to save cards to thier account. It stores the card info at CyberSource and a token representing the card is stored in your database.',
                'default'     => 'no'
            ),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout within the credit card payment type.', 'woocommerce' ),
				'default'     => __( 'Credit card', 'woocommerce' )
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout within the credit card payment type.', 'woocommerce' ),
				'default'     => 'Pay with credit card.'
			),
			'sandbox_merchant_id' => array(
				'title'       => __( 'Sandbox - Merchant ID', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the sandbox merchant ID specified when creating your account', 'woocommerce' ),
				'default'     => ''
			),
			'sandbox_soap_key' => array(
				'title'       => __( 'Sandbox - SOAP Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This key is required for basic credit card processing in the sandbox environment. Get your SOAP API key from the <a href="https://ebc2test.cybersource.com" target="_blank">test CyberSource Enterprise Business Center</a>.', 'woocommerce' ),
				'default'     => ''
            ),
			'live_merchant_id' => array(
				'title'       => __( 'Live - Merchant ID', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This is the merchant ID provided to you by CyberSource when going live.', 'woocommerce' ),
				'default'     => ''
			),
			'live_soap_key' => array(
				'title'       => __( 'Live - SOAP Key', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This key is required for basic credit card processing in the production environment. Get your SOAP API key from the <a href="https://ebc2.cybersource.com" target="_blank">production CyberSource Enterprise Business Center</a>.', 'woocommerce' ),
				'default'     => ''
            ),
            'wsdl_version' => array(
                'title'       => __( 'WSDL Version', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'The version number of the CyberSource WSDL. Used by the SOAP API.', 'woocommerce' ),
                'default'     => '149'
            ),
            'log_enabled' => array(
                'title'       => __( 'Debug Log', 'woocommerce' ),
                'label'       => $label,
                'description' => $description,
                'type'        => 'checkbox',
                'default'     => 'no'
            ),
            'microform_section' => array(
                'title'       => __( 'Microform Settings - Everything below applys only if using Microform', 'woocommerce' ),
                'type'        => 'title'
            ),
            'microform' => array(
				'title'       => __( 'Secure Acceptance Microform', 'woocommerce' ),
				'label'       => __( 'Enable Secure Acceptance Microform.', 'woocommerce' ),
				'type'        => 'checkbox',
				'description' => 'The Microform reduces PCI scope by capturing the customer credit card and returning a token submitted to your server in the place of an account number. This relies on use of the default credit card form supplied with Woocommerce. A custom form will probably not work without customization.',
                'default'     => 'no'
            ),
            'sandbox_shared_secret_key' => array(
                'title'       => __( 'Sandbox - Shared Secret Key', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This key is required when Secure Acceptance Microform is enabled. Get your Shared Secret Key from the <a href="https://ebc2test.cybersource.com" target="_blank">test CyberSource Enterprise Business Center</a>.', 'woocommerce' ),
                'default'     => ''
            ),
            'sandbox_shared_secret_key_serial_number' => array(
                'title'       => __( 'Sandbox - Shared Secret Key Serial Number', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'The serial number corresponding to the shared secret key above required when Secure Acceptance Microform is enabled. Get your Shared Secret Key Serial Number from the <a href="https://ebc2test.cybersource.com" target="_blank">test CyberSource Enterprise Business Center</a>.', 'woocommerce' ),
                'default'     => ''
            ),
            'live_shared_secret_key' => array(
                'title'       => __( 'Live - Shared Secret Key', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This key is required when Secure Acceptance Microform is enabled. Get your Shared Secret Key from the <a href="https://ebc2.cybersource.com" target="_blank">production CyberSource Enterprise Business Center</a>', 'woocommerce' ),
                'default'     => ''
            ),
            'live_shared_secret_key_serial_number' => array(
                'title'       => __( 'Live - Shared Secret Key Serial Number', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'The serial number corresponding to the shared secret key above required when Secure Acceptance Microform is enabled. Get your Shared Secret Key Serial Number from the <a href="https://ebc2.cybersource.com" target="_blank">production CyberSource Enterprise Business Center</a>.', 'woocommerce' ),
                'default'     => ''
            )
		);
    }
    
    /**
     * Check if the gateway is enabled and correctly setup.
     *
     * @return bool
     */
    public function is_available() {

        // Gateway itself must be enabled
        if ( 'no' == $this->enabled ) {
            return false;
        }

        // If not in sandbox mode HTTPS must be used
        if ( 'no' == $this->sandbox && ! wc_checkout_is_https() ) {
            if ( $this->log_enabled == 'yes' ) {
                $this->logger->alert("Live mode enabled, but site is not HTTPS", $this->logger_context);
            }
            return false;
        }

        // Merchant ID, key, and WSDL version must be set
        if ( ! $this->merchantID || ! $this->soap_key || ! $this->wsdl_version) {
            if ( $this->log_enabled == 'yes' ) {
                $this->logger->alert("Merchant ID, key, or WSDL version not set in payment gateway configuration", $this->logger_context);
            }
            return false;
        }

        return true;
    }

	/**
	 * Payment form on checkout page.
	 */
	public function payment_fields() {
		$description = $this->get_description();

		if ( 'yes' == $this->sandbox ) {
			$description .= ' ' . 'Test Card: 4111111111111111' ;
		}

		if ( $description ) {
			echo wpautop( wptexturize( trim( $description ) ) );
		}

        parent::payment_fields();
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id
	 */
	public function process_payment( $order_id ) {
        $save_card_on_file = false;
        $tokenReasonCode = "";
        
        $order = new WC_Order( $order_id );

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Posted Data from checkout page:", $this->logger_context);
            $this->logger->debug(wc_print_r($_POST, true), $this->logger_context);
        }

        // Create a Cybs Client and set all the fields in the request
        $this->soap_client = new CybsSoapiCC($this->merchantID, $this->soap_key, $this->wsdl_version, $this->sandbox);
        $request = $this->soap_client->createRequest($order_id);
        $this->soap_client->setAuthInRequest($request);

        // Request capture of funds if immediate capture/settlement enabled.
        if ( 'yes' == $this->capture) {
            $this->soap_client->setCaptureInRequest($request);
        }

        $this->setBillToInRequest($request, $order);
        $this->setShipToInRequest($request, $order);
        $this->soap_client->setPurchaseTotals($request, $order->get_currency(), $order->get_total());

        // Saving this payment method as a token?
        if ( is_user_logged_in() && isset( $_POST['wc-cybersource_cc-new-payment-method'] ) && true === (bool) $_POST['wc-cybersource_cc-new-payment-method'] ) {
            
            $save_card_on_file = true;
            
            if ( 'yes' == $this->microform ) {
                $subscriptionID = $_POST['microform_token'];
            } else {
                $this->soap_client->setSubscriptionCreateInRequest($request);
            }
        }

        // Using a card on file to process the payment?
        if ( is_user_logged_in() && isset( $_POST['wc-cybersource_cc-payment-token'] ) && 'new' !== $_POST['wc-cybersource_cc-payment-token'] ) {
            $token_id = $_POST['wc-cybersource_cc-payment-token'];
            $token = WC_Payment_Tokens::get( $token_id );

            //Verify token belongs to the logged in user
            if ( $token->get_user_id() !== get_current_user_id() ) {
                wc_add_notice( __('Payment error:', 'woothemes') . 'Payment Failed. User not associated to token ', 'error' );
                return;
            }

            $this->soap_client->setTokenInRequest($request, $token->get_token());
        // Using the Microform?
        } else if ( 'yes' == $this->microform ) {
            $this->soap_client->setTokenInRequest($request, $_POST['microform_token']);
        // Not using Microform or a card on file?
        } else {
            $this->setCardInRequest($request);
        }

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Request To CyberSource for Payment:", $this->logger_context);
            $this->logger->debug(wc_print_r($request, true), $this->logger_context);
        }

        // Use the Cybs soap_client to execute the transaction
        $reply = $this->soap_client->runTransaction($request);

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Response from CyberSource for Payment:", $this->logger_context);
            $this->logger->debug(wc_print_r($reply, true), $this->logger_context);
        }

        $decision = $reply->decision;
        $reasonCode = $reply->reasonCode;

        if(isset ($reply->ccAuthReply)) {
            $authReasonCode = $reply->ccAuthReply->reasonCode;
        }

        if(isset ($reply->paySubscriptionCreateReply)) {
            $tokenReasonCode = $reply->paySubscriptionCreateReply->reasonCode;
            $subscriptionID = $reply->paySubscriptionCreateReply->subscriptionID;
        }

        if(strcmp($decision,'ACCEPT') == 0 && strcmp($reasonCode, '100') == 0) {
            // Remove cart
            WC()->cart->empty_cart();

            // Complete the order
            $order->payment_complete();

            // If we are saving this card to file and either a token was returned in the reply or we are using microform
            if($save_card_on_file && (strcmp($tokenReasonCode,'100') == 0 || 'yes' == $this->microform)) {
                $this->saveToken($subscriptionID);
            }

            // Return thank you redirect
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url( $order )
            );

        } else {
            wc_add_notice( __('Payment error:', 'woothemes') . 'Payment Failed. Reason Code: ' . $reply->reasonCode, 'error' );
            return;
        }
	}

	/**
	 * Get gateway icon.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa.png' ) . '" alt="Visa" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard.png' ) . '" alt="MasterCard" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover.png' ) . '" alt="Discover" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex.png' ) . '" alt="Amex" />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb.png' ) . '" alt="JCB" />';

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	public function field_name( $name ) {
		return ' name="' . esc_attr( $this->id . '-' . $name ) . '" ';
	}

    /**
     * Add a Credit Card as a Payment Method. This tokenizes and stores
     * the token for use at checkout.
     *
     * @access public
     */
    public function add_payment_method() {

        if(! is_user_logged_in()) {
            wc_add_notice( __( 'User not logged in, cannot add card.', 'woocommerce' ), 'error' );
            return;
        }

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Posted Data from account management page:", $this->logger_context);
            $this->logger->debug(wc_print_r($_POST, true), $this->logger_context);
        }

        // Create a Cybs Client and set all the fields in the request
        $this->soap_client = new CybsSoapiCC($this->merchantID, $this->soap_key, $this->wsdl_version, $this->sandbox);
        $request = $this->soap_client->createRequest('12345');
        $this->setCardInRequest($request);

        $this->soap_client->setSubscriptionCreateInRequest($request);

        $purchaseTotals = new stdClass();
        $purchaseTotals->currency = 'USD';
        $request->purchaseTotals = $purchaseTotals;

        global $woocommerce;
        $this->setBillToInRequest($request, $woocommerce->customer);

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Request to CyberSource to tokenize:", $this->logger_context);
            $this->logger->debug(wc_print_r($request, true), $this->logger_context);
        }

        // Use the Cybs soap_client to execute the transaction
        $reply = $this->soap_client->runTransaction($request);

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("****** Response from CyberSource to tokenize:", $this->logger_context);
            $this->logger->debug(wc_print_r($reply, true), $this->logger_context);
        }

        $decision = $reply->decision;
        $reasonCode = $reply->reasonCode;

        if(isset ($reply->paySubscriptionCreateReply)) {
            $tokenReasonCode = $reply->paySubscriptionCreateReply->reasonCode;
            $subscriptionID = $reply->paySubscriptionCreateReply->subscriptionID;
        }

        if(strcmp($decision,'ACCEPT') == 0 && strcmp($reasonCode, '100') == 0) {

            // If tokenization successful save the token
            if(strcmp($tokenReasonCode,'100') == 0) {
                $this->saveToken($subscriptionID);
            }

            // Return to payment methods page
            return array(
                'result'   => 'success',
                'redirect' => wc_get_endpoint_url( 'payment-methods' ),
            );

        } else {
            wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce' ), 'error' );
            return;
        }
    }

    /**
     * Saves a CyberSource token (subscriptionID) within a WooCommerce token.
     *
     * @access public
     * @param string $subscriptionID
     */
    public function saveToken($subscriptionID) {

        if ('yes' == $this->microform ) {
            $accountNumber = str_replace( array(' ', '-' ), '', $_POST['microform_masked_pan'] ); 
        } else {
            $accountNumber = str_replace( array(' ', '-' ), '', $_POST['cybersource_cc-card-number'] );
        }

        $first_six = substr($accountNumber,0,5);
        error_log("BIN : " . $first_six);

        $expirationDate = str_replace( array( '/', ' '), '', $_POST['cybersource_cc-card-expiry'] );
        $expirationMonth = substr($expirationDate,0,2);
        if(strlen($expirationDate) > 4) {
            $expirationYear = substr($expirationDate,2,4);
        } else {
            $expirationYear = substr($expirationDate,2,2);
        }

        $token = new WC_Payment_Token_CC();
        $token->set_token( $subscriptionID );
        $token->set_gateway_id( $this->id );
        $token->set_card_type( $this->soap_client->getCardType($first_six)[1] );
        $token->set_last4( substr($accountNumber,12,4) );
        $token->set_expiry_month( $expirationMonth );
        $token->set_expiry_year( '20' . $expirationYear );
        $token->set_user_id( get_current_user_id() );

        if(! $token->save()) {
            if ( $this->log_enabled == 'yes' ) {
                $this->logger->error("Failed to save token locally.", $this->logger_context);
            }
            wc_add_notice( __( 'There was a problem saving your card to file.', 'woocommerce' ), 'error' );
        }

    }

    /**
     * Deletes a CyberSource token (subscriptionID) as well as the related WooCommerce token.
     *
     * @access public
     * @param string $subscriptionID
     */
    public function delete_payment_method($subscriptionID) {

        // Create a Cybs Client and set all the fields in the request
        $this->soap_client = new CybsSoapiCC($this->merchantID, $this->soap_key, $this->wsdl_version, $this->sandbox);
        $request = $this->soap_client->createRequest('12345');
        $this->soap_client->setSubscriptionDeleteInRequest($subscriptionID, $request);

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("Request to CyberSource to delete token.", $this->logger_context);
            $this->logger->debug(wc_print_r($request, true), $this->logger_context);
        }

        // Use the Cybs soap_client to execute the transaction
        $reply = $this->soap_client->runTransaction($request);

        if ( $this->log_enabled == 'yes' ) {
            $this->logger->debug("Response from CyberSource to delete token.", $this->logger_context);
            $this->logger->debug(wc_print_r($reply, true), $this->logger_context);
        }

        $decision = $reply->decision;
        $reasonCode = $reply->reasonCode;

        if(isset ($reply->paySubscriptionDeleteReply)) {
            $tokenReasonCode = $reply->paySubscriptionDeleteReply->reasonCode;
        }

    }

    public function setBillToInRequest($request, $order) {
        $billTo = new stdClass();
        $billTo->firstName = $order->get_billing_first_name();
        $billTo->lastName = $order->get_billing_last_name();
        $billTo->company = $order->get_billing_company();
        $billTo->street1 = $order->get_billing_address_1();
        $billTo->street2 = $order->get_billing_address_2();
        $billTo->city = $order->get_billing_city();
        $billTo->state = $order->get_billing_state();
        $billTo->postalCode = $order->get_billing_postcode();
        $billTo->country = $order->get_billing_country();
        $billTo->email = $order->get_billing_email();
        $billTo->phoneNumber = $order->get_billing_phone();
        $billTo->customerID = get_current_user_id();
        $request->billTo = $billTo;
    }

    public function setShipToInRequest($request, $order) {
        $shipTo = new stdClass();
        $shipTo->firstName = $order->get_shipping_first_name();
        $shipTo->lastName = $order->get_shipping_last_name();
        $shipTo->company = $order->get_shipping_company();
        $shipTo->street1 = $order->get_shipping_address_1();
        $shipTo->street2 = $order->get_shipping_address_2();
        $shipTo->city = $order->get_shipping_city();
        $shipTo->state = $order->get_shipping_state();
        $shipTo->postalCode = $order->get_shipping_postcode();
        $shipTo->country = $order->get_shipping_country();
        $request->shipTo = $shipTo;
    }

    public function setCardInRequest($request) {
        $card = new stdClass();
        $accountNumber = str_replace( array(' ', '-' ), '', $_POST['cybersource_cc-card-number'] );
        $card->accountNumber = $accountNumber;
        $card->cardType = $this->soap_client->getCardType($accountNumber)[0];
        $card->cvNumber = ( isset( $_POST['cybersource_cc-card-cvc'] ) ) ? $_POST['cybersource_cc-card-cvc'] : '';
        $expirationDate = str_replace( array( '/', ' '), '', $_POST['cybersource_cc-card-expiry'] );
        $card->expirationMonth = substr($expirationDate,0,2);
        if(strlen($expirationDate) > 4) {
            $card->expirationYear = substr($expirationDate,2,4);
        } else {
            $card->expirationYear = substr($expirationDate,2,2);
        }
        $request->card = $card;
    }

    /**
     *  Retrieve the public key for use by the Microform JavaScript.
     *  This key can be reused for a limited amount of time.
     */
    public function get_microform_jwk() {

        $transient = get_transient( 'microform_key_data' );
        
        // Check to see if we already have the key cached
        if( ! empty( $transient ) ) {
            
            if ( $this->log_enabled == 'yes' ) {
                $this->logger->debug("Cached Public Key Used.", $this->logger_context);
            }

            return $transient;
        
        // If we don't have it cached make a call to get it
        } else {
        
            // Get the scheme and host parts of the URL
            $url_components = wp_parse_url(get_home_url());
            $base_url = $url_components['scheme'] . "://" .$url_components['host'];
            $this->rest_client = new Cybs_Rest_Client($this->merchantID, $this->sandbox, $this->shared_secret_key, $this->shared_secret_key_serial_number);
            $response = $this->rest_client->get_key($base_url);

            if ( $this->log_enabled == 'yes' ) {
                $this->logger->debug("Request to CyberSource for Public Key:", $this->logger_context);
                $this->logger->debug(wc_print_r($this->rest_client->last_request(), true), $this->logger_context);
                $this->logger->debug("Response from CyberSource for Public Key:", $this->logger_context);
                $this->logger->debug($response, $this->logger_context);
            }

            // Make sure the response has the public key
            if (strpos($response, 'jwk') !== false) {
                $json_response = json_decode($response, true);
                $jwk = $json_response["jwk"];

                // Save the public key so we don't have to request it again for 1 minute.
                set_transient( 'microform_key_data',  $jwk, 2 * MINUTE_IN_SECONDS );
                return  $jwk;
            } else {
                // If this fails when Javascript tries to load the Microform it will fail
                // This will cause an alert to be shown to the customer and the page reloaded
                if ( $this->log_enabled == 'yes' ) {
                    $this->logger->error("Error retrieving JWK for Microform. This will cause an alert notifying the customer the page security failed to load.", $this->logger_context);
                }

                return null;
            }
          
        }
        
    }

    /**
     *  Add Javascript for Microform functionality
     */
    public function microform_scripts() {

        // Get the key used to initialize the microform javascript
        $jwk = $this->get_microform_jwk();
              
        // Enqueue the CyberSource Flex Token JavaScript remote SDK and local setup files
        $my_js_ver  = date("ymd-Gis", filemtime( plugin_dir_path( __FILE__ ) . 'cybersource-microform.js' ));
        wp_enqueue_script( 'flex-microform-cybersource-wc-1', 'https://flex.cybersource.com/cybersource/assets/microform/0.4.0/flex-microform.min.js', null , null, false);
        wp_enqueue_script( 'flex-microform-cybersource-wc-2',  plugins_url( 'cybersource-microform.js', __FILE__ ), null, $my_js_ver, true);
        wp_localize_script( 'flex-microform-cybersource-wc-2', 'flex_microform_params', array('jwk' => $jwk) );
                
    }

    /**
     *  Modify the default credit card field to work with Microform
     */
    public function microform_credit_card_fields ($cc_fields, $payment_id) {

        // Change the card number field from an input to a div
        $cc_fields['card-number-field'] = str_replace('input id=','div id=',$cc_fields['card-number-field']);
        // Remove the wc-credit-card-form-card-number class
        $cc_fields['card-number-field'] = str_replace(' wc-credit-card-form-card-number','',$cc_fields['card-number-field']);
        // TODO: Add the styles from configuration.
        $cc_fields['card-number-field'] = str_replace('name="cybersource_cc-card-number"','name="cybersource_cc-card-number" style="height: 40px;"',$cc_fields['card-number-field']);

        return $cc_fields;
        
    }

    /**
     *  This function sets Content Security Policy headers so that Microform will work.
     */
    public function add_microform_headers () {

        // Detects production vs sandbox for correct URL.
        if ( 'yes' == $this->sandbox ) {
            header("Content-Security-Policy: frame-src 'self' https://testflex.cybersource.com; child-src 'self' https://testflex.cybersource.com;");
        } else  {
            header("Content-Security-Policy: frame-src 'self' https://flex.cybersource.com; child-src 'self' https://flex.cybersource.com;");
        }

    }

 
}
