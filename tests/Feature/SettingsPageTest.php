<?php

use App\Enums\SmartMode;
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

it('saves smart mode', function (): void {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user);

    Livewire::test('pages.settings')
        ->set('smart_mode', SmartMode::ZeroBased->value)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->fresh()->smart_mode)->toBe(SmartMode::ZeroBased);
});
