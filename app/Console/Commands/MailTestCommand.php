<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailTestCommand extends Command
{
    protected $signature = 'mail:test {email : Recipient email address}';

    protected $description = 'Send a test message using the configured mailer (useful for local SMTP checks)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $this->line('Mailer: '.config('mail.default'));
        $this->line('Host: '.config('mail.mailers.smtp.host'));
        $this->line('Port: '.config('mail.mailers.smtp.port'));
        $this->line('EHLO domain: '.config('mail.mailers.smtp.local_domain'));

        try {
            Mail::raw(
                'Budget Buddy mail test at '.now()->toIso8601String(),
                function ($message) use ($email): void {
                    $message->to($email)->subject('Budget Buddy mail test');
                }
            );
        } catch (Throwable $e) {
            $this->error('Failed: '.$e->getMessage());
            $this->components->warn('Tip: some hosts reject mail to the same "no-reply" address (525 Disabled recipient). Try an external inbox (e.g. Gmail).');

            return self::FAILURE;
        }

        $this->info('Sent. Check the inbox (and spam) for: '.$email);

        return self::SUCCESS;
    }
}
