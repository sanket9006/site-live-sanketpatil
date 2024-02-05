<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Plugin Name: Credit Card Payments for Woocommerce with CyberSource
 * Description: Credit card payments for Woocommerce with CyberSource as the gateway. Tokenizes in the browser to reduce PCI scope and allow cards on file.
 * Version: 1.0.2
 *
 * WC requires at least: 3.5.1
 * WC tested up to: 3.5.5
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 *  If WooCommerce is active add load up the gateway
 **/
 if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    add_action( 'plugins_loaded', 'cybersource_credit_cards', 0 );
}


/**
 * Add the CyberSource Credit Card gateway class to the Woocommerce Gateway list
 *
 */
function cybersource_credit_cards() {
	
	include_once('class-woocommerce-cybersource-cc.php');
	
	function add_cybersource_cc_class( $methods ) {
		$methods[] = 'WC_Gateway_CyberSource_CC';
		return $methods;
	}

    add_filter( 'woocommerce_payment_gateways', 'add_cybersource_cc_class' );


}

add_action( 'wp', 'delete_payment_method_action', 0 );
/**
 *  This function will delete tokens at CyberSource when the token is deleted
 *  using the manage accounts functionality with Woocommerce.
 *  TODO: See if there is a more direct hook then the generic wp hook.
 */
function delete_payment_method_action() {
    global $wp;

    if ( isset( $wp->query_vars['delete-payment-method'] ) ) {
        
        $token_id = absint( $wp->query_vars['delete-payment-method'] );
        $token = WC_Payment_Tokens::get( $token_id );
                
        //error_log(print_r($token, true) . "\n\n");

        $delete = true;

        if ( is_null( $token ) ) {
            $delete = false;
        }

        if ( get_current_user_id() !== $token->get_user_id() ) {
            $delete = false;
        }

        if ( false === wp_verify_nonce( $_REQUEST['_wpnonce'], 'delete-payment-method-' . $token_id ) ) {
            $delete = false;
        }

        if ( $delete ) {
            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $result = $available_gateways[ 'cybersource_cc' ]->delete_payment_method($token->get_token());
            
            if ( $result['result'] == 'success' ) {
                wc_add_notice( __( 'Payment method deleted at CyberSource.', 'woocommerce' ) );
            }
        }

    }
}