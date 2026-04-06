<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;
use App\Services\CurrentBudget;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Category $category): bool
    {
        if ($category->is_system && $category->user_id === null) {
            return true;
        }

        if ($category->budget_id === null) {
            return false;
        }

        return $user->budgets()->where('budgets.id', $category->budget_id)->exists();
    }

    public function create(User $user): bool
    {
        return app(CurrentBudget::class)->current()->roleFor($user)?->canManageCategories() ?? false;
    }

    public function update(User $user, Category $category): bool
    {
        if ($category->is_system || $category->user_id === null) {
            return false;
        }

        $budget = app(CurrentBudget::class)->current();

        if ((int) $category->budget_id !== (int) $budget->id) {
            return false;
        }

        return $budget->roleFor($user)?->canManageCategories() ?? false;
    }

    public function delete(User $user, Category $category): bool
    {
        return $this->update($user, $category);
    }
}
