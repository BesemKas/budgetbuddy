<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Livewire\Livewire;

it('moves funds between two same-currency accounts', function (): void {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $from = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
        'name' => 'Cheque',
    ]);

    $to = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
        'name' => 'Savings',
    ]);

    $salary = Category::query()->where('name', 'Salary')->whereNull('user_id')->firstOrFail();

    Transaction::query()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'bank_account_id' => $from->id,
        'category_id' => $salary->id,
        'amount' => '1000.0000',
        'type' => LedgerEntryType::Income,
        'currency_code' => $budget->base_currency,
        'exchange_rate' => '1',
        'occurred_on' => now()->toDateString(),
        'description' => null,
    ]);

    $from->refresh();
    expect((float) $from->balance)->toBe(1000.0);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('accounts.index')
        ->call('openMoveFunds')
        ->set('transfer_from_bank_account_id', $from->id)
        ->set('transfer_to_bank_account_id', $to->id)
        ->set('transfer_amount', '100')
        ->set('transfer_occurred_on', now()->toDateString())
        ->call('saveMoveFunds')
        ->assertHasNoErrors();

    $from->refresh();
    $to->refresh();

    expect((float) $from->balance)->toBe(900.0)
        ->and((float) $to->balance)->toBe(100.0);
});

it('rejects transfers between different currencies', function (): void {
    $this->seed(CategorySeeder::class);

    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    $from = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => 'ZAR',
    ]);

    $to = BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => 'USD',
        'exchange_rate' => '18.5',
    ]);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('accounts.index')
        ->call('openMoveFunds')
        ->set('transfer_from_bank_account_id', $from->id)
        ->set('transfer_to_bank_account_id', $to->id)
        ->set('transfer_amount', '50')
        ->set('transfer_occurred_on', now()->toDateString())
        ->call('saveMoveFunds')
        ->assertHasErrors('transfer_amount');
});
