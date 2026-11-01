<?php

return [
    'url'         => env('ERPNEXT_URL', ''),
    'api_key'     => env('ERPNEXT_API_KEY', ''),
    'api_secret'  => env('ERPNEXT_API_SECRET', ''),
    'company'     => env('ERPNEXT_COMPANY', ''),
    'pos_profile' => env('ERPNEXT_POS_PROFILE', ''),
    'auto_submit' => true,
    'timeout'     => 30,
    'verify_ssl'  => false,
    'default_customer' => 'Walk-in Customer',
    'default_uom'      => 'Nos',
];