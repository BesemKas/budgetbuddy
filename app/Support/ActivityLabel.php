<?php

namespace App\Support;

use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLabel
{
    public static function summary(Activity $activity): string
    {
        return match (true) {
            $activity->description === 'budget_invitation_sent' => __('Invitation sent'),
            $activity->description === 'budget_invitation_accepted' => __('Invitation accepted'),
            $activity->description === 'budget_switched' => __('Switched active budget'),
            $activity->subject_type === Transaction::class && in_array($activity->log_name, ['ledger', null], true) => __('Transaction :action', [
                'action' => Str::title($activity->description),
            ]),
            $activity->subject_type === Budget::class => Str::title($activity->description),
            default => $activity->description,
        };
    }
}
