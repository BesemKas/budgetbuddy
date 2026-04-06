<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;

it('recalculates bank account balance when transactions change', function () {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create(['base_currency' => 'ZAR']);
    $account = BankAccount::factory()->create([
        'user_id' => $user->id,
        'currency_code' => 'ZAR',
        'exchange_rate' => null,
    ]);
    $account->refresh();

    $expenseCat = Category::query()->where('type', LedgerEntryType::Expense)->firstOrFail();
    $incomeCat = Category::query()->where('type', LedgerEntryType::Income)->firstOrFail();

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $account->budget_id,
        'bank_account_id' => $account->id,
        'category_id' => $expenseCat->id,
        'amount' => '100.0000',
        'type' => LedgerEntryType::Expense,
        'currency_code' => 'ZAR',
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
        'description' => null,
    ]);

    $account->refresh();
    expect((float) $account->balance)->toBe(-100.0);

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $account->budget_id,
        'bank_account_id' => $account->id,
        'category_id' => $incomeCat->id,
        'amount' => '250.0000',
        'type' => LedgerEntryType::Income,
        'currency_code' => 'ZAR',
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
        'description' => null,
    ]);

    $account->refresh();
    expect((float) $account->balance)->toBe(150.0);
});

it('allows authenticated users to open the accounts page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('accounts.index'))
        ->assertSuccessful();
});
