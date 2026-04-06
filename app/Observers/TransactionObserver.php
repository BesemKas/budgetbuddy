<?php

namespace App\Observers;

use App\Models\BankAccount;
use App\Models\Transaction;

class TransactionObserver
{
    public function saved(Transaction $transaction): void
    {
        $originalAccountId = $transaction->getOriginal('bank_account_id');

        if ($transaction->wasChanged('bank_account_id') && $originalAccountId !== null) {
            BankAccount::query()->find($originalAccountId)?->recalculateBalanceFromTransactions();
        }

        $transaction->bankAccount?->recalculateBalanceFromTransactions();
    }

    public function deleted(Transaction $transaction): void
    {
        BankAccount::query()->find($transaction->bank_account_id)?->recalculateBalanceFromTransactions();
    }
}
