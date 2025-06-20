<?php

return [
    /*
    |--------------------------------------------------------------------------
    | PayTabs Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for PayTabs payment gateway
    |
    */

    'environment' => env('PAYTABS_ENVIRONMENT', 'sandbox'),
    
    'profile_id' => env('PAYTABS_PROFILE_ID'),
    
    'server_key' => env('PAYTABS_SERVER_KEY'),
    
    'client_key' => env('PAYTABS_CLIENT_KEY'),
    
    'base_url' => env('PAYTABS_BASE_URL', 'https://secure.paytabs.sa'),
    
    'currency' => env('PAYTABS_CURRENCY', 'SAR'),
    
    'callback_url' => env('APP_URL') . '/paytabs/callback',
    
    'return_url' => env('APP_URL') . '/paytabs/success',
    
    'cancel_url' => env('APP_URL') . '/paytabs/cancel',
    
    'timeout' => 30,
    
    'verify_ssl' => env('PAYTABS_ENVIRONMENT', 'sandbox') === 'production',
];