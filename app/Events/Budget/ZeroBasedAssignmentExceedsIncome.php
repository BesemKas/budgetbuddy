<?php

namespace App\Events\Budget;

use App\Models\Budget;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when category assignments exceed projected income for the month (zero-based planning breach).
 */
class ZeroBasedAssignmentExceedsIncome
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Budget $budget,
        public int $year,
        public int $month,
        public string $projectedIncome,
        public string $totalAssigned,
        public string $overageBase,
    ) {}
}
