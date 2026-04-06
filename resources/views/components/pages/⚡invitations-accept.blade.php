<?php

use App\Models\BudgetInvitation;
use App\Services\BudgetInvitationService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $token = '';

    public ?string $fatal = null;

    public ?string $errorMessage = null;

    public function mount(string $token, BudgetInvitationService $invitationService): void
    {
        $this->token = $token;

        $invitation = BudgetInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invitation === null) {
            $this->fatal = __('This invitation link is not valid.');

            return;
        }

        if ($invitation->accepted_at !== null) {
            $this->fatal = __('This invitation was already accepted.');

            return;
        }

        if ($invitation->isExpired()) {
            $this->fatal = __('This invitation has expired.');

            return;
        }

        if (! auth()->check()) {
            session([
                'budget_invitation_token' => $token,
                'budget_invitation_email' => $invitation->email,
            ]);
            session()->flash('invitation_notice', __('Please sign in with :email to accept.', ['email' => $invitation->email]));
            $this->redirectRoute('login');

            return;
        }

        try {
            $invitationService->accept($token, auth()->user());
            session()->forget(['budget_invitation_token', 'budget_invitation_email']);
            session()->flash('status', __('You joined the budget.'));
            $this->redirectRoute('dashboard', navigate: true);
        } catch (ValidationException $e) {
            $this->errorMessage = collect($e->errors())->flatten()->first()
                ?? __('Could not accept this invitation.');
        }
    }
};
?>

<div class="mx-auto max-w-lg px-4 py-12">
    @if ($fatal)
        <div class="alert alert-warning">
            <span>{{ $fatal }}</span>
        </div>
        @auth
            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm mt-4" wire:navigate>{{ __('Go to dashboard') }}</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm mt-4" wire:navigate>{{ __('Sign in') }}</a>
        @endauth
    @elseif ($errorMessage)
        <div class="alert alert-error alert-soft">
            <span>{{ $errorMessage }}</span>
        </div>
        <p class="text-base-content/70 mt-4 text-sm">
            {{ __('Sign out and sign in again with the invited email, or ask the budget owner to send a new invite.') }}
        </p>
        <div class="mt-4 flex flex-wrap gap-2">
            <a href="{{ route('dashboard') }}" class="btn btn-ghost btn-sm" wire:navigate>{{ __('Dashboard') }}</a>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="btn btn-outline btn-sm">{{ __('Log out') }}</button>
            </form>
        </div>
    @endif
</div>
