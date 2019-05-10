<?php

return [

    /**
     * minates
     */
    'ttl' => 7 * 24 * 60,

    'store_drivers' => [
        'database' => [
            'model' => 'Viviniko\Cart\Models\Cart'
        ],
        'redis' => [
            'conn' => 'cart',
        ],
        'cookie' => [
        ],
    ],

    'authed_store' => [
        'driver' => 'redis',
    ],

    'default_store' => [
        'driver' => 'cookie',
    ],

    'table' => 'carts',

];