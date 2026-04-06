<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class BudgetActivityQuery
{
    /**
     * Activity for this budget: budget-level events plus transaction events on accounts the user can access.
     *
     * @return Builder<Activity>
     */
    public function forBudget(Budget $budget, User $user): Builder
    {
        $accessibleIds = app(BudgetAccountAccess::class)->accessibleBankAccountIds($user, $budget);

        $transactionIds = Transaction::query()
            ->where('budget_id', $budget->id)
            ->whereIn('bank_account_id', $accessibleIds)
            ->select('id');

        return Activity::query()
            ->where(function (Builder $q) use ($budget, $transactionIds): void {
                $q->where(function (Builder $inner) use ($budget): void {
                    $inner->where('subject_type', Budget::class)
                        ->where('subject_id', $budget->id);
                })->orWhere(function (Builder $inner) use ($transactionIds): void {
                    $inner->where('subject_type', Transaction::class)
                        ->whereIn('subject_id', $transactionIds);
                });
            })
            ->with(['causer'])
            ->latest('created_at');
    }
}
