<?php

namespace App\Listeners;

use App\Enums\BudgetRole;
use App\Events\Budget\ZeroBasedAssignmentExceedsIncome;
use App\Notifications\ZeroBasedOverAssignedNotification;

class SendZeroBasedOverAssignedInAppNotification
{
    public function handle(ZeroBasedAssignmentExceedsIncome $event): void
    {
        $notification = new ZeroBasedOverAssignedNotification(
            $event->budget,
            $event->year,
            $event->month,
            $event->projectedIncome,
            $event->totalAssigned,
            $event->overageBase,
        );

        $event->budget->loadMissing('users');
        foreach ($event->budget->users as $user) {
            if ($event->budget->roleFor($user) === BudgetRole::Owner) {
                $user->notify($notification);
            }
        }
    }
}
