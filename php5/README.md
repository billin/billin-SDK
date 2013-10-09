This is a draft of a manual for Billin PHP5 SDK
================================================================================

To get you started quickly have a look at a simple example contained in the [test-billin.php5](https://github.com/billin/billin-SDK/blob/master/php5/test-billin.php5) file. Each SDK method used in there is commented to let you know what is happening in every step.

Step 0. Before you start
----------------------------------------------------------------------------

* PHP version 5.3 or higher is required
* You must have the cURL extension built and enabled in your php.ini to use the SDK
* You should be able to use the syslog module to be able to debug the SDK requests in case something goes wrong

Step 1. Configure your Billin API
--------------------------------------------------------------------------------

Open [config.php5](https://github.com/billin/billin-SDK/blob/master/php5/config.php5) in your favourite text editor and set following variables:

* `$user` - your Billin application user name
* `$password` - your Billin application user password - deprecated in favor of `$api_key`
* `$api_key` - your Billin application user key string Create a user in the GUI, generate the key and set `$api_key` to it
* `$secure` - if set, the certificate of Billin API and Paylane Card Proxy will be verified
* `$pcp` - Paylane Card Proxy address, e.g. https://localhost:9080/
* `$pcp_user` - Paylane Card Proxy user name - used only for credit card payments
* `$pcp_pass` - Paylane Card Proxy password - used only for credit card payments
* `$debug` - enables extra debugging output to syslog, set to false when done testing the SDK
* `$console_log` - enables loging to console when debugging with PHP CLI

Step 2. Test your Billin API communication
--------------------------------------------------------------------------------

Our demo server is OK for testing. Please setup the [config.php5](https://github.com/billin/billin-SDK/blob/master/php5/config.php5) file for use on Billin demo. 

Relevant server configuration parameters in [config.php5](https://github.com/billin/billin-SDK/blob/master/php5/config.php5) (besides `$user`, `$api_key` and `$password`) are:

* `$server` - eg 'http://localhost:8080' - Protocol, server and port number for the Billin API server
* `$prefix` - eg '/dev/API/' - Your individual, private API prefix
* `$system` - eg 'test' - Your system ID. Set this to 'prod' when done testing

Let's give it a spin. Fire up your php5 CLI. On * nix and Linux it's

    % time php5 ./billin-test.php5

The output for the command execution should look like:

	GET: https://a.billin.pl/prefix???/API/test/v1/rollback/json
	GET: https://a.billin.pl/prefix???/API/test/v1/create(billing_data,fname="Jan",lname="Kowalski",city="Warszawa",post_code="00001",country="Poland",tax_id="1112223344",email="bit-bucket@mailinator.com",phone="+48111222333",company_name="ACME Widgets Inc.")/json
	GET: https://a.billin.pl/prefix???/API/test/v1/search(billing_data,oid=13934)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/elt(@-1,0)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/create(customer,billing_data=@-1)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/get_product_params("CRM Complete - month",parent=@-1)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/search(customer,oid=13938)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/elt(@-1,0)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/create(subscription,parent=@-1)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/search(subscription,oid=13947)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/elt(@-1,0)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/assign_product(@-1,"CRM Complete - month",17=$100,19=1)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/commit/json
	GET: https://a.billin.pl/prefix???/API/test/v1/search(subscription)/json
	Array
	(
	    [0] => Array
		(
		    [0] => 50
		    [1] => 
		    [2] => :new
		)

	...........
	)
	GET: https://a.billin.pl/prefix???/API/test/v1/search(customer,oid=13938)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/elt(@-1,0)/json
	GET: https://a.billin.pl/prefix???/API/test/v1/get_pending_payment(@-1,:payu)/json
	stdClass Object
	(
	    [success] => 1
	    [class_name] => payment
	    [oid] => 13995
	    [id] => fLzDKurvifxZLEdODYBN
	    [status] => :pending
	    [status_descr] => Oczekująca
	    [currency] => PLN
	    [amount] => 135.30
	    [balance] => 0.00
	    [descr] => id= 57
	    [create_dt] => 2012-02-17T11:29:58+01:00
	    [trans_dt] => 2012-02-17T11:29:58+01:00
	    [def] => stdClass Object
		(
		    [class_name] => payment_def
		    [oid] => 1059
		    [id] => payu
		    [name] => PayU
		    [descr] => 
		    [method] => :online_payment
		    [accounting_code] => 
		)

	    [assigned_invoice_id] => 
	    [subscription_oid] => 
	    [subscription_id] => 
	    [customer_oid] => 13938
	    [customer_id] => 57
	    [customer_ext_id] => 
	    [customer_billing_email] => bit-bucket@mailinator.com
	    [customer_billing_fname] => Jan
	    [customer_billing_lname] => Kowalski
	    [customer_billing_company_name] => ACME Widgets Inc.
	)
	php5 test-billin.php5  0.07s user 0.03s system 3% cpu 2.649 total

If the communication is working you can start integrating your subscription
store with Billin!

If there is a connection problem, please ensure that you have a CA certificate for a.billin.pl installed.

Function Reference
----------------------------------------------------------------------------

### Session Object

    new BillinSession($session_id = None);

Creates a new session and invokes login on first API call if the API user is
not logged in already. If `$session_id` is passed, an exisiting session is
resumed. Due to the construction of Billin API it's necessary to start a
separate session for each logged customer application user. Using a single
session object may lead to [Heisenbugs](https://en.wikipedia.org/wiki/Heisenbug).

To authenticate, following variables set in
[config.php5](https://github.com/billin/billin-SDK/blob/master/php5/config.php5)
are used: `$user`, `$password` or `$api_key`. The preferred way to login is to
use `$api_key` instead of the `$password`. It allows to login as the user and
change it's password without blocking application access.

### Transactions

    function BillinSession->commit()

Commits changes made during current transaction.

- - -

    function BillinSession->rollback()

Discards changes made during current transaction.

### Generic utilities

    function BillinSession->call_api($fn, $args = array(), $named_args = array(), $post_args = array(), $return_json = True) 
Invokes any API call named `$fn`. The mandatory arguments for invocation are
`$args`, named arguments are `$named_args` and `$post_args` are used to invoke
POST instead of a GET HTTP method for the API call.  The `$return_json`
argument defaulting to True causes API call result to be formatted as JSON
Returns API call result formatted as JSON or HTML if `$return_json` is False.
Note that you don't have to use this function if the scope of current SDK
version is sufficient.


    function stack_api($fn, $args = array(), $named_args = array(), $post_args = array()) 
Adds function invocation to an API call stack.

    function run_api_stack($return_json = True)
Executes all functions collected in the API call stack and empties the stack.

- - -
    function BillinSession->elt($n, $list = Null) 
Returns element `$n` of `$list`. Lists are 0-indexed. If `$list` is not
specified it defaults to the last API call result - `ref(-1)`.

Example - get the first subscription created in the system:

	$sess->list_customer_subscriptions();
	$sess->elt(0);

- - -
    function ref($n) 
Returns referal to returned value number `$n` in the transaction call history

Examples: `ref(-1)`; `ref(0)`;

- - -
    function amount($string) 

Returns amount object for passing as an argument to API calls. The amount
precision is 2 digits and it must be passed as a string.

Examples: `amount('100')`; `amount('100.22')`; `amount('100.23545')`;
             
- - -
    function keyword($val) 

Returns keyword for use as a name in API calls.
        
Examples: `keyword(payment)`; `keyword(all)`;

### Customer utilities

	function BillinSession->create_billing_data($billing_data_args) 

Creates and returns customer billing data. `$billing_data_args` are specified
in the [create API call documentation](http://billin.pl/upload/billin_1.0/doc/create.html)

Example:

	   $billing_data =
                $sess->create_billing_data(
                   array(fname => 'Jan', 
                     lname => 'Kowalski', 
                     city => 'Warszawa', 
                     post_code => '00001', 
                     country => 'Poland', 
                     tax_id => '1112223344', 
                     email => 'bit-bucket@mailinator.com', 
                     phone => '+48111222333', 
                     company_name => 'ACME Widgets Inc.')
                 );


- - -
	function BillinSession->modify_customer_billing_data($customer = Null, $args) 

Modifies current customer billing data, retaining history of changes.

Example use:

	$sess = new BillinSession();
	$customer = $sess->find_customer(array(id => "32"));
	$sess->modify_customer_billing_data($customer, array(fname => "John"));
	$sess->commit();

- - -
	function BillinSession->create_customer($billing_data = Null, $customer_args = array())

Creates and returns a customer with `$billing_data` specified or
defaulting to the most recently created object. `$customer_args` are
specified in the [create API call documentation](http://billin.pl/upload/billin_1.0/doc/create.html)

Example: 

	$customer = $sess->create_customer($billing_data);
- - -
	function search_customers($params = array())
Finds all customer objects defined by criteria described in the [search API
call documentation](http://billin.pl/upload/billin_1.0/doc/search.html)

Examples:

	$customers = search_customers(array('city' => 'Warsaw'))
Returns all customers from Warsaw

	$customers = search_customers(array('fts' => 'lname:kowal*'))
Performs __f__ull __t__ext __s__earch on all customers and returns customers with the
last name starting with kowal (e.g. Kowalski, Kowalczyk...). The search is case
insensitive.

	function find_customer($params)
Returns the first customer object matching search parameters (equivalent of
elt(0) on the results of `search_customers`).

- - -
    function BillinSession->list_customer_subscriptions($customer = Null, $parameters = array())

Returns a list of all subscriptions in the system. The optional $customer parameter enables narrowing of the subscription list to the $customer's subscriptions. You can specify additional filter expression using the paramters array. For full information refer the [API call documentation](http://billin.pl/upload/billin_1.0/doc/list_data.html).

- - -
    function BillinSession->all_subscriptions_status() 

Returns a list of lists containing subscription id, subscription
external id and subsription status (as a keyword starting with a colon)

### Product utilities

    function BillinSession->get_product_params($id, $customer = Null) 

For a product that is identified by `$id` to be assigned to `$customer`,
returns an object for product configuration using the `configure_product`
method

Example:

	$product = $sess->get_product_params('CRM Complete - month', $customer);

- - -
    function BillinSession->get_product_params_by_oid($oid, $customer = Null) 

For a product that is identified by `$oid` to be assigned to `$customer`,
returns an object for product configuration using the `configure_product`
method

Example:

	$product = $sess->get_product_params('CRM Complete - month', $customer);

- - -
    function BillinSession->configure_product($product, $changes) 
        
Configures product for assignment. `$product` is returned by `get_product_params` invocation. `$changes` is an array mapping product definition tree parameter modifications to product configuration. Tree nodes are separated with the ! character.

        Example:    $sess->configure_product($product, 
                             array('CRM Complete - miesieczny!Abonament CRM Complete!Cena indywidualna' => amount('100'),
                                   'CRM Complete - miesieczny' => 1)
                           );

- - -
    function BillinSession->create_subscription($customer = Null, $subscription_args = array()) 

Creates and returns a subscription object. `$subscription_args` are described in the [create API call documentation](http://billin.pl/upload/billin_1.0/doc/create.html)

Example: 

	$subscription = $sess->create_subscription($customer);

- - -
	function BillinSession->assign_product($product, $subscription_or_customer = Null, $product_args = array()) 

Assigns a configured product to a previously created subscription or customer. When you assign a product to a customer, you must add customer\_assignment=true to \$product\_args. Arguments for product creation are described in the [assign\_product API call documentation](http://billin.pl/upload/billin_1.0/doc/assign\_product.html)

Example:

	$sess->assign_product($product, $subscription);

- - -
	function BillinSession->swap_product($product, $subscription = Null, $swap_args = array())

Replaces a product in subscription with another. `$swap_args` are described in the
[swap\_product API call documentation](http://billin.pl/upload/billin\_1.0/doc/swap\_product.html)

- - -
    function BillinSession->list_products()
        
List all product id's and names as an array of arrays.

### Coupons

    function BillinSession->check_coupon($code, $product = Null)

The function verifies if a coupon code is valid. On success a coupon\_def is returned. On failure null is returned. If the optional $product argument is passed, the function validates if the $code is valid for the supplied product. The $product argument can be one of product name/product oid/product def object.

    function BillinSession->redeem_coupon($customer, $code, $skip_invalid_coupon_error)

Redeem coupon code for a customer. The function causes API error if the coupon code is invalid or unusable (e.g. coupon use count or expiry date were reached). Null value is returned on error if $skip\_invalid\_coupon\_error is set to True.

    function public function create_coupon($params)

Create a discount coupon for specified params. Example:

	$sess->search_products(array(id => 'CRM Basic - year'));
	$sess->create_coupon(array(name => 'kupon', code_length => 20, descr => 'opis',
		limited_number_of_redemptions => 1, expiration_dt => '2012-10-30',
		is_single_use => True, rate => 100,
		rate_type => keyword(percent), rate_currency_id => 'PLN',
		charge_exclusion => keyword(all_onetime_charges), currency_restriction => 'PLN',
		product_restriction => ref(-1)));

### Balance information - invoices and payments

    function BillinSession->list_customer_invoices($customer, $named_args = array())
    
Returns a list of `$customer` invoices. `$named_args` are used to limit the
list and are described in the [list\_data API call documentation](http://billin.pl/upload/billin\_1.0/doc/list\_data.html)

Note that `:initial`, `:normal` and so on indicate keywords. The are created in PHP with keyword('initial') and similar invocations of the `keyword` function.

- - -
    function BillinSession->list_customer_payments($customer = Null) 

Returns all `$customer` payments

- - -
    function BillinSession->list_customer_balance_details($customer = Null) 
        
Returns all `$customer` balance information - invoices and payments

- - -
    function modify_unit_quantity($subscription, $unit, $quantity)

Change the amount of a registered unit for a subscription.

- - -
    function get_payu_pending_payment($customer_or_invoice = Null) 

Returns pending payment information for PayU Internet payment services provider. The data returned allow you to populate an HTML transaction form to invoke online payment with PayU. The important fields of returned object are id and amount. 

- - -
    function get_paylane_pending_payment($customer_or_invoice = Null) 

Returns pending payment information for PayLane Internet payment services provider via PayPal payment gateway. The data returned allow you to populate an HTML transaction form to invoke online payment with PayPal.

- - -
    function get_paylane_paypal_pending_payment($customer_or_invoice = Null) 

Returns pending payment information for PayLane Internet payment services provider. The data returned allow you to populate an HTML transaction form to invoke online payment with PayLane.

### Invoice images

    function BillinSession->get_document_image($path, $invoice = Null, $output_format = Null, $image_type = Null)

Stores an invoice image in HTML or PDF in file `$path`. The `$path` must be writable. `$invoice` is an invoice object, `$output_format` is one of `keyword(html)`, `keyword(pdf)`. `$image_type` is one of `keyword(original)`, `keyword(copy)`.

Example:   

	$sess->list_customer_invoices();
	$sess->elt(0);
	$sess->get_document_image('/tmp/invoice.pdf', ref(-1),
			$output_format=keyword(pdf),
			$image_type=keyword(original));

### Payments

	function authorize_card($customer, $issuer, $ccno, $cvv, $expy, $expm, $name, $email, $ip, $country, $city, 
				$street, $zipcode, $currency, $descr = 'Test authorisation', $amount = '1.00')

Perform card authorization for selected $customer object. 

<table>
<tr> <td> $issuer <td> one of :visa and :mastercard </tr>
<tr> <td> $ccno <td> the credit card number </tr>
<tr> <td> $cvv <td> the Card Verification Value code </tr>
<tr> <td> $expy <td> expiration year </tr>
<tr> <td> $expm <td> expiration month </tr>
<tr> <td> $email <td> customer email </tr>
<tr> <td> $ip <td> customer ip address </tr>
<tr> <td> $country <td> customer country </tr>
<tr> <td> $city <td> customer city </tr>
<tr> <td> $street <td> customer street </tr>
<tr> <td> $zipcode <td> customer postal code </tr>
<tr> <td> $currency <td> transaction currency </tr>
<tr> <td> $descr <td> transaction description - authorisation only </tr>
<tr> <td> $amount <td> transaction amount - authorisation only, the amount will never be collected </tr>
<table>

Hints
----------------------------------------------------------------------------

Massive amounts of data logged to syslog might trip on the rate limiting
mechanism of rsyslogd. To disable rate limitting in rsyslogd on Linux, set
following variables in `/etc/rsyslogd.conf`:

	$IMUXSockRateLimitInterval 0
	$IMUXSockRateLimitBurst 10000
	$SystemLogRateLimitBurst 10000
