<?php
/* @wordpress-plugin
 * Plugin Name:       Bitcorn payment gateway
 * Plugin URI:        https://bitcornfarms.com
 * Description:       Bitcorn payments
 * Version:           1
 * WC requires at least: 3.0
 * WC tested up to: 3.8
 * Author:            Clayman
 * Author URI:        https://bitcornfarms.com
 * Text Domain:       bitcorn-payment-gateway
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */


$active_plugins = apply_filters('active_plugins', get_option('active_plugins'));
if(__is_woocommerce_active())
{
	add_filter('woocommerce_payment_gateways', 'add_bitcorn_payment_gateway');
	
	add_action('plugins_loaded', 'init_bitcorn_payment_gateway');
	

	add_action("wp_loaded","process_post_request");


}

/**
 * @return bool
 */

function __is_woocommerce_active()
{
	$active_plugins = (array) get_option('active_plugins', array());

	if (is_multisite()) {
		$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
	}

	return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

function add_bitcorn_payment_gateway( $gateways ){
	$gateways[] = 'WC_Bitcorn_Payment_Gateway';
	return $gateways; 
}


function init_bitcorn_payment_gateway(){
	require_once 'class-bitcorn-payment-gateway.php';
	
}

function process_post_request() {
	if ( ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) || ($_SERVER['QUERY_STRING'] !== "wc_ajax=complete_order"))
			
	{
		return;
	}
	try {
		$transaction = $_POST["jwt"];
		if(isset($transaction)) {
			$corn_handler = new  WC_Bitcorn_Payment_Gateway();
			$response = $corn_handler->transaction_validation_flow($transaction);
			die(json_encode($response));
		} else {
			die("bad request, missing jwt");
		}
	} catch(Exception $e) {
		die($e->getMessage());
	}
}
