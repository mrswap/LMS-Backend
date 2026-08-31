<?php

return [

    'paths' => ['api/*', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5000',
        'http://localhost:5173',
        'https://avanta-sales.vercel.app',
        'https://avante-medical.vercel.app',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

    'allowed_origins' => ['*'],
];
