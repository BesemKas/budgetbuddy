<?php

use App\Models\Budget;
use App\Models\User;

it('shows the tax calculator for authenticated users', function (): void {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user)
        ->get(route('tools.tax'))
        ->assertOk()
        ->assertSee(__('Tax calculator'), false);
});
