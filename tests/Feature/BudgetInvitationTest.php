<?php

use App\Enums\BudgetRole;
use App\Mail\BudgetInvitationMail;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('sends an invitation email for budget owners', function () {
    Mail::fake();

    $owner = User::factory()->create();
    Budget::bootstrapPersonalForUser($owner);
    $account = BankAccount::factory()->create(['user_id' => $owner->id]);

    $this->actingAs($owner);

    Livewire::test('pages.budget-team')
        ->set('inviteEmail', 'teammate@example.com')
        ->set('inviteBankAccountIds', [$account->id])
        ->call('sendInvite')
        ->assertHasNoErrors();

    Mail::assertSent(BudgetInvitationMail::class);
});

it('accepts an invitation when the signed-in email matches', function () {
    $owner = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($owner);
    $account = BankAccount::factory()->create(['user_id' => $owner->id]);

    $inviteeEmail = 'invitee@example.com';
    $invitee = User::factory()->create(['email' => $inviteeEmail]);

    $plainToken = str_repeat('a', 64);

    $invitation = BudgetInvitation::query()->create([
        'budget_id' => $budget->id,
        'email' => $inviteeEmail,
        'token_hash' => hash('sha256', $plainToken),
        'expires_at' => now()->addDay(),
        'invited_by_user_id' => $owner->id,
    ]);

    $invitation->bankAccounts()->sync([$account->id]);

    $this->actingAs($invitee);

    $this->get(route('budget-invitations.accept', ['token' => $plainToken]))
        ->assertRedirect(route('dashboard'));

    expect($invitee->fresh()->budgets()->where('budgets.id', $budget->id)->exists())->toBeTrue();
});

it('forbids viewers from opening the team page', function () {
    $owner = User::factory()->create();
    $budget = Budget::bootstrapPersonalForUser($owner);

    $viewer = User::factory()->create();
    $budget->users()->attach($viewer->id, ['role' => BudgetRole::Viewer->value]);

    $this->actingAs($viewer);

    $this->get(route('budget.team'))->assertForbidden();
});
