<?php

use App\Models\Budget;
use App\Models\User;
use Livewire\Livewire;

it('saves payday settings', function (): void {
    $user = User::factory()->create(['payday_day' => null]);
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user);

    Livewire::test('pages.settings')
        ->set('payday_day', 25)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->payday_day)->toBe(25);
});
