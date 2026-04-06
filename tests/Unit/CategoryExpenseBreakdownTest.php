<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BudgetAnalyticsService;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('groups current month expenses by category in base currency', function (): void {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $groceries = Category::query()->where('name', 'Groceries')->whereNull('user_id')->firstOrFail();

    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    Transaction::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $account->id,
        'category_id' => $groceries->id,
        'amount' => '100.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => $budget->base_currency,
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
    ]);

    $rows = app(BudgetAnalyticsService::class)->currentMonthExpenseByCategory($budget, [$account->id]);

    expect($rows)->not->toBeEmpty()
        ->and($rows[0]['name'])->toBe('Groceries')
        ->and((float) $rows[0]['total'])->toBe(100.0);
});
