<?php

use App\Models\User;
use App\Services\CurrentBudget;
use App\Services\OtpService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;

new #[Layout('layouts.app')] class extends Component
{
    #[Validate(['required', 'digits:6'])]
    public string $code = '';

    public string $otpEmail = '';

    public function mount(string $email = ''): void
    {
        $this->otpEmail = $email !== ''
            ? $email
            : (string) session('otp_email', '');

        if ($this->otpEmail === '') {
            $this->redirectRoute('login');
        }
    }

    public function verify(OtpService $otpService): void
    {
        $email = $this->otpEmail;

        $key = 'otp-verify:'.$email.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError(
                'code',
                __('Too many attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ])
            );

            return;
        }

        $this->validate();

        if (! $otpService->verify($email, $this->code)) {
            RateLimiter::hit($key, decaySeconds: 60);
            $this->addError('code', __('That code is invalid or has expired.'));

            return;
        }

        RateLimiter::clear($key);

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => Str::title(Str::before($email, '@')),
                'password' => Hash::make(Str::random(64)),
            ]
        );

        if (! $user->hasRole('member')) {
            $user->assignRole('member');
        }

        Auth::login($user, remember: true);

        app(CurrentBudget::class)->bootstrapSessionIfNeeded($user);

        $inviteToken = session('budget_invitation_token');

        Session::forget('otp_email');
        Session::regenerate();

        if ($inviteToken !== null) {
            session(['budget_invitation_token' => $inviteToken]);
        }

        if ($inviteToken !== null) {
            $this->redirectRoute('budget-invitations.accept', ['token' => $inviteToken], navigate: true);

            return;
        }

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function backToEmail(): void
    {
        Session::forget('otp_email');
        $this->redirectRoute('login', navigate: true);
    }
};
?>

<div class="flex min-h-screen items-center justify-center p-4">
    <div class="card bg-base-100 w-full max-w-md shadow-xl">
        <div class="card-body gap-4">
            <h1 class="card-title text-2xl">{{ __('Enter your code') }}</h1>
            <p class="text-base-content/70 text-sm">
                {{ __('We sent a 6-digit code to :email', ['email' => $otpEmail]) }}
            </p>
            <form wire:submit="verify" class="flex flex-col gap-4">
                <label class="form-control w-full">
                    <span class="label-text mb-1">{{ __('One-time code') }}</span>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        wire:model="code"
                        class="input input-bordered w-full tracking-widest font-mono text-lg"
                        autocomplete="one-time-code"
                        autofocus
                    />
                    @error('code')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>{{ __('Sign in') }}</span>
                    <span wire:loading class="loading loading-spinner loading-sm"></span>
                </button>
                <button type="button" wire:click="backToEmail" class="btn btn-ghost btn-sm">
                    {{ __('Use a different email') }}
                </button>
            </form>
        </div>
    </div>
</div>
