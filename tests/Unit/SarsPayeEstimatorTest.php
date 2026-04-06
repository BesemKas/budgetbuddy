<?php

use App\Services\SarsPayeEstimator;
use Tests\TestCase;

uses(TestCase::class);

it('estimates paye uif and net for a mid bracket monthly gross', function (): void {
    $est = app(SarsPayeEstimator::class)->estimateMonthly(20_000.0, false, false);

    expect($est['monthly_paye'])->toBe(2163.75)
        ->and($est['monthly_uif_employee'])->toBe(177.12)
        ->and($est['net_monthly'])->toBe(17659.13);
});

it('adds secondary and tertiary rebates for older age bands', function (): void {
    $under65 = app(SarsPayeEstimator::class)->estimateMonthly(10_000.0, false, false);
    $age65 = app(SarsPayeEstimator::class)->estimateMonthly(10_000.0, true, false);
    $age75 = app(SarsPayeEstimator::class)->estimateMonthly(10_000.0, true, true);

    expect($age65['rebate'])->toBeGreaterThan($under65['rebate'])
        ->and($age75['rebate'])->toBeGreaterThan($age65['rebate']);
});
