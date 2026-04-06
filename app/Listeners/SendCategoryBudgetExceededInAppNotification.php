<?php

namespace App\Listeners;

use App\Enums\BudgetRole;
use App\Events\Budget\CategoryBudgetExceeded;
use App\Notifications\CategoryBudgetExceededNotification;

class SendCategoryBudgetExceededInAppNotification
{
    public function handle(CategoryBudgetExceeded $event): void
    {
        $notification = new CategoryBudgetExceededNotification(
            $event->budget,
            $event->category,
            $event->year,
            $event->month,
            $event->spentBase,
            $event->budgetAmountBase,
        );

        $event->budget->loadMissing('users');
        foreach ($event->budget->users as $user) {
            if ($event->budget->roleFor($user) === BudgetRole::Owner) {
                $user->notify($notification);
            }
        }
    }
}
