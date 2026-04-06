<?php

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

it('redirects guests from the dashboard to login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('sends an otp and redirects to verify', function () {
    Mail::fake();

    Livewire::test('auth.request-otp')
        ->set('email', 'otp-user@example.com')
        ->call('send')
        ->assertRedirect(route('login.verify'));

    Mail::assertSent(OtpCodeMail::class);

    expect(session('otp_email'))->toBe('otp-user@example.com');
});

it('logs in after a valid otp', function () {
    Mail::fake();

    $email = 'verified@example.com';

    OtpCode::query()->create([
        'email' => $email,
        'code_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10),
    ]);

    Livewire::test('auth.verify-otp', ['email' => $email])
        ->set('code', '123456')
        ->call('verify')
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticated();

    $user = User::query()->where('email', $email)->first();
    expect($user)->not->toBeNull()
        ->and($user->hasRole('member'))->toBeTrue()
        ->and($user->budgets()->count())->toBe(1);
});

it('rejects an invalid otp code', function () {
    Mail::fake();

    $email = 'bad-code@example.com';

    OtpCode::query()->create([
        'email' => $email,
        'code_hash' => Hash::make('999999'),
        'expires_at' => now()->addMinutes(10),
    ]);

    Livewire::test('auth.verify-otp', ['email' => $email])
        ->set('code', '123456')
        ->call('verify')
        ->assertHasErrors('code');

    $this->assertGuest();
});
