<?php

use App\Models\SinkingFundRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('estimates months to reach target from monthly amount', function () {
    $rule = new SinkingFundRule([
        'monthly_amount' => '100.00',
        'target_amount' => '1200.00',
    ]);

    expect($rule->estimatedMonthsAtMonthlyRate())->toBe(12);
});

it('returns null when target or monthly is missing or zero', function () {
    $rule = new SinkingFundRule([
        'monthly_amount' => '100.00',
        'target_amount' => null,
    ]);

    expect($rule->estimatedMonthsAtMonthlyRate())->toBeNull();
});
