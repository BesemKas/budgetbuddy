<?php

use App\Enums\BankAccountKind;
use App\Enums\BudgetPriority;
use App\Enums\BudgetRole;
use App\Enums\LedgerEntryType;
use App\Enums\SmartMode;
use App\Models\Budget;
use App\Models\BudgetMonthSummary;
use App\Models\Category;
use App\Models\CategoryMonthBudget;
use App\Models\SinkingFundRule;
use App\Services\BudgetInAppNotifier;
use App\Services\BudgetMonthCopyService;
use App\Services\BudgetNotificationService;
use App\Services\BudgetRealityCheckService;
use App\Services\BudgetService;
use App\Services\CurrentBudget;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /** @var \Illuminate\Database\Eloquent\Collection<int, SinkingFundRule>|null */
    public $sinkingFundRules = null;

    public ?int $sinking_category_id = null;

    public string $sinking_amount = '';

    public string $sinking_goal_name = '';

    public string $sinking_target_amount = '';

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

        $this->sinkingFundRules = SinkingFundRule::query()
            ->where('budget_id', $budget->id)
            ->with('category')
            ->orderBy('id')
            ->get();

        app(BudgetNotificationService::class)->markBudgetMonthNotificationsAsRead(
            auth()->user(),
            $budget->id,
            $this->year,
            $this->month
        );
    }

    public function saveSinkingFundRule(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        if ($budget->roleFor(auth()->user()) !== BudgetRole::Owner) {
            abort(403);
        }

        $v = Validator::make(
            [
                'sinking_category_id' => $this->sinking_category_id,
                'sinking_amount' => $this->sinking_amount,
                'sinking_goal_name' => $this->sinking_goal_name === '' ? null : $this->sinking_goal_name,
                'sinking_target_amount' => $this->sinking_target_amount === '' ? null : $this->sinking_target_amount,
            ],
            [
                'sinking_category_id' => ['required', 'integer', 'exists:categories,id'],
                'sinking_amount' => ['required', 'numeric', 'min:0.01'],
                'sinking_goal_name' => ['nullable', 'string', 'max:255'],
                'sinking_target_amount' => ['nullable', 'numeric', 'min:0'],
            ]
        );

        if ($v->fails()) {
            foreach ($v->errors()->all() as $message) {
                $this->addError('sinking_fund', $message);
            }

            return;
        }

        $validated = $v->validated();
        $category = Category::query()->visibleToBudget($budget)->whereKey($validated['sinking_category_id'])->first();
        if ($category === null || $category->type !== LedgerEntryType::Expense) {
            $this->addError('sinking_fund', __('Pick an expense category for this budget.'));

            return;
        }

        $goalName = $validated['sinking_goal_name'] ?? null;
        $targetRaw = $validated['sinking_target_amount'] ?? null;
        $targetAmount = ($targetRaw !== null && $targetRaw !== '') ? $targetRaw : null;

        SinkingFundRule::query()->updateOrCreate(
            [
                'budget_id' => $budget->id,
                'category_id' => $category->id,
            ],
            [
                'monthly_amount' => $validated['sinking_amount'],
                'goal_name' => $goalName !== null && $goalName !== '' ? $goalName : null,
                'target_amount' => $targetAmount,
                'is_active' => true,
            ]
        );

        $this->sinking_category_id = null;
        $this->sinking_amount = '';
        $this->sinking_goal_name = '';
        $this->sinking_target_amount = '';
        $this->resetErrorBag('sinking_fund');
        $this->loadMonth($currentBudget);
    }

    public function deleteSinkingFundRule(int $ruleId, CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        if ($budget->roleFor(auth()->user()) !== BudgetRole::Owner) {
            abort(403);
        }

        SinkingFundRule::query()
            ->where('budget_id', $budget->id)
            ->whereKey($ruleId)
            ->delete();

        $this->loadMonth($currentBudget);
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{user_id: int, user_name: string, total_base: string}>
     */
    public function whoSpentWhat(): \Illuminate\Support\Collection
    {
        return app(BudgetRealityCheckService::class)->expenseTotalsByUserForMonth(
            app(CurrentBudget::class)->current(),
            $this->year,
            $this->month
        );
    }

    public function isBudgetOwner(): bool
    {
        return app(CurrentBudget::class)->current()->roleFor(auth()->user()) === BudgetRole::Owner;
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

        $income = $v->validated()['projectedIncome'];
        if (auth()->user()->smart_mode === SmartMode::ZeroBased) {
            if (bccomp($this->totalAssigned(), (string) $income, 4) > 0) {
                $this->addError(
                    'projectedIncome',
                    __('In zero-based mode, projected income must be at least the total assigned to categories.')
                );

                return;
            }
        }

        BudgetMonthSummary::query()->updateOrCreate(
            [
                'budget_id' => $budget->id,
                'year' => $this->year,
                'month' => $this->month,
            ],
            ['projected_income' => $income]
        );

        $this->resetErrorBag('projectedIncome');

        app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $this->year, $this->month);
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

        if (auth()->user()->smart_mode === SmartMode::ZeroBased) {
            $hypothetical = '0';
            foreach ($this->lines as $cid => $l) {
                $amt = (string) ($cid === $categoryId ? $validated['amount'] : ($l['amount'] ?? '0'));
                if ($amt === '') {
                    $amt = '0';
                }
                $hypothetical = bcadd($hypothetical, $amt, 4);
            }
            if (bccomp($hypothetical, (string) $this->projectedIncome, 4) > 0) {
                $this->addError(
                    'line_'.$categoryId,
                    __('In zero-based mode, total assigned cannot exceed projected income.')
                );

                return;
            }
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

        app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $this->year, $this->month);
    }

    public function copyPreviousMonth(CurrentBudget $currentBudget, BudgetMonthCopyService $copyService): void
    {
        $this->authorizeEdit($currentBudget);
        $budget = $currentBudget->current();

        try {
            $copyService->copyFromPreviousMonth($budget, $this->year, $this->month, auth()->user());
        } catch (ValidationException $e) {
            $messages = $e->errors();
            $first = $messages['copy'][0] ?? $e->getMessage();
            $this->addError('copy', $first);

            return;
        }

        $this->resetErrorBag('copy');
        $this->loadMonth($currentBudget);

        app(BudgetInAppNotifier::class)->notifyPlanLevelAlerts($budget, $this->year, $this->month);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function categoryPace(int $categoryId): ?array
    {
        return app(BudgetService::class)->getVelocity(
            app(CurrentBudget::class)->current(),
            $categoryId,
            $this->year,
            $this->month
        );
    }

    /**
     * @return list<int>
     */
    public function unassignedFundingCategoryIds(): array
    {
        return app(BudgetRealityCheckService::class)->unassignedFundingCategoryIds(
            app(CurrentBudget::class)->current(),
            $this->year,
            $this->month
        );
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

    /**
     * @return array{total_liquid_base: string, total_budgeted: string, is_funded: bool, shortfall_base: string}
     */
    public function liquidityAssessment(): array
    {
        return app(BudgetRealityCheckService::class)->liquidityAssessment(
            app(CurrentBudget::class)->current(),
            $this->year,
            $this->month
        );
    }

    /**
     * @return array{projected_income: string, total_assigned: string, is_within_income: bool, overage_base: string}
     */
    public function zeroBasedAssessment(): array
    {
        return app(BudgetRealityCheckService::class)->zeroBasedAssessment(
            app(CurrentBudget::class)->current(),
            $this->year,
            $this->month
        );
    }

    /**
     * @return list<int>
     */
    public function creditLinkedCategoryIds(): array
    {
        return app(BudgetRealityCheckService::class)->categoryIdsLinkedToCreditAccounts(
            app(CurrentBudget::class)->current(),
            $this->year,
            $this->month
        );
    }

    public function syncPlannerFromServer(CurrentBudget $currentBudget): void
    {
        $budget = $currentBudget->current();
        $this->authorize('view', $budget);
        $this->loadMonth($currentBudget);
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
                    <button
                        type="button"
                        class="btn btn-ghost btn-xs"
                        title="{{ __('Reload this month from the server (e.g. after a partner updates the plan).') }}"
                        wire:click="syncPlannerFromServer"
                    >
                        {{ __('Refresh') }}
                    </button>
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
            @error('copy')
                <div role="alert" class="alert alert-error alert-soft text-sm">{{ $message }}</div>
            @enderror
        </div>
    </div>

    @php
        $liq = $this->liquidityAssessment();
        $zb = $this->zeroBasedAssessment();
    @endphp

    @if (! $liq['is_funded'])
        <div role="alert" class="alert alert-warning mt-4 shadow-sm">
            <span>
                {{ __('Liquid cash (:currency) is below what you have budgeted. Shortfall: :amount.', [
                    'currency' => $budgetBaseCurrency,
                    'amount' => number_format((float) $liq['shortfall_base'], 2),
                ]) }}
            </span>
        </div>
    @endif

    @if (! $zb['is_within_income'])
        <div role="alert" class="alert alert-info mt-4 shadow-sm">
            <span>
                {{ __('Assigned categories exceed projected income by :amount :currency (zero-based check).', [
                    'amount' => number_format((float) $zb['overage_base'], 2),
                    'currency' => $budgetBaseCurrency,
                ]) }}
            </span>
        </div>
    @endif

    @php
        $unassignedIds = $this->unassignedFundingCategoryIds();
        $unassignedNames = $this->categories->filter(fn ($c) => in_array($c->id, $unassignedIds, true))->pluck('name')->implode(', ');
    @endphp
    @if ($unassignedNames !== '')
        <div role="status" class="alert alert-warning alert-soft mt-4 shadow-sm">
            <span class="text-sm">
                {{ __(':names — planned amount with no linked account (link an account so funding checks are clear).', ['names' => $unassignedNames]) }}
            </span>
        </div>
    @endif

    <div class="card bg-base-100 mt-4 border border-base-300/60 shadow-sm">
        <div class="card-body p-0">
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="table table-zebra table-sm md:table-md min-w-[40rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Category') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                            <th class="hidden md:table-cell">{{ __('Pace') }}</th>
                            <th>{{ __('Linked account') }}</th>
                            <th>{{ __('Priority') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->categories as $category)
                            <tr wire:key="budget-line-{{ $category->id }}">
                                <td>
                                    <div class="font-medium">{{ $category->name }}</div>
                                    @if (in_array($category->id, $this->creditLinkedCategoryIds(), true))
                                        <p class="text-warning mt-1 text-xs">
                                            {{ __('Linked to a credit account — not counted as liquid cash for funding checks.') }}
                                        </p>
                                    @endif
                                </td>
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
                                <td class="hidden align-top md:table-cell">
                                    @php $pace = $this->categoryPace($category->id); @endphp
                                    @if ($pace && ! ($pace['is_future_month'] ?? false))
                                        <div class="text-xs">
                                            <span class="font-mono">{{ number_format((float) $pace['actual_daily_base'], 2) }}</span>
                                            <span class="text-base-content/50"> / </span>
                                            <span class="font-mono">{{ number_format((float) $pace['ideal_daily_base'], 2) }}</span>
                                            <span class="text-base-content/60">{{ $budgetBaseCurrency }}</span>
                                        </div>
                                        <div class="text-base-content/50 text-[0.65rem]">{{ __('spent vs even spread / day') }}</div>
                                        @if ($pace['is_over_pace'])
                                            <span class="badge badge-warning badge-xs mt-1">{{ __('Over pace') }}</span>
                                        @endif
                                    @else
                                        <span class="text-base-content/40 text-xs">—</span>
                                    @endif
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
                                            <option value="{{ $account->id }}">
                                                {{ $account->name }}@if ($account->kind === BankAccountKind::Credit) — {{ __('Credit') }}@endif
                                            </option>
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
                                <td colspan="5" class="text-base-content/60">{{ __('No expense categories available for this budget.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-3 p-4 sm:p-6">
            <h2 class="card-title text-base sm:text-lg">{{ __('Who spent what') }}</h2>
            <p class="text-base-content/60 text-xs">
                {{ __('Expense totals in :currency (base) for this month, by person who recorded the transaction.', ['currency' => $budgetBaseCurrency]) }}
            </p>
            <div class="overflow-x-auto overscroll-x-contain rounded-lg">
                <table class="table table-zebra table-sm md:table-md min-w-[16rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Person') }}</th>
                            <th class="text-end">{{ __('Expenses') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->whoSpentWhat() as $row)
                            <tr wire:key="who-spent-{{ $row['user_id'] }}">
                                <td>{{ $row['user_name'] }}</td>
                                <td class="text-end font-mono">{{ number_format((float) $row['total_base'], 2) }} {{ $budgetBaseCurrency }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="text-base-content/60">{{ __('No expense transactions this month yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($this->isBudgetOwner())
        <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
            <div class="card-body gap-4 p-4 sm:p-6">
                <h2 class="card-title text-base sm:text-lg">{{ __('Sinking funds') }}</h2>
                <p class="text-base-content/60 text-xs">
                    {{ __('Each month (on the 1st) the scheduled job adds this amount to the category plan for that month. Use it for goals you fund a little at a time.') }}
                </p>
                @error('sinking_fund')
                    <div role="alert" class="alert alert-error alert-soft text-sm">{{ $message }}</div>
                @enderror
                <form wire:submit="saveSinkingFundRule" class="flex flex-col gap-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                        <label class="form-control w-full max-w-xs">
                            <span class="label"><span class="label-text text-sm">{{ __('Category') }}</span></span>
                            <select class="select select-bordered select-sm w-full" wire:model="sinking_category_id">
                                <option value="">{{ __('Choose…') }}</option>
                                @foreach ($this->categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="form-control w-full max-w-[12rem]">
                            <span class="label"><span class="label-text text-sm">{{ __('Monthly add') }} ({{ $budgetBaseCurrency }})</span></span>
                            <input
                                type="text"
                                inputmode="decimal"
                                class="input input-bordered input-sm w-full font-mono"
                                wire:model="sinking_amount"
                                placeholder="0.00"
                            />
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">{{ __('Save rule') }}</button>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="form-control w-full">
                            <span class="label"><span class="label-text text-sm">{{ __('Goal name') }} ({{ __('optional') }})</span></span>
                            <input
                                type="text"
                                class="input input-bordered input-sm w-full"
                                wire:model="sinking_goal_name"
                                placeholder="{{ __('e.g. Annual insurance') }}"
                            />
                        </label>
                        <label class="form-control w-full">
                            <span class="label"><span class="label-text text-sm">{{ __('Savings target') }} ({{ __('optional') }})</span></span>
                            <input
                                type="text"
                                inputmode="decimal"
                                class="input input-bordered input-sm w-full font-mono"
                                wire:model="sinking_target_amount"
                                placeholder="{{ __('Total amount to save') }}"
                            />
                        </label>
                    </div>
                    <p class="text-base-content/60 text-[0.7rem]">
                        {{ __('Target is a hint for labels and “≈ months to goal” from your monthly add — not enforced automatically.') }}
                    </p>
                </form>
                @if ($sinkingFundRules !== null && $sinkingFundRules->isNotEmpty())
                    <ul class="divide-y divide-base-300/50 rounded-box border border-base-300/60">
                        @foreach ($sinkingFundRules as $rule)
                            <li class="flex flex-wrap items-start justify-between gap-2 px-3 py-2 text-sm" wire:key="sink-rule-{{ $rule->id }}">
                                <span class="min-w-0">
                                    <span class="font-medium">{{ $rule->category->name }}</span>
                                    @if ($rule->goal_name)
                                        <span class="text-base-content/80"> — {{ $rule->goal_name }}</span>
                                    @endif
                                    <span class="text-base-content/70 block text-xs">
                                        {{ __(':amount / mo', ['amount' => number_format((float) $rule->monthly_amount, 2).' '.$budgetBaseCurrency]) }}
                                        @if ($rule->target_amount !== null && (float) $rule->target_amount > 0)
                                            · {{ __('Target :amount', ['amount' => number_format((float) $rule->target_amount, 2).' '.$budgetBaseCurrency]) }}
                                        @endif
                                        @php $estMo = $rule->estimatedMonthsAtMonthlyRate(); @endphp
                                        @if ($estMo !== null)
                                            · {{ __('≈ :n months to target at this rate', ['n' => $estMo]) }}
                                        @endif
                                    </span>
                                </span>
                                <button
                                    type="button"
                                    class="btn btn-ghost btn-xs text-error"
                                    wire:click="deleteSinkingFundRule({{ $rule->id }})"
                                    wire:confirm="{{ __('Remove this sinking fund rule?') }}"
                                >
                                    {{ __('Remove') }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endif
</div>
