<?php

namespace App\Events\Budget;

use App\Models\Budget;
use App\Models\Category;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when recorded spending in a category exceeds its monthly plan (after dedupe gate in the notifier).
 */
class CategoryBudgetExceeded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Budget $budget,
        public Category $category,
        public int $year,
        public int $month,
        public string $spentBase,
        public string $budgetAmountBase,
    ) {}
}
