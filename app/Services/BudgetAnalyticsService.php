<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Carbon;

class BudgetAnalyticsService
{
    public function __construct(
        private LedgerCurrencyService $ledger,
    ) {}

    /**
     * @param  list<int>|null  $limitToBankAccountIds
     * @return list<array{period: string, label: string, income: float, expense: float, net: float}>
     */
    public function monthlyTrend(Budget $budget, int $months, ?array $limitToBankAccountIds): array
    {
        $months = max(1, min(24, $months));
        $out = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i)->startOfMonth();
            $totals = $this->ledger->periodTotalsInBase(
                $budget,
                $month,
                $month->copy()->endOfMonth(),
                $limitToBankAccountIds
            );

            $out[] = [
                'period' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M y'),
                'income' => (float) $totals['income'],
                'expense' => (float) $totals['expense'],
                'net' => (float) $totals['net'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>|null  $limitToBankAccountIds
     */
    public function rollingAverageMonthlyExpense(Budget $budget, int $monthCount, ?array $limitToBankAccountIds): string
    {
        $monthCount = max(1, min(24, $monthCount));
        $sum = '0';

        for ($i = 0; $i < $monthCount; $i++) {
            $month = now()->subMonths($i)->startOfMonth();
            $totals = $this->ledger->periodTotalsInBase(
                $budget,
                $month,
                $month->copy()->endOfMonth(),
                $limitToBankAccountIds
            );
            $sum = bcadd($sum, $totals['expense'], 4);
        }

        return bcdiv($sum, (string) $monthCount, 4);
    }

    /**
     * @param  list<int>|null  $limitToBankAccountIds
     */
    public function rollingAverageMonthlyIncome(Budget $budget, int $monthCount, ?array $limitToBankAccountIds): string
    {
        $monthCount = max(1, min(24, $monthCount));
        $sum = '0';

        for ($i = 0; $i < $monthCount; $i++) {
            $month = now()->subMonths($i)->startOfMonth();
            $totals = $this->ledger->periodTotalsInBase(
                $budget,
                $month,
                $month->copy()->endOfMonth(),
                $limitToBankAccountIds
            );
            $sum = bcadd($sum, $totals['income'], 4);
        }

        return bcdiv($sum, (string) $monthCount, 4);
    }

    /**
     * Sum of account balances converted to budget base currency.
     *
     * @param  list<int>  $bankAccountIds
     */
    public function totalCashInBase(Budget $budget, array $bankAccountIds): string
    {
        if ($bankAccountIds === []) {
            return '0';
        }

        $accounts = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $bankAccountIds)
            ->get();

        $total = '0';

        foreach ($accounts as $account) {
            $rate = $this->ledger->effectiveRateToBase($account, $budget);
            $inBase = bcmul((string) $account->balance, $rate, 4);
            $total = bcadd($total, $inBase, 4);
        }

        return $total;
    }

    public function nextPayday(?int $paydayDay): Carbon
    {
        if ($paydayDay === null) {
            return now()->copy()->endOfMonth()->startOfDay();
        }

        $day = max(1, min(31, $paydayDay));
        $today = now()->startOfDay();
        $startOfMonth = now()->copy()->startOfMonth();
        $thisMonthPayday = $startOfMonth->copy()->day(min($day, $startOfMonth->daysInMonth));

        if ($thisMonthPayday->greaterThanOrEqualTo($today)) {
            return $thisMonthPayday;
        }

        $nextMonth = $startOfMonth->copy()->addMonthNoOverflow();
        $nextMonthPayday = $nextMonth->copy()->day(min($day, $nextMonth->daysInMonth));

        return $nextMonthPayday;
    }

    /**
     * Whole days from today until the next payday (inclusive of payday when future).
     */
    public function daysUntilPayday(?int $paydayDay): int
    {
        $next = $this->nextPayday($paydayDay);
        $today = now()->startOfDay();

        if ($next->lessThan($today)) {
            return 0;
        }

        return (int) $today->diffInDays($next);
    }

    /**
     * Available cash ÷ days until payday (base currency). Null when runway cannot be computed.
     *
     * @param  list<int>  $bankAccountIds
     */
    public function dailyRunway(Budget $budget, array $bankAccountIds, User $user): ?string
    {
        $days = $this->daysUntilPayday($user->payday_day);
        if ($days < 1) {
            return null;
        }

        $cash = $this->totalCashInBase($budget, $bankAccountIds);

        return bcdiv($cash, (string) max(1, $days), 4);
    }
}
