<?php

use App\Enums\BudgetPriority;
use App\Enums\LedgerEntryType;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Services\BudgetMonthCopyService;
use App\Services\CurrentBudget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public int $year;

    public int $month;

    public string $projectedIncome = '0';

    /**
     * @var array<int, array{amount: string, bank_account_id: ?int, priority: ?string}>
     */
    public array $lines = [];

    public string $budgetBaseCurrency = 'ZAR';

    public bool $canEdit = false;

    public function mount(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        $this->authorize('view', $budget);
        $this->budgetBaseCurrency = $budget->base_currency;
        $this->year = (int) now()->year;
        $this->month = (int) now()->month;
        $this->loadMonth($currentBudget);
    }

    public function updatedYear(): void
    {
        $this->year = max(2000, min(2100, $this->year));
        $this->loadMonth(app(CurrentBudget::class));
    }

    public function updatedMonth(): void
    {
        $this->month = max(1, min(12, $this->month));
        $this->loadMonth(app(CurrentBudget::class));
    }

    public function goToPreviousMonth(CurrentBudget $currentBudget): void
    {
        $d = Carbon::createFromDate($this->year, $this->month, 1)->subMonth();
        $this->year = (int) $d->year;
        $this->month = (int) $d->month;
        $this->loadMonth($currentBudget);
    }

    public function goToNextMonth(CurrentBudget $currentBudget): void
    {
        $d = Carbon::createFromDate($this->year, $this->month, 1)->addMonth();
        $this->year = (int) $d->year;
        $this->month = (int) $d->month;
        $this->loadMonth($currentBudget);
    }

    public function loadMonth(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        $summary = BudgetMonthSummary::query()->firstOrCreate(
            [
                'budget_id' => $budget->id,
                'year' => $this->year,
                'month' => $this->month,
            ],
            ['projected_income' => '0']
        );

        $this->projectedIncome = (string) $summary->projected_income;

        $this->canEdit = $budget->roleFor(auth()->user())?->canEditMonthlyBudget() ?? false;

        $categories = $this->expenseCategories($budget);
        $existing = CategoryMonthBudget::query()
            ->where('budget_id', $budget->id)
            ->where('year', $this->year)
            ->where('month', $this->month)
            ->get()
            ->keyBy('category_id');

        $this->lines = [];
        foreach ($categories as $category) {
            $row = $existing->get($category->id);
            $this->lines[$category->id] = [
                'amount' => $row ? (string) $row->amount : '0',
                'bank_account_id' => $row?->bank_account_id,
                'priority' => $row?->priority?->value,
            ];
        }
    }

    public function expenseCategories(Budget $budget): Collection
    {
        return Category::query()
            ->visibleToBudget($budget)
            ->where('type', LedgerEntryType::Expense)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, \App\Models\Category>
     */
    #[Computed]
    public function categories(): Collection
    {
        return $this->expenseCategories(app(CurrentBudget::class)->current());
    }

    /**
     * @return Collection<int, \App\Models\BankAccount>
     */
    #[Computed]
    public function bankAccounts(): Collection
    {
        return app(CurrentBudget::class)->current()->bankAccounts()->orderBy('name')->get();
    }

    public function saveProjectedIncome(CurrentBudget $currentBudget): void
    {
        $this->authorizeEdit($currentBudget);
        $budget = $currentBudget->current();

        $v = Validator::make(
            ['projectedIncome' => $this->projectedIncome],
            ['projectedIncome' => ['required', 'numeric', 'min:0']],
            [],
            ['projectedIncome' => __('Projected income')]
        );

        if ($v->fails()) {
            $this->addError('projectedIncome', $v->errors()->first('projectedIncome'));

            return;
        }

        BudgetMonthSummary::query()->updateOrCreate(
            [
                'budget_id' => $budget->id,
                'year' => $this->year,
                'month' => $this->month,
            ],
            ['projected_income' => $v->validated()['projectedIncome']]
        );

        $this->resetErrorBag('projectedIncome');
    }

    public function saveLine(int $categoryId, CurrentBudget $currentBudget): void
    {
        $this->authorizeEdit($currentBudget);
        $budget = $currentBudget->current();

        if (! isset($this->lines[$categoryId])) {
            return;
        }

        $line = $this->lines[$categoryId];
        $bankRaw = $line['bank_account_id'] ?? null;
        if ($bankRaw === '' || $bankRaw === null) {
            $bankId = null;
        } else {
            $bankId = (int) $bankRaw;
        }

        $priorityRaw = $line['priority'] ?? null;
        if ($priorityRaw === '') {
            $priorityRaw = null;
        }

        $v = Validator::make(
            [
                'amount' => $line['amount'],
                'bank_account_id' => $bankId,
                'priority' => $priorityRaw,
            ],
            [
                'amount' => ['required', 'numeric', 'min:0'],
                'bank_account_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('bank_accounts', 'id')->where('budget_id', $budget->id),
                ],
                'priority' => ['nullable', 'string', Rule::enum(BudgetPriority::class)],
            ],
            [],
            [
                'amount' => __('Amount'),
            ]
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $message) {
                $this->addError('line_'.$categoryId, $message);
            }

            return;
        }

        $validated = $v->validated();

        $priorityEnum = null;
        if (array_key_exists('priority', $validated) && $validated['priority'] !== null) {
            $priorityEnum = BudgetPriority::from($validated['priority']);
        }

        CategoryMonthBudget::query()->updateOrCreate(
            [
                'budget_id' => $budget->id,
                'category_id' => $categoryId,
                'year' => $this->year,
                'month' => $this->month,
            ],
            [
                'amount' => $validated['amount'],
                'bank_account_id' => $validated['bank_account_id'] ?? null,
                'priority' => $priorityEnum,
            ]
        );

        $this->resetErrorBag('line_'.$categoryId);
    }

    public function copyPreviousMonth(CurrentBudget $currentBudget, BudgetMonthCopyService $copyService): void
    {
        $this->authorizeEdit($currentBudget);
        $budget = $currentBudget->current();

        $copyService->copyFromPreviousMonth($budget, $this->year, $this->month);
        $this->loadMonth($currentBudget);
    }

    public function totalAssigned(): string
    {
        $total = '0';
        foreach ($this->lines as $line) {
            $amt = (string) ($line['amount'] ?? '0');
            if ($amt === '') {
                $amt = '0';
            }
            $total = bcadd($total, $amt, 4);
        }

        return $total;
    }

    #[Computed]
    public function monthLabel(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)->translatedFormat('F Y');
    }

    private function authorizeEdit(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        $role = $budget->roleFor(auth()->user());
        if ($role === null || ! $role->canEditMonthlyBudget()) {
            abort(403);
        }
    }
};
?>

<div class="bb-page max-w-5xl">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Budget planner') }}</h1>
            <p class="text-base-content/70 mt-1 text-sm">
                {{ __('Set projected income and monthly amounts per category for this budget.') }}
            </p>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-4 p-4 sm:p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-outline"
                        wire:click="goToPreviousMonth"
                    >
                        {{ __('Previous month') }}
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline"
                        wire:click="goToNextMonth"
                    >
                        {{ __('Next month') }}
                    </button>
                    <span class="text-base-content/80 font-medium">
                        {{ $this->monthLabel }}
                    </span>
                </div>
                @if ($canEdit)
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        wire:click="copyPreviousMonth"
                        wire:confirm="{{ __('Copy amounts and projected income from the previous month?') }}"
                    >
                        {{ __('Copy from previous month') }}
                    </button>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="form-control w-full max-w-md">
                    <span class="label"><span class="label-text font-medium">{{ __('Projected income') }} ({{ $budgetBaseCurrency }})</span></span>
                    <input
                        type="text"
                        inputmode="decimal"
                        class="input input-bordered w-full @error('projectedIncome') input-error @enderror"
                        wire:model="projectedIncome"
                        wire:blur="saveProjectedIncome"
                        @disabled(! $canEdit)
                    />
                    @error('projectedIncome')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>
                <div class="flex flex-col justify-end gap-1 text-sm">
                    <span class="text-base-content/70">{{ __('Total assigned to categories') }}</span>
                    <span class="font-mono text-lg">{{ number_format((float) $this->totalAssigned(), 2) }} {{ $budgetBaseCurrency }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-4 border border-base-300/60 shadow-sm">
        <div class="card-body p-0">
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="table table-zebra table-sm md:table-md min-w-[40rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Category') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th>{{ __('Linked account') }}</th>
                            <th>{{ __('Priority') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->categories as $category)
                            <tr wire:key="budget-line-{{ $category->id }}">
                                <td class="font-medium">{{ $category->name }}</td>
                                <td class="text-end">
                                    <input
                                        type="text"
                                        inputmode="decimal"
                                        class="input input-bordered input-sm w-28 max-w-full text-end @error('line_'.$category->id) input-error @enderror"
                                        wire:model="lines.{{ $category->id }}.amount"
                                        wire:blur="saveLine({{ $category->id }})"
                                        @disabled(! $canEdit)
                                    />
                                    @error('line_'.$category->id)
                                        <span class="block text-xs text-error">{{ $message }}</span>
                                    @enderror
                                </td>
                                <td>
                                    <select
                                        class="select select-bordered select-sm w-full max-w-xs"
                                        wire:model="lines.{{ $category->id }}.bank_account_id"
                                        wire:change="saveLine({{ $category->id }})"
                                        @disabled(! $canEdit)
                                    >
                                        <option value="">{{ __('— None —') }}</option>
                                        @foreach ($this->bankAccounts as $account)
                                            <option value="{{ $account->id }}">{{ $account->name }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td>
                                    <select
                                        class="select select-bordered select-sm w-full max-w-[12rem]"
                                        wire:model="lines.{{ $category->id }}.priority"
                                        wire:change="saveLine({{ $category->id }})"
                                        @disabled(! $canEdit)
                                    >
                                        <option value="">{{ __('—') }}</option>
                                        @foreach (\App\Enums\BudgetPriority::cases() as $p)
                                            <option value="{{ $p->value }}">{{ ucfirst($p->value) }}</option>
                                        @endforeach
                                    </select>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-base-content/60">{{ __('No expense categories available for this budget.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
