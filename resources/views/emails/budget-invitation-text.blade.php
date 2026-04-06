{{ __(':name invited you to collaborate on the budget “:budget”.', ['name' => $invitation->invitedBy->name, 'budget' => $invitation->budget->name]) }}

{{ __('Shared accounts:') }}

@foreach ($invitation->bankAccounts as $account)
- {{ $account->name }} ({{ $account->currency_code }})
@endforeach


{{ __('Open this link to accept (sign in with this email address):') }}

{{ route('budget-invitations.accept', ['token' => $plainToken], absolute: true) }}

{{ __('This link expires on :date.', ['date' => $invitation->expires_at->toFormattedDateString()]) }}
