<?php

return [

    'base_currency' => env('BUDGET_BASE_CURRENCY', 'ZAR'),

    'otp_ttl_minutes' => (int) env('OTP_TTL_MINUTES', 10),

    'invitation_ttl_days' => (int) env('BUDGET_INVITATION_TTL_DAYS', 7),

    'dashboard_chart_months' => max(3, min(24, (int) env('BUDGET_DASHBOARD_CHART_MONTHS', 6))),

    /** When planned expense usage reaches this % of plan, dashboard shows “near limit”. */
    'dashboard_plan_near_limit_percent' => max(50, min(99, (int) env('BUDGETBUDDY_DASHBOARD_NEAR_LIMIT', 85))),

    'rolling_average_months' => [
        'short' => 3,
        'long' => 6,
    ],

    'snapshot_trend_months' => max(3, min(36, (int) env('BUDGET_SNAPSHOT_TREND_MONTHS', 24))),

    /** In Survival mode, expenses above this amount (account currency) require a longer note. */
    'survival_expense_note_threshold' => max(0.0, (float) env('BUDGET_SURVIVAL_EXPENSE_NOTE_THRESHOLD', 200)),

    'currency_codes' => [
        'ZAR', 'USD', 'EUR', 'GBP', 'AUD', 'CAD', 'CHF', 'JPY', 'CNY', 'INR', 'NZD', 'SEK', 'NOK',
    ],

];
