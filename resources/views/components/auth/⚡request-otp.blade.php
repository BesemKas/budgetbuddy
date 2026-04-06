<?php

use App\Services\OtpService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\RateLimiter;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate(['required', 'email:rfc'])]
    public string $email = '';

    public function mount(): void
    {
        if ($this->email === '' && session()->has('budget_invitation_email')) {
            $this->email = (string) session('budget_invitation_email');
        }
    }

    public function send(OtpService $otpService): void
    {
        $this->validate();

        $key = 'otp-send:'.$this->email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->addError(
                'email',
                __('Too many code requests. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ])
            );

            return;
        }

        RateLimiter::hit($key, decaySeconds: 60);

        $otpService->send($this->email);

        session(['otp_email' => $this->email]);

        $this->redirectRoute('login.verify', navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center p-4">
    <div class="card bg-base-100 w-full max-w-md shadow-xl">
        <div class="card-body gap-4">
            <h1 class="card-title text-2xl">{{ __('Sign in to Budget Buddy') }}</h1>
            @if (session('invitation_notice'))
                <div role="status" class="alert alert-info alert-soft text-sm">{{ session('invitation_notice') }}</div>
            @endif
            <p class="text-base-content/70 text-sm">
                {{ __('We will email you a one-time code. No password required.') }}
            </p>
            <form wire:submit="send" class="flex flex-col gap-4">
                <label class="form-control w-full">
                    <span class="label-text mb-1">{{ __('Email') }}</span>
                    <input
                        type="email"
                        wire:model="email"
                        class="input input-bordered w-full"
                        autocomplete="email"
                        autofocus
                    />
                    @error('email')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Continue') }}</span>
                    <span wire:loading class="loading loading-spinner loading-sm"></span>
                </button>
            </form>
        </div>
    </div>
</div>
