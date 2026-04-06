<?php

namespace App\Services;

use App\Enums\BankAccountKind;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\CategoryMonthBudget;

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
}
