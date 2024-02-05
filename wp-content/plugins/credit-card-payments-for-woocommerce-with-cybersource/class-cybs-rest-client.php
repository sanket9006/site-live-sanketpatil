<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Functions to build a CyberSource REST API message then call Cybs endpoint.
 * 
 */

class Cybs_Rest_Client {

    //const VERSION = "1.0";
    //const CLIENT_LIBRARY_VERSION = "Success Payments PHP REST " . VERSION;

    private $merchant_id;
    private $key_id;
    private $shared_secret;
    private $host;
    private $target_url;
    private $request_body;
    private $request_method;
    private $gmt_date;
    private $request_body_hash;
    private $signature;
    private $url;
    private $headers;

    function __construct($merchant_id, $sandbox, $shared_secret_key, $shared_secret_key_serial_number) {
        $this->merchant_id = $merchant_id;
        $this->key_id = $shared_secret_key_serial_number;
        $this->shared_secret = $shared_secret_key;

        if ( 'yes' == $sandbox ) {
            $this->host = "apitest.cybersource.com";
        } else {
            $this->host = "api.cybersource.com";
        }

        $this->gmt_date = gmdate("D, d M Y H:i:s") . " GMT";
    }

    /**
     * The function that actually sends the API requests to CyberSource. 
     * 
     */
    public function send_request() {
        $curl = curl_init();

        //Build REST Request Parameters
        $this->url = "https://{$this->host}{$this->target_url}";
        $this->headers = array(
            "Cache-Control: no-cache",
            "Content-Type: application/json",
            "Date: {$this->gmt_date}",
            "Digest: SHA-256={$this->request_body_hash}",
            "Host: $this->host",
            "Signature: $this->signature",
            "v-c-merchant-id: $this->merchant_id"
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->request_method,
            CURLOPT_POSTFIELDS => $this->request_body,
            CURLOPT_HTTPHEADER => $this->headers,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            $response = "cURL Error #:" . $err;
        }

        return $response;
	
    }

    /**
     * Function used to request a public key used by the Flex API's
     * 
     */
    public function get_key($site_url, $encryption_type = "RsaOaep") {

        // Settings required to send a message type for flex key retrieval
        $this->request_method = "POST";
        $this->target_url = "/flex/v1/keys";
        $this->request_body = "{\r\n\t\"encryptionType\": \"{$encryption_type}\",\r\n\t\"targetOrigin\": \"{$site_url}\"\r\n}";
        $this->request_body_hash = base64_encode(hash("sha256", $this->request_body, true));

        $this->compute_http_signature();

        return $this->send_request();

    }

    /**
     * Function to calculate the signature that goes in the HTTP header of all CyberSource
     * REST API calls.
     * 
     */
    public function compute_http_signature() {
        // base64 decode secret key 
        $binary_key = base64_decode($this->shared_secret);

        $sig_base = "";

        // Build the string to sign
        // POST and GET methods have a different signature algorithm
        if ($this->request_method == "POST") {
            $sig_base = "host: {$this->host}\ndate: {$this->gmt_date}\n(request-target): post {$this->target_url}\ndigest: SHA-256={$this->request_body_hash}\nv-c-merchant-id: {$this->merchant_id}";
        } else if ($this->request_method == "GET") {
            $sig_base = "host: {$this->host}\ndate: {$this->gmt_date}\n(request-target): get {$this->target_url}\nv-c-merchant-id: {$this->merchant_id}";
        }

        //error_log("Signature Base: " . $sig_base);
        
        // Hash signature input string with secret key
        $sig = base64_encode(hash_hmac("sha256", $sig_base, $binary_key, true));

        // Build the signature header string
        // POST and GET methods have a different signature algorithm
        if ($this->request_method == "POST") {
            $this->signature = "keyid=\"{$this->key_id}\", algorithm=\"HmacSHA256\", headers=\"host date (request-target) digest v-c-merchant-id\", signature=\"$sig\"";
        } else if ($this->request_method == "GET") {
            $this->signature = "keyid=\"{$this->key_id}\", algorithm=\"HmacSHA256\", headers=\"host date (request-target) v-c-merchant-id\", signature=\"$sig\"";
        }

    }

    /**
     * Return the url, method, body, and headers of the last API request made with this
     * class.
     * 
     */
    public function last_request() {
        $request = new stdClass();
        $request->url = $this->url;
        $request->request_method = $this->request_method;
        $request->request_body = $this->request_body;
        $request->headers = $this->headers;

        return $request;
    }

}