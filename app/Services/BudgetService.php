<?php

namespace App\Services;

use App\Models\Budget;

/**
 * Phase 3 facade: reality checks (liquidity, velocity) plus existing analytics/ledger helpers.
 */
class BudgetService
{
    public function __construct(
        private BudgetAnalyticsService $analytics,
        private LedgerCurrencyService $ledger,
        private BudgetRealityCheckService $reality,
    ) {}

    public function analytics(): BudgetAnalyticsService
    {
        return $this->analytics;
    }

    public function ledger(): LedgerCurrencyService
    {
        return $this->ledger;
    }

    public function reality(): BudgetRealityCheckService
    {
        return $this->reality;
    }

    /**
     * Liquid accounts only vs total budgeted for the month (see {@see BudgetRealityCheckService::liquidityAssessment}).
     *
     * @return array{
     *     total_liquid_base: string,
     *     total_budgeted: string,
     *     is_funded: bool,
     *     shortfall_base: string
     * }
     */
    public function checkBudgetLiquidity(Budget $budget, int $year, int $month): array
    {
        return $this->reality->liquidityAssessment($budget, $year, $month);
    }

    /**
     * Spend rate vs even spread for the month (see {@see BudgetRealityCheckService::categorySpendPace}).
     *
     * @return array{
     *     budget_amount_base: string,
     *     spent_base: string,
     *     days_in_month: int,
     *     days_elapsed: int,
     *     ideal_daily_base: string,
     *     actual_daily_base: string,
     *     is_over_pace: bool,
     *     is_future_month: bool
     * }|null
     */
    public function getVelocity(Budget $budget, int $categoryId, int $year, int $month): ?array
    {
        return $this->reality->categorySpendPace($budget, $categoryId, $year, $month);
    }
}
