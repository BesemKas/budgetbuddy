<?php

use App\Models\Budget;
use App\Models\User;

it('shows the activity page for budget members', function () {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user)
        ->get(route('budget.activity'))
        ->assertSuccessful()
        ->assertSee(__('Activity'), escape: false);
});
