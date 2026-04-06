<?php

use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\User;
use App\Services\BudgetAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('computes daily runway from cash and payday', function (): void {
    $user = User::factory()->create(['payday_day' => 25]);
    $budget = Budget::bootstrapPersonalForUser($user);
    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '1000',
        'currency_code' => $budget->base_currency,
    ]);

    $analytics = app(BudgetAnalyticsService::class);
    $runway = $analytics->dailyRunway($budget, [$account->id], $user);

    expect((float) $runway)->toBeGreaterThan(0);
});
