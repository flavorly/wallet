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
    ]
];
