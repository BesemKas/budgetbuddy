<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

it('shows month check-in with last month totals on early-month dashboard', function () {
    Carbon::setTestNow(Carbon::create(2026, 1, 5, 10, 0, 0));

    $user = User::factory()->create(['sweep_prompt_dismissed_month' => null]);
    $budget = Budget::bootstrapPersonalForUser($user);

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    $incomeCat = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Income,
    ]);

    $expCat = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $incomeCat->id,
        'amount' => '5000.0000',
        'type' => LedgerEntryType::Income,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => '2025-12-15',
        'description' => null,
    ]);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $expCat->id,
        'amount' => '2000.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $account->currency_code,
        'exchange_rate' => '1',
        'occurred_on' => '2025-12-20',
        'description' => null,
    ]);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->assertSet('showMonthSweepPrompt', true)
        ->assertSet('previousMonthIncome', '5000')
        ->assertSet('previousMonthExpense', '2000')
        ->assertSet('previousMonthNet', '3000.0000')
        ->assertSee(__('Month check-in'), escape: false);

    Carbon::setTestNow();
});
