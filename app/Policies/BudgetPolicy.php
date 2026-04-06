<?php

namespace App\Policies;

use App\Models\Budget;
use App\Models\User;

class BudgetPolicy
{
    public function view(User $user, Budget $budget): bool
    {
        return $user->budgets()->where('budgets.id', $budget->id)->exists();
    }
}
