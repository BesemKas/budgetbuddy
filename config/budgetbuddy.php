<?php

return [

    'base_currency' => env('BUDGET_BASE_CURRENCY', 'ZAR'),

    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    'invitation_ttl_days' => (int) env('BUDGET_INVITATION_TTL_DAYS', 7),

    'dashboard_chart_months' => max(3, min(24, (int) env('BUDGET_DASHBOARD_CHART_MONTHS', 6))),

    'rolling_average_months' => [
        'short' => 3,
        'long' => 6,
    ],

    'snapshot_trend_months' => max(3, min(36, (int) env('BUDGET_SNAPSHOT_TREND_MONTHS', 24))),

    'currency_codes' => [
        'ZAR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'CHF', 'JPY', 'CNY', 'INR', 'NZD', 'SEK', 'NOK',
    ],

];
