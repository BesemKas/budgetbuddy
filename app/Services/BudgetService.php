<?php

namespace App\Services;

/**
 * Phase 4 aggregate: analytics (rolling averages, runway, category breakdown) and ledger totals.
 * Prefer injecting the specific service you need; this is a convenience facade for documentation parity with the build plan.
 */
class BudgetService
{
    public function __construct(
        private BudgetAnalyticsService $analytics,
        private LedgerCurrencyService $ledger,
    ) {}

    public function analytics(): BudgetAnalyticsService
    {
        return $this->analytics;
    }

    public function ledger(): LedgerCurrencyService
    {
        return $this->ledger;
    }
}
