<?php

namespace App\Services;

use App\Enums\BudgetRole;
use App\Mail\BudgetInvitationMail;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BudgetInvitationService
{
    public function __construct(
        private CurrentBudget $currentBudget,
    ) {}

    /**
     * Create a pending invitation and email a link with the one-time token.
     *
     * @param  list<int>  $bankAccountIds  Accounts in this budget to share with the invitee.
     *
     * @throws AuthorizationException
     */
    public function sendInvitation(Budget $budget, User $inviter, string $email, array $bankAccountIds): void
    {
        Gate::authorize('invite', $budget);

        $email = strtolower(trim($email));

        if ($email === '') {
            throw ValidationException::withMessages([
                'inviteEmail' => __('Enter a valid email address.'),
            ]);
        }

        $bankAccountIds = array_values(array_unique(array_map('intval', $bankAccountIds)));
        if ($bankAccountIds === []) {
            throw ValidationException::withMessages([
                'inviteBankAccountIds' => __('Select at least one account to share.'),
            ]);
        }

        $validCount = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $bankAccountIds)
            ->count();

        if ($validCount !== count($bankAccountIds)) {
            throw ValidationException::withMessages([
                'inviteBankAccountIds' => __('One or more selected accounts are not in this budget.'),
            ]);
        }

        $existingMember = User::query()->where('email', $email)->first();
        if ($existingMember !== null && (int) $existingMember->id === (int) $budget->owner_user_id) {
            throw ValidationException::withMessages([
                'inviteEmail' => __('You cannot invite the budget owner.'),
            ]);
        }

        $hasPending = BudgetInvitation::query()
            ->where('budget_id', $budget->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages([
                'inviteEmail' => __('An invitation is already pending for that email.'),
            ]);
        }

        $plainToken = Str::random(64);

        $invitation = BudgetInvitation::query()->create([
            'budget_id' => $budget->id,
            'email' => $email,
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addDays(config('budgetbuddy.invitation_ttl_days', 7)),
            'invited_by_user_id' => $inviter->id,
        ]);

        $invitation->bankAccounts()->sync($bankAccountIds);

        $invitation->load(['bankAccounts', 'invitedBy', 'budget']);

        Mail::to($email)->send(new BudgetInvitationMail($invitation, $plainToken));

        activity()
            ->performedOn($budget)
            ->causedBy($inviter)
            ->withProperties([
                'invitation_id' => $invitation->id,
                'email' => $email,
                'bank_account_ids' => $bankAccountIds,
            ])
            ->log('budget_invitation_sent');
    }

    /**
     * Accept an invitation for the authenticated user.
     *
     * @throws ValidationException
     */
    public function accept(string $plainToken, User $user): Budget
    {
        $invitation = BudgetInvitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'token' => __('This invitation link is not valid.'),
            ]);
        }

        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages([
                'token' => __('This invitation was already accepted.'),
            ]);
        }

        if ($invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => __('This invitation has expired.'),
            ]);
        }

        if (strtolower($user->email) !== strtolower($invitation->email)) {
            throw ValidationException::withMessages([
                'email' => __('Sign in as :email to accept this invitation.', ['email' => $invitation->email]),
            ]);
        }

        $budget = DB::transaction(function () use ($invitation, $user): Budget {
            $budget = $invitation->budget;
            $invitation->load('bankAccounts');

            $accountIds = $invitation->bankAccounts->pluck('id')->map(fn ($id): int => (int) $id)->all();

            if ($accountIds === []) {
                throw ValidationException::withMessages([
                    'token' => __('This invitation has no shared accounts.'),
                ]);
            }

            if (! $budget->users()->where('users.id', $user->id)->exists()) {
                $budget->users()->attach($user->id, [
                    'role' => BudgetRole::Viewer->value,
                ]);
            }

            $this->grantSharedBankAccounts($budget->id, $user->id, $accountIds);

            $invitation->update([
                'accepted_at' => now(),
            ]);

            activity()
                ->performedOn($budget)
                ->causedBy($user)
                ->withProperties([
                    'invitation_id' => $invitation->id,
                    'bank_account_ids' => $accountIds,
                ])
                ->log('budget_invitation_accepted');

            return $budget;
        });

        $this->currentBudget->switchTo($budget, User::query()->findOrFail($user->id));

        return $budget;
    }

    /**
     * @param  list<int>  $bankAccountIds
     */
    private function grantSharedBankAccounts(int $budgetId, int $userId, array $bankAccountIds): void
    {
        foreach ($bankAccountIds as $bankAccountId) {
            DB::table('budget_shared_bank_accounts')->insertOrIgnore([
                'budget_id' => $budgetId,
                'user_id' => $userId,
                'bank_account_id' => $bankAccountId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Revoke a pending invitation (owner only).
     */
    public function cancel(BudgetInvitation $invitation, User $actor): void
    {
        Gate::authorize('invite', $invitation->budget);

        if ($invitation->accepted_at !== null) {
            return;
        }

        $invitation->delete();
    }
}
