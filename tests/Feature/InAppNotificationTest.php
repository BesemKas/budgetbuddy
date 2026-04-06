<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\Transaction;
use App\Models\User;

it('stores an in-app notification when expense spending exceeds the monthly category budget', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $occurred = now();
    $year = (int) $occurred->year;
    $month = (int) $occurred->month;

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $year,
        'month' => $month,
        'amount' => '100.0000',
        'bank_account_id' => null,
        'priority' => null,
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
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $occurred = now();

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => (int) $occurred->year,
        'month' => (int) $occurred->month,
        'amount' => '500.0000',
        'bank_account_id' => null,
        'priority' => null,
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
