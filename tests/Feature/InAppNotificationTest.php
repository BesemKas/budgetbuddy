<?php

use App\Enums\BudgetRole;
use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\SinkingFundRule;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\LiquidityShortfallNotification;
use App\Services\BudgetInAppNotifier;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
});

it('stores an in-app notification when expense spending exceeds the monthly category budget', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '50000.0000',
        'currency_code' => $budget->base_currency,
    ]);

    $occurred = now();
    $year = (int) $occurred->year;
    $month = (int) $occurred->month;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $year,
        'month' => $month,
        'projected_income' => '50000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $year,
        'month' => $month,
        'amount' => '100.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    $incomeCategory = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Income,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'amount' => '100000.0000',
        'type' => LedgerEntryType::Income,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => $occurred->toDateString(),
        'description' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '150.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => $occurred->toDateString(),
        'description' => null,
    ]);

    expect($user->fresh()->notifications()->count())->toBe(1)
        ->and($user->fresh()->notifications()->first()->data['kind'] ?? null)->toBe('category_over_budget');
});

it('does not notify when spending stays within the monthly category budget', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '50000.0000',
        'currency_code' => $budget->base_currency,
    ]);

    $occurred = now();
    $year = (int) $occurred->year;
    $month = (int) $occurred->month;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $year,
        'month' => $month,
        'projected_income' => '50000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $incomeCategory = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Income,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $year,
        'month' => $month,
        'amount' => '500.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $incomeCategory->id,
        'amount' => '100000.0000',
        'type' => LedgerEntryType::Income,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => $occurred->toDateString(),
        'description' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $category->id,
        'amount' => '100.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => $occurred->toDateString(),
        'description' => null,
    ]);

    expect($user->fresh()->notifications()->count())->toBe(0);
});

it('stores a liquidity in-app notification when total budgeted exceeds liquid balances', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '1000.0000',
        'currency_code' => $budget->base_currency,
    ]);

    $y = 2027;
    $m = 8;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $y,
        'month' => $m,
        'projected_income' => '20000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '5000.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $y, $m);

    expect($user->fresh()->notifications()->where('data->kind', 'liquidity_shortfall')->count())->toBe(1);
});

it('stores a zero-based in-app notification when assigned exceeds projected income', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'balance' => '100000.0000',
        'currency_code' => $budget->base_currency,
    ]);

    $y = 2027;
    $m = 9;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $y,
        'month' => $m,
        'projected_income' => '1000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '5000.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $y, $m);

    expect($user->fresh()->notifications()->where('data->kind', 'zero_based_over')->count())->toBe(1);
});

it('sends budget in-app alerts only to owners, not viewers', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $budget = Budget::factory()->create(['owner_user_id' => $owner->id]);
    $budget->users()->attach($owner->id, ['role' => BudgetRole::Owner->value]);
    $budget->users()->attach($viewer->id, ['role' => BudgetRole::Viewer->value]);

    BankAccount::factory()->create([
        'user_id' => $owner->id,
        'budget_id' => $budget->id,
        'balance' => '1000.0000',
        'currency_code' => $budget->base_currency,
    ]);

    $y = 2028;
    $m = 3;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $y,
        'month' => $m,
        'projected_income' => '20000.0000',
    ]);

    $category = Category::factory()->create([
        'user_id' => $owner->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '5000.0000',
        'bank_account_id' => null,
        'priority' => null,
    ]);

    app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $y, $m);

    expect($owner->fresh()->notifications()->where('data->kind', 'liquidity_shortfall')->count())->toBe(1)
        ->and($viewer->fresh()->notifications()->count())->toBe(0);
});

it('marks matching budget month notifications read when the budget planner loads that month', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    $y = (int) now()->year;
    $m = (int) now()->month;

    $user->notify(new LiquidityShortfallNotification(
        $budget,
        $y,
        $m,
        '1000',
        '5000',
        '4000',
    ));

    expect($user->fresh()->unreadNotifications()->count())->toBe(1);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.budget-planner')
        ->assertSet('year', $y)
        ->assertSet('month', $m);

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});

it('applies sinking fund rules once per month and bumps the category month plan', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    SinkingFundRule::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'monthly_amount' => '42.50',
        'is_active' => true,
    ]);

    $this->artisan('budget:apply-sinking-fund-rules')->assertSuccessful();

    $y = (int) now()->year;
    $m = (int) now()->month;

    $line = CategoryMonthBudget::query()
        ->where('budget_id', $budget->id)
        ->where('category_id', $category->id)
        ->where('year', $y)
        ->where('month', $m)
        ->first();

    expect($line)->not->toBeNull()
        ->and((string) $line->amount)->toBe('42.5000');

    $this->artisan('budget:apply-sinking-fund-rules')->assertSuccessful();

    $line->refresh();

    expect((string) $line->amount)->toBe('42.5000');
});
