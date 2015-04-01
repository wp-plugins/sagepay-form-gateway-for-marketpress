<?php
/**
 * Plugin Name: SagePay Form Gateway for MarketPress
 * Plugin URI: http://www.patsatech.com/
 * Description: MarketPress Plugin for accepting payment through SagePay Form Gateway.
 * Version: 1.0.0
 * Author: PatSaTECH
 * Author URI: http://www.patsatech.com
 * Contributors: patsatech
 * Requires at least: 3.5
 * Tested up to: 4.1
 *
 * Text Domain: patsatech-marketpress-sagepayform
 * Domain Path: /lang/
 *
 * @package SagePay Form Gateway for MarketPress
 * @author PatSaTECH
 */

add_action('init', 'sagepayform_gateway_init');

function sagepayform_gateway_init() {

	load_plugin_textdomain('patsatech-marketpress-sagepayform', false,  basename(dirname(__FILE__)) . '/languages' );

}

add_action('mp_load_gateway_plugins', 'register_sagepayform_gateway');

function register_sagepayform_gateway() {
	
	class MP_Gateway_SagePay_Form extends MP_Gateway_API {
	
		//private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
	  	var $plugin_name = 'sagepayform';
	  	
	  	//name of your gateway, for the admin side.
	  	var $admin_name = '';
	  	
	  	//public name of your gateway, for lists and such.
	  	var $public_name = '';
		
	  	//url for an image for your checkout method. Displayed on checkout form if set
	  	var $method_img_url = '';
	  	
	  	//url for an submit button image for your checkout method. Displayed on checkout form if set
	  	var $method_button_img_url = '';
		
	  	//whether or not ssl is needed for checkout page
	  	var $force_ssl = false;
	  	
	  	//always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
	  	var $ipn_url;
		
	  	//whether if this is the only enabled gateway it can skip the payment_form step
	  	var $skip_form = true;
		
	  	/**
	   	 * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
	   	 */
	  	function on_creation() {
	    	global $mp;
	    	$settings = get_option('mp_settings');
	    	
	    	//set names here to be able to translate
	    	$this->admin_name = __('SagePay Form', 'patsatech-marketpress-sagepayform');
	    	$this->public_name = __('Credit Card', 'patsatech-marketpress-sagepayform');
	       	
	    	if ( isset( $settings['gateways']['sagepayform'] ) ) {
	    		
		        $this->currency  	= $settings['gateways']['sagepayform']['currency'];
		        $this->vendor_name  = $settings['gateways']['sagepayform']['vendorname'];
		        $this->vendor_pass  = $settings['gateways']['sagepayform']['vendorpass'];
		        $this->mode         = $settings['gateways']['sagepayform']['mode'];
		        $this->transtype    = $settings['gateways']['sagepayform']['transtype'];
		        $this->vendoremail  = $settings['gateways']['sagepayform']['vendoremail'];
		        $this->sendemails   = $settings['gateways']['sagepayform']['sendemails'];
		        $this->emailmessage = $settings['gateways']['sagepayform']['emailmessage'];	
		  		
	    	}
			
	  	}
		
		/**
		 * Return fields you need to add to the top of the payment screen, like your credit card info fields
		 *
		 * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
		 * @param array $shipping_info. Contains shipping info and email in case you need it
		 */
	  	function payment_form($cart, $shipping_info) {
	    	if (isset($_GET['cancel'])){
	      		echo '<div class="mp_checkout_error">' . __('Your SagePay Form transaction has been canceled.', 'patsatech-marketpress-sagepayform') . '</div>';
			}
	  	}

	  	/**
	   	 * Use this to process any fields you added. Use the $_REQUEST global,
	     *  and be sure to save it to both the $_SESSION and usermeta if logged in.
	     *  DO NOT save credit card details to usermeta as it's not PCI compliant.
	     *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	     *  it will redirect to the next step.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	     * @param array $shipping_info. Contains shipping info and email in case you need it
	     */
	  	function process_payment_form($cart, $shipping_info) {
	    	global $mp;
	    
	    	$mp->generate_order_id();
			
	  	}
	  
	    /**
	     * Return the chosen payment details here for final confirmation. You probably don't need
	     *  to post anything in the form as it should be in your $_SESSION var already.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
  	     * @param array $shipping_info. Contains shipping info and email in case you need it
	     */
	  	function confirm_payment_form($cart, $shipping_info) {
	    	global $mp;
			
	  	}
	
	  	/**
	     * Use this to do the final payment. Create the order then process the payment. If
	     *  you know the payment is successful right away go ahead and change the order status
	     *  as well.
	     *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
	     *  it will redirect to the next step.
	     *
	     * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
	     * @param array $shipping_info. Contains shipping info and email in case you need it
		 */
	  	function process_payment($cart, $shipping_info) {
	    	global $mp, $current_user;;
	    	
		    $timestamp = time();
			
		    $settings = get_option('mp_settings');
			
		    $order_id = $mp->generate_order_id();
			
		    $totals = array();
		    $counter = 0;
	    	
	    	foreach ($cart as $product_id => $variations) {
	      		foreach ($variations as $variation => $data) {
		  			$totals[] = $mp->before_tax_price($data['price']) * $data['quantity'];
					
				    $sku = empty($data['SKU']) ? $product_id : $data['SKU'];
					
					//$mj_order->add_item( $data['name'], $mp->before_tax_price($data['price']), $data['quantity'], $sku );
					
					$counter++;
	      		}
	    	}
	    	
		    $total = array_sum($totals);
		    
		    if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
				//$mj_order->discount = $total - $coupon['new_total'];
				$total = $coupon['new_total'];
		    }
			
		    //shipping line
		    if ( ($shipping_price = $mp->shipping_price()) !== false ) {
			    
				$total = $total + $shipping_price;
				
				if($settings['tax']['tax_shipping']) {
				//	$mj_order->shipping 		= $shipping_price;
				}else {
				//	$mj_order->shipping 		= $shipping_price;
				}
				
		    }
	    	
		    //tax line
		    if ( ($tax_price = $mp->tax_price()) !== false ) {
			    $total = $total + $tax_price;
		    }
			
		    //setup transients for ipn in case checkout doesn't redirect (ipn should come within 12 hrs!)
			set_transient('mp_order_'. $order_id . '_cart', $cart, 60*60*12);
			set_transient('mp_order_'. $order_id . '_shipping', $shipping_info, 60*60*12);
			set_transient('mp_order_'. $order_id . '_userid', $current_user->ID, 60*60*12);
			set_transient('mp_order_'. $order_id . '_sagetotal', $total, 60*60*12);
	    	
			if( $this->mode == 'test' ){
				$gateway_url = 'https://test.sagepay.com/gateway/service/vspform-register.vsp';
			}else if( $this->mode == 'live' ){
				$gateway_url = 'https://live.sagepay.com/gateway/service/vspform-register.vsp';
			}
			
	        $time_stamp = date("ymdHis");
	        $orderid = $this->vendor_name . "-" . $time_stamp . "-" . $order_id;
	        
	        $names = explode(" ", $shipping_info['name']);
			
	        $sagepay_arg['ReferrerID'] 			= 'CC923B06-40D5-4713-85C1-700D690550BF';
	        $sagepay_arg['Amount'] 				= $total;
			$sagepay_arg['CustomerName']		= substr($names[0].' '.$names[1], 0, 100);
	        $sagepay_arg['CustomerEMail'] 		= substr($shipping_info['email'], 0, 255);
	        $sagepay_arg['BillingSurname'] 		= substr($names[1], 0, 20);
	        $sagepay_arg['BillingFirstnames'] 	= substr($names[0], 0, 20);
	        $sagepay_arg['BillingAddress1'] 	= substr($shipping_info['address1'], 0, 100);
	        $sagepay_arg['BillingAddress2'] 	= substr($shipping_info['address2'], 0, 100);
	        $sagepay_arg['BillingCity'] 		= substr($shipping_info['city'], 0, 40);
			if( $shipping_info['country'] == 'US' ){
	        	$sagepay_arg['BillingState'] 	= $shipping_info['state'];
			}else{
	        	$sagepay_arg['BillingState'] 	= '';
			}
	        $sagepay_arg['BillingPostCode'] 	= substr($shipping_info['zip'], 0, 10);
	        $sagepay_arg['BillingCountry'] 		= $shipping_info['country'];
	        $sagepay_arg['BillingPhone'] 		= substr($shipping_info['phone'], 0, 20);
	        $sagepay_arg['DeliverySurname'] 	= substr($names[1], 0, 20);
	        $sagepay_arg['DeliveryFirstnames'] 	= substr($names[0], 0, 20);
	        $sagepay_arg['DeliveryAddress1'] 	= substr($shipping_info['address1'], 0, 100);
	        $sagepay_arg['DeliveryAddress2'] 	= substr($shipping_info['address2'], 0, 100);
	        $sagepay_arg['DeliveryCity'] 		= substr($shipping_info['city'], 0, 40);
			if( $shipping_info['country'] == 'US' ){
	        	$sagepay_arg['DeliveryState'] 	= $shipping_info['state'];
			}else{
	        	$sagepay_arg['DeliveryState'] 	= '';
			}
	        $sagepay_arg['DeliveryPostCode'] 	= substr($shipping_info['zip'], 0, 10);
	        $sagepay_arg['DeliveryCountry'] 	= $shipping_info['country'];
	        $sagepay_arg['DeliveryPhone'] 		= substr($shipping_info['phone'], 0, 20);
	        $sagepay_arg['FailureURL'] 			= $this->ipn_url;
	        $sagepay_arg['SuccessURL'] 			= $this->ipn_url;
	        $sagepay_arg['Description'] 		= sprintf(__('Order #%s' , 'patsatech-marketpress-sagepayform'), $order_id );
	        $sagepay_arg['Currency'] 			= $this->currency;
	        $sagepay_arg['VendorTxCode'] 		= $orderid;
	        $sagepay_arg['VendorEMail'] 		= $this->vendoremail;
	        $sagepay_arg['SendEMail'] 			= $this->sendemails;
			if( $order->shipping_state == 'US' ){
	        	$sagepay_arg['eMailMessage']	= $this->emailmessage;
			}
	        $sagepay_arg['Apply3DSecure'] 		= '0';
			
	        $post_values = "";
	        foreach( $sagepay_arg as $key => $value ) {
	            $post_values .= "$key=" . trim( $value ) . "&";
	        }
	      	$post_values = substr($post_values, 0, -1);
			
			$params['VPSProtocol'] = 3.00;
			$params['TxType'] = $this->transtype;
			$params['Vendor'] = $this->vendor_name;
	      	$params['Crypt'] = $this->encryptAndEncode($post_values);
			
			$sagepay_arg_array = array();
			
			foreach ($params as $key => $value) {
				$sagepay_arg_array[] = $key.'<input type="text" name="'.esc_attr( $key ).'" value="'.esc_attr( $value ).'" /><br>';
			}
			/*
			echo '<form action="'.$gateway_url.'" method="post" name="sagepay_payment_form" >
					' . implode('', $sagepay_arg_array) . '
					</form>		
					<b>Please wait while you are being redirected.</b>			
					<script type="text/javascript" event="onload">
							document.sagepay_payment_form.submit();
					</script>';*/
	    	wp_redirect($gateway_url.'?'.http_build_query($params));
	    		
	    	exit(0);	
			
		}
		
		private function encryptAndEncode($strIn) {
			$strIn = $this->pkcs5_pad($strIn, 16);
			return "@".bin2hex(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $this->vendor_pass, $strIn, MCRYPT_MODE_CBC, $this->vendor_pass));
		}
		
		private function decodeAndDecrypt($strIn) {
			$strIn = substr($strIn, 1);
			$strIn = pack('H*', $strIn);
			return mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $this->vendor_pass, $strIn, MCRYPT_MODE_CBC, $this->vendor_pass);
		}
		
		
		private function pkcs5_pad($text, $blocksize)	{
			$pad = $blocksize - (strlen($text) % $blocksize);
			return $text . str_repeat(chr($pad), $pad);
		}

		public function decode($strIn) {
			$decodedString = $this->decodeAndDecrypt($strIn);
			parse_str($decodedString, $sagePayResponse);
			return $sagePayResponse;
		}
	  	
	  	/**
	     * Filters the order confirmation email message body. You may want to append something to
	     *  the message. Optional
	     *
	     * Don't forget to return!
	     */
	  	function order_confirmation_email($msg, $order) {
	    	return $msg;
	  	}
	  	
	  	/**
	     * Return any html you want to show on the confirmation screen after checkout. This
	     *  should be a payment details box and message.
	     *
	     * Don't forget to return!
	     */
	  	function order_confirmation_msg($content, $order) {
		    global $mp;
		    if ($order->post_status == 'order_received') {
		    	$content .= '<p>' . sprintf(__('Your payment via SagePay Form for this order totaling %s is not yet complete. Here is the latest status:', 'patsatech-marketpress-sagepayform'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
	      		$statuses = $order->mp_payment_info['status'];
		      	krsort($statuses); //sort with latest status at the top
		      	$status = reset($statuses);
		      	$timestamp = key($statuses);
		      	$content .= '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong> ' . htmlentities($status) . '</p>';
	    	} else {
	      		$content .= '<p>' . sprintf(__('Your payment via SagePay Form for this order totaling %s is complete. The transaction number is <strong>%s</strong>.', 'patsatech-marketpress-sagepayform'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
	    	}
	    	return $content;
	  	}
	  	
	  	/**
	     * Runs before page load incase you need to run any scripts before loading the success message page
	    */
		function order_confirmation($order) {
			global $mp;
		}
	    
	  	/**
	     * Echo a settings meta box with whatever settings you need for you gateway.
	     *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
	     *  You can access saved settings via $settings array.
	     */
	  	function gateway_settings_box($settings) {
	    	global $mp;
	    	
	    	$settings = get_option('mp_settings');
	    	
	    	?>
			<div id="mp_sagepayform" class="postbox">
		    	<h3 class='handle'><span><?php _e('SagePay Form Settings', 'patsatech-marketpress-sagepayform'); ?></span></h3>
		      	<div class="inside">
		        	<span class="description"><?php _e('SagePay Form provides a fully PCI Compliant, secure way to collect and transmit credit card data to your payment gateway while keeping you in control of the design of your site.', 'patsatech-marketpress-sagepayform') ?></span>
		        	<table class="form-table">
		  				<tr>
							<th scope="row"><?php _e('Vendor Name', 'patsatech-marketpress-sagepayform') ?></th>
						    <td>
				          		<span class="description"><?php _e('Please enter your vendor name provided by SagePay.', 'patsatech-marketpress-sagepayform'); ?></span><br />
						        <p>
									<input value="<?php echo esc_attr($settings['gateways']['sagepayform']['vendorname']); ?>" size="30" name="mp[gateways][sagepayform][vendorname]" type="text" />
							    </p>	
						    </td>
						</tr>
		  				<tr>
							<th scope="row"><?php _e('Encryption Password', 'patsatech-marketpress-sagepayform') ?></th>
						    <td>
				          		<span class="description"><?php _e('Please enter your encryption password provided by SagePay.', 'patsatech-marketpress-sagepayform'); ?></span><br />
						        <p>
									<input value="<?php echo esc_attr($settings['gateways']['sagepayform']['vendorpass']); ?>" size="30" name="mp[gateways][sagepayform][vendorpass]" type="text" />
							    </p>	
						    </td>
						</tr>	
		  				<tr>
							<th scope="row"><?php _e('Vendor E-Mail', 'patsatech-marketpress-sagepayform') ?></th>
						    <td>
				          		<span class="description"><?php _e('An e-mail address on which you can be contacted when a transaction completes.', 'patsatech-marketpress-sagepayform'); ?></span><br />
						        <p>
									<input value="<?php echo esc_attr($settings['gateways']['sagepayform']['vendoremail']); ?>" size="30" name="mp[gateways][sagepayform][vendoremail]" type="text" />
							    </p>	
						    </td>
						</tr>	
		          		<tr valign="top">
			        		<th scope="row"><?php _e('Send E-Mail', 'patsatech-marketpress-sagepayform') ?></th>
			        		<td>
				          		<span class="description"><?php _e('Who to send e-mails to.', 'patsatech-marketpress-sagepayform'); ?></span><br />
			          			<select name="mp[gateways][sagepayform][sendemails]">
				          		<?php
				          		$sel_semails = ($settings['gateways']['sagepayform']['sendemails']) ? $settings['gateways']['sagepayform']['sendemails'] : '0';
				          		$semails = array(
													'0' => 'No One',
													'1' => 'Customer and Vendor',
													'2' => 'Vendor Only'
												);
								
				          		foreach ($semails as $k => $v) {
				              		echo '		<option value="' . $k . '"' . ($k == $sel_semails ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
				          		}
				          		?>
				          		</select>
			        		</td>
						</tr>
		  				<tr>
							<th scope="row"><?php _e('Customer E-Mail Message', 'patsatech-marketpress-sagepayform') ?></th>
						    <td>
				          		<span class="description"><?php _e('A message to the customer which is inserted into the successful transaction e-mails only.', 'patsatech-marketpress-sagepayform'); ?></span><br />
						        <p>
									<textarea value="<?php echo esc_attr($settings['gateways']['sagepayform']['emailmessage']); ?>" cols="100" rows="5" name="mp[gateways][sagepayform][emailmessage]" ></textarea>
							    </p>	
						    </td>
						</tr>
		          		<tr valign="top">
			        		<th scope="row"><?php _e('Mode Type', 'patsatech-marketpress-sagepayform') ?></th>
			        		<td>
				          		<span class="description"><?php _e('Select Simulator, Test or Live modes.', 'patsatech-marketpress-sagepayform'); ?></span><br />
			          			<select name="mp[gateways][sagepayform][mode]">
				          		<?php
				          		$sel_mode = ($settings['gateways']['sagepayform']['mode']) ? $settings['gateways']['sagepayform']['mode'] : 'simulator';
				          		$mode = array( 
												'test' => 'Test',
												'live' => 'Live'
												);
								
				          		foreach ($mode as $k => $v) {
				              		echo '		<option value="' . $k . '"' . ($k == $sel_mode ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
				          		}
				          		?>
				          		</select>
			        		</td>
						</tr>
		          		<tr valign="top">
			        		<th scope="row"><?php _e('Transition Type', 'patsatech-marketpress-sagepayform') ?></th>
			        		<td>
				          		<span class="description"><?php _e('Select Payment, Deferred or Authenticated.', 'patsatech-marketpress-sagepayform'); ?></span><br />
			          			<select name="mp[gateways][sagepayform][transtype]">
				          		<?php
				          		$sel_transtype = ($settings['gateways']['sagepayform']['transtype']) ? $settings['gateways']['sagepayform']['transtype'] : 'PAYMENT';
				          		$transtype = array(
													'PAYMENT' => 'Payment', 
													'DEFFERRED' => 'Deferred',
													'AUTHENTICATE' => 'Authenticate'
												);
								
				          		foreach ($transtype as $k => $v) {
				              		echo '		<option value="' . $k . '"' . ($k == $sel_transtype ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
				          		}
				          		?>
				          		</select>
			        		</td>
						</tr>
		          		<tr valign="top">
			        		<th scope="row"><?php _e('SagePay Form Currency', 'patsatech-marketpress-sagepayform') ?></th>
			        		<td>
				          		<span class="description"><?php _e('Selecting a currency other than that used for your store may cause problems at checkout.', 'patsatech-marketpress-sagepayform'); ?></span><br />
			          			<select name="mp[gateways][sagepayform][currency]">
				          		<?php
				          		$sel_currency = ($settings['gateways']['sagepayform']['currency']) ? $settings['gateways']['sagepayform']['currency'] : $settings['currency'];
				          		$currencies = array(
								              	'GBP' => 'GBP - Pound Sterling',
								              	'EUR' => 'EUR - Euro',
								              	'USD' => 'USD - U.S. Dollar'
												);
								
				          		foreach ($currencies as $k => $v) {
				              		echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
				          		}
				          		?>
				          		</select>
			        		</td>
						</tr>
					</table>
		    	</div>
		    </div>
	    	<?php
	  	}
	  	
	  	/**
	   	 * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
	     *  array. Don't forget to return!
	     */
	  	function process_gateway_settings($settings) {
	    	return $settings;
	  	}
	  	
	  	/**
	     * IPN and payment return
	     */
	  	function process_ipn_return() {
	    	global $mp;
	    	$settings = get_option('mp_settings');
			
	      	if ( isset($_REQUEST['crypt']) && !empty($_REQUEST['crypt']) ) {
			
		        $transaction_response = $this->decode(str_replace(' ', '+',$_REQUEST['crypt']));
				
				$order_id = explode('-',$transaction_response['VendorTxCode']);
				
	        	if ( $transaction_response['Status'] == 'OK' || $transaction_response['Status'] == 'AUTHENTICATED'|| $transaction_response['Status'] == 'REGISTERED' ) {
		  		    $order_id 	= $order_id[2];
					
					$timestamp = time();
					
					$payment_status = $transaction_response['Status'];
					
					$status = __('Completed - The sender\'s transaction has completed.', 'patsatech-marketpress-sagepayform');
		          	$paid = true;
		          	$payment_info['gateway_public_name'] = 'Credit Card';
	      			$payment_info['transaction_id'] = $transaction_response['VPSTxId'];  
			      	$payment_info['method'] = 'sagepayform';
			        $payment_info['currency'] = $settings['gateways']['sagepayform']['currency'];
					
		      		//status's are stored as an array with unix timestamp as key
				  	$payment_info['status'][$timestamp] = $status;
					
		      		if ($mp->get_order($order_id)) {
		        		$mp->update_order_payment_status($order_id, $status, $paid);
		      		} else {
						$cart = get_transient('mp_order_' . $order_id . '_cart');
			  			$shipping_info = get_transient('mp_order_' . $order_id . '_shipping');
						$user_id = get_transient('mp_order_' . $order_id . '_userid');						
						$total = get_transient('mp_order_' . $order_id . '_sagetotal');
						
			        	$payment_info['total'] = $total;
					  	
		        		$success = $mp->create_order($order_id, $cart, $shipping_info, $payment_info, $paid, $user_id);
						
						//if successful delete transients
		        		if ($success) {
		        			delete_transient('mp_order_' . $order_id . '_cart');
	        				delete_transient('mp_order_' . $order_id . '_shipping');
							delete_transient('mp_order_' . $order_id . '_userid');
							delete_transient('mp_order_' . $order_id . '_sagetotal');
		        		}
						
		      		} 
					
					wp_redirect( mp_checkout_step_url('confirmation') ); exit;
					
				}else{
		        	
			    	$mp->cart_checkout_error( sprintf(__('Transaction Failed. The Error Message was %s', 'woocommerce'), $transaction_response['StatusDetail'] ) );
					
				}
			}
			
			
		}
	}
	
	mp_register_gateway_plugin( 'MP_Gateway_SagePay_Form', 'sagepayform', __('SagePay Form', 'patsatech-marketpress-sagepayform') );
	
}

?>