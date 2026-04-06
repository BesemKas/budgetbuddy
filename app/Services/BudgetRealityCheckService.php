<?php

namespace App\Services;

use App\Enums\BankAccountKind;
use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\CategoryMonthBudget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BudgetRealityCheckService
{
    public function __construct(
        private LedgerCurrencyService $ledgerCurrency,
    ) {}

    /**
     * Sum of liquid account balances in the budget base currency (cheque, savings, cash — not credit cards).
     */
    public function totalLiquidBalanceInBase(Budget $budget): string
    {
        $total = '0';
        foreach ($budget->bankAccounts()->where('kind', BankAccountKind::Liquid)->cursor() as $account) {
            $rate = $this->ledgerCurrency->effectiveRateToBase($account, $budget);
            $inBase = bcmul((string) $account->balance, $rate, 4);
            $total = bcadd($total, $inBase, 4);
        }

        return $total;
    }

    /**
     * Total planned spend for the month (category month budgets).
     */
    public function totalBudgetedForMonth(Budget $budget, int $year, int $month): string
    {
        $total = '0';
        foreach (
            CategoryMonthBudget::query()
                ->where('budget_id', $budget->id)
                ->where('year', $year)
                ->where('month', $month)
                ->cursor() as $row
        ) {
            $total = bcadd($total, (string) $row->amount, 4);
        }

        return $total;
    }

    /**
     * @return array{
     *     total_liquid_base: string,
     *     total_budgeted: string,
     *     is_funded: bool,
     *     shortfall_base: string
     * }
     */
    public function liquidityAssessment(Budget $budget, int $year, int $month): array
    {
        $liquid = $this->totalLiquidBalanceInBase($budget);
        $budgeted = $this->totalBudgetedForMonth($budget, $year, $month);
        $cmp = bccomp($budgeted, $liquid, 4);
        $isFunded = $cmp <= 0;
        $shortfall = $isFunded ? '0' : bcsub($budgeted, $liquid, 4);

        return [
            'total_liquid_base' => $liquid,
            'total_budgeted' => $budgeted,
            'is_funded' => $isFunded,
            'shortfall_base' => $shortfall,
        ];
    }

    /**
     * @return array{
     *     projected_income: string,
     *     total_assigned: string,
     *     is_within_income: bool,
     *     overage_base: string
     * }
     */
    public function zeroBasedAssessment(Budget $budget, int $year, int $month): array
    {
        $summary = BudgetMonthSummary::query()
            ->where('budget_id', $budget->id)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        $projected = $summary !== null ? (string) $summary->projected_income : '0';
        $assigned = $this->totalBudgetedForMonth($budget, $year, $month);
        $cmp = bccomp($assigned, $projected, 4);
        $within = $cmp <= 0;
        $overage = $within ? '0' : bcsub($assigned, $projected, 4);

        return [
            'projected_income' => $projected,
            'total_assigned' => $assigned,
            'is_within_income' => $within,
            'overage_base' => $overage,
        ];
    }

    /**
     * Expense category IDs whose month line links to a credit (non-liquid) account.
     *
     * @return list<int>
     */
    public function categoryIdsLinkedToCreditAccounts(Budget $budget, int $year, int $month): array
    {
        $ids = [];
        foreach (
            CategoryMonthBudget::query()
                ->where('budget_id', $budget->id)
                ->where('year', $year)
                ->where('month', $month)
                ->whereNotNull('bank_account_id')
                ->with('bankAccount')
                ->cursor() as $row
        ) {
            $account = $row->bankAccount;
            if ($account === null) {
                continue;
            }
            if ($account->kind === BankAccountKind::Credit) {
                $ids[] = (int) $row->category_id;
            }
        }

        return $ids;
    }

    /**
     * Expense category IDs with a positive month plan but no linked bank account (“unassigned funding” target).
     *
     * @return list<int>
     */
    public function unassignedFundingCategoryIds(Budget $budget, int $year, int $month): array
    {
        $ids = [];
        foreach (
            CategoryMonthBudget::query()
                ->where('budget_id', $budget->id)
                ->where('year', $year)
                ->where('month', $month)
                ->whereNull('bank_account_id')
                ->cursor() as $row
        ) {
            if (bccomp((string) $row->amount, '0', 4) > 0) {
                $ids[] = (int) $row->category_id;
            }
        }

        return $ids;
    }

    /**
     * Sum of expense transactions for a category in a calendar month, in budget base currency.
     */
    public function sumCategoryExpenseInBaseForMonth(int $budgetId, int $categoryId, int $year, int $month): string
    {
        $row = Transaction::query()
            ->where('budget_id', $budgetId)
            ->where('category_id', $categoryId)
            ->where('type', LedgerEntryType::Expense)
            ->whereYear('occurred_on', $year)
            ->whereMonth('occurred_on', $month)
            ->selectRaw('COALESCE(SUM(amount * COALESCE(exchange_rate, 1)), 0) as total')
            ->first();

        return $this->normalizeDecimal($row->total ?? '0');
    }

    /**
     * Total expense transactions for the month in budget base currency.
     */
    public function totalExpenseInBaseForMonth(Budget $budget, int $year, int $month): string
    {
        $row = Transaction::query()
            ->where('budget_id', $budget->id)
            ->where('type', LedgerEntryType::Expense)
            ->whereYear('occurred_on', $year)
            ->whereMonth('occurred_on', $month)
            ->selectRaw('COALESCE(SUM(amount * COALESCE(exchange_rate, 1)), 0) as total')
            ->first();

        return $this->normalizeDecimal($row->total ?? '0');
    }

    /**
     * Expense totals in base currency grouped by the user who recorded the transaction.
     *
     * @return Collection<int, array{user_id: int, user_name: string, total_base: string}>
     */
    public function expenseTotalsByUserForMonth(Budget $budget, int $year, int $month): Collection
    {
        $rows = Transaction::query()
            ->where('transactions.budget_id', $budget->id)
            ->where('transactions.type', LedgerEntryType::Expense)
            ->whereYear('transactions.occurred_on', $year)
            ->whereMonth('transactions.occurred_on', $month)
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->groupBy('transactions.user_id', 'users.name')
            ->orderBy('users.name')
            ->selectRaw('transactions.user_id as user_id, users.name as user_name, COALESCE(SUM(transactions.amount * COALESCE(transactions.exchange_rate, 1)), 0) as total_base')
            ->get();

        return $rows->map(fn ($r): array => [
            'user_id' => (int) $r->user_id,
            'user_name' => (string) $r->user_name,
            'total_base' => $this->normalizeDecimal($r->total_base ?? '0'),
        ]);
    }

    /**
     * Spend pace vs an even spread of the monthly budget (ideal daily vs actual daily).
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
    public function categorySpendPace(Budget $budget, int $categoryId, int $year, int $month): ?array
    {
        $line = CategoryMonthBudget::query()
            ->where('budget_id', $budget->id)
            ->where('category_id', $categoryId)
            ->where('year', $year)
            ->where('month', $month)
            ->first();

        if ($line === null) {
            return null;
        }

        $budgetAmount = (string) $line->amount;
        if (bccomp($budgetAmount, '0', 4) <= 0) {
            return null;
        }

        $spent = $this->sumCategoryExpenseInBaseForMonth($budget->id, $categoryId, $year, $month);

        $monthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();
        $today = now()->startOfDay();
        $daysInMonth = (int) $monthStart->daysInMonth;

        if ($today->lt($monthStart)) {
            return [
                'budget_amount_base' => $budgetAmount,
                'spent_base' => $spent,
                'days_in_month' => $daysInMonth,
                'days_elapsed' => 0,
                'ideal_daily_base' => bcdiv($budgetAmount, (string) $daysInMonth, 4),
                'actual_daily_base' => '0',
                'is_over_pace' => false,
                'is_future_month' => true,
            ];
        }

        if ($today->gt($monthEnd)) {
            $daysElapsed = $daysInMonth;
        } else {
            $daysElapsed = (int) $monthStart->diffInDays($today) + 1;
        }

        $idealDaily = bcdiv($budgetAmount, (string) $daysInMonth, 4);
        $actualDaily = $daysElapsed > 0 ? bcdiv($spent, (string) $daysElapsed, 4) : '0';
        $isOverPace = $daysElapsed > 0 && bccomp($actualDaily, $idealDaily, 4) > 0;

        return [
            'budget_amount_base' => $budgetAmount,
            'spent_base' => $spent,
            'days_in_month' => $daysInMonth,
            'days_elapsed' => $daysElapsed,
            'ideal_daily_base' => $idealDaily,
            'actual_daily_base' => $actualDaily,
            'is_over_pace' => $isOverPace,
            'is_future_month' => false,
        ];
    }

    private function normalizeDecimal(mixed $value): string
    {
        if (is_string($value)) {
            return $value === '' ? '0' : $value;
        }

        return number_format((float) $value, 4, '.', '');
    }
}
