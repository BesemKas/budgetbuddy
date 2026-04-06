<?php

namespace App\Services;

use App\Enums\BankAccountKind;
use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountTransferService
{
    public function __construct(
        private LedgerCurrencyService $ledger,
    ) {}

    /**
     * Move funds from one budget account to another using paired ledger rows (expense + income).
     * Only same-currency transfers are supported so balances and reports stay consistent.
     *
     * @throws ValidationException
     */
    public function transfer(
        User $user,
        Budget $budget,
        int $fromBankAccountId,
        int $toBankAccountId,
        string $amount,
        string $occurredOn,
        ?string $description = null,
    ): void {
        if ($fromBankAccountId === $toBankAccountId) {
            throw ValidationException::withMessages([
                'transfer_to_bank_account_id' => __('Choose a different account than the source.'),
            ]);
        }

        $from = BankAccount::query()->where('budget_id', $budget->id)->findOrFail($fromBankAccountId);
        $to = BankAccount::query()->where('budget_id', $budget->id)->findOrFail($toBankAccountId);

        if (strtoupper($from->currency_code) !== strtoupper($to->currency_code)) {
            throw ValidationException::withMessages([
                'transfer_amount' => __('Transfers are only available between accounts that use the same currency.'),
            ]);
        }

        $amountNorm = number_format((float) $amount, 4, '.', '');
        if (bccomp($amountNorm, '0.0001', 4) < 0) {
            throw ValidationException::withMessages([
                'transfer_amount' => __('Enter an amount greater than zero.'),
            ]);
        }

        if ($from->kind === BankAccountKind::Liquid && bccomp((string) $from->balance, $amountNorm, 4) < 0) {
            throw ValidationException::withMessages([
                'transfer_amount' => __('That amount is more than the available balance in :account.', ['account' => $from->name]),
            ]);
        }

        $expenseCategory = Category::query()
            ->where('name', 'Account transfer')
            ->where('type', LedgerEntryType::Expense)
            ->where('is_system', true)
            ->whereNull('user_id')
            ->firstOrFail();

        $incomeCategory = Category::query()
            ->where('name', 'Account transfer')
            ->where('type', LedgerEntryType::Income)
            ->where('is_system', true)
            ->whereNull('user_id')
            ->firstOrFail();

        $rateFrom = $this->ledger->effectiveRateToBase($from, $budget);
        $rateTo = $this->ledger->effectiveRateToBase($to, $budget);

        DB::transaction(function () use ($user, $budget, $from, $to, $amountNorm, $occurredOn, $description, $expenseCategory, $incomeCategory, $rateFrom, $rateTo): void {
            Transaction::query()->create([
                'user_id' => $user->id,
                'budget_id' => $budget->id,
                'bank_account_id' => $from->id,
                'category_id' => $expenseCategory->id,
                'amount' => $amountNorm,
                'type' => LedgerEntryType::Expense,
                'currency_code' => $from->currency_code,
                'exchange_rate' => $rateFrom,
                'occurred_on' => $occurredOn,
                'description' => $description,
            ]);

            Transaction::query()->create([
                'user_id' => $user->id,
                'budget_id' => $budget->id,
                'bank_account_id' => $to->id,
                'category_id' => $incomeCategory->id,
                'amount' => $amountNorm,
                'type' => LedgerEntryType::Income,
                'currency_code' => $to->currency_code,
                'exchange_rate' => $rateTo,
                'occurred_on' => $occurredOn,
                'description' => $description,
            ]);
        });
    }
}
