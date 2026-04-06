<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class LedgerCurrencyService
{
    /**
     * Base-currency units per one unit of the account currency.
     */
    public function effectiveRateToBase(BankAccount $account, Budget $budget): string
    {
        if (strtoupper($account->currency_code) === strtoupper($budget->base_currency)) {
            return '1';
        }

        if ($account->exchange_rate !== null) {
            return (string) $account->exchange_rate;
        }

        return '1';
    }

    /**
     * Signed amount in base currency for a transaction (income positive, expense negative).
     */
    public function signedAmountInBase(Transaction $transaction, Budget $budget): string
    {
        $rate = (string) ($transaction->exchange_rate ?? '1');
        $amount = (string) $transaction->amount;
        $signed = $transaction->type === LedgerEntryType::Income ? $amount : bcmul($amount, '-1', 4);

        return bcmul($signed, $rate, 4);
    }

    /**
     * @param  list<int>|null  $limitToBankAccountIds  If set, only these accounts (e.g. viewer’s shared accounts). Empty list yields zero totals.
     * @return array{income: string, expense: string, net: string}
     */
    public function periodTotalsInBase(Budget $budget, Carbon $start, Carbon $end, ?array $limitToBankAccountIds = null): array
    {
        if (is_array($limitToBankAccountIds) && $limitToBankAccountIds === []) {
            return [
                'income' => '0',
                'expense' => '0',
                'net' => '0',
            ];
        }

        $query = Transaction::query()
            ->where('budget_id', $budget->id)
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()]);

        if (is_array($limitToBankAccountIds)) {
            $query->whereIn('bank_account_id', $limitToBankAccountIds);
        }

        $row = $query->selectRaw(
            'COALESCE(SUM(CASE WHEN type = ? THEN amount * COALESCE(exchange_rate, 1) ELSE 0 END), 0) as income,
             COALESCE(SUM(CASE WHEN type = ? THEN amount * COALESCE(exchange_rate, 1) ELSE 0 END), 0) as expense',
            [LedgerEntryType::Income->value, LedgerEntryType::Expense->value]
        )->first();

        $income = $this->normalizeDecimalString($row->income ?? '0');
        $expense = $this->normalizeDecimalString($row->expense ?? '0');
        $net = bcsub($income, $expense, 4);

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
        ];
    }

    /**
     * @param  list<int>|null  $limitToBankAccountIds
     * @return array{income: string, expense: string, net: string}
     */
    public function currentMonthTotals(Budget $budget, ?array $limitToBankAccountIds = null): array
    {
        return $this->periodTotalsInBase(
            $budget,
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
            $limitToBankAccountIds
        );
    }

    private function normalizeDecimalString(string|float|int $value): string
    {
        if (is_string($value)) {
            return $value === '' ? '0' : $value;
        }

        return (string) $value;
    }
}
