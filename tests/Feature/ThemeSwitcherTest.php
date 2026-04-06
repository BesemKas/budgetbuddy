<?php

use App\Models\Budget;
use App\Models\User;

it('includes the theme switcher on the login page', function (): void {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('bb-theme-controller', escape: false)
        ->assertSee('theme-controller', escape: false);
});

it('includes the theme switcher on the dashboard', function (): void {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('bb-theme-controller', escape: false)
        ->assertSee('theme-controller', escape: false);
});
