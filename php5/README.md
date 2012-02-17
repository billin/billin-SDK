This is a draft of a manual for Billin PHP5 SDK - version 0.1
================================================================================

To get you started quickly have a look at a simple example contained in the
test-billin.php5 file. Each SDK method used in there is commented to let you
know what is happening in every step.

Step 1. Configure your Billin API
--------------------------------------------------------------------------------

Make sure you are using PHP5 with the cURL extension installed. When cURL is not
present, the SDK will scream about it.

Open [config.php5](config.php5) in your favourite text editor and set following variables:

* `$user` - your Billin application user name
* `$password` - your Billin application user password - deprecated in favor of `$api_key`
* `$api_key` - your Billin application user key string Create a user in the GUI, generate the key and set `$api_key` to it
* `$debug` - set to false when done testing the SDK
* `$cookie_jar` - set to a full path of a writable cookie store file

Step 2. Test your Billin API communication
--------------------------------------------------------------------------------

Our demo server is OK for testing. Please setup the config.ph5 file for use on Billin demo. 

Relevant server configuration parameters in config.php5 (besides `$user`,
`$api_key` and `$password`) are:

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

Function Reference
----------------------------------------------------------------------------

### Session Object

    new BillinSession()

Creates a new session and invokes login on first API call if the API
user is not logged in already.

To login following variables set in [config.php5](config.php5) are used: `$user`, `$password` or `$api_key`. The preferred way to login is to use `$api_key` instead of the `$password`. It allows to login as the user and change it's password without blocking application access.

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

- - -
    function BillinSession->elt($n, $list = Null) 
Returns element `$n` of `$list`. Lists are 0-indexed. If `$list` is not
specified it defaults to the last API call result - `ref(-1)`.

Example - get the first subscription created in the system:

	$sess->list_customer_subscriptions()
	$sess->elt(0)

- - -
    function ref($n) 
Returns referal to returned value number `$n` in the transaction call history

Examples: `ref(-1)`; `ref(0)`

- - -
    function amount($string) 

Returns amount object for passing as an argument to API calls. The amount
precision is 2 digits and it must be passed as a string.

Examples: `amount('100')`; `amount('100.22')`; `amount('100.23545')`
             
- - -
    function keyword($val) 

Returns keyword for use as a name in API calls.
        
Examples: `keyword(payment)`; `keyword(all)`

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
	function BillinSession->create_customer($billing_data = Null, $customer_args = array())

Creates and returns a customer with `$billing_data` specified or
defaulting to the most recently created object. `$customer_args` are
specified in the [create API call documentation](http://billin.pl/upload/billin_1.0/doc/create.html)

Example: 

	$customer = $sess->create_customer($billing_data);

- - -
    function BillinSession->list_customer_subscriptions()

Returns a list of all subscriptions in the system

- - -
    function BillinSession->all_subscriptions_status() 

Returns a list of lists containing subscription id, subscription
external id and subsription status (as a keyword starting with a colon)

### Product utilities

    function BillinSession->get_product_params($id, $customer = Null) 

For a product that is identified by `$id` and will be assigned to `$customer`,
returns an object for product configuration using the `configure_product`
method

Example:

	$product = $sess->get_product_params('CRM Complete - month', $customer)

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
	function BillinSession->assign_product($product, $subscription = Null, $product_args = array()) 

Assigns a configured product to a previously created `$subscription`. Arguments for product creation are described in the [assign\_product API call documentation](http://billin.pl/upload/billin_1.0/doc/assign\_product.html)

Example:

	$sess->assign_product($product, $subscription);

- - -
	function BillinSession->swap_product($product, $subscription = Null, $swap_args = array())

Replaces a product in subscription with another. `$swap_args` are described in the
[swap\_product API call documentation](http://billin.pl/upload/billin\_1.0/doc/swap\_product.html)

- - -
    function BillinSession->list_products()
        
List all product id's and names as an array of arrays.

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
    function get_payu_pending_payment($customer = Null) 

Returns pending payment information for PayU Internet payment services provider. The data returned allow you to populate an HTML transaction form to invoke online payment with PayU. The important fields of returned object are id and amount. 

### Invoice images

    function BillinSession->get_document_image($path, $invoice = Null, $output_format = Null, $image_type = Null)

Stores an invoice image in HTML or PDF in file `$path`. The `$path` must be writable. `$invoice` is an invoice object, `$output_format` is one of `keyword(html)`, `keyword(pdf)`. `$image_type` is one of `keyword(original)`, `keyword(copy)`.

Example:   

	$sess->list_customer_invoices();
	$sess->elt(0);
	$sess->get_document_image('/tmp/invoice.pdf', ref(-1),
			$output_format=keyword(pdf),
			$image_type=keyword(original));

