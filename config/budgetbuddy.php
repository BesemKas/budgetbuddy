<?php

return [

    'base_currency' => env('BUDGET_BASE_CURRENCY', 'ZAR'),

    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    'currency_codes' => [
        'ZAR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'CHF', 'JPY', 'CNY', 'INR', 'NZD', 'SEK', 'NOK',
    ],

];
