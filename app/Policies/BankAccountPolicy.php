<?php

namespace App\Policies;

use App\Enums\BudgetRole;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\User;
use App\Services\CurrentBudget;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        if ($bankAccount->budget_id === null) {
            return false;
        }

        return $user->budgets()->where('budgets.id', $bankAccount->budget_id)->exists();
    }

    public function create(User $user): bool
    {
        return $this->roleInCurrentBudget($user)?->canManageAccounts() ?? false;
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        if ($bankAccount->budget_id === null) {
            return false;
        }

        return $this->roleInBudget($user, $bankAccount->budget_id)?->canManageAccounts() ?? false;
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $this->update($user, $bankAccount);
    }

    private function roleInCurrentBudget(User $user): ?BudgetRole
    {
        $budget = app(CurrentBudget::class)->current();

        return $budget->roleFor($user);
    }

    private function roleInBudget(User $user, int $budgetId): ?BudgetRole
    {
        $budget = Budget::query()->find($budgetId);

        return $budget?->roleFor($user);
    }
}
