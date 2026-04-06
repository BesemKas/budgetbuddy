<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function send(string $email): void
    {
        OtpCode::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->delete();

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        $otp = OtpCode::query()->create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('budgetbuddy.otp_ttl_minutes')),
        ]);

        try {
            Mail::to($email)->send(new OtpCodeMail($code));
        } catch (\Throwable $e) {
            Log::error('OTP email failed to send.', [
                'exception' => $e,
                'to' => $email,
            ]);

            $otp->delete();

            throw ValidationException::withMessages([
                'email' => __('We could not send the sign-in code. Try another address or check the mail server settings. Details were written to the application log.'),
            ]);
        }
    }

    public function verify(string $email, string $code): bool
    {
        $record = OtpCode::query()
            ->where('email', $email)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($record === null || ! Hash::check($code, $record->code_hash)) {
            return false;
        }

        $record->forceFill(['consumed_at' => now()])->save();

        return true;
    }
}
