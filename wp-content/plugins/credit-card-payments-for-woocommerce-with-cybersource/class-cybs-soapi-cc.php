<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Functions to build the CyberSource SOAP Toolkit API message
 * then call Cybs endpoint.
 */

class CybsSoapiCC extends SoapClient
{

    const CLIENT_LIBRARY_VERSION = "Credit Card Payments for Woocommerce with CyberSource 1.0.2";

    private $merchantId;
    private $transactionKey;

    function __construct($merchantID, $private_key, $wsdl_version, $sandbox)
    {
        if ( 'no' == $sandbox ) {
            $wsdl = "https://ics2ws.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.$wsdl_version.wsdl";
        } else {
            $wsdl = "https://ics2wstest.ic3.com/commerce/1.x/transactionProcessor/CyberSourceTransaction_1.$wsdl_version.wsdl";
        }
        

        parent::__construct($wsdl);
        $this->merchantId = $merchantID;
        $this->transactionKey = $private_key;

        $nameSpace = "http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd";

        $soapUsername = new SoapVar(
            $this->merchantId,
            XSD_STRING,
            NULL,
            $nameSpace,
            NULL,
            $nameSpace
        );

        $soapPassword = new SoapVar(
            $this->transactionKey,
            XSD_STRING,
            NULL,
            $nameSpace,
            NULL,
            $nameSpace
        );

        $auth = new stdClass();
        $auth->Username = $soapUsername;
        $auth->Password = $soapPassword;

        $soapAuth = new SoapVar(
            $auth,
            SOAP_ENC_OBJECT,
            NULL, $nameSpace,
            'UsernameToken',
            $nameSpace
        );

        $token = new stdClass();
        $token->UsernameToken = $soapAuth;

        $soapToken = new SoapVar(
            $token,
            SOAP_ENC_OBJECT,
            NULL,
            $nameSpace,
            'UsernameToken',
            $nameSpace
        );

        $security =new SoapVar(
            $soapToken,
            SOAP_ENC_OBJECT,
            NULL,
            $nameSpace,
            'Security',
            $nameSpace
        );

        $header = new SoapHeader($nameSpace, 'Security', $security, true);
        $this->__setSoapHeaders(array($header));
    }

    /**
     * @return string The client's merchant ID.
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * @return string The client's transaction key.
     */
    public function getTransactionKey()
    {
        return $this->transactionKey;
    }

    /**
     * Returns an object initialized with basic client information.
     *
     * @param string $merchantReferenceCode Desired reference code for the request
     * @return stdClass An object initialized with the basic client info.
     */
    public function createRequest($merchantReferenceCode)
    {
        $request = new stdClass();
        $request->merchantID = $this->getMerchantId();
        $request->merchantReferenceCode = $merchantReferenceCode;
        $request->clientLibrary = self::CLIENT_LIBRARY_VERSION;
        $request->clientLibraryVersion = phpversion();
        $request->clientEnvironment = php_uname();
        return $request;
    }

   /**
     * Obtain a brand constant from a PAN 
     *
     * @param type $pan               Credit card number
     * @param type $include_sub_types Include detection of sub visa brands
     * @return string
    */
    public static function getCardType($pan)
    {
        //maximum length is not fixed now, there are growing number of CCs has more numbers in length, limiting can give false negatives atm

        //these regexps accept not whole cc numbers too
        //visa        
        $visa_regex = "/^4[0-9]{0,}$/";

        // MasterCard
        $mastercard_regex = "/^(5[1-5]|222[1-9]|22[3-9]|2[3-6]|27[01]|2720)[0-9]{0,}$/";
        $maestro_regex = "/^(5[06789]|6)[0-9]{0,}$/"; 

        // American Express
        $amex_regex = "/^3[47][0-9]{0,}$/";

        // Diners Club
        $diners_regex = "/^3(?:0[0-59]{1}|[689])[0-9]{0,}$/";

        //Discover
        $discover_regex = "/^(6011|65|64[4-9]|62212[6-9]|6221[3-9]|622[2-8]|6229[01]|62292[0-5])[0-9]{0,}$/";

        //JCB
        $jcb_regex = "/^(?:2131|1800|35)[0-9]{0,}$/";

        //ordering matter in detection, otherwise can give false results in rare cases
        if (preg_match($jcb_regex, $pan)) {
            return array("006", "JCB");
        }

        if (preg_match($amex_regex, $pan)) {
            return array("003", "American Express");
        }

        if (preg_match($diners_regex, $pan)) {
            return array("005", "Diners");
        }

        if (preg_match($visa_regex, $pan)) {
            return array("001", "Visa");
        }

        if (preg_match($mastercard_regex, $pan)) {
            return array("002", "Mastercard");
        }

        if (preg_match($discover_regex, $pan)) {
            return array("004", "Discover");
        }

        if (preg_match($maestro_regex, $pan)) {
            if ($pan[0] == '5') {//started 5 must be mastercard
                return array("002", "Mastercard");
            }
            //maestro is all 60-69 which is not something else, thats why this condition in the end
            return array("024", "Maestro");
        }

        return array("", "Undetermined");; //unable to calculate
    }

    public function setAuthInRequest($request) {
        $ccAuthService = new stdClass();
        $ccAuthService->run = 'true';
        $request->ccAuthService = $ccAuthService;
    }

    public function setCaptureInRequest($request) {
        $ccCaptureService = new stdClass();
        $ccCaptureService->run = 'true';
        $request->ccCaptureService = $ccCaptureService;
    }

    public function setSubscriptionCreateInRequest($request) {
        $recurringSubscriptionInfo = new stdClass();
        $recurringSubscriptionInfo->frequency = 'on-demand';
        $request->recurringSubscriptionInfo = $recurringSubscriptionInfo;

        $paySubscriptionCreateService = new stdClass();
        $paySubscriptionCreateService->run = 'true';
        $request->paySubscriptionCreateService = $paySubscriptionCreateService;
    }

    public function setPurchaseTotals($request, $currency, $order_total) {
        $purchaseTotals = new stdClass();
        $purchaseTotals->currency = $currency;
        $purchaseTotals->grandTotalAmount = $order_total;
        $request->purchaseTotals = $purchaseTotals;
    }

    public function setSubscriptionDeleteInRequest($subscriptionID, $request)
    {
        $paySubscriptionDeleteService = new stdClass();
        $paySubscriptionDeleteService->run = 'true';
        $request->paySubscriptionDeleteService = $paySubscriptionDeleteService;

        $recurringSubscriptionInfo = new stdClass();
        $recurringSubscriptionInfo->subscriptionID = $subscriptionID;
        $request->recurringSubscriptionInfo = $recurringSubscriptionInfo;
    }

    public function setTokenInRequest($request, $token)
    {
        $recurringSubscriptionInfo = new stdClass();
        $recurringSubscriptionInfo->subscriptionID = $token;
        $request->recurringSubscriptionInfo = $recurringSubscriptionInfo;
    }

    public function setInvoiceHeaderInRequest($request, $merchantDescriptor)
    {
        $invoiceHeader = new stdClass();
        $invoiceHeader->merchantDescriptor = $merchantDescriptor;
        $request->invoiceHeader = $invoiceHeader;
    }

    public function setApSaleInRequest($request, $cancelURL, $successURL, $failureURL)
    {
        $apSaleService = new stdClass();
        $apSaleService->run = 'true';
        $apSaleService->cancelURL = $cancelURL;
        $apSaleService->successURL = $successURL;
        $apSaleService->failureURL = $failureURL;
        //$apSaleService->paymentOptionID = 'ideal-TESTNL99';
        $request->apSaleService = $apSaleService;
    }

    public function setMDDProcessorFieldsInRequest($request, $processor)
    {
        $merchantDefinedData = new stdClass();
        $mddField1 = new stdClass();
        $mddField1->id = "1";
        $mddField1->_ = "selectprocessor";
        $mddField2 = new stdClass();
        $mddField2->id = "2";
        $mddField2->_ = $processor;
        $merchantDefinedData->mddField = array($mddField1,$mddField2);
        $request->merchantDefinedData = $merchantDefinedData;
    }

    public function mapApPaymentTypeToProcessor($apPaymentType) {
        $processor = '';
        if($apPaymentType === 'IDL')
            $processor = 'mollie';
        else if($apPaymentType === 'SOF')
            $processor = 'sofort';
        else if($apPaymentType === 'MCH')
            $processor = 'mollie';
        else if($apPaymentType === 'GPY')
            $processor = 'giropay';
        else if($apPaymentType === 'EPS')
            $processor = 'eps';
        else if($apPaymentType === 'MLB')
            $processor = 'hipay';
        else if($apPaymentType === 'ION')
            $processor = 'debitway';
        else
            $processor = '';

        return $processor;
    }
}