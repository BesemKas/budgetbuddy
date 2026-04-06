<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Livewire\Livewire;

it('opens quick add and includes modal-box so daisyui shows the panel', function (): void {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->call('openQuickAdd')
        ->assertSet('showQuickAdd', true)
        ->assertSee('modal-box bb-modal-box', escape: false);
});

it('saves a quick transaction and closes the modal', function (): void {
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

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->call('openQuickAdd')
        ->set('quick_bank_account_id', $account->id)
        ->set('quick_type', 'expense')
        ->set('quick_category_id', $category->id)
        ->set('quick_amount', '42.50')
        ->set('quick_occurred_on', now()->toDateString())
        ->call('saveQuickTransaction')
        ->assertHasNoErrors()
        ->assertSet('showQuickAdd', false);

    expect(Transaction::query()->where('budget_id', $budget->id)->where('amount', '42.5000')->exists())->toBeTrue();
});
