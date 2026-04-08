<?php

use App\Enums\LedgerEntryType;
use App\Enums\SmartMode;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\User;
use Livewire\Livewire;

it('shows this month heading and plan hero for a budget member', function (): void {
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
        ->assertSee(__('This month'), escape: false)
        ->assertSee(__('Plan vs actual (this month)'), escape: false)
        ->assertSee(__('You have not set this month’s category amounts yet.'), escape: false)
        ->assertSee(__('Budget planner'), escape: false);
});

it('shows on-track plan status when spend is within the near-limit threshold', function (): void {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $y = (int) now()->year;
    $m = (int) now()->month;

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '10000.0000',
    ]);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->assertSee(__('On track'), escape: false);
});

it('shows zero-based income warning when assigned exceeds projected income', function (): void {
    $user = User::factory()->create(['smart_mode' => SmartMode::ZeroBased]);
    $budget = Budget::bootstrapPersonalForUser($user);

    BankAccount::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'currency_code' => $budget->base_currency,
    ]);

    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $y = (int) now()->year;
    $m = (int) now()->month;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $y,
        'month' => $m,
        'projected_income' => '1000.0000',
    ]);

    CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => $y,
        'month' => $m,
        'amount' => '5000.0000',
    ]);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->assertSee(__('Assigned amounts exceed projected income by'), escape: false);
});

it('includes stacked layouts for small screens on category and transaction sections', function (): void {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('pages.dashboard')
        ->assertSee('space-y-2 md:hidden', escape: false)
        ->assertSee('space-y-3 md:hidden', escape: false);
});
