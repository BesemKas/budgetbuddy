<?php

namespace App\Policies;

use App\Enums\BudgetRole;
use App\Models\Budget;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Transaction $transaction): bool
    {
        if ($transaction->budget_id === null) {
            return false;
        }

        return $user->budgets()->where('budgets.id', $transaction->budget_id)->exists();
    }

    public function create(User $user): bool
    {
        return app(CurrentBudget::class)->current()->roleFor($user) !== null;
    }

    public function update(User $user, Transaction $transaction): bool
    {
        if ($transaction->budget_id === null) {
            return false;
        }

        if (! $user->budgets()->where('budgets.id', $transaction->budget_id)->exists()) {
            return false;
        }

        $role = Budget::query()->find($transaction->budget_id)?->roleFor($user);

        if ($role === BudgetRole::Owner) {
            return true;
        }

        return $role === BudgetRole::Viewer && $transaction->user_id === $user->id;
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $this->update($user, $transaction);
    }
}
