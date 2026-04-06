<?php

use App\Enums\BudgetPriority;
use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\User;
use Illuminate\Database\QueryException;

it('stores monthly summary and category budget lines scoped to a budget', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    $category = Category::factory()->create([
        'user_id' => $user->id,
        'budget_id' => $budget->id,
        'type' => LedgerEntryType::Expense,
    ]);

    $summary = BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => 2026,
        'month' => 4,
        'projected_income' => '25000.0000',
    ]);

    $line = CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 4,
        'amount' => '1500.0000',
        'priority' => BudgetPriority::Needs,
    ]);

    expect($summary->budget_id)->toBe($budget->id)
        ->and($line->budget->is($budget))->toBeTrue()
        ->and($line->priority)->toBe(BudgetPriority::Needs);

    expect(fn () => CategoryMonthBudget::query()->create([
        'budget_id' => $budget->id,
        'category_id' => $category->id,
        'year' => 2026,
        'month' => 4,
        'amount' => '1.0000',
    ]))->toThrow(QueryException::class);
});

it('exposes month summary and category lines on the budget model', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BudgetMonthSummary::factory()->create([
        'budget_id' => $budget->id,
        'year' => 2026,
        'month' => 3,
        'projected_income' => '30000.0000',
    ]);

    expect($budget->fresh()->monthSummaries)->toHaveCount(1);
});
