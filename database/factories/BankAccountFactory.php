<?php

namespace Database\Factories;

use App\Enums\BankAccountKind;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'budget_id' => null,
            'name' => fake()->company(),
            'kind' => BankAccountKind::Liquid,
            'currency_code' => 'ZAR',
            'balance' => '0',
            'include_in_budget_reports' => true,
            'exchange_rate' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (BankAccount $account): void {
            if ($account->budget_id !== null) {
                return;
            }

            $user = User::query()->findOrFail($account->user_id);
            if (! $user->budgets()->exists()) {
                Budget::bootstrapPersonalForUser($user);
            }

            $budget = $user->budgets()->first();
            $account->forceFill(['budget_id' => $budget->id])->saveQuietly();
        });
    }
}
