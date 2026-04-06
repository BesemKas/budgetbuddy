<?php

use App\Enums\BudgetRole;
use App\Models\Budget;
use App\Models\User;
use App\Services\AccountDeletionService;
use Livewire\Livewire;

it('blocks deletion when a budget has more than one member', function (): void {
    $owner = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($owner);
    $viewer = User::factory()->create();
    $budget->users()->attach($viewer->id, ['role' => BudgetRole::Viewer->value]);

    $svc = app(AccountDeletionService::class);

    expect($svc->blockingReason($owner))->not->toBeNull();
    expect($svc->blockingReason($viewer))->not->toBeNull();
});

it('deletes a sole member user and their budget', function (): void {
    $user = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($user);

    app(AccountDeletionService::class)->deleteAccount($user);

    expect(User::query()->find($user->id))->toBeNull()
        ->and(Budget::query()->find($budget->id))->toBeNull();
});

it('deletes account via settings when sole member', function (): void {
    $user = User::factory()->create();
    Budget::bootstrapPersonalForUser($user);

    $this->actingAs($user);

    Livewire::test('pages.settings')
        ->set('delete_confirmation', 'DELETE')
        ->call('destroyAccount')
        ->assertRedirect(route('home'))
        ->assertHasNoErrors();

    expect(User::query()->count())->toBe(0);
});
