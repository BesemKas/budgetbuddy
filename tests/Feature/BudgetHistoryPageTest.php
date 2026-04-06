<?php

use App\Models\Budget;
use App\Models\BudgetSnapshot;
use App\Models\User;

it('shows budget history for the current budget', function (): void {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    BudgetSnapshot::query()->create([
        'budget_id' => $budget->id,
        'period' => '2026-03',
        'payload' => [
            'income' => '100.0000',
            'expense' => '40.0000',
            'net' => '60.0000',
            'base_currency' => $budget->base_currency,
        ],
    ]);

    $this->actingAs($user);

    $this->get(route('budget.history'))
        ->assertSuccessful()
        ->assertSee('2026-03')
        ->assertSee('100.00');
});
