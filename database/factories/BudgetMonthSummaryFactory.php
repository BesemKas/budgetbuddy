<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetMonthSummary>
 */
class BudgetMonthSummaryFactory extends Factory
{
    protected $model = BudgetMonthSummary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        $budget = Budget::bootstrapPersonalForUser($user);
        $date = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'budget_id' => $budget->id,
            'year' => (int) $date->format('Y'),
            'month' => (int) $date->format('n'),
            'projected_income' => fake()->randomFloat(4, 10000, 80000),
        ];
    }
}
