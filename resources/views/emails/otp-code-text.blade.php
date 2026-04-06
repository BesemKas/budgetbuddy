{{ __('Your one-time code is: :code', ['code' => $code]) }}

{{ __('This code expires in :minutes minutes.', ['minutes' => config('budgetbuddy.otp_ttl_minutes')]) }}
