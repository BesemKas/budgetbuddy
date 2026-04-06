<?php

namespace Database\Seeders;

use App\Enums\BudgetPriority;
use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use Illuminate\Database\Seeder;

class SampleBudgetMonthSeeder extends Seeder
{
    /**
     * Sample month summaries and per-category amounts for each budget (for UI development).
     */
    public function run(): void
    {
        $year = (int) now()->year;
        $month = (int) now()->month;

        foreach (Budget::query()->cursor() as $budget) {
            BudgetMonthSummary::query()->updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'year' => $year,
                    'month' => $month,
                ],
                [
                    'projected_income' => '45000.0000',
                ]
            );

            $categories = Category::query()
                ->visibleToBudget($budget)
                ->where('type', LedgerEntryType::Expense)
                ->orderBy('id')
                ->take(6)
                ->get();

            $priorities = [
                BudgetPriority::Needs,
                BudgetPriority::Needs,
                BudgetPriority::Wants,
                BudgetPriority::Wants,
                BudgetPriority::Savings,
                BudgetPriority::Needs,
            ];

            foreach ($categories as $index => $category) {
                $amount = (string) (2000 + ($index * 750));

                CategoryMonthBudget::query()->updateOrCreate(
                    [
                        'budget_id' => $budget->id,
                        'category_id' => $category->id,
                        'year' => $year,
                        'month' => $month,
                    ],
                    [
                        'amount' => $amount,
                        'bank_account_id' => null,
                        'priority' => $priorities[$index % count($priorities)],
                    ]
                );
            }
        }
    }
}
