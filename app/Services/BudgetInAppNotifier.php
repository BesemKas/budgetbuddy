<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\Transaction;
use App\Notifications\CategoryBudgetExceededNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class BudgetInAppNotifier
{
    public function handleTransactionSaved(Transaction $transaction): void
    {
        if ($transaction->type !== LedgerEntryType::Expense) {
            return;
        }

        if ($transaction->category_id === null || $transaction->budget_id === null) {
            return;
        }

        $budget = Budget::query()->find($transaction->budget_id);
        if ($budget === null) {
            return;
        }

        $occurred = Carbon::parse($transaction->occurred_on);
        $year = (int) $occurred->year;
        $month = (int) $occurred->month;

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

        $spent = $this->sumCategoryExpenseInBaseForMonth($budget->id, $transaction->category_id, $year, $month);
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

        $notification = new CategoryBudgetExceededNotification(
            $budget,
            $category,
            $year,
            $month,
            $spent,
            $budgetAmount,
        );

        $budget->loadMissing('users');
        foreach ($budget->users as $user) {
            $user->notify($notification);
        }

        Cache::put($cacheKey, true, $occurred->copy()->endOfMonth());
    }

    public function sumCategoryExpenseInBaseForMonth(int $budgetId, int $categoryId, int $year, int $month): string
    {
        $row = Transaction::query()
            ->where('budget_id', $budgetId)
            ->where('category_id', $categoryId)
            ->where('type', LedgerEntryType::Expense)
            ->whereYear('occurred_on', $year)
            ->whereMonth('occurred_on', $month)
            ->selectRaw('COALESCE(SUM(amount * COALESCE(exchange_rate, 1)), 0) as total')
            ->first();

        return $this->normalizeDecimal($row->total ?? '0');
    }

    private function monthlyCategoryCacheKey(int $budgetId, int $categoryId, int $year, int $month): string
    {
        return "inapp_cat_over:{$budgetId}:{$categoryId}:{$year}:{$month}";
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (is_string($value)) {
            return $value === '' ? '0' : $value;
        }

        return number_format((float) $value, 4, '.', '');
    }
}
