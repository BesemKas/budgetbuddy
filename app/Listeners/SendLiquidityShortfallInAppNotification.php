<?php

namespace App\Listeners;

use App\Enums\BudgetRole;
use App\Events\Budget\LiquidityShortfallDetected;
use App\Notifications\LiquidityShortfallNotification;

class SendLiquidityShortfallInAppNotification
{
    public function handle(LiquidityShortfallDetected $event): void
    {
        $notification = new LiquidityShortfallNotification(
            $event->budget,
            $event->year,
            $event->month,
            $event->totalLiquidBase,
            $event->totalBudgeted,
            $event->shortfallBase,
        );

        $event->budget->loadMissing('users');
        foreach ($event->budget->users as $user) {
            if ($event->budget->roleFor($user) === BudgetRole::Owner) {
                $user->notify($notification);
            }
        }
    }
}
