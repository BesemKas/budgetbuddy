<?php

namespace Database\Factories;

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => null,
            'amount' => number_format(fake()->randomFloat(2, 5, 500), 4, '.', ''),
            'type' => LedgerEntryType::Expense,
            'currency_code' => 'ZAR',
            'exchange_rate' => '1',
            'occurred_on' => fake()->date(),
            'description' => fake()->optional()->sentence(),
        ];
    }

    public function configure(): static
    {
        return $this
            ->afterMaking(function (Transaction $transaction): void {
                if (! $transaction->user_id) {
                    $transaction->user_id = User::factory()->create()->id;
                }

                if (! $transaction->bank_account_id) {
                    $transaction->bank_account_id = BankAccount::factory()->create([
                        'user_id' => $transaction->user_id,
                    ])->id;
                }

                if (! $transaction->category_id) {
                    $type = $transaction->type ?? LedgerEntryType::Expense;
                    $account = BankAccount::query()->findOrFail($transaction->bank_account_id);
                    if ($account->budget_id === null) {
                        $user = User::query()->findOrFail($transaction->user_id);
                        if (! $user->budgets()->exists()) {
                            Budget::bootstrapPersonalForUser($user);
                        }
                        $account->forceFill(['budget_id' => $user->budgets()->first()->id])->saveQuietly();
                        $account->refresh();
                    }
                    $budget = Budget::query()->findOrFail($account->budget_id);
                    $category = Category::query()
                        ->visibleToBudget($budget)
                        ->where('type', $type)
                        ->firstOrFail();
                    $transaction->category_id = $category->id;
                }

                $account = BankAccount::query()->findOrFail($transaction->bank_account_id);
                $transaction->currency_code = $account->currency_code;
            })
            ->afterCreating(function (Transaction $transaction): void {
                if ($transaction->budget_id !== null) {
                    return;
                }

                $account = BankAccount::query()->findOrFail($transaction->bank_account_id);
                if ($account->budget_id !== null) {
                    $transaction->forceFill(['budget_id' => $account->budget_id])->saveQuietly();
                }
            });
    }
}
