<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Events\Budget\CategoryBudgetExceeded;
use App\Events\Budget\LiquidityShortfallDetected;
use App\Events\Budget\ZeroBasedAssignmentExceedsIncome;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BudgetInAppNotifier
{
    public function __construct(
        private BudgetRealityCheckService $realityCheck,
    ) {}

    public function handleTransactionSaved(Transaction $transaction): void
    {
        if ($transaction->budget_id === null) {
            return;
        }

        $budget = Budget::query()->find($transaction->budget_id);
        if ($budget === null) {
            return;
        }

        $occurred = Carbon::parse($transaction->occurred_on);
        $year = (int) $occurred->year;
        $month = (int) $occurred->month;

        if ($transaction->type === LedgerEntryType::Expense && $transaction->category_id !== null) {
            $this->maybeNotifyCategoryOverBudget($transaction, $budget, $year, $month);
        }

        $this->notifyPlanLevelAlerts($budget, $year, $month);
    }

    /**
     * Call after planner saves (projected income, lines, copy month).
     */
    public function notifyPlanLevelAlerts(Budget $budget, int $year, int $month): void
    {
        $anchor = Carbon::createFromDate($year, $month, 1);
        $budget->loadMissing('users');

        $liq = $this->realityCheck->liquidityAssessment($budget, $year, $month);
        $liqKey = $this->liquidityCacheKey($budget->id, $year, $month);

        if ($liq['is_funded']) {
            Cache::forget($liqKey);
        } elseif (! Cache::has($liqKey)) {
            LiquidityShortfallDetected::dispatch(
                $budget,
                $year,
                $month,
                $liq['total_liquid_base'],
                $liq['total_budgeted'],
                $liq['shortfall_base'],
            );
            Cache::put($liqKey, true, $anchor->copy()->endOfMonth());
        }

        $zb = $this->realityCheck->zeroBasedAssessment($budget, $year, $month);
        $zbKey = $this->zeroBasedCacheKey($budget->id, $year, $month);

        if ($zb['is_within_income']) {
            Cache::forget($zbKey);
        } elseif (! Cache::has($zbKey)) {
            ZeroBasedAssignmentExceedsIncome::dispatch(
                $budget,
                $year,
                $month,
                $zb['projected_income'],
                $zb['total_assigned'],
                $zb['overage_base'],
            );
            Cache::put($zbKey, true, $anchor->copy()->endOfMonth());
        }
    }

    private function maybeNotifyCategoryOverBudget(Transaction $transaction, Budget $budget, int $year, int $month): void
    {
        $line = CategoryMonthBudget::query()
            ->where('budget_id', $budget->id)
            ->where('category_id', $transaction->category_id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($line === null) {
            return;
        }

        if (bccomp((string) $line->amount, '0', 4) <= 0) {
            return;
        }

        $spent = $this->realityCheck->sumCategoryExpenseInBaseForMonth($budget->id, $transaction->category_id, $year, $month);
        $budgetAmount = (string) $line->amount;

        $cacheKey = $this->monthlyCategoryCacheKey($budget->id, $transaction->category_id, $year, $month);

        if (bccomp($spent, $budgetAmount, 4) <= 0) {
            Cache::forget($cacheKey);

            return;
        }

        if (Cache::has($cacheKey)) {
            return;
        }

        $category = Category::query()->find($transaction->category_id);
        if ($category === null) {
            return;
        }

        CategoryBudgetExceeded::dispatch(
            $budget,
            $category,
            $year,
            $month,
            $spent,
            $budgetAmount,
        );

        $occurred = Carbon::parse($transaction->occurred_on);
        Cache::put($cacheKey, true, $occurred->copy()->endOfMonth());
    }

    private function liquidityCacheKey(int $budgetId, int $year, int $month): string
    {
        return "inapp_liq_short:{$budgetId}:{$year}:{$month}";
    }

    private function zeroBasedCacheKey(int $budgetId, int $year, int $month): string
    {
        return "inapp_zb_over:{$budgetId}:{$year}:{$month}";
    }

    private function monthlyCategoryCacheKey(int $budgetId, int $categoryId, int $year, int $month): string
    {
        return "inapp_cat_over:{$budgetId}:{$categoryId}:{$year}:{$month}";
    }
}
