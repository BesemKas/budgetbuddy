<?php

namespace App\Events\Budget;

use App\Models\Budget;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when total planned category amounts exceed aggregate liquid cash for the budget (Phase 3 domain hook; Phase 4 can broadcast).
 */
class LiquidityShortfallDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Budget $budget,
        public int $year,
        public int $month,
        public string $totalLiquidBase,
        public string $totalBudgeted,
        public string $shortfallBase,
    ) {}
}
