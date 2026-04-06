<?php

namespace App\Services;

use App\Models\User;

class BudgetNotificationService
{
    /**
     * Mark unread in-app notifications for this budget and calendar month as read (opening the planner).
     */
    public function markBudgetMonthNotificationsAsRead(User $user, int $budgetId, int $year, int $month): void
    {
        $user->unreadNotifications()
            ->where('data->budget_id', $budgetId)
            ->where('data->year', $year)
            ->where('data->month', $month)
            ->update(['read_at' => now()]);
    }

    /**
     * Mark all unread notifications for the given budget as read (bell “dismiss all”).
     */
    public function markAllUnreadForBudgetAsRead(User $user, int $budgetId): void
    {
        $user->unreadNotifications()
            ->where('data->budget_id', $budgetId)
            ->update(['read_at' => now()]);
    }
}
