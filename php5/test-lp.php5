#!/usr/bin/env php

<?php
require 'billin.php5';

$sess = new BillinSession();
print_r($sess->list_products());
?>
