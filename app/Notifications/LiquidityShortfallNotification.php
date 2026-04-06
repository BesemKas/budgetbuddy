<?php

namespace App\Notifications;

use App\Models\Budget;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LiquidityShortfallNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Budget $budget,
        public int $year,
        public int $month,
        public string $totalLiquidBase,
        public string $totalBudgeted,
        public string $shortfallBase,
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
            'title' => __('Liquid cash shortfall'),
            'message' => __('Budgeted spending for :month exceeds liquid account balances by :amount (base currency).', [
                'month' => $monthLabel,
                'amount' => number_format((float) $this->shortfallBase, 2),
            ]),
            'budget_id' => $this->budget->id,
            'year' => $this->year,
            'month' => $this->month,
            'total_liquid_base' => $this->totalLiquidBase,
            'total_budgeted' => $this->totalBudgeted,
            'shortfall_base' => $this->shortfallBase,
            'kind' => 'liquidity_shortfall',
        ];
    }
}
