<?php

namespace App\Notifications;

use App\Models\Budget;
use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategoryBudgetExceededNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Budget $budget,
        public Category $category,
        public int $year,
        public int $month,
        public string $spentBase,
        public string $budgetAmountBase,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $monthLabel = Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');

        return [
            'title' => __('Over budget'),
            'message' => __('Spending on “:name” has exceeded the :month plan.', [
                'name' => $this->category->name,
                'month' => $monthLabel,
            ]),
            'budget_id' => $this->budget->id,
            'category_id' => $this->category->id,
            'year' => $this->year,
            'month' => $this->month,
            'spent_base' => $this->spentBase,
            'budget_amount_base' => $this->budgetAmountBase,
            'kind' => 'category_over_budget',
        ];
    }
}
