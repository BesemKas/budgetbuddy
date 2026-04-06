<?php

use App\Models\BudgetSnapshot;
use App\Services\CurrentBudget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public function mount(CurrentBudget $currentBudget): void
    {
        $this->authorize('view', $currentBudget->current());
    }

    public function getSnapshotsProperty(): Collection
    {
        $budget = app(CurrentBudget::class)->current();

        return BudgetSnapshot::query()
            ->where('budget_id', $budget->id)
            ->orderByDesc('period')
            ->limit((int) config('budgetbuddy.snapshot_trend_months', 24))
            ->get();
    }

    /**
     * Chronological data for the trend chart (oldest → newest).
     *
     * @return array{labels: list<string>, income: list<float>, expense: list<float>, net: list<float>, currency: string}
     */
    public function getSnapshotTrendProperty(): array
    {
        $budget = app(CurrentBudget::class)->current();
        $limit = (int) config('budgetbuddy.snapshot_trend_months', 24);

        $snaps = BudgetSnapshot::query()
            ->where('budget_id', $budget->id)
            ->orderBy('period')
            ->limit($limit)
            ->get();

        $labels = [];
        $income = [];
        $expense = [];
        $net = [];
        $currency = $budget->base_currency;

        foreach ($snaps as $snap) {
            $labels[] = (string) $snap->period;
            $p = $snap->payload;
            $income[] = (float) ($p['income'] ?? 0);
            $expense[] = (float) ($p['expense'] ?? 0);
            $net[] = (float) ($p['net'] ?? 0);
            if (isset($p['base_currency']) && is_string($p['base_currency'])) {
                $currency = $p['base_currency'];
            }
        }

        return [
            'labels' => $labels,
            'income' => $income,
            'expense' => $expense,
            'net' => $net,
            'currency' => $currency,
        ];
    }
};
?>

<div class="mx-auto max-w-4xl px-4 py-6">
    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Budget history') }}</h1>
    <p class="text-base-content/70 mt-1 text-sm">
        {{ __('Month-end totals stored for this budget (from the scheduled snapshot job or manual command). Amounts are in base currency.') }}
    </p>

    @if (count($this->snapshotTrend['labels']) >= 2)
        <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-lg">{{ __('Snapshot trend') }}</h2>
                <p class="text-base-content/60 text-xs">{{ __('Income, expenses, and net from stored month-end snapshots (chronological).') }}</p>
                <div
                    wire:key="snap-trend-{{ md5(json_encode($this->snapshotTrend)) }}"
                    wire:ignore
                    class="bb-snapshot-trend mt-2 min-h-[300px] w-full"
                    data-labels='@json($this->snapshotTrend['labels'])'
                    data-income='@json($this->snapshotTrend['income'])'
                    data-expense='@json($this->snapshotTrend['expense'])'
                    data-net='@json($this->snapshotTrend['net'])'
                    data-currency="{{ e($this->snapshotTrend['currency']) }}"
                    data-income-label="{{ e(__('Income')) }}"
                    data-expense-label="{{ e(__('Expenses')) }}"
                    data-net-label="{{ e(__('Net')) }}"
                ></div>
            </div>
        </div>
    @endif

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body p-0">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Month') }}</th>
                            <th class="text-end">{{ __('Income') }}</th>
                            <th class="text-end">{{ __('Expenses') }}</th>
                            <th class="text-end">{{ __('Net') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->snapshots as $snap)
                            <tr wire:key="snap-{{ $snap->id }}">
                                <td class="whitespace-nowrap font-medium">{{ $snap->period }}</td>
                                <td class="text-end font-mono">{{ number_format((float) ($snap->payload['income'] ?? 0), 2) }}</td>
                                <td class="text-end font-mono">{{ number_format((float) ($snap->payload['expense'] ?? 0), 2) }}</td>
                                <td class="text-end font-mono">{{ number_format((float) ($snap->payload['net'] ?? 0), 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-base-content/60">{{ __('No snapshots yet. They are created on the 1st of each month, or run the budget snapshot Artisan command manually.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
