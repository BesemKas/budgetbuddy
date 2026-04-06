<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetAccountAccess;
use App\Services\LedgerCurrencyService;
use Database\Seeders\CategorySeeder;

it('excludes accounts from reporting totals when include_in_budget_reports is false', function (): void {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $tracked = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
        'name' => 'Cheque',
        'include_in_budget_reports' => true,
    ]);

    $untracked = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
        'name' => 'Savings',
        'include_in_budget_reports' => false,
    ]);

    $groceries = Category::query()->where('name', 'Groceries')->whereNull('user_id')->firstOrFail();

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $tracked->id,
        'category_id' => $groceries->id,
        'amount' => '50.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $budget->base_currency,
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
        'description' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $untracked->id,
        'category_id' => $groceries->id,
        'amount' => '200.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $budget->base_currency,
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
        'description' => null,
    ]);

    session(['current_budget_id' => $budget->id]);

    $reporting = app(BudgetAccountAccess::class)->reportingBankAccountIds($user, $budget);
    expect($reporting)->toBe([(int) $tracked->id]);

    $totals = app(LedgerCurrencyService::class)->currentMonthTotals($budget, $reporting);
    expect((float) $totals['expense'])->toBe(50.0);
});
