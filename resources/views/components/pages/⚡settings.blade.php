<?php

use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $payday_day = null;

    public function mount(): void
    {
        $this->payday_day = auth()->user()->payday_day;
    }

    public function save(): void
    {
        $this->validate([
            'payday_day' => ['nullable', 'integer', 'min:1', 'max:31'],
        ]);

        auth()->user()->update([
            'payday_day' => $this->payday_day,
        ]);

        session()->flash('status', __('Saved.'));
    }
};
?>

<div class="mx-auto max-w-lg px-4 py-6">
    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mb-4 text-sm">{{ session('status') }}</div>
    @endif

    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Settings') }}</h1>
    <p class="text-base-content/70 mt-1 text-sm">
        {{ __('Payday is used for the daily runway metric on your dashboard (available cash ÷ days until payday). Leave empty to use the end of each month.') }}
    </p>

    <form wire:submit="save" class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-4">
            <label class="form-control w-full max-w-xs">
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

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        </div>
    </form>
</div>
