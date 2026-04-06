<?php

use App\Enums\SmartMode;
use App\Services\AccountDeletionService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $payday_day = null;

    public string $smart_mode = '';

    public string $delete_confirmation = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->payday_day = $user->payday_day;
        $mode = $user->smart_mode ?? SmartMode::Standard;
        $this->smart_mode = $mode instanceof SmartMode ? $mode->value : SmartMode::Standard->value;
    }

    public function save(): void
    {
        $this->validate([
            'payday_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'smart_mode' => ['required', Rule::enum(SmartMode::class)],
        ]);

        auth()->user()->update([
            'payday_day' => $this->payday_day,
            'smart_mode' => SmartMode::from($this->smart_mode),
        ]);

        session()->flash('status', __('Saved.'));
    }

    public function destroyAccount(AccountDeletionService $accountDeletion): void
    {
        $this->validate([
            'delete_confirmation' => ['required', 'string', 'in:DELETE'],
        ]);

        $user = auth()->user();

        auth()->logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $accountDeletion->deleteAccount($user);

        $this->redirect(route('home'));
    }
};
?>

<div class="bb-page max-w-lg">
    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mb-4 text-sm">{{ session('status') }}</div>
    @endif

    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Settings') }}</h1>
    <p class="text-base-content/70 mt-1 text-sm">
        {{ __('Payday is used for the daily runway metric on your dashboard (available cash ÷ days until payday). Leave empty to use the end of each month.') }}
    </p>

    <form wire:submit="save" class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-4 p-4 sm:p-6">
            <label class="form-control w-full max-w-full sm:max-w-xs">
                <span class="label-text">{{ __('Payday (day of month, 1–31)') }}</span>
                <input
                    type="number"
                    min="1"
                    max="31"
                    class="input input-bordered w-full"
                    wire:model="payday_day"
                    placeholder="{{ __('e.g. 25') }}"
                />
                @error('payday_day')
                    <span class="label-text-alt text-error">{{ $message }}</span>
                @enderror
            </label>

            <label class="form-control w-full">
                <span class="label-text">{{ __('Smart mode') }}</span>
                <span class="label-text-alt text-base-content/70">{{ __('Changes validation and hints when you add transactions from the dashboard.') }}</span>
                <select class="select select-bordered w-full max-w-full text-sm sm:max-w-md sm:text-base" wire:model="smart_mode">
                    @foreach (SmartMode::cases() as $mode)
                        <option value="{{ $mode->value }}">{{ $mode->label() }} — {{ $mode->description() }}</option>
                    @endforeach
                </select>
                @error('smart_mode')
                    <span class="label-text-alt text-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="card-actions justify-stretch sm:justify-end">
                <button type="submit" class="btn btn-primary btn-sm w-full sm:w-auto" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        </div>
    </form>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm sm:mt-8">
        <div class="card-body gap-2 p-4 sm:p-6">
            <h2 class="card-title text-lg">{{ __('Tools') }}</h2>
            <p class="text-base-content/70 text-sm">
                {{ __('Estimate take-home pay from gross using SARS tables (indicative only — not tax advice).') }}
            </p>
            <div class="card-actions justify-stretch sm:justify-start">
                <a href="{{ route('tools.tax') }}" class="btn btn-outline btn-sm w-full sm:w-auto" wire:navigate>{{ __('Tax calculator') }}</a>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-error/30 shadow-sm sm:mt-8">
        <div class="card-body gap-4 p-4 sm:p-6">
            <h2 class="card-title text-lg text-error">{{ __('Delete account & data') }}</h2>
            <p class="text-base-content/80 text-sm">
                {{ __('Permanently delete your login and every budget where you are the only member. You cannot do this while you share a budget with someone else.') }}
            </p>
            @php
                $block = app(\App\Services\AccountDeletionService::class)->blockingReason(auth()->user());
            @endphp
            @if ($block)
                <p class="text-warning text-sm">{{ $block }}</p>
            @else
                <label class="form-control w-full">
                    <span class="label-text">{{ __('Type DELETE to confirm') }}</span>
                    <input
                        type="text"
                        class="input input-bordered w-full max-w-full font-mono sm:max-w-xs"
                        wire:model="delete_confirmation"
                        autocomplete="off"
                    />
                    @error('delete_confirmation')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>
                <div class="card-actions justify-stretch sm:justify-start">
                    <button
                        type="button"
                        class="btn btn-error btn-outline btn-sm w-full sm:w-auto"
                        wire:click="destroyAccount"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="destroyAccount">{{ __('Delete my account') }}</span>
                        <span wire:loading wire:target="destroyAccount" class="loading loading-spinner loading-sm"></span>
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
