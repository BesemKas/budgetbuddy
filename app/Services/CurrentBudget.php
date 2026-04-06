<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrentBudget
{
    public const SESSION_KEY = 'current_budget_id';

    public function __construct(
        private Request $request,
    ) {}

    public function current(): Budget
    {
        $user = $this->request->user();
        if ($user === null) {
            throw new \RuntimeException('No authenticated user.');
        }

        if ($this->request->hasSession()) {
            $sessionId = $this->request->session()->get(self::SESSION_KEY);

            if ($sessionId !== null) {
                $budget = Budget::query()
                    ->whereKey($sessionId)
                    ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
                    ->first();

                if ($budget !== null) {
                    return $budget;
                }
            }
        }

        $this->ensurePersonalBudgetExists($user);

        $first = $user->budgets()->orderBy('budget_user.id')->first();
        if ($first === null) {
            throw new \RuntimeException('User has no budgets.');
        }

        $this->putSessionBudgetId((int) $first->id);

        return $first;
    }

    public function switchTo(Budget $budget, User $user): void
    {
        $isMember = DB::table('budget_user')
            ->where('budget_id', $budget->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isMember) {
            abort(403);
        }

        $this->putSessionBudgetId((int) $budget->id);

        if ($this->request->hasSession()) {
            activity()
                ->performedOn($budget)
                ->causedBy($user)
                ->log('budget_switched');
        }
    }

    public function ensurePersonalBudgetExists(User $user): void
    {
        if ($user->budgets()->exists()) {
            return;
        }

        Budget::bootstrapPersonalForUser($user);
    }

    public function bootstrapSessionIfNeeded(User $user): void
    {
        $this->ensurePersonalBudgetExists($user);

        if (! $this->request->hasSession()) {
            return;
        }

        if ($this->request->session()->get(self::SESSION_KEY) !== null) {
            return;
        }

        $first = $user->budgets()->orderBy('budget_user.id')->first();
        if ($first !== null) {
            $this->putSessionBudgetId((int) $first->id);
        }
    }

    private function putSessionBudgetId(int $id): void
    {
        if (! $this->request->hasSession()) {
            return;
        }

        $this->request->session()->put(self::SESSION_KEY, $id);
    }
}
