<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AccountDeletionService
{
    /**
     * When non-null, account deletion is blocked and the string is a user-facing reason.
     */
    public function blockingReason(User $user): ?string
    {
        foreach ($user->budgets as $budget) {
            if ($budget->users()->count() > 1) {
                return __('You can only delete your account when you are the only member of every budget you belong to. Leave shared teams or remove other members first.');
            }
        }

        return null;
    }

    public function deleteAccount(User $user): void
    {
        $reason = $this->blockingReason($user);
        if ($reason !== null) {
            throw new InvalidArgumentException($reason);
        }

        DB::transaction(function () use ($user): void {
            $table = config('activitylog.table_name', 'activity_log');
            DB::table($table)
                ->where('causer_type', User::class)
                ->where('causer_id', $user->id)
                ->delete();

            $budgets = $user->budgets()->get();
            foreach ($budgets as $budget) {
                $this->assertSoleMember($budget);
                $budget->delete();
            }

            $user->syncRoles([]);
            $user->delete();
        });
    }

    private function assertSoleMember(Budget $budget): void
    {
        if ($budget->users()->count() !== 1) {
            throw new InvalidArgumentException(__('This budget still has multiple members.'));
        }
    }
}
