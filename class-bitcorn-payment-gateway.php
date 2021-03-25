<?php 
class WC_Bitcorn_Payment_Gateway extends WC_Payment_Gateway{

    private $order_status;
	
	public function __construct(){
		$this->id = 'bitcorn';
		$this->method_title = 'Bitcorn payment';
		$this->has_fields = true;
		$this->init_form_fields();
		$this->init_settings();
		$this->enabled = $this->get_option('enabled');
		$this->title = $this->get_option('title'); 
		$this->description = $this->get_option('description');
		$this->order_status = $this->get_option('order_status');
		$this->test_mode = $this->get_option("test-mode");
		$this->client_id = $this->get_option("bitcorn_client_id");
		$this->client_secret = $this->get_option("bitcorn_client_secret");
		$this->validation_key = $this->get_option("bitcorn_validation_key");

		if($this->test_mode=='yes') {
			$this->api_audience = "BITCORNService-Test";
			$this->auth_domain = "bitcorn-test.auth0.com";
		} else {
			$this->api_audience = "https://bitcorndata.ngrok.io";
			$this->auth_domain = "bitcorn.auth0.com";
		}
		add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
		
	
	}


	public function init_form_fields(){
				$this->form_fields = array(
					'enabled' => array(
					'title' 		=>  'Enable/Disable',
					'type' 			=> 'checkbox',
					'label' 		=>  'Enable Bitcorn payments',
					'default' 		=> 'yes'
					),

		            'title' => array(
						'title' 		=> 'Method Title',
						'type' 			=> 'text',
						'description' 	=> 'This controls the title', 'bitcorn-payment-gateway',
						'default'		=> 'Bitcorn Payment', 'bitcorn-payment-gateway',
						'desc_tip'		=> true,
					),
					'description' => array(
						'title' =>  'Description',
						'type' => 'textarea',
						'css' => 'width:500px;',
						'default' => '<description>',
						'description' 	=> 'The message which you want it to appear to the customer in the checkout page.',
					),
					'order_status' => array(
						'title' => 'Order Status After The Checkout',
						'type' => 'select',
						'options' => wc_get_order_statuses(),
						'default' => 'wc-pending-payment',
						'description' 	=>  'The Order status if this gateway used in payment.',
					),
					'capture_status' => array(
						'title' => 'Order Status After Order Authorized',
						'type' => 'select',
						'options' => wc_get_order_statuses(),
						'default' => 'wc-processing',
						'description' 	=>  'The Order status after order has been Authorized',
					),
					'bitcorn_client_id' => array(
						'title' 		=> "BITCORN Client id",
						'type' 			=> 'text',
						'default'		=> "<bitcorn-client-id>",
					),
					'bitcorn_client_secret' => array(
						'title' 		=> "BITCORN Client Secret",
						'type' 			=> 'password',
						'default'		=> "<bitcorn-client-secret>",
					),
					
					'bitcorn_validation_key' => array(
						'title' 		=> "BITCORN Validation Key",
						'type' 			=> 'password',
						'default'		=> "<bitcorn-validation-key>",
					),
					'test-mode' => array(
						'title' 		=> "Test mode",
						'type' 			=> 'checkbox',
						'label' 		=> "Test mode",
						'default' 		=> 'yes'
						),
			 );
	}

	public function admin_options() {
		?>
		<h3><?php _e( 'Bitcorn payment settings', 'bitcorn-payment-gateway' ); ?></h3>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder columns-2">
					<div id="post-body-content">
						<table class="form-table">
							<?php 
								$this->generate_settings_html();
								
							?>
						</table><!--/.form-table-->
					</div>
				</div>
				
				<?php
	
	
	


	}

	
	function transaction_validation_flow($transaction) {
    	$corn_client = $this->create_corn_client();
		$tx = $corn_client->validate_transaction($transaction);
		
    	if($tx!=false) {
			if(isset($tx->error)) {
				return $tx;
			}

        	$items = $tx->items;
        	$tx_info = json_decode($tx->txInfo);

        	$corn_order_id = $tx_info->orderId;
        	$total_amount = $tx_info->totalAmount;
        	$tx_id = $tx_info->txId;
			
        	$response = $corn_client->close_order($corn_order_id,$tx_id);
			if(isset($response->success) && $response->success) {
				$order_id = $tx_info->clientOrderId;
				$order = wc_get_order( (int)$order_id );
				if($order) {
					$note = sprintf( __( 'Payment was captured - Corn Order ID: %1$s, Transaction ID: %2$s', 'woocommerce' ),
					$corn_order_id, $tx_id ) ;
					
					$state = $this->get_option("capture_status");
					$order->update_status($state, $note);
					wc_reduce_stock_levels( $order_id );
				}
				return true;
			} else {
				return $response;
			}
		} else {
			$object = new stdClass();
            $object->status = "failed to verify transaction:".$transaction;//$response->getStatusCode();
			return $object;
		}
		//return false;
	}

	public function create_corn_client () {
		require_once "bitcorn-api/class.bitcorn.php";
		
		$client = new Bitcorn($this->auth_domain,
			$this->client_id,
			$this->client_secret,
			$this->api_audience,
			$this->validation_key);
		
		return $client;
	}

	public function validate_fields() {
		
		return true;
	}

	public function process_payment( $order_id ) {
		
		global $woocommerce;
		$order = wc_get_order( $order_id );
		$order->update_status($this->order_status,  'Awaiting payment' );
		
		return array(
			'result' => 'success',
			'redirect' => $this->get_return_url_bitcorn( $order, $order_id )
		);	
		
	}

	public function get_return_url_bitcorn($order,$order_id) {
		$endpoint = "https://checkout.bitcornfarms.com/";
		if($this->test_mode=='yes') {
			$endpoint = "http://localhost:8080/";
		}
		
		
		$items=array();

		foreach ( $order->get_items() as $item ) {
			
			$item_name = $item->get_name();
			
			$item_entry = [
				"quantity" => $item->get_quantity(),
				"name" => $item_name,
				"itemId" => strval($item->get_product_id()),
				"amountUsd" => ((float)$item->get_total()),
				"quantity" => $item->get_quantity()
			];
			
			$items[] = $item_entry;
			
		}

		$request_body = [
			"clientId" => $this->client_id,
			"clientOrderId" => $order_id,
			"items" => $items
		];

		$corn_client = $this->create_corn_client();
		$response = $corn_client->create_order($request_body);
		$corn_order_id = $response->orderId;
		$auth_url = $endpoint . "?authorizeOrder=" . $corn_order_id . "&clientId=" . ($this ->client_id);
		
		return apply_filters( 'woocommerce_get_return_url', $auth_url, $order );
	}
	
	public function payment_fields(){
	    ?>
		<fieldset>
			<img src='/wp-content/plugins/bitcorn-payment-gateway/assets/corn-logo.png'>
			<p class="form-row form-row-wide">
                <label for="<?php echo $this->id; ?>-admin-note"><?php echo ($this->description); ?> <span class="required"></span></label>
                
			</p>						
			<div class="clear"></div>
		</fieldset>
		<?php
	}
}