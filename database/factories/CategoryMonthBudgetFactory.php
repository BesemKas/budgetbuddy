<?php

namespace Database\Factories;

use App\Enums\BudgetPriority;
use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryMonthBudget>
 */
class CategoryMonthBudgetFactory extends Factory
{
    protected $model = CategoryMonthBudget::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $budget = Budget::bootstrapPersonalForUser($user);
        $category = Category::factory()->create([
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'type' => LedgerEntryType::Expense,
        ]);
        $date = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'budget_id' => $budget->id,
            'category_id' => $category->id,
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'amount' => fake()->randomFloat(4, 100, 15000),
            'bank_account_id' => null,
            'priority' => fake()->randomElement(BudgetPriority::cases()),
        ];
    }
}
