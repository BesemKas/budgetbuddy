<?php

use App\Models\BankAccount;
use App\Models\BudgetInvitation;
use App\Services\BudgetInvitationService;
use App\Services\CurrentBudget;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate(['required', 'email:rfc'])]
    public string $inviteEmail = '';

    /** @var list<int> */
    public array $inviteBankAccountIds = [];

    public function mount(CurrentBudget $currentBudget): void
    {
        $this->authorize('invite', $currentBudget->current());
    }

    public function sendInvite(BudgetInvitationService $invitationService, CurrentBudget $currentBudget): void
    {
        $this->authorize('invite', $currentBudget->current());
        $budget = $currentBudget->current();

        $this->validate([
            'inviteEmail' => ['required', 'email:rfc'],
            'inviteBankAccountIds' => ['required', 'array', 'min:1'],
            'inviteBankAccountIds.*' => ['integer', Rule::exists('bank_accounts', 'id')->where('budget_id', $budget->id)],
        ]);

        $invitationService->sendInvitation(
            $budget,
            auth()->user(),
            $this->inviteEmail,
            $this->inviteBankAccountIds,
        );

        $this->reset('inviteEmail', 'inviteBankAccountIds');
        session()->flash('status', __('Invitation sent.'));
    }

    public function cancelInvite(int $id, BudgetInvitationService $invitationService): void
    {
        $invitation = BudgetInvitation::query()->findOrFail($id);
        $invitationService->cancel($invitation, auth()->user());
        session()->flash('status', __('Invitation cancelled.'));
    }

    public function getBudgetBankAccountsProperty(): Collection
    {
        return BankAccount::query()
            ->where('budget_id', app(CurrentBudget::class)->current()->id)
            ->orderBy('name')
            ->get();
    }

    public function getPendingInvitationsProperty(): Collection
    {
        $budget = app(CurrentBudget::class)->current();

        return BudgetInvitation::query()
            ->where('budget_id', $budget->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->with(['invitedBy', 'bankAccounts'])
            ->orderByDesc('created_at')
            ->get();
    }
};
?>

<div class="mx-auto max-w-3xl px-4 py-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Budget team') }}</h1>
        <p class="text-base-content/70 mt-1 text-sm">
            {{ __('Invite someone by email. They join as a viewer only for the accounts you select. They keep their own personal budget separately.') }}
        </p>
    </div>

    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mt-4 text-sm">{{ session('status') }}</div>
    @endif

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-4">
            <h2 class="card-title text-lg">{{ __('Invite a member') }}</h2>
            <form wire:submit="sendInvite" class="flex flex-col gap-4">
                <label class="form-control w-full">
                    <span class="label-text">{{ __('Email') }}</span>
                    <input type="email" wire:model="inviteEmail" class="input input-bordered w-full" autocomplete="off" placeholder="name@example.com" />
                    @error('inviteEmail')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>

                <div class="form-control w-full">
                    <span class="label-text mb-2">{{ __('Accounts they can use') }}</span>
                    <p class="text-base-content/70 mb-2 text-xs">{{ __('They will not see other accounts in this budget.') }}</p>
                    <div class="flex flex-col gap-2 rounded-lg border border-base-300/60 p-3">
                        @forelse ($this->budgetBankAccounts as $acc)
                            <label class="label cursor-pointer justify-start gap-3">
                                <input type="checkbox" class="checkbox checkbox-sm" wire:model="inviteBankAccountIds" value="{{ $acc->id }}" />
                                <span class="label-text">{{ $acc->name }} <span class="text-base-content/60">({{ $acc->currency_code }})</span></span>
                            </label>
                        @empty
                            <p class="text-base-content/60 text-sm">{{ __('Add a bank account in this budget first.') }}</p>
                        @endforelse
                    </div>
                    @error('inviteBankAccountIds')
                        <span class="label-text-alt text-error mt-1">{{ $message }}</span>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-sm w-fit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="sendInvite">{{ __('Send invite') }}</span>
                    <span wire:loading wire:target="sendInvite" class="loading loading-spinner loading-sm"></span>
                </button>
            </form>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-2">
            <h2 class="card-title text-lg">{{ __('Pending invitations') }}</h2>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Email') }}</th>
                            <th>{{ __('Shared accounts') }}</th>
                            <th>{{ __('Invited by') }}</th>
                            <th>{{ __('Expires') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pendingInvitations as $inv)
                            <tr wire:key="inv-{{ $inv->id }}">
                                <td>{{ $inv->email }}</td>
                                <td class="text-sm">
                                    {{ $inv->bankAccounts->pluck('name')->join(', ') }}
                                </td>
                                <td>{{ $inv->invitedBy->name }}</td>
                                <td class="whitespace-nowrap">{{ $inv->expires_at->toFormattedDateString() }}</td>
                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs text-error"
                                        wire:click="cancelInvite({{ $inv->id }})"
                                        wire:confirm="{{ __('Cancel this invitation?') }}"
                                    >
                                        {{ __('Cancel') }}
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-base-content/60">{{ __('No pending invitations.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
