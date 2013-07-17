<?php
###############################################################
### Billin PHP5 SDK configuration
###############################################################

## !!! SET $secure TO True IN PRODUCTION !!!
$secure = False;

# system credentials
$user = 'CRM';
$password = 'CRM-api-123';
$api_key = Null;

# end-point configuration
$server = 'https://a.billin.pl';
$prefix = 'PLEASE_SET_THE_PREFIX';
$system = 'prod';
$api_version = 'v1';

# PCP configuration
$pcp = 'https://localhost:9080/';
## !!! CHANGE THIS IN PRODUCTION !!!
$pcp_user = 'test';
$pcp_pass = 'test';

# logging
$debug = True;
$console_log = True;
$log_facility = LOG_LOCAL0;
$log_process = "billin";
?>
