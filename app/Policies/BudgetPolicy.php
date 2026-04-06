<?php

namespace App\Policies;

use App\Enums\BudgetRole;
use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $user->budgets()->where('budgets.id', $budget->id)->exists();
    }

    public function invite(User $user, Budget $budget): bool
    {
        return $budget->roleFor($user) === BudgetRole::Owner;
    }
}
