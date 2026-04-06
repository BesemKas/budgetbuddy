<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\CategoryMonthBudget;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BudgetMonthCopyService
{
    /**
     * Copy projected income and all category lines from the previous calendar month into the target month.
     */
    public function copyFromPreviousMonth(Budget $budget, int $targetYear, int $targetMonth): void
    {
        $prev = Carbon::createFromDate($targetYear, $targetMonth, 1)->subMonth();
        $prevYear = (int) $prev->year;
        $prevMonth = (int) $prev->month;

        DB::transaction(function () use ($budget, $targetYear, $targetMonth, $prevYear, $prevMonth): void {
            $prevSummary = BudgetMonthSummary::query()
                ->where('budget_id', $budget->id)
                ->where('year', $prevYear)
                ->where('month', $prevMonth)
                ->first();

            BudgetMonthSummary::query()->updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'year' => $targetYear,
                    'month' => $targetMonth,
                ],
                [
                    'projected_income' => $prevSummary?->projected_income ?? '0',
                ]
            );

            $prevLines = CategoryMonthBudget::query()
                ->where('budget_id', $budget->id)
                ->where('year', $prevYear)
                ->where('month', $prevMonth)
                ->get();

            foreach ($prevLines as $line) {
                CategoryMonthBudget::query()->updateOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'category_id' => $line->category_id,
                        'year' => $targetYear,
                        'month' => $targetMonth,
                    ],
                    [
                        'amount' => $line->amount,
                        'bank_account_id' => $line->bank_account_id,
                        'priority' => $line->priority,
                    ]
                );
            }
        });
    }
}
