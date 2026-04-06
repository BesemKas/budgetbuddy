<?php

use App\Enums\BudgetRole;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\User;
use Livewire\Livewire;

it('shows the budget planner for budget members', function () {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user)
        ->get(route('budget.planner'))
        ->assertSuccessful()
        ->assertSee(__('Budget planner'), escape: false);
});

it('saves projected income for the budget owner', function () {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);
    $budget = $user->budgets()->first();

    Livewire::actingAs($user)
        ->test('pages.budget-planner')
        ->set('projectedIncome', '42000.50')
        ->call('saveProjectedIncome')
        ->assertHasNoErrors();

    expect(BudgetMonthSummary::query()->where('budget_id', $budget->id)->first()?->projected_income)->toBe('42000.5000');
});

it('forbids viewers from saving projected income', function () {
    $owner = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($owner);

    $viewer = User::factory()->create();
    $budget->users()->attach($viewer->id, ['role' => BudgetRole::Viewer->value]);

    Livewire::actingAs($viewer)
        ->test('pages.budget-planner')
        ->set('projectedIncome', '10000')
        ->call('saveProjectedIncome')
        ->assertForbidden();
});

it('copies the previous month into the current month', function () {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);
    $year = (int) now()->year;
    $month = (int) now()->month;

    $prev = now()->subMonth();
    $py = (int) $prev->year;
    $pm = (int) $prev->month;

    BudgetMonthSummary::query()->create([
        'budget_id' => $budget->id,
        'year' => $py,
        'month' => $pm,
        'projected_income' => '30000.0000',
    ]);

    Livewire::actingAs($user)
        ->test('pages.budget-planner')
        ->set('year', $year)
        ->set('month', $month)
        ->call('copyPreviousMonth');

    $summary = BudgetMonthSummary::query()
        ->where('budget_id', $budget->id)
        ->where('year', $year)
        ->where('month', $month)
        ->first();

    expect($summary?->projected_income)->toBe('30000.0000');
});
