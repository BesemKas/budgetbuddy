<?php

use App\Enums\BudgetRole;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('labels own budget as Personal', function (): void {
    $user = User::factory()->create(['name' => 'Alex']);
    $budget = Budget::bootstrapPersonalForUser($user);

    expect($budget->displayNameFor($user))->toBe(__('Personal'));
});

it('labels someone elses budget as the owners team', function (): void {
    $owner = User::factory()->create(['name' => 'Pete']);
    $member = User::factory()->create(['name' => 'Alex']);
    $budget = Budget::bootstrapPersonalForUser($owner);
    $budget->users()->attach($member->id, ['role' => BudgetRole::Viewer->value]);

    expect($budget->displayNameFor($member))->toBe(__(":name's team", ['name' => 'Pete']))
        ->and($budget->teamLabel())->toBe(__(":name's team", ['name' => 'Pete']));
});
