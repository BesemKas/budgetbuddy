<?php

use App\Services\BudgetActivityQuery;
use App\Services\CurrentBudget;
use App\Support\ActivityLabel;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $limit = 100;

    public string $budgetName = '';

    public function mount(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        $this->authorize('view', $budget);
        $this->budgetName = $budget->displayNameFor(auth()->user());
    }

    public function getActivitiesProperty(): \Illuminate\Support\Collection
    {
        $budget = app(CurrentBudget::class)->current();

        return app(BudgetActivityQuery::class)
            ->forBudget($budget, auth()->user())
            ->limit($this->limit)
            ->get();
    }
};
?>

<div class="bb-page max-w-4xl">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Activity') }}</h1>
        <p class="text-base-content/70 mt-1 text-sm">
            {{ __('Recent changes for “:name”. Showing up to :n events.', ['name' => $budgetName, 'n' => $limit]) }}
        </p>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body p-0">
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="table table-zebra table-sm md:table-md min-w-[28rem]">
                    <thead>
                        <tr>
                            <th>{{ __('When') }}</th>
                            <th>{{ __('What') }}</th>
                            <th>{{ __('Who') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->activities as $row)
                            <tr wire:key="act-{{ $row->id }}">
                                <td class="whitespace-nowrap text-sm">{{ $row->created_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td class="text-sm">{{ ActivityLabel::summary($row) }}</td>
                                <td class="text-sm">
                                    @if ($row->causer instanceof \App\Models\User)
                                        {{ $row->causer->name }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-base-content/60">{{ __('No activity recorded yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
