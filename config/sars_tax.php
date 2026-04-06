<?php

/**
 * SARS individual income tax — indicative figures for PAYE-style estimates.
 * Update brackets and rebates when National Treasury / SARS publishes annual changes.
 *
 * @see https://www.sars.gov.za/tax-rates/income-tax/rates-of-tax-for-individuals/
 */
return [

    'tax_year_label' => env('SARS_TAX_YEAR_LABEL', '2026 (1 Mar 2025 – 28 Feb 2026)'),

    'rebates' => [
        'primary_under_65' => 17_235,
        'secondary_65_and_older' => 9_444,
        'tertiary_75_and_older' => 3_145,
    ],

    /** Employee UIF: 1% of remuneration, subject to monthly ceiling (ZAR). */
    'uif' => [
        'employee_rate' => 0.01,
        'monthly_ceiling' => 17_712.00,
    ],

];
