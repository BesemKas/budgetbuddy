<?php

namespace App\Notifications;

use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ZeroBasedOverAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Budget $budget,
        public int $year,
        public int $month,
        public string $projectedIncome,
        public string $totalAssigned,
        public string $overageBase,
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
            'title' => __('Over projected income'),
            'message' => __('Category assignments for :month exceed projected income by :amount (base currency).', [
                'month' => $monthLabel,
                'amount' => number_format((float) $this->overageBase, 2),
            ]),
            'budget_id' => $this->budget->id,
            'year' => $this->year,
            'month' => $this->month,
            'projected_income' => $this->projectedIncome,
            'total_assigned' => $this->totalAssigned,
            'overage_base' => $this->overageBase,
            'kind' => 'zero_based_over',
        ];
    }
}
