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

    /**
     * Bank accounts that feed dashboard totals, plan vs actual “spent”, category charts, and pace — a subset of
     * accessible accounts with include_in_budget_reports enabled.
     *
     * @return list<int>
     */
    public function reportingBankAccountIds(User $user, Budget $budget): array
    {
        $accessible = $this->accessibleBankAccountIds($user, $budget);
        if ($accessible === []) {
            return [];
        }

        return BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $accessible)
            ->where('include_in_budget_reports', true)
            ->pluck('id')
            ->map(fn (int|string $id): int => (int) $id)
            ->values()
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
