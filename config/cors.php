<?php
return [

    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5000',
        'http://localhost:5173',
        'http://127.0.0.1:5000',
        'http://127.0.0.1:5000',
        'https://avante-medical.vercel.app/',
        'https://avante-medical.vercel.app',
        'https://avanta-sales.vercel.app/',
        'https://avanta-sales.vercel.app',


    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,
];