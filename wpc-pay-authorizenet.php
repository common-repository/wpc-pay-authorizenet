<?php
/*
Plugin Name: WPC Payment Auth.net
Plugin URI: http://www.wordpresscart.org
Description: This plugin adds authorize.net to the WordPress Cart plugin. The WordPress Cart plugin must be already installed to actually be used. If you are looking for some help with the plugin please goto: <a href="http://www.wordpresscart.org/">wordpresscart.org</a>.  This is a collaborative project by <a href="http://www.davemerwin.com">DaveMerwin.com</a> and <a href="http://www.dunamisdesign.com">Dunamis Design</a>.
Author: Michael Calabrese
Author URI: http://dunamisdesign.com
Version: 1.0.0
*/

/*  Copyright 2005, 2006  Michael Calabrese  (email : m2calabr@dunamisdesign.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

//In older versions of WordPress you need to guard against this from being
//run twice so that classes and function are not defined twice.
//__________________________________________________________________________________
if (!isset($__wpc_authnet)) {
global $__shopcart_installed_payments;

	function wpc_authnet_hmac ($key, $data)
	{
		// RFC 2104 HMAC implementation for php.
		// Creates an md5 HMAC.
		// Eliminates the need to install mhash to compute a HMAC
		// Hacked by Lance Rushing
	
		$b = 64; // byte length for md5
		if (strlen($key) > $b) {
			$key = pack("H*",md5($key));
		}
		$key  = str_pad($key, $b, chr(0x00));
		$ipad = str_pad('', $b, chr(0x36));
		$opad = str_pad('', $b, chr(0x5c));
		$k_ipad = $key ^ $ipad ;
		$k_opad = $key ^ $opad;
	
		return md5($k_opad  . pack("H*",md5($k_ipad . $data)));
	}
	function wpc_authnet_fingerprint ($loginid, $txnkey, $amount, $currency = "") {
		$sequence = rand(1, 1000);
		$tstamp = time ();
		$fingerprint = wpc_authnet_hmac ($txnkey, $loginid . "^" . $sequence . "^" . $tstamp . "^" . $amount . "^" . $currency);
		
		$str = 'x_fp_sequence='. $sequence .
					'&x_fp_timestamp='. $tstamp .
					'&x_fp_hash='. $fingerprint;
		$str = 'x_tran_key='. $txnkey;
		return $str;
	}


	function wpc_authnet_update_option ($option,$default,$reset=false) {
		if ((strlen(get_option("wpc_authnet_{$option}"))==0) || $reset) {
			update_option("wpc_authnet_{$option}", $default);
		}
	}

	if (strstr($_SERVER['PHP_SELF'], 'wp-admin/')) {
		//___________________________________________________
		function wpc_authnet_options() {
		//___________________________________________________
			//This is a silly hack... I am guessing the we need to turn off magic quotes.  Because without this block of code a ' becomes \' in the DB meaning that addslashes is happening twice.
			if (get_magic_quotes_gpc()) {
			// Yes? Strip the added slashes
				$_REQUEST = array_map('stripslashes', $_REQUEST);
				$_GET = array_map('stripslashes', $_GET);
				$_POST = array_map('stripslashes', $_POST);
				$_COOKIE = array_map('stripslashes', $_COOKIE);
			}
			//save any options sent
			$options = array('wpc_authnet_url','wpc_authnet_username','wpc_authnet_txkey');
			foreach ($options as $an_option) {
				if (isset($_POST[$an_option])) update_option($an_option, $_POST[$an_option]);
				${$an_option} = get_option($an_option);
			}
			//Deal with checkboxes
			$options = array('wpc_authnet_active','wpc_authnet_test');
			foreach ($options as $an_option) {
				//Make sure we are submitting...otherwise all checkboxes will be set to false
				if  (isset($_POST['Submit'])) {
					if (isset($_POST[$an_option])) update_option($an_option, 'true');
					else update_option($an_option, 'false');
				}
				${$an_option} = get_option($an_option);
			}
			?>
			<fieldset><legend><b>Authorize.Net Options</b></legend>
			<table id="comp" cellspacing="2" cellpadding="5" class="editform">
				<tr><th>Activated:</th>
					<td><input id="wpc_authnet_active" name="wpc_authnet_active" type="checkbox" <?php checked($wpc_authnet_active, 'true'); ?> />
					<br />If checked this payment system will be active<br /> Default: unchecked
				</td></tr>	
				<tr><th>In Test Mode:</th>
					<td><input id="wpc_authnet_test" name="wpc_authnet_test" type="checkbox" <?php checked($wpc_authnet_test, 'true'); ?> />
					<br />If check this payment system will be active<br /> Default: unchecked
				</td></tr>	
			
				<tr><th>AuthNet Url:</th><td><INPUT type="text" size="70" name="wpc_authnet_url" id="wpc_authnet_url" value="<?php echo $wpc_authnet_url; ?>"></td></tr>
				<tr><th>Username</th><td><INPUT type=text" size="70" name="wpc_authnet_username" id="wpc_authnet_username" value="<?php echo $wpc_authnet_username;?>"/> </td></tr>
				<tr><th>Transaction Key:</th><td><INPUT type="text"  size="70" name="wpc_authnet_txkey" id="wpc_authnet_txkey" value="<?php echo $wpc_authnet_txkey; ?>"></td></tr>
				<tr><td colspan="2"><INPUT type="submit" value="Update All Options"></td></tr>
			</table>
			</fieldset>
			<?php
		}

		//___________________________________________________
		function wpc_authnet_install ($reset=false) {
		//___________________________________________________
			wpc_authnet_update_option('active', 'false');
			wpc_authnet_update_option('test', 'true');
			wpc_authnet_update_option('url', 'https://secure.authorize.net/gateway/transact.dll');
			wpc_authnet_update_option('username', 'authnet-username');
			wpc_authnet_update_option('txkey', 'authnet-transactionkey');
		}
		if (isset($_GET['activate'])) {
			add_action('init', 'wpc_authnet_install');
		}
		add_action('wpc_pay_options', 'wpc_authnet_options');
	
	} else {
	//user side only
	}
		//___________________________________________________
		function wpc_authnet_auth($cart){
		//___________________________________________________
		//is this payment actived
			$ordertotal = $cart->get_orderfinaltotal();
			$paytotal = $cart->get_paymenttotal();
			//If we have already paid the total amount...do nothing
			if ($paytotal >= $ordertotal) return;

			//$orderinfo = $cart->get_orderinfo();
			$orderinfo = $cart->get_orderinfo();
			$login = get_option('wpc_authnet_username');
			$wp_userdata = get_userdata($orderinfo->user_id);
			$chargeamt = $ordertotal - $paytotal;
			
			//$params .= "$key=$value&";
			$params ='';
			$params .= 'x_login='. urlencode($login) .'&';
			//Basic order information
			$params .= 'x_card_num=' . urlencode($orderinfo->cc_number).'&';
			$params .= 'x_exp_date='. urlencode($orderinfo->cc_expmonth . $orderinfo->cc_expyear) .'&';
			$params .= 'x_amount='  . urlencode(number_format($chargeamt,2)) .'&';
			$params .= 'x_cust_id=' . urlencode($orderinfo->user_id).'&';
			//Address info Billing
			$params .= "x_first_name={$wp_userdata->first_name}&";
			$params .= "x_last_name={$wp_userdata->last_name}&";
			//Address info Billing
			$params .= 'x_address=' . urlencode($orderinfo->bill_address_1) .'&';
			$params .= 'x_city='    . urlencode($orderinfo->bill_city).'&';
			$params .= 'x_state='   . urlencode($orderinfo->bill_state).'&';
			$params .= 'x_zip='     . urlencode($orderinfo->bill_postal).'&';
			$params .= 'x_country=' . urlencode($orderinfo->bill_country).'&';
			//Address info Shipping
			$params .= "x_ship_to_first_name={$wp_userdata->first_name}&";
			$params .= "x_ship_to_last_name={$wp_userdata->last_name}&";
			$params .= 'x_ship_to_address=' . urlencode($orderinfo->ship_address_1).'&';
			$params .= 'x_ship_to_city='    . urlencode($orderinfo->ship_city).'&';
			$params .= 'x_ship_to_state='   . urlencode($orderinfo->ship_state).'&';
			$params .= 'x_ship_to_zip='     . urlencode($orderinfo->ship_postal).'&';
			$params .= 'x_ship_to_country=' . urlencode($orderinfo->ship_country).'&';
			
			$params .= 'x_phone='. urlencode($orderinfo->phone).'&';
			$params .= 'x_email='. urlencode($orderinfo->email).'&';
			//Contsants
			$params .= "x_version=3.1&";
			$params .= "x_type=AUTH_CAPTURE&";
			$params .= "x_delim_data=TRUE&";
			//these may be changed later for more authnet functionallity
			$params .= "x_method=CC&";
			$params .= "x_email_customer=FALSE&";
			$params .= 'x_customer_ip='. urlencode($_SERVER['REMOTE_ADDR']).'&' ;
			
			if ( get_option('wpc_authnet_test')=='true' ) {
				$params .= "x_test_request=TRUE&";
			}
			//this needs to be the last as it finishes so that & is not on the end.
			$params .= wpc_authnet_fingerprint ($login, get_option('wpc_authnet_txkey'), $total);
			//print "<br />params: $params<br />";
			//print "<pre>";print_r ($wp_userdata); print "</pre>";
			//print "<br />txkey: ".get_option('wpc_authnet_txkey')."<br />";
			//exit;
			/*
    
				tep_draw_hidden_field('x_first_name', $order->billing['firstname']) .
				tep_draw_hidden_field('x_last_name', $order->billing['lastname']) .
				tep_draw_hidden_field('x_ship_to_first_name', $order->delivery['firstname']) .
				tep_draw_hidden_field('x_ship_to_last_name', $order->delivery['lastname']) .
				tep_draw_hidden_field('x_customer_ip', $HTTP_SERVER_VARS['REMOTE_ADDR']) ;
         */
			if ( get_option('wpc_authnet_active')=='true' ) {
				$count=0;
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST,1);
				curl_setopt($ch, CURLOPT_POSTFIELDS,$params);
				curl_setopt($ch, CURLOPT_URL,get_option('wpc_authnet_url'));
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,  2);
				curl_setopt($ch, CURLOPT_TIMEOUT,  120);
				curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
				//We try seven times just incase the service is busy.
				do {
					$result=curl_exec ($ch);$count++;
				} while ($result== NULL && $count < 7);

				if ($result == NULL) {
					//WHAT TO DO ON A FAILURE
					//currently we do nothing...we just fall through.
					//this will keep the customer process going and
					//then it can be charge on the back end in the admin screens
					//Someone running the backend screen should notice the 
					//balance on the order and not fulfil it until it can be
					//run.
				}
				curl_close ($ch);
				
				//Make the result manageble
				list($responceCode,$responceSubcode,$reasonCode,$reasonText,
						$approvalCode,$AVSCode,$transactionID,$rest) = explode(',',$result,8);
				switch ($reasonCode) {
					case '1':
						//yeah success save info to DB
						$cc_info = array();
						$cc_info['cc_name']=$orderinfo->cc_name;
						$cc_info['cc_number']=substr($orderinfo->cc_number,0,4) . str_repeat('X',strlen($orderinfo->cc_number)-8) . substr($orderinfo->cc_number,-4);
						$cc_info['cc_expmonth']=$orderinfo->cc_expmonth;
						$cc_info['cc_expyear']=$orderinfo->cc_expyear;
						$cc_info['cc_cvv']=$orderinfo->cc_cvv;
						$cc_info['cc_cvvindicator']=$orderinfo->cc_cvvindicator;
						$cc_info['cc_type']=creditCardType($orderinfo->cc_number);
						$cc_info['amount']=$chargeamt;
						$cc_info['txn_id']=$transactionID;
						$cc_info['approval_code']=$approvalCode;
						$cc_info['otherinfo']='';
						$cc_info['payment_date']=date('Y-m-d H:i:s',time());
						$cc_info['transaction_type']= 'creditcard';

						$cart->add_payment($cc_info);
						$cc_order = array();
						$cc_order['cc_message']='';
						$cart->update_order($cc_order);
					return; break;
				// Code 3 is an error - but anything else is an error too (IMHO)
				case '2':
					// oooh declined
				default:
					$cc_order = array();
					$cc_order['cc_message']=$reasonText.' ('.$AVSCode.$orderinfo->bill_address_1.')';
					$cart->update_order($cc_order);
   			} //end switch

			} //if active
		} // wpc_authnet_auth
		//___________________________________________________
		function wpc_authnet_display($cart){
		//___________________________________________________
		//Get the information to display
		$userres = $cart->get_userinfo();
		$orderres = $cart->get_orderinfo();
		$e_class=' class="error" ';

		$cc_cvv_ind = array ('Present','Unreadable','Not Present');
		$cc_months = array('','01 Jan','02 Feb','03 Mar','04 Apr','05 May','06 Jun','07 Jul','08 Aug','09 Sep','10 Oct','11 Nov','12 Dec');

		?>
		<div><input type="text" name="authnet_cc_name" id="authnet_cc_name" value="<?php echo $orderres->cc_name; ?>" <?php echo $e_cc_name ? $e_class :''; ?>><label for="authnet_cc_name">Name on card</label><span class="required"> *</span></div>
		
		<div><input type="text" name="authnet_cc_number" id="authnet_cc_number" value="<?php echo $orderres->cc_number; ?>" <?php echo $e_cc_number ? $e_class :''; ?>><label for="authnet_cc_number">Card number</label><span class="required"> *</span></div>
		
		<div><input type="text" name="authnet_cc_cvv" id="authnet_cc_cvv" value="<?php echo $orderres->cc_cvv; ?>" <?php echo $e_cc_cvv ? $e_class :''; ?>><label for="authnet_cc_cvv">CVV</label><span class="required"> *</span></div>
	
		
		<div><select name="authnet_cc_expmonth" id="authnet_cc_expmonth" <?php echo $e_cc_expmonth ? $e_class :''; ?>>
		<?php
		foreach ($cc_months as $value => $display) {
			$selected = ($orderres->cc_expmonth == $value) ? 'selected' : '';
			echo "<option $selected value=\"$value\">$display</option>\n";
			}
		?>
		</select><label for="auhnet_cc_expmonth">exp month</label><span class="required"> *</span></div>
		
		<div><select name="authnet_cc_expyear" id="authnet_cc_expyear" <?php echo $e_cc_expyear ? $e_class :''; ?>>
		<option value=""></option>
		<?php
		for ($year=date('Y');$year < (10 +date('Y'));$year++) {
			$selected = ($orderres->cc_expyear == $year) ? 'selected' : '';
			echo "<option $selected value=\"$year\">$year</option>\n";
			}
		?>
		</select><label for="authnet_cc_expyear">exp year</label><span class="required"> *</span></div>
                <input type="hidden" name="authnet_cc_cvvindicator" id="authnet_cc_cvvindicator" value="Present" />
		
		<?php
		if (strlen($orderres->cc_message) != 0 ) {
			//print '<div id="cc_error" class="' . $e_class . '">' . $orderres->cc_message . '</div>';
		}
			
		} //function display
		//___________________________________________________
		function wpc_authnet_result($cart){
		//___________________________________________________
		//Get the information to display
		$userres = $cart->get_userinfo();
		$orderres = $cart->get_orderinfo();
		$cc_cvv_ind = array ('Present','Unreadable','Not Present');
		$cc_months = array('','01 Jan','02 Feb','03 Mar','04 Apr','05 May','06 Jun','07 Jul','08 Aug','09 Sep','10 Oct','11 Nov','12 Dec');

		?>
		<div><input type="text" name="authnet_cc_name" id="authnet_cc_name" value="<?php echo $orderres->cc_name; ?>" <?php echo $e_cc_name ? $e_class :''; ?>><label for="authnet_cc_name">Name on card</label><span class="required"> *</span></div>
		
		<div><input type="text" name="authnet_cc_number" id="authnet_cc_number" value="<?php echo $orderres->cc_number; ?>" <?php echo $e_cc_number ? $e_class :''; ?>><label for="authnet_cc_number">Card number</label><span class="required"> *</span></div>
		
		<div><input type="text" name="authnet_cc_cvv" id="authnet_cc_cvv" value="<?php echo $orderres->cc_cvv; ?>" <?php echo $e_cc_cvv ? $e_class :''; ?>><label for="authnet_cc_cvv">CVV</label><span class="required"> *</span></div>
	
		<div><select name="authnet_cc_expmonth" id="authnet_cc_expmonth" <?php echo $e_cc_expmonth ? $e_class :''; ?>>
		<?php
		foreach ($cc_months as $value => $display) {
			$selected = ($orderres->cc_expmonth == $value) ? 'selected' : '';
			echo "<option $selected value=\"$value\">$display</option>\n";
			}
		?>
		</select><label for="auhnet_cc_expmonth">exp month</label><span class="required"> *</span></div>
		
	
		<div><select name="authnet_cc_expyear" id="authnet_cc_expyear" <?php echo $e_cc_expyear ? $e_class :''; ?>>
		<option value=""></option>
		<?php
		for ($year=date('Y');$year < (10 +date('Y'));$year++) {
			$selected = ($orderres->cc_expyear == $year) ? 'selected' : '';
			echo "<option $selected value=\"$year\">$year</option>\n";
			}
		?>
		</select><label for="authnet_cc_expyear">exp year</label><span class="required"> *</span></div>
		
		<div><select name="authnet_cc_cvvindicator" id="authnet_cc_cvvindicator" <?php echo $e_cc_cvvindicator ? $e_class :''; ?>>
		<?php
		foreach ($cc_cvv_ind as $value) {
			$selected = ($orderres->cc_cvvindicator == $value) ? 'selected' : '';
			echo "<option $selected value=\"$value\">$value</option>\n";
			}
		?>
		</select><label for="authnet_cc_cvvindicator">Card Is Present</label></div>
		
		<?php
			
		} //function display
		//___________________________________________________
		function wpc_authnet_save($cart,$vararray) {
		//___________________________________________________
		global $wpdb;
		$prefix='authnet_';
		foreach($vararray as $varname=>$newvalue) {
			if (substr($varname, 0, strlen($prefix)) == $prefix) {
					$field = substr($varname, strlen($prefix));
					$wpdb->get_var("UPDATE {$cart->order_table} SET $field='$newvalue' WHERE cart_id='{$cart->cart_id}'");

				//$this->$functionname($newvalue, substr($varname, strlen($prefix)));
				}
			}
		} //function save
		//___________________________________________________
		function wpc_authnet_installed() {
		//___________________________________________________
			global $__shopcart_installed_payments;
                        if ( get_option('wpc_authnet_active')=='true' ) {
 				$__shopcart_installed_payments++;
                        }
                }
		add_action('wpc_pay_auth', 'wpc_authnet_auth');
		add_action('wpc_display_auth', 'wpc_authnet_display');
		add_action('wpc_result_auth', 'wpc_authnet_result');
		add_action('wpc_save_ccinfo', 'wpc_authnet_save',10,2);
		add_action('wpc_installed', 'wpc_authnet_installed',10,0);


$__wpc_authnet = 1;
} // if __wpc_authnet
?>
