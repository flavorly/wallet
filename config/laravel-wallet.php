<?php

return [
    'decimal_places' => 10,
    'currency' => 'USD',

    // This should be name of the database columns for your model/models
    // Where it saves the current configuration
    'columns' => [
        'balance' => 'wallet_balance',
        'decimals' => 'wallet_decimal_places',
        'currency' => 'wallet_currency',
        'credit' => 'wallet_credit',
    ],

    // This should be the name of the database tables where you want to use the wallet
    // It will run the migrations on these tables
    'tables' => [
        'users',
    ],
];
