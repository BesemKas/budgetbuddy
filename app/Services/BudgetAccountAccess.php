<?php

namespace App\Services;

use App\Enums\BudgetRole;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BudgetAccountAccess
{
    /**
     * Bank account IDs in this budget the user may use (view transactions, post). Owners see all.
     *
     * @return list<int>
     */
    public function accessibleBankAccountIds(User $user, Budget $budget): array
    {
        $role = $budget->roleFor($user);
        if ($role === null) {
            return [];
        }

        if ($role === BudgetRole::Owner) {
            return BankAccount::query()
                ->where('budget_id', $budget->id)
                ->pluck('id')
                ->map(fn (int|string $id): int => (int) $id)
                ->all();
        }

        return DB::table('budget_shared_bank_accounts')
            ->where('budget_id', $budget->id)
            ->where('user_id', $user->id)
            ->pluck('bank_account_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    public function userCanAccessBankAccount(User $user, BankAccount $account): bool
    {
        if ($account->budget_id === null) {
            return false;
        }

        $budget = Budget::query()->find($account->budget_id);
        if ($budget === null) {
            return false;
        }

        return in_array($account->id, $this->accessibleBankAccountIds($user, $budget), true);
    }
}
