<?
###############################################################
### Billin Software Developer's Kit for PHP5
### Copyright © 2012. All rights reserved. Billin Sp. z o.o.
###############################################################


## requirement checks
if (!in_array('curl', get_loaded_extensions())) {
	die("cURL is not present in your PHP installation\n");
}

## libs
require 'config.php5';
require 'constants.php5';

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
	} elseif (is_keyword($val)) {
		$res = $val->value;
	} elseif ($val === True) {
		$res = 'true';
	} elseif ($val === False) {
		$res = 'false';
	} else {
		$res = $val;
	}

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

		foreach ($subs as $child) {
			if ($child->{name} == $name or $child->{id} == $name) {
				if (count($nlist) > $n+1) {
					$this->update_property_list($child, $nlist, $value, $n+1);
				} else {
					if ($child->{type} == ':charging_plan') {
						if ($value < $child->{cardinality}->{min} or $value > $child->{cardinality}->{max}) {
							die("Assignment count outside of cardinality bounds");
						}
					}
					$this->changes[$child->{xid}] = $value;
				}
			}
		}
	}

	public function update_property($name, $value) 
	{
		$nlist = split('!', $name);
		$this->update_property_list($this->json, $nlist, $value);
	}
}

class BillinPCPException extends Exception {
	public $descr;
	public $number;
	public $id_error;

	function __construct($descr, $number, $id_error) {
		list($this->descr, $this->number, $this->id_error) =
			array($descr, $number, $id_error);
	}
}

class BillinAPIException extends Exception {
	public $url;
	public $code;
	public $response;

	function __construct($url, $code, $response) 
	{
		list($this->url, $this->code, $this->reponse) = 
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
		global $debug; 

		if ($debug) {
			if (is_array($x)) {
				foreach ($x as $label => $val) {
					syslog(LOG_DEBUG, sprintf("%s: %s", $label, ((is_array($val) or is_object($val)) ? print_r($val, True) : $val)));
				}
			} elseif (is_string($x)) {
				syslog(LOG_DEBUG, $x);
			} else {
				die("Cannot mlog value of type " . gettype($x) . "\n");
			}
		}
	}

	function curl_call($ch, $url, $post_fields = '')
	{
		curl_setopt($ch, CURLOPT_URL, $url);
		$this->mlog(array(($post_fields == '') ? 'GET' : 'POST' => $url));
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
			foreach(split("\n", $result) as $line) {
				$this->mlog($line);
			}
		}
		$result = $code == 200 ? json_decode($result) : $result;
		return array($result, $code);
	}

	function call_url($url, $post_args = array()) 
	{
		$qurl = $this->url . urlencode($url);
		list($result, $code) = $this->curl_call($this->ch, $qurl, post_fields($post_args, 'api_quote'));
		if ($code == 200) {
			$this->calls[] = $url;
			return $result;
		} else {
			$this->mlog(array('Result' => 'fail - ' . $result, 'Code' => $code));
			if ($code == 505) {
				throw new BillinNoLoginException($url, $code, $result);
			} elseif ($code == 501 or $code == 502) {
				throw new BillinInvalidSessionException($url, $code, $result);
			} elseif ($code == 0) {
				die("Connection refused: $url\n"); 
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

	public function create_customer($billing_data = Null, $customer_args = array())
	{
		$billing_data = $this->default_object($billing_data);
		return $this->call_api(create, array(customer), $customer_args+array(billing_data => ref(-1)));
	}

	public function search_customers($params = array())
	{ 
		return $this->call_api(search, array(customer), $params);
	}

	public function find_customer($params) {
		$this->stack_api(search, array(customer), $params);
		$this->stack_api(elt, array(0));
		return $this->run_api_stack();
	}

	## subscription
	public function list_customer_subscriptions()
	{
		return $this->call_api(search, array(subscription));
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
		return map(get_attrs(id, name), $this->call_api(search, array(product_def)));
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
	public function check_coupon($code)
	{
		return $this->call_api(check_coupon, array($code));
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


	## payments
	public function get_payu_pending_payment($customer = Null) 
	{
		$customer = $this->default_object($customer);
		return $this->call_api(get_pending_payment, array($customer, keyword(payu)));
	}

	### card payments
	public function authorize_card($customer, $issuer, $ccno, $cvv, $expy, $expm, $name, $email, $ip, $country, $city, 
					$street, $zipcode, $currency, $descr = 'Test authorisation', $amount = '1.00')
	{
		global $pcp, $pcp_user, $pcp_pass;

		$customer = $this->default_object($customer);
		if (!$this->pcp_ch) {
			$this->pcp_ch = init_curl($pcp_user, $pcp_pass);
		}
		$fields = post_fields(array( ccno => $ccno, cvv => $cvv,
			expy => $expy, expm => str_pad($expm . '', 2, '0', STR_PAD_LEFT), name => $name, email => $email, 
			ip => $ip, country => $country, city => $city, street => $street,
			zipcode => $zipcode, currency => $currency, amount => $amount, descr => $descr));
		$this->mlog(array('user' => $pcp_user, 'pass' => $pcp_pass));
		list($result, $code) = $this->curl_call($this->pcp_ch, $pcp . "auth", $fields);
		if ($code == 200) {
			$sale_id = $result->{'OK'}->{'id_sale_authorization'};
			$fraud_score = $result->{'DATA'}->{'fraud_score'};
			return $this->call_api(authorize_payment_method, array($customer, keyword('credit_card')),
						array(sale_id => $sale_id, 
						      successp => True,
						      fraud_score => $fraud_score . '',
						      issuer => keyword($issuer),
						      ip => $ip,
						      masked_number => mask_ccno($ccno),
						      exp_year => intval($expy),
						      exp_month => intval($expm)));

		} else {
			$result = json_decode($result);
			if (property_exists($result, 'ERROR')) {
				$e = $result->{'ERROR'};
				return $this->call_api(authorize_payment_method, array($customer, keyword('credit_card')),
						array(successp => False,
						      ip => $ip,
						      masked_number => mask_ccno($ccno),
						      issuer => keyword($issuer),
						      exp_year => intval($expy),
						      exp_month => intval($expm),
						      error_description => $e->{'error_description'}, 
						      error_number => $e->{'error_number'}, 
						      error_id => $e->{'id_error'}));
			} else {
				mlog(array("Invalid PCP error message format" => $result));
			}
		}
	}
}
?>
