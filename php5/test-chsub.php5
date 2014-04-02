#!/usr/bin/env php

<?php
require 'billin.php5';

## session initiation
$sess = new BillinSession();

## billing data creation
$billing_data = $sess->create_billing_data(
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

## customer creation
$customer = $sess->create_customer($billing_data);

## product retrieval by product_id, $customer was assigned before
$product = $sess->get_product_params('CRM Complete - month', ref(-1));

## product configuration
## ! is used as a product parameter tree separator - it reflects product
## configuration in the GUI
$sess->configure_product($product, 
         array('CRM Complete - miesieczny!Abonament CRM Complete!Cena indywidualna' => amount('100'),
               'CRM Complete - miesieczny' => 1)
       );

## to assign a product we need a subscription object, let's create it
$subscription = $sess->create_subscription($customer);

$sub_id = $subscription->id;

## asignment of a configured product - before product assignment a subscription object is created
$sess->assign_product($product, $subscription);

## Billin API is transactional - a commit is mandatory
$sess->commit();

$sub = $sess->search_subscriptions(array(id => $sub_id))[0];
$sess->modify_subscription($sub, array(ext_id => "test"));
$sess->commit();
$sub = $sess->search_subscriptions(array(id => $sub_id))[0];
$sess->cancel_subscription($sub, "2013-12-10T21:57:01");
$sess->commit();
?>
