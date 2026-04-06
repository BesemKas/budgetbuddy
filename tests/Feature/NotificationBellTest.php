<?php

use App\Models\Budget;
use App\Models\User;
use Livewire\Livewire;

it('polls notification state for the current budget without errors', function () {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);
    $budget = $user->budgets()->firstOrFail();

    session(['current_budget_id' => $budget->id]);

    Livewire::actingAs($user)
        ->test('notification-bell')
        ->call('pollForNewNotifications')
        ->assertHasNoErrors();
});
