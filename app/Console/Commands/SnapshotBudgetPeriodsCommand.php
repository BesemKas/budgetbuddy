<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\BudgetSnapshot;
use App\Services\LedgerCurrencyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SnapshotBudgetPeriodsCommand extends Command
{
    protected $signature = 'budget:snapshot-periods {--period= : YYYY-MM (default: previous calendar month)}';

    protected $description = 'Store month-end income/expense/net totals per budget for analytics and history.';

    public function handle(LedgerCurrencyService $ledger): int
    {
        $periodOption = $this->option('period');

        if (is_string($periodOption) && $periodOption !== '') {
            if (! preg_match('/^\d{4}-\d{2}$/', $periodOption)) {
                $this->error(__('Use --period=YYYY-MM (e.g. 2026-03).'));

                return self::INVALID;
            }

            $start = Carbon::parse($periodOption.'-01')->startOfMonth();
        } else {
            $start = now()->subMonthNoOverflow()->startOfMonth();
        }

        $end = $start->copy()->endOfMonth();
        $period = $start->format('Y-m');

        $count = 0;

        foreach (Budget::query()->cursor() as $budget) {
            $totals = $ledger->periodTotalsInBase($budget, $start, $end, null);

            BudgetSnapshot::query()->updateOrCreate(
                [
                    'budget_id' => $budget->id,
                    'period' => $period,
                ],
                [
                    'payload' => [
                        'income' => $totals['income'],
                        'expense' => $totals['expense'],
                        'net' => $totals['net'],
                        'base_currency' => $budget->base_currency,
                    ],
                ]
            );
            $count++;
        }

        $this->info(__('Stored :count snapshot(s) for :period.', ['count' => $count, 'period' => $period]));

        return self::SUCCESS;
    }
}
