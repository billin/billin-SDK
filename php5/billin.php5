<?php
###############################################################
### Billin Software Developer's Kit for PHP5
### Copyright © 2012. All rights reserved. Billin Sp. z o.o.
###############################################################

global $system, $user, $password, $api_key, $server, $prefix, 
$debug, $log_process, $log_facility, $api_version, $console_log;

## requirement checks
if (!in_array('curl', get_loaded_extensions())) {
	die("cURL is not present in your PHP installation\n");
}

## libs
require_once 'config.php5';
require_once 'constants.php5';

function identity($x)
{
	return $x;
}

function map($fn, $arr) 
{
	$res = array();
	foreach ($arr as $k => $v) {
		$res[$k] = call_user_func($fn, $v, $k);
	}
	return $res;
}

function first($arr)
{
	return $arr[0];
}

function second($arr)
{
	return $arr[1];
}

function normalize_id($arr)
{
	if (array_key_exists('id', $arr)) {
		$arr['id'] = (string)$arr['id'];
	}
	return $arr;
}

function map_list($fn, $arr) 
{
	$res = array();
	foreach ($arr as $v) {
		$res[] = call_user_func($fn, $v);
	}
	return $res;
}

function get_attr($attr) 
{
	return function($elt) use ($attr) 
	{
		return $elt->{$attr};
	};
}

function get_attrs() 
{
	$properties = func_get_args();
	return function($elt) use ($properties) 
	{
		$res = array();
		foreach ($properties as $prop) {
			$res[$prop] = $elt->{$prop};
		}
		return $res;
	};
}

function api_quote($val, $key = Null) 
{
	# print "api quote\nval:\n";
	# var_dump($val);
	# print "key:\n";
	# var_dump($key);

	if (is_string($val)) {
		if (defined($val)) {
			## constant
			$res = $val;
		} else {
			$res = '"' . $val . '"';
		}
	} elseif (is_ref($val)) {
		$res = '@' . $val->pos;
	} elseif (is_amount($val)) {
		$res = $val->value;
	} elseif (is_billinString($val)) {
		$res = '"' . $val->value . '"';
	} elseif (is_symbol($val)) {
		$res = $val->value;
	} elseif (is_keyword($val)) {
		$res = $val->value;
	} elseif ($val === True) {
		$res = 'true';
	} elseif ($val === False) {
		$res = 'false';
	} else {
		$res = $val;
	}

	# print "res: $res\n";

	if ($key) {
		return $key . '=' . $res;
	} else {
		return $res;
	}
}

function post_fields($arr, $fn = 'identity')
{
	$fields = '';
	foreach($arr as $k => $v) { 
		$fields .= $k . '=' . urlencode(call_user_func($fn, $v)) . '&'; 
	}
	$fields = rtrim($fields, '&');
	return $fields;
}

function mask_ccno($str)
{
	return substr($str, 12, 4);
}


class BillinAPIRef {
	public $pos;
	function __construct($pos) 
	{
		$this->pos = $pos;
	}
}

function ref($n) 
{
	return new BillinAPIRef($n);
}

function is_ref($val) 
{
	return gettype($val) == 'object' and get_class($val) == 'BillinAPIRef';
}

class BillinAmount {
	public $value;
	function __construct($value) 
	{
		if (!is_string($value)) {
			die('Use strings for currency values');
		}
		$this->value = '$' . $value;
	}
}

function amount($string) 
{
	return new BillinAmount($string);
}

function is_amount($val) 
{
	return gettype($val) == 'object' and get_class($val) == 'BillinAmount';
}

class BillinKeyword {
	public $value;
	function __construct($value) 
	{
		if (!is_string($value)) {
			die('Use strings for keywords');
		}
		$this->value = ':' . $value;
	}
}

function keyword($val) 
{
	return new BillinKeyword($val);
}

function is_keyword($val) 
{
	return gettype($val) == 'object' and get_class($val) == 'BillinKeyword';
}

class BillinSymbol {
	public $value;
	function __construct($value) 
	{
		if (!is_string($value)) {
			die('Use strings for symbols');
		}
		$this->value = $value;
	}
}

function symbol($val) 
{
	return new BillinSymbol($val);
}

function is_symbol($val) 
{
	return gettype($val) == 'object' and get_class($val) == 'BillinSymbol';
}

class BillinString {
	public $value;
	function __construct($value) 
	{
		if (!is_string($value)) {
			die('Use strings for BillinStrings');
		}
		$this->value = $value;
	}
}

function string($val) 
{
	return new BillinString($val);
}

function is_billinString($val) 
{
	return gettype($val) == 'object' and get_class($val) == 'BillinString';
}

function rm_vars($locals, $to_rm)
{
	$res = array();
	foreach ($locals as $k => $v) {
		if (!in_array($k, $to_rm) and !is_null($v)) {
			$res[$k] = $v;
		}
	}
	return $res;
}

class BillinProductParams {
	public $changes = array();
	public $json;
	public $id;

	function __construct($json) 
	{
		$this->json = $json;
		$this->id = $json->{id};
	}

	function update_property_list($json, $nlist, $value, $n = 0) 
	{
		$name = $nlist[$n];
		if (property_exists($json, 'children')) {
			$subs = $json->{children};
		} else {
			$subs = $json->{params};
		}

		$updated = False;
		foreach ($subs as $child) {
			if ($child->{name} == $name or $child->{id} == $name) {
				$updated = True;
				if (count($nlist) > $n+1) {
					$this->update_property_list($child, $nlist, $value, $n+1);
				} else {
					if ($child->{type} == ':charging_plan') {
						if ($value < $child->{cardinality}->{min} or $value > $child->{cardinality}->{max}) {
							throw new BillinRuntimeException("Assignment count outside of cardinality bounds");
						}
					}
					$this->changes[$child->{xid}] = $value;
				}
			}
		}
		if (!$updated) {
			throw new BillinRuntimeException("Unable to find property $name to update");
		}
	}

	public function update_property($name, $value) 
	{
		$nlist = mb_split('!', $name);
		$this->update_property_list($this->json, $nlist, $value);
	}
}

class BillinException extends Exception {
	public $message;

	function __construct($message) {
		$this->message = $message;
	}
}

class BillinRuntimeException extends BillinException {
}

class BillinPCPException extends BillinException {
	public $message;
	public $number;
	public $id_error;

	function __construct($message, $number, $id_error) {
		list($this->message, $this->number, $this->id_error) =
			array($message, $number, $id_error);
	}
}

class BillinAPIException extends BillinException {
	public $url;
	public $code;
	public $response;

	function __construct($url, $code, $response) 
	{
		list($this->url, $this->code, $this->response) = 
			array($url, $code, $response);
	}
}

class BillinNoLoginException extends BillinAPIException {
}

class BillinInvalidSessionException extends BillinAPIException {
}

class BillinInvalidCredentialsException extends BillinAPIException {
	public $user;
	public $pass;

	function __construct($user, $pass) 
	{
		list($this->user, $this->pass) = array($user, $pass);
	}
}

function init_curl($user = Null, $pass = Null) 
{
	global $secure;

	$ch = curl_init();

	# basic cURL config
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $secure ? True : False);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 5);

	# cURL auth config
	if ($user and $pass) {
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
	}
	return $ch;
}

class BillinSession {
	public $sid;
	public $ch;
	public $pcp_ch;
	public $calls = array();
	public $log_stream;

	function __construct($sid = Null) 
	{
		global $system, $debug, $log_process, $log_facility;

		if ($debug) {
			openlog($log_process, LOG_PID | LOG_PERROR, $log_facility);
		}
		global $system;
		$this->ch = init_curl();
		$this->change_system($system);

		## initiate session id
		if ($sid) {
			$this->set_sid($sid);
		} else {
			$this->login();
		}
	}

	function __destruct() {
		global $debug;
		if ($debug) {
			closelog();
		}
	}

	function mlog($x) 
	{
		global $debug, $console_log; 

		if ($debug) {
			$msg = False;
			if (is_array($x)) {
				foreach ($x as $label => $val) {
					$msg .= sprintf("%s: %s\n", $label, ((is_array($val) or is_object($val)) ? print_r($val, True) : $val));
				}
			} elseif (is_string($x)) {
				$msg = "$x\n";
			} else {
				throw new BillinRuntimeException("Cannot mlog value of type " . gettype($x) . "\n");
			}

			if ($msg) {
				if ($console_log) {
					print($msg);
				} else {
					syslog(LOG_DEBUG, $msg);
				}
			}
		}
	}

	function curl_call($ch, $url, $post_fields = '')
	{
		$qurl = $this->url . urlencode($url);
		curl_setopt($ch, CURLOPT_URL, $qurl);
		$this->mlog(array(($post_fields == '') ? 'GET' : 'POST' => $this->url . $url));
		if ($post_fields != '') {
			$this->mlog(array('POST ARGS' => $post_fields));
			curl_setopt($ch, CURLOPT_POST, 1+substr_count($post_fields, '&'));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
		}
		$result = curl_exec($ch);
		if ($post_fields != '') {
			curl_setopt($ch, CURLOPT_POST, False);
			curl_setopt($ch, CURLOPT_POSTFIELDS, Null);
		}
		$status = curl_getinfo($ch);
		$code = $status['http_code'];
		if ($code == 200) {
			$this->mlog('RESULT');
			foreach(mb_split("\n", $result) as $line) {
				$this->mlog($line);
			}
		}
		if ($code == 200 && (($json_result = json_decode($result)) !== NULL)) {
			return array($json_result, $code);
		} else {
			return array($result, $code);
		}

	}

	function call_url($url, $post_args = array()) 
	{
		list($result, $code) = $this->curl_call($this->ch, $url, post_fields($post_args, 'api_quote'));
		if ($code == 200) {
			$this->calls[] = $url;
			return $result;
		} else {
			$this->mlog(array('Result' => "fail - $result", 'Code' => $code));
			if ($code == 505) {
				throw new BillinNoLoginException($url, $code, $result);
			} elseif ($code == 501 or $code == 502) {
				throw new BillinInvalidSessionException($url, $code, $result);
			} elseif ($code == 0) {
				throw new BillinRuntimeException("Connection refused, timed out or certificate validation failed for $url\n"); 
			} else {
				throw new BillinAPIException($url, $code, $result);
			}
		}
	}

	public function stack_api($fn, $args = array(), $named_args = array(), $post_args = array()) 
	{
		$args = join(',', map_list('api_quote', $args));
		$named_args = join(',', map('api_quote', $named_args));

		$url = $fn;
		if ($args != '' or $named_args != '') {
			$url = $fn . '(' . $args;
			if (!empty($args) and !empty($named_args)) {
				$url .= ',';
			}
			$url .= $named_args . ')';
		}

		$this->api_stack[] = array($url, $post_args);
	}

	public function run_api_stack($return_json = True)
	{
		$res = map_list('first', $this->api_stack);
		$url = join('/', $res);

		if ($return_json) {
			$url .= '/json';
		}

		$res = map_list('second', $this->api_stack);
		$post_args = array_reduce($res, 'array_merge', array());

		# reset the stack
		$this->api_stack = array();

		try {
			return $this->call_url($url, $post_args);
		} catch (BillinNoLoginException $nologin) {
			if (empty($this->calls)) {
				$this->login();
				return $this->call_url($url);
			} else {
				throw $nologin;
			}
		} catch (BillinInvalidSessionException $expired) {
			if (empty($this->calls)) {
				$this->login();
				return $this->call_url($url);
			} else {
				throw $expired;
			}
		}
	}

	public function call_api($fn, $args = array(), $named_args = array(), $post_args = array(), $return_json = True) 
	{
		$this->stack_api($fn, $args, $named_args, $post_args);
		return $this->run_api_stack($return_json);
	}



	function get_object($obj) 
	{
		$this->stack_api(search, array($obj->{class_name}), array(oid => $obj->{oid}));
		$this->stack_api(elt, array(0));
		return $this->run_api_stack();
	}

	function default_object($obj) 
	{
		if ($obj) {
			if (is_ref($obj)) {
				return $obj;
			} else {
				$this->get_object($obj);
				return ref(-1);
			}
		}
		return ref(-1);
	}


	## API
	public function change_system($sys) 
	{
		global $system, $prefix, $server, $api_version;
		$system = $sys;
		if ($sys) {
			$this->url = $server . $prefix . $sys .
				'/' . $api_version . '/';
		} else {
			$this->url = $server . $prefix ;
		}
	}

	public function set_sid($sid)
	{
		$this->sid = $sid;
		curl_setopt($this->ch, CURLOPT_COOKIE, 'XBS-SID=' . $sid);
	}

	public function login() 
	{
		global $system, $user, $password, $api_key;
		## check if session initiated already
		$_sys = $system;
		## switch to top level for login
		$this->change_system(Null);
		if (isset($user) and (isset($password) or isset($api_key))) {
			try {
				if (isset($password)) {
					$json = $this->call_api('login(*)',
						array(),
						array(),
						array(user => $user, pass => $password));
				} else {
					$json = $this->call_api('login(*)',
						array(),
						array(),
						array(user => $user, api_key => $api_key));
				}
				$this->set_sid($json->{'sid'});
				$this->mlog(array('New session' => $json->{'sid'}));
			} catch (BillinAPIException $e) {
				if ($e->code == 503) {
					throw new BillinInvalidCredentialsException($user, $password);
				} elseif ($e->code == 500) {
					throw $e;
				}
			}
		} else {
			die('Set $user and $password or $api_key in configuration');
		}
		## switch back to saved system
		$this->change_system($_sys);
		return $this->sid;
	}

	## transaction
	public function commit() 
	{
		$this->calls = array();
		return $this->call_api(commit);
	}

	public function rollback() 
	{
		$this->calls = array();
		return $this->call_api(rollback);
	}

	public function logout() 
	{
		$this->calls = array();
		return $this->call_api(logout);
	}


	## data utils
	public function elt($n, $list = Null) 
	{
		$list = $list ? $list : ref(-1);
		return $this->call_api(elt, array($list, $n));
	}

	## customer
	public function create_billing_data($billing_data_args) 
	{
		return $this->call_api(create, array(billing_data), $billing_data_args);
	}

	public function modify_customer_billing_data($customer = Null, $args) 
	{
		$customer = $this->default_object($customer);
		$this->stack_api(value, array($customer), array(billing_data));
		$this->stack_api(modify, array(), $args);
		return $this->run_api_stack();
	}

	public function create_customer($billing_data = Null, $customer_args = array())
	{
		$billing_data = $this->default_object($billing_data);
		return $this->call_api(create, array(customer), $customer_args+array(billing_data => ref(-1)));
	}

	public function search_customers($params = array())
	{ 
		$params = normalize_id($params);
		return $this->call_api(search, array(customer), $params);
	}

	public function search_invoices($params = array())
	{ 
		return $this->call_api(search, array(invoice), $params);
	}

	public function search_subscriptions($params = array())
	{ 
		$params = normalize_id($params);
		return $this->call_api(search, array(subscription), $params);
	}

	public function search_coupon($params = array())
	{ 
		return $this->call_api(search, array(coupon_def), $params);
	}

	public function find_customer($params) {
		$params = normalize_id($params);
		$this->stack_api(search, array(customer), $params);
		$this->stack_api(elt, array(0));
		return $this->run_api_stack();
	}

	## subscription
	public function list_customer_subscriptions($customer = Null, $params = array())
	{
		if ($customer) {
			$customer = $this->default_object($customer);
			return $this->call_api(list_data, array($customer, subscription), $params);
		} else {
			return $this->call_api(search, array(subscription));
		}
	}

	public function all_subscriptions_status() 
	{
		$subscriptions = $this->list_customer_subscriptions();
		return map_list(get_attrs(id, ext_id, status), $subscriptions);
	}

	## product
	public function get_product_params($id, $customer = Null) 
	{
		$customer = $this->default_object($customer);
		$product = $this->call_api(get_product_params, array($id), array('parent' => $customer));
		return new BillinProductParams($product);
	}

	public function get_product_params_by_oid($oid, $customer = Null) 
	{
		$customer = $this->default_object($customer);
		$this->stack_api(search, array(product_def), array(oid => $oid));
		$this->stack_api(elt, array(0));
		$this->stack_api(get_product_params, array(), array('parent' => $customer));
		return new BillinProductParams($this->run_api_stack());
	}

	public function create_subscription($customer = Null, $subscription_args = array()) 
	{
		$customer = $this->default_object($customer);
		return $this->call_api(create, array(subscription), $subscription_args+array(parent => $customer));
	}

	public function assign_product($product, $subscription = Null, $product_args = array()) 
	{
		$subscription = $this->default_object($subscription);
		if (empty($product->changes)) {
			$product_args['no_params_assign'] = True;
		}
		return $this->call_api(assign_product, array($subscription, $product->id), $product_args+$product->changes);
	}

	public function configure_product($product, $changes) 
	{
		foreach ($changes as $name => $value) {
			$product->update_property($name, $value);
		}
	}

	public function list_products() 
	{
		# return map(get_attrs(id, name, descr), $this->call_api(search, array(product_def)));
		return $this->call_api(search, array(product_def));
	}

	public function search_products($params)
	{
		return $this->call_api(search, array(product_def), $params);
	}

	public function swap_product($product, $subscription = Null, $swap_args = array())
	{
		$subscription = $this->default_object($subscription);
		if (empty($product->changes)) {
			$swap_args['no_params_swap'] = True;
		}
		return $this->call_api(swap_product, array($subscription, $product->{id}), $swap_args+$product->changes);
	}

	## coupons
	public function create_coupon($params)
	{
		return $this->call_api(create, array(coupon_def), $params);
	}

	public function check_coupon($code, $product = Null)
	{
		return $this->call_api(check_coupon, array($code), $product ? array(product => $product) : array());
	}

	public function redeem_coupon($customer = Null, $code, $skip_invalid_coupon_error = False)
	{
		$customer = $this->default_object($customer);
		return $this->call_api(redeem_coupon, array($customer, $code), 
		array(skip_invalid_coupon_error => $skip_invalid_coupon_error));
	}

	## balance details
	public function list_customer_payments($customer = Null) 
	{
		$customer = $this->default_object($customer);
		return $this->call_api(list_data, array($customer, balance_detail), array(subtype => keyword(payment)));
	}

	public function list_customer_balance_details($customer = Null) 
	{
		$customer = $this->default_object($customer);
		return $this->call_api(list_data, array($customer, balance_detail), array(subtype => keyword(all)));
	}

	## invoices
	public function list_customer_invoices($customer, $named_args = array())
	{
		$customer = $this->default_object($customer);
		return $this->call_api(list_data, array($customer, invoice), $named_args);
	}

	## subscription charge inst details
	public function list_subscription_charge_discount_inst_detail($subscription, $named_args = array())
	{
		$subscription = $this->default_object($subscription);
		return $this->call_api(list_data, array($subscription, charge_discount_inst_detail), $named_args);
	}


	public function format_document($invoice = Null, $desired_output_format = Null)
	{
		$invoice = $this->default_object($invoice);
		$desired_output_format = $desired_output_format ? $desired_output_format : keyword(html);
		return $this->call_api(format_document, array($invoice), array(desired_output_format => $desired_output_format));
	}

	public function get_document_image($path, $invoice = Null, $output_format = Null, $image_type = Null)
	{
		$invoice = $this->default_object($invoice);
		$output_format = $output_format ? $output_format : keyword(pdf);
		$image_type = $image_type ? $image_type : keyword(original);
		if (is_string($path)) {
			$fp = fopen($path, 'w');
		} else {
			$fp = $path;
		}
		curl_setopt($this->ch, CURLOPT_FILE, $fp);
		$this->call_api(get_document_image, array($invoice, $output_format), array(image_type => $image_type), array(), False);
		if (is_string($path)) {
			fclose($fp);
		}
		curl_close($this->ch);
		$this->ch = init_curl();
	}

	public function get_document_image_raw($invoice = Null, $output_format = Null, $image_type = Null)
	{
		$invoice = $this->default_object($invoice);
		$output_format = $output_format ? $output_format : keyword(pdf);
		$image_type = $image_type ? $image_type : keyword(original);
		$result = $this->call_api(get_document_image, array($invoice, $output_format), array(image_type => $image_type), array(), False);
		curl_close($this->ch);
		$this->ch = init_curl();
		return $result;
	}

	## units
	public function modify_unit_quantity($subscription, $unit, $quantity)
	{
		$subscription = $this->default_object($subscription);
		return $this->call_api(modify_unit_quantity, array($subscription, $unit, $quantity));
	}

	## payments
	public function get_payu_pending_payment($customer_or_invoice = Null) 
	{
		$customer = $this->default_object($customer_or_invoice);
		return $this->call_api(get_pending_payment, array($customer, keyword(payu)));
	}

	public function get_paylane_pending_payment($customer_or_invoice = Null) 
	{
		$customer = $this->default_object($customer_or_invoice);
		return $this->call_api(get_pending_payment, array($customer, keyword(paylane)));
	}

	public function search_payment_gateway($id = Null, $name = Null, $method = Null, $currency_id = Null) 
	{
		$args = array();
		if ($id) {
			$args['id'] = $id;
		}
		if ($name) {
			$args['name'] = $name;
		}
		if ($method) {
			$args['method'] = $method;
		}
		if ($currency_id) {
			$args['currency_id'] = $currency_id;
		}
		return $this->call_api(search, array(payment_gateway), $args);
	}

	public function authorize_card($customer = Null, $sale_id = Null, $expy = Null, $expm = Null, $masked_number = Null)
	{
		$customer = $this->default_object($customer);
		return $this->call_api(authorize_payment_method, 
					array($customer, keyword('credit-card')), 
					array(sale_id => $sale_id, masked_number => $masked_number,
						exp_year => $expy, exp_month => $expm));
	}

	public function charge_payment_method($customer = Null)
	{
		$customer = $this->default_object($customer);
		return $this->call_api(charge_payment_method, array($customer));
	}

	public function remove_payment_method($customer = Null)

	{
		$customer = $this->default_object($customer);
		return $this->call_api(remove_payment_method, array($customer));
	}

	## paypal payments
	public function get_paylane_paypal_pending_payment($customer_or_invoice = Null)
	{
		$customer = $this->default_object($customer_or_invoice);
		return $this->call_api(get_pending_payment, array($customer, keyword(paylane_paypal)));
	}
}

?>
