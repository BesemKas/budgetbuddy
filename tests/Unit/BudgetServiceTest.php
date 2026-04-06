<?php

use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetRealityCheckService;
use App\Services\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('delegates checkBudgetLiquidity to the reality check service', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $viaFacade = app(BudgetService::class)->checkBudgetLiquidity($budget, 2026, 8);
    $direct = app(BudgetRealityCheckService::class)->liquidityAssessment($budget, 2026, 8);

    expect($viaFacade)->toEqual($direct);
});

it('delegates getVelocity to category spend pace', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $viaFacade = app(BudgetService::class)->getVelocity($budget, 999, 2026, 8);
    $direct = app(BudgetRealityCheckService::class)->categorySpendPace($budget, 999, 2026, 8);

    expect($viaFacade)->toBe($direct);
});
