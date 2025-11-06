<?php

/**
 * Configuration de test pour les environnements Sandbox
 * À utiliser dans config/aida-gateway.php pour les tests
 */

return [
    'default' => 'wave',
    
    'gateways' => [
        'wave' => [
            'enabled' => true,
            'api_url' => 'https://api.sandbox.wave.com', // URL Sandbox
            'api_key' => 'test_pk_sandbox_xxxxxxxxxxxx',
            'api_secret' => 'test_sk_sandbox_xxxxxxxxxxxx',
            'currency' => 'XOF',
            'environment' => 'sandbox', // Mode test
        ],
        
        'orange_money' => [
            'enabled' => true,
            'api_url' => 'https://api.sandbox.orange.sn', // URL Sandbox
            'merchant_key' => 'sandbox_merchant_key',
            'api_username' => 'sandbox_user',
            'api_password' => 'sandbox_pass',
            'currency' => 'XOF',
            'country_code' => 'SN',
            'environment' => 'sandbox',
        ],
        
        'free_money' => [
            'enabled' => true,
            'api_url' => 'https://api.sandbox.free.sn',
            'merchant_id' => 'sandbox_merchant',
            'api_key' => 'sandbox_key',
            'api_secret' => 'sandbox_secret',
            'currency' => 'XOF',
            'environment' => 'sandbox',
        ],
    ],
    
    // Webhooks de test
    'webhook' => [
        'route_prefix' => 'aida/webhooks',
        'middleware' => ['web'], // Pas de vérification CSRF en test
    ],
    
    // Logging activé pour debug
    'logging' => [
        'enabled' => true,
        'channel' => 'daily',
        'level' => 'debug',
    ],
];

// Numéros de test (fournis par les opérateurs) :
// Wave : +221771234567, +221701234567
// Orange Money : +221771234568, +221701234568  
// Free Money : +221701234569, +221771234569