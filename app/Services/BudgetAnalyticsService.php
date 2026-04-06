<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Transaction;
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

    /**
     * Expense totals in base currency for the current calendar month, grouped by category.
     *
     * @param  list<int>|null  $limitToBankAccountIds
     * @return list<array{category_id: int, name: string, total: string, percent: float}>
     */
    public function currentMonthExpenseByCategory(Budget $budget, ?array $limitToBankAccountIds): array
    {
        if (is_array($limitToBankAccountIds) && $limitToBankAccountIds === []) {
            return [];
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $query = Transaction::query()
            ->where('transactions.budget_id', $budget->id)
            ->where('transactions.type', LedgerEntryType::Expense)
            ->whereBetween('transactions.occurred_on', [$start->toDateString(), $end->toDateString()])
            ->join('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('categories.internal_transfer', false)
            ->selectRaw('categories.id as category_id, categories.name as category_name, SUM(transactions.amount * COALESCE(transactions.exchange_rate, 1)) as total_base');

        if (is_array($limitToBankAccountIds)) {
            $query->whereIn('transactions.bank_account_id', $limitToBankAccountIds);
        }

        $rows = $query
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_base')
            ->get();

        $grand = '0';
        foreach ($rows as $r) {
            $grand = bcadd($grand, $this->normalizeDecimalString($r->total_base), 4);
        }

        $out = [];
        foreach ($rows as $r) {
            $total = $this->normalizeDecimalString($r->total_base);
            $percent = 0.0;
            if (bccomp($grand, '0', 4) !== 0) {
                $percent = (float) bcmul(bcdiv($total, $grand, 8), '100', 2);
            }

            $out[] = [
                'category_id' => (int) $r->category_id,
                'name' => (string) $r->category_name,
                'total' => $total,
                'percent' => $percent,
            ];
        }

        return $out;
    }

    private function normalizeDecimalString(string|float|int $value): string
    {
        if (is_string($value)) {
            return $value === '' ? '0' : $value;
        }

        return (string) $value;
    }
}
