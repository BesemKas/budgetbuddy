<?php

use App\Enums\BankAccountKind;
use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetRealityCheckService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('flags when total budgeted exceeds liquid balances in base currency', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '10000',
        'kind' => BankAccountKind::Liquid,
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 6,
        'amount' => '15000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    $r = app(BudgetRealityCheckService::class)->liquidityAssessment($budget, 2026, 6);

    expect($r['is_funded'])->toBeFalse()
        ->and($r['shortfall_base'])->toBe('5000.0000');
});

it('ignores credit card balances in liquid total', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '99999',
        'kind' => BankAccountKind::Credit,
        'currency_code' => $budget->base_currency,
    ]);

    $r = app(BudgetRealityCheckService::class)->liquidityAssessment($budget, 2026, 1);

    expect($r['total_liquid_base'])->toBe('0')
        ->and($r['is_funded'])->toBeTrue();
});

it('detects category lines linked to credit accounts', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    $credit = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'kind' => BankAccountKind::Credit,
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 3,
        'amount' => '100',
        'bank_account_id' => $credit->id,
        'priority' => null,
    ]);

    $ids = app(BudgetRealityCheckService::class)->categoryIdsLinkedToCreditAccounts($budget, 2026, 3);

    expect($ids)->toBe([(int) $category->id]);
});

it('computes category spend pace vs even daily spread', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $y = 2026;
    $m = 5;

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '3100.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    Carbon::setTestNow(Carbon::create(2026, 5, 10, 12, 0, 0));

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '1500.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => '2026-05-05',
        'description' => null,
    ]);

    $pace = app(BudgetRealityCheckService::class)->categorySpendPace($budget, $category->id, $y, $m);

    expect($pace)->not->toBeNull()
        ->and($pace['days_elapsed'])->toBe(10)
        ->and($pace['is_over_pace'])->toBeTrue();

    Carbon::setTestNow();
});

it('compares assigned totals to projected income for zero-based', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => 2026,
        'month' => 4,
        'projected_income' => '10000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 4,
        'amount' => '12000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    $z = app(BudgetRealityCheckService::class)->zeroBasedAssessment($budget, 2026, 4);

    expect($z['is_within_income'])->toBeFalse()
        ->and($z['overage_base'])->toBe('2000.0000');
});

it('lists expense categories with a plan but no linked account as unassigned funding', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $withLink = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $noLink = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $withLink->id,
        'year' => 2026,
        'month' => 7,
        'amount' => '100.0000',
        'bank_account_id' => $account->id,
        'priority' => null,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $noLink->id,
        'year' => 2026,
        'month' => 7,
        'amount' => '50.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    $ids = app(BudgetRealityCheckService::class)->unassignedFundingCategoryIds($budget, 2026, 7);

    expect($ids)->toBe([(int) $noLink->id]);
});
