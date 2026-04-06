<?php

use App\Enums\LedgerEntryType;
use App\Enums\SmartMode;
use App\Models\BankAccount;
use App\Models\BudgetInvitation;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BudgetAccountAccess;
use App\Services\BudgetAnalyticsService;
use App\Services\BudgetRealityCheckService;
use App\Services\CurrentBudget;
use App\Services\LedgerCurrencyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public bool $privacyBlur = false;

    public bool $showQuickAdd = false;

    public ?int $quick_bank_account_id = null;

    public ?int $quick_category_id = null;

    public string $quick_type = 'expense';

    public string $quick_amount = '';

    public string $quick_occurred_on = '';

    public string $quick_description = '';

    /** @var array{income: string, expense: string, net: string} */
    public array $monthTotals = ['income' => '0', 'expense' => '0', 'net' => '0'];

    public Collection $recentTransactions;

    /** @var \Illuminate\Support\Collection<int, \App\Models\BankAccount> */
    public Collection $bankAccounts;

    public string $budgetBaseCurrency = 'ZAR';

    /** @var list<array{period: string, label: string, income: float, expense: float, net: float}> */
    public array $monthlyTrend = [];

    public string $avgExpense3 = '0';

    public string $avgExpense6 = '0';

    public string $avgIncome3 = '0';

    public ?string $dailyRunway = null;

    public int $daysUntilPayday = 0;

    public string $nextPaydayLabel = '';

    /** @var list<array{category_id: int, name: string, total: string, percent: float}> */
    public array $categoryExpenseBreakdown = [];

    public string $monthPlannedSpend = '0';

    public string $monthActualExpenseBase = '0';

    public bool $showMonthSweepPrompt = false;

    public string $previousMonthLabel = '';

    public string $previousMonthIncome = '0';

    public string $previousMonthExpense = '0';

    public string $previousMonthNet = '0';

    /** True when the user has accounts but none are marked for budget/dashboard totals. */
    public bool $allAccountsExcludedFromReports = false;

    public ?string $liquidityAlertMessage = null;

    public ?string $zeroBasedAlertMessage = null;

    public function mount(LedgerCurrencyService $ledger, CurrentBudget $currentBudget, BudgetAnalyticsService $analytics): void
    {
        $this->privacyBlur = session('dashboard_privacy_blur', false);
        $this->quick_occurred_on = now()->toDateString();
        $this->recentTransactions = collect();
        $this->bankAccounts = collect();
        $this->refreshData($ledger, $currentBudget, $analytics);
    }

    public function refreshData(LedgerCurrencyService $ledger, CurrentBudget $currentBudget, BudgetAnalyticsService $analytics): void
    {
        $user = auth()->user();
        $budget = $currentBudget->current();
        $accountAccess = app(BudgetAccountAccess::class);
        $accessibleAccountIds = $accountAccess->accessibleBankAccountIds($user, $budget);
        $reportingAccountIds = $accountAccess->reportingBankAccountIds($user, $budget);
        $this->allAccountsExcludedFromReports = $accessibleAccountIds !== [] && $reportingAccountIds === [];
        $this->budgetBaseCurrency = $budget->base_currency;
        $this->monthTotals = $ledger->currentMonthTotals($budget, $reportingAccountIds);

        $chartMonths = (int) config('budgetbuddy.dashboard_chart_months', 6);
        $this->monthlyTrend = $analytics->monthlyTrend($budget, $chartMonths, $reportingAccountIds);

        $short = (int) config('budgetbuddy.rolling_average_months.short', 3);
        $long = (int) config('budgetbuddy.rolling_average_months.long', 6);
        $this->avgExpense3 = $analytics->rollingAverageMonthlyExpense($budget, $short, $reportingAccountIds);
        $this->avgExpense6 = $analytics->rollingAverageMonthlyExpense($budget, $long, $reportingAccountIds);
        $this->avgIncome3 = $analytics->rollingAverageMonthlyIncome($budget, $short, $reportingAccountIds);

        $this->daysUntilPayday = $analytics->daysUntilPayday($user->payday_day);
        $next = $analytics->nextPayday($user->payday_day);
        $this->nextPaydayLabel = $next->translatedFormat('j M Y');
        $runway = $analytics->dailyRunway($budget, $reportingAccountIds, $user);
        $this->dailyRunway = $runway;

        $this->categoryExpenseBreakdown = $analytics->currentMonthExpenseByCategory($budget, $reportingAccountIds);

        $y = (int) now()->year;
        $m = (int) now()->month;
        $reality = app(BudgetRealityCheckService::class);
        $this->monthPlannedSpend = $reality->totalBudgetedForMonth($budget, $y, $m);
        $this->monthActualExpenseBase = $reality->totalExpenseInBaseForMonth($budget, $y, $m, $reportingAccountIds);
        $this->showMonthSweepPrompt = $this->shouldShowMonthSweepPrompt($user);

        $this->liquidityAlertMessage = null;
        $this->zeroBasedAlertMessage = null;
        $mode = $user->smart_mode ?? SmartMode::Standard;
        $liquidity = $reality->liquidityAssessment($budget, $y, $m, $reportingAccountIds);
        $zeroBased = $reality->zeroBasedAssessment($budget, $y, $m);

        if (
            in_array($mode, [SmartMode::ZeroBased, SmartMode::Survival, SmartMode::Travel], true)
            && ! $liquidity['is_funded']
        ) {
            $this->liquidityAlertMessage = __('Your category plan totals more than your liquid cash by :amount :currency.', [
                'amount' => number_format((float) $liquidity['shortfall_base'], 2),
                'currency' => $this->budgetBaseCurrency,
            ]);
        }

        if ($mode === SmartMode::ZeroBased && ! $zeroBased['is_within_income']) {
            $this->zeroBasedAlertMessage = __('Assigned amounts exceed projected income by :amount :currency.', [
                'amount' => number_format((float) $zeroBased['overage_base'], 2),
                'currency' => $this->budgetBaseCurrency,
            ]);
        }

        $prevStart = Carbon::now()->subMonth()->startOfMonth();
        $prevEnd = $prevStart->copy()->endOfMonth();
        $prevTotals = $ledger->periodTotalsInBase($budget, $prevStart, $prevEnd, $reportingAccountIds);
        $this->previousMonthLabel = $prevStart->translatedFormat('F Y');
        $this->previousMonthIncome = $prevTotals['income'];
        $this->previousMonthExpense = $prevTotals['expense'];
        $this->previousMonthNet = $prevTotals['net'];

        $this->recentTransactions = $reportingAccountIds === []
            ? collect()
            : Transaction::query()
                ->where('budget_id', $budget->id)
                ->whereIn('bank_account_id', $reportingAccountIds)
                ->with(['bankAccount', 'category', 'user'])
                ->latest('occurred_on')
                ->latest('id')
                ->limit(15)
                ->get();

        $this->bankAccounts = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $accessibleAccountIds)
            ->orderBy('name')
            ->get();
    }

    public function updatedPrivacyBlur(bool $value): void
    {
        session(['dashboard_privacy_blur' => $value]);
    }

    public function dismissMonthSweepPrompt(): void
    {
        auth()->user()->update(['sweep_prompt_dismissed_month' => now()->format('Y-m')]);
        $this->showMonthSweepPrompt = false;
    }

    private function shouldShowMonthSweepPrompt(\App\Models\User $user): bool
    {
        if (now()->day > 7) {
            return false;
        }

        return $user->sweep_prompt_dismissed_month !== now()->format('Y-m');
    }

    public function openQuickAdd(CurrentBudget $currentBudget): void
    {
        $this->resetValidation();
        $this->quick_occurred_on = now()->toDateString();
        $this->quick_amount = '';
        $this->quick_description = '';
        $this->quick_category_id = null;
        $budget = $currentBudget->current();
        $accessibleAccountIds = app(BudgetAccountAccess::class)->accessibleBankAccountIds(auth()->user(), $budget);
        $this->bankAccounts = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $accessibleAccountIds)
            ->orderBy('name')
            ->get();
        $this->showQuickAdd = true;
    }

    public function closeQuickAdd(): void
    {
        $this->showQuickAdd = false;
    }

    public function updatedQuickType(): void
    {
        $this->quick_category_id = null;
    }

    public function saveQuickTransaction(LedgerCurrencyService $ledger, CurrentBudget $currentBudget): void
    {
        $this->validate([
            'quick_bank_account_id' => ['required', 'exists:bank_accounts,id'],
            'quick_category_id' => ['required', 'exists:categories,id'],
            'quick_type' => ['required', 'in:income,expense'],
            'quick_amount' => ['required', 'numeric', 'min:0.01'],
            'quick_occurred_on' => ['required', 'date'],
            'quick_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = auth()->user();
        $mode = $user->smart_mode ?? SmartMode::Standard;
        $survivalThreshold = (float) config('budgetbuddy.survival_expense_note_threshold', 200);

        if ($mode === SmartMode::ZeroBased) {
            $this->validate([
                'quick_description' => ['required', 'string', 'min:3', 'max:1000'],
            ]);
        } elseif ($mode === SmartMode::Survival && $this->quick_type === 'expense' && (float) $this->quick_amount > $survivalThreshold) {
            $this->validate([
                'quick_description' => ['required', 'string', 'min:5', 'max:1000'],
            ]);
        }

        $this->authorize('create', Transaction::class);

        $budget = $currentBudget->current();
        $account = BankAccount::query()->where('budget_id', $budget->id)->findOrFail($this->quick_bank_account_id);
        $this->authorize('view', $account);

        $category = Category::query()->visibleToBudget($budget)->whereKey($this->quick_category_id)->firstOrFail();
        $type = LedgerEntryType::from($this->quick_type);

        if ($category->type !== $type) {
            $this->addError('quick_category_id', __('Pick a category that matches this transaction type.'));

            return;
        }

        $rate = $ledger->effectiveRateToBase($account, $budget);

        Transaction::query()->create([
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'bank_account_id' => $account->id,
            'category_id' => $category->id,
            'amount' => number_format((float) $this->quick_amount, 4, '.', ''),
            'type' => $type,
            'currency_code' => $account->currency_code,
            'exchange_rate' => $rate,
            'occurred_on' => $this->quick_occurred_on,
            'description' => $this->quick_description !== '' ? $this->quick_description : null,
        ]);

        $this->showQuickAdd = false;
        $this->refreshData($ledger, $currentBudget, app(BudgetAnalyticsService::class));
    }

    public function categoriesForType(): Collection
    {
        $budget = app(CurrentBudget::class)->current();
        $type = LedgerEntryType::from($this->quick_type);

        return Category::query()
            ->visibleToBudget($budget)
            ->where('type', $type)
            ->orderBy('name')
            ->get();
    }

    public function getHasPendingTeamInvitationProperty(): bool
    {
        $user = auth()->user();

        return BudgetInvitation::query()
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    #[Computed]
    public function smartMode(): SmartMode
    {
        return auth()->user()->smart_mode ?? SmartMode::Standard;
    }

    #[Computed]
    public function planRemainingBase(): string
    {
        return bcsub($this->monthPlannedSpend, $this->monthActualExpenseBase, 4);
    }

    #[Computed]
    public function planPercentUsed(): ?float
    {
        $planned = (float) $this->monthPlannedSpend;
        if ($planned <= 0.00001) {
            return null;
        }

        return round(((float) $this->monthActualExpenseBase / $planned) * 100, 1);
    }

    #[Computed]
    public function planProgressBarPercent(): int
    {
        $pct = $this->planPercentUsed;
        if ($pct === null) {
            return 0;
        }

        return (int) min(100, round($pct));
    }

    /**
     * @return 'no_plan'|'on_track'|'near_limit'|'over_plan'
     */
    #[Computed]
    public function planStatus(): string
    {
        $planned = (float) $this->monthPlannedSpend;
        $actual = (float) $this->monthActualExpenseBase;
        if ($planned <= 0.00001) {
            return 'no_plan';
        }
        if ($actual > $planned) {
            return 'over_plan';
        }
        $near = (float) config('budgetbuddy.dashboard_plan_near_limit_percent', 85);
        $pct = ($planned > 0) ? ($actual / $planned) * 100 : 0.0;
        if ($pct >= $near) {
            return 'near_limit';
        }

        return 'on_track';
    }

    #[Computed]
    public function daysLeftInMonth(): int
    {
        $start = now()->startOfDay();
        $end = now()->copy()->endOfMonth()->startOfDay();

        return (int) $start->diffInDays($end) + 1;
    }

    #[Computed]
    public function safeDailySpendBase(): ?string
    {
        if ($this->planStatus === 'no_plan' || $this->planStatus === 'over_plan') {
            return null;
        }
        $remaining = (float) $this->planRemainingBase;
        if ($remaining <= 0) {
            return null;
        }
        $days = $this->daysLeftInMonth;
        if ($days <= 0) {
            return null;
        }

        return bcdiv((string) $remaining, (string) $days, 4);
    }

    /**
     * @return list<array{category_id: int, name: string, total: string, percent: float}>
     */
    #[Computed]
    public function topCategoryExpenseRows(): array
    {
        return array_slice($this->categoryExpenseBreakdown, 0, 8);
    }

    #[Computed]
    public function hasMoreCategoryRowsThanTop(): bool
    {
        return count($this->categoryExpenseBreakdown) > 8;
    }

    #[Computed]
    public function planProgressColorClass(): string
    {
        return match ($this->planStatus) {
            'over_plan' => 'progress-error',
            'near_limit' => 'progress-warning',
            'on_track' => 'progress-success',
            default => 'progress-neutral',
        };
    }
};
?>

<div class="bb-page max-w-5xl" wire:poll.60s="refreshData">
    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mb-4 text-sm">{{ session('status') }}</div>
    @endif
    @if ($this->hasPendingTeamInvitation)
        <div role="status" class="alert alert-info mb-4 text-sm">
            {{ __('You have a pending budget invitation. Open the link in the email we sent to :email to join the team. You can keep using your personal budget until you accept.', ['email' => auth()->user()->email]) }}
        </div>
    @endif
    @if ($this->allAccountsExcludedFromReports)
        <div role="alert" class="alert alert-warning mb-4 text-sm">
            {{ __('No bank accounts are included in budget totals. Open ') }}
            <a href="{{ route('accounts.index') }}" wire:navigate class="link link-primary">{{ __('Bank accounts') }}</a>
            {{ __(' and turn on “Include in budget & dashboard” for at least one account.') }}
        </div>
    @endif
    @if ($this->showMonthSweepPrompt)
        <div class="card bg-base-100 border border-base-300/60 shadow-sm mb-4">
            <div class="card-body gap-3 p-4 sm:p-5">
                <h2 class="card-title text-base">{{ __('Month check-in') }}</h2>
                <p class="text-base-content/80 text-sm">
                    {{ __('Close out :month, then set this month’s plan. Shared budgets update here while everyone records (this page refreshes periodically).', ['month' => $previousMonthLabel]) }}
                </p>
                <div class="stats stats-vertical w-full shadow-sm sm:stats-horizontal">
                    <div class="stat bg-base-200/40 rounded-box px-3 py-2">
                        <div class="stat-title text-xs">{{ __('Last month income') }}</div>
                        <div class="stat-value text-lg tabular-nums {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                            {{ number_format((float) $previousMonthIncome, 2) }}
                        </div>
                        <div class="stat-desc">{{ $budgetBaseCurrency }}</div>
                    </div>
                    <div class="stat bg-base-200/40 rounded-box px-3 py-2">
                        <div class="stat-title text-xs">{{ __('Last month expenses') }}</div>
                        <div class="stat-value text-lg tabular-nums text-error {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                            {{ number_format((float) $previousMonthExpense, 2) }}
                        </div>
                        <div class="stat-desc">{{ $budgetBaseCurrency }}</div>
                    </div>
                    <div class="stat bg-base-200/40 rounded-box px-3 py-2">
                        <div class="stat-title text-xs">{{ __('Last month net') }}</div>
                        <div @class([
                            'stat-value text-lg tabular-nums',
                            'text-success' => (float) $previousMonthNet >= 0,
                            'text-warning' => (float) $previousMonthNet < 0,
                            'blur-sm select-none' => $privacyBlur,
                        ])>
                            {{ number_format((float) $previousMonthNet, 2) }}
                        </div>
                        <div class="stat-desc">{{ $budgetBaseCurrency }}</div>
                    </div>
                </div>
                <div class="card-actions flex-wrap justify-end gap-2">
                    <a href="{{ route('budget.planner') }}" wire:navigate class="btn btn-primary btn-sm">{{ __('Open budget planner') }}</a>
                    <button type="button" class="btn btn-ghost btn-sm" wire:click="dismissMonthSweepPrompt">
                        {{ __('Dismiss for this month') }}
                    </button>
                </div>
            </div>
        </div>
    @endif
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">{{ __('This month') }}</h1>
            <p class="text-base-content/70 mt-1 text-sm">
                {{ __('This month (:start – :end) in :currency. Track spending against your plan and stay on budget.', [
                    'start' => now()->startOfMonth()->toFormattedDateString(),
                    'end' => now()->endOfMonth()->toFormattedDateString(),
                    'currency' => $budgetBaseCurrency,
                ]) }}
            </p>
        </div>
        <div class="flex w-full flex-wrap items-stretch gap-2 sm:w-auto sm:items-center">
            <label class="swap swap-rotate btn btn-ghost btn-sm grow sm:grow-0">
                <input type="checkbox" wire:model.live="privacyBlur" />
                <span class="swap-off">{{ __('Privacy off') }}</span>
                <span class="swap-on">{{ __('Privacy on') }}</span>
            </label>
            <a href="{{ route('budget.planner') }}" wire:navigate class="btn btn-outline btn-sm grow sm:grow-0">{{ __('Budget planner') }}</a>
            <button type="button" class="btn btn-primary btn-sm w-full sm:w-auto" wire:click="openQuickAdd">
                {{ __('Quick add') }}
            </button>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-primary/20 shadow-md ring-1 ring-primary/10">
        <div class="card-body gap-4 p-4 sm:p-6">
            @if ($liquidityAlertMessage)
                <div role="status" class="alert alert-warning alert-soft text-sm">{{ $liquidityAlertMessage }}</div>
            @endif
            @if ($zeroBasedAlertMessage)
                <div role="status" class="alert alert-warning alert-soft text-sm">{{ $zeroBasedAlertMessage }}</div>
            @endif
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="card-title text-base sm:text-lg">{{ __('Plan vs actual (this month)') }}</h2>
                    <p class="text-base-content/60 text-xs">
                        {{ __('Planned category totals for this calendar month compared to expenses in :currency (base).', ['currency' => $budgetBaseCurrency]) }}
                    </p>
                </div>
                @if ($this->planStatus !== 'no_plan')
                    <span
                        @class([
                            'badge badge-lg',
                            'badge-success' => $this->planStatus === 'on_track',
                            'badge-warning' => $this->planStatus === 'near_limit',
                            'badge-error' => $this->planStatus === 'over_plan',
                        ])
                    >
                        @if ($this->planStatus === 'on_track')
                            {{ __('On track') }}
                        @elseif ($this->planStatus === 'near_limit')
                            {{ __('Near limit') }}
                        @else
                            {{ __('Over plan') }}
                        @endif
                    </span>
                @endif
            </div>

            @if ($this->planStatus === 'no_plan')
                <div role="status" class="alert alert-info text-sm">
                    <span>{{ __('You have not set this month’s category amounts yet. Add your plan so you can track spending against it.') }}</span>
                    <div class="mt-2">
                        <a href="{{ route('budget.planner') }}" wire:navigate class="btn btn-primary btn-sm">{{ __('Set your plan') }}</a>
                    </div>
                </div>
            @else
                <div>
                    <div class="mb-1 flex flex-wrap items-center justify-between gap-2 text-xs text-base-content/70">
                        <span id="plan-progress-label">{{ __('Plan used') }}</span>
                        @if ($this->planPercentUsed !== null)
                            <span class="font-mono tabular-nums {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format($this->planPercentUsed, 1) }}%</span>
                        @endif
                    </div>
                    <progress
                        id="plan-progress"
                        class="progress {{ $this->planProgressColorClass }} h-3 w-full"
                        max="100"
                        value="{{ $this->planProgressBarPercent }}"
                        aria-labelledby="plan-progress-label"
                        aria-valuenow="{{ $this->planProgressBarPercent }}"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    ></progress>
                    <p class="sr-only">
                        {{ __('Plan used: :pct percent.', ['pct' => $this->planProgressBarPercent]) }}
                    </p>
                </div>
            @endif

            <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-box border border-base-300/50 bg-base-200/30 p-3">
                    <dt class="text-base-content/70 text-xs">{{ __('Planned (categories)') }}</dt>
                    <dd @class(['font-mono text-lg tabular-nums', 'blur-sm select-none' => $privacyBlur])>
                        {{ number_format((float) $monthPlannedSpend, 2) }} {{ $budgetBaseCurrency }}
                    </dd>
                </div>
                <div class="rounded-box border border-base-300/50 bg-base-200/30 p-3">
                    <dt class="text-base-content/70 text-xs">{{ __('Actual (expenses)') }}</dt>
                    <dd @class(['font-mono text-lg tabular-nums text-error', 'blur-sm select-none' => $privacyBlur])>
                        {{ number_format((float) $monthActualExpenseBase, 2) }} {{ $budgetBaseCurrency }}
                    </dd>
                </div>
                @if ($this->planStatus !== 'no_plan')
                    <div class="rounded-box border border-base-300/50 bg-base-200/30 p-3">
                        <dt class="text-base-content/70 text-xs">{{ __('Remaining') }}</dt>
                        <dd
                            @class([
                                'font-mono text-lg tabular-nums',
                                'text-success' => (float) $this->planRemainingBase >= 0,
                                'text-warning' => (float) $this->planRemainingBase < 0,
                                'blur-sm select-none' => $privacyBlur,
                            ])
                        >
                            {{ number_format((float) $this->planRemainingBase, 2) }} {{ $budgetBaseCurrency }}
                        </dd>
                    </div>
                    <div class="rounded-box border border-base-300/50 bg-base-200/30 p-3">
                        <dt class="text-base-content/70 text-xs">{{ __('% of plan used') }}</dt>
                        <dd @class(['font-mono text-lg tabular-nums', 'blur-sm select-none' => $privacyBlur])>
                            @if ($this->planPercentUsed !== null)
                                {{ number_format($this->planPercentUsed, 1) }}%
                            @else
                                {{ __('—') }}
                            @endif
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($this->planStatus !== 'no_plan')
                <div class="flex flex-col gap-1 rounded-box border border-base-300/40 bg-base-200/20 p-3 text-sm sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between sm:gap-4">
                    <div>
                        <span class="text-base-content/70">{{ __('Days left this month') }}</span>
                        <span class="ms-1 font-medium tabular-nums">{{ $this->daysLeftInMonth }}</span>
                    </div>
                    <div>
                        <span class="text-base-content/70">{{ __('Safe spend per day (plan)') }}</span>
                        <span class="ms-1 font-mono tabular-nums {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                            @if ($this->safeDailySpendBase === null)
                                {{ __('—') }}
                            @else
                                {{ number_format((float) $this->safeDailySpendBase, 2) }} {{ $budgetBaseCurrency }}
                            @endif
                        </span>
                    </div>
                </div>
            @endif

            <div class="card-actions justify-end border-t border-base-300/40 pt-2">
                <a href="{{ route('budget.planner') }}" wire:navigate class="btn btn-primary btn-sm">{{ __('Open budget planner') }}</a>
            </div>
        </div>
    </div>

    <div class="stats stats-vertical mt-4 w-full shadow-sm sm:stats-horizontal">
        <div class="stat bg-base-100 rounded-box border border-base-300/60 px-2 py-3 sm:px-3">
            <div class="stat-title text-xs sm:text-sm">{{ __('Income') }}</div>
            <div class="stat-value text-success text-xl tabular-nums sm:text-2xl {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                {{ number_format((float) $monthTotals['income'], 2) }}
            </div>
            <div class="stat-desc text-xs">{{ $budgetBaseCurrency }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box border border-base-300/60 px-2 py-3 sm:px-3">
            <div class="stat-title text-xs sm:text-sm">{{ __('Expenses') }}</div>
            <div class="stat-value text-error text-xl tabular-nums sm:text-2xl {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                {{ number_format((float) $monthTotals['expense'], 2) }}
            </div>
            <div class="stat-desc text-xs">{{ $budgetBaseCurrency }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box border border-base-300/60 px-2 py-3 sm:px-3">
            <div class="stat-title text-xs sm:text-sm">{{ __('Left this month') }}</div>
            <div @class([
                'stat-value text-xl tabular-nums sm:text-2xl',
                'text-success' => (float) $monthTotals['net'] >= 0,
                'text-warning' => (float) $monthTotals['net'] < 0,
                'blur-sm select-none' => $privacyBlur,
            ])>
                {{ number_format((float) $monthTotals['net'], 2) }}
            </div>
            <div class="stat-desc text-xs">{{ __('Income minus expenses (base)') }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:mt-8 xl:grid-cols-2">
        <div class="card bg-base-100 border border-base-300/60 shadow-sm">
            <div class="card-body gap-3 p-4 sm:p-6">
                <h2 class="card-title text-base sm:text-lg">{{ __('Income & expenses by month') }}</h2>
                <p class="text-base-content/60 text-xs">{{ __('Last :n full calendar months (base currency).', ['n' => config('budgetbuddy.dashboard_chart_months')]) }}</p>
                <div
                    wire:key="dash-chart-{{ md5(json_encode($monthlyTrend)) }}"
                    wire:ignore
                    class="bb-dashboard-chart mt-2 min-h-[280px] w-full"
                    data-labels='@json(array_column($monthlyTrend, 'label'))'
                    data-income='@json(array_column($monthlyTrend, 'income'))'
                    data-expense='@json(array_column($monthlyTrend, 'expense'))'
                    data-currency="{{ e($budgetBaseCurrency) }}"
                    data-income-label="{{ e(__('Income')) }}"
                    data-expense-label="{{ e(__('Expenses')) }}"
                ></div>
            </div>
        </div>
        <div class="card bg-base-100 border border-base-300/60 shadow-sm">
            <div class="card-body gap-3 p-4 sm:p-6">
                <h2 class="card-title text-base sm:text-lg">{{ __('Averages & runway') }}</h2>
                <dl class="grid grid-cols-1 gap-3 text-sm">
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-300/40 pb-2">
                        <dt>{{ __('Avg monthly expenses (:n mo)', ['n' => config('budgetbuddy.rolling_average_months.short')]) }}</dt>
                        <dd class="font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format((float) $avgExpense3, 2) }} {{ $budgetBaseCurrency }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-300/40 pb-2">
                        <dt>{{ __('Avg monthly expenses (:n mo)', ['n' => config('budgetbuddy.rolling_average_months.long')]) }}</dt>
                        <dd class="font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format((float) $avgExpense6, 2) }} {{ $budgetBaseCurrency }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-300/40 pb-2">
                        <dt>{{ __('Avg monthly income (:n mo)', ['n' => config('budgetbuddy.rolling_average_months.short')]) }}</dt>
                        <dd class="font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format((float) $avgIncome3, 2) }} {{ $budgetBaseCurrency }}</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-base-300/40 pb-2">
                        <dt>{{ __('Next payday') }}</dt>
                        <dd>{{ $nextPaydayLabel }} ({{ trans_choice(':count day|:count days', $daysUntilPayday, ['count' => $daysUntilPayday]) }})</dd>
                    </div>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <dt>{{ __('Daily runway') }}</dt>
                        <dd class="text-right font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                            @if ($dailyRunway === null)
                                {{ __('—') }}
                            @else
                                {{ number_format((float) $dailyRunway, 2) }} {{ $budgetBaseCurrency }}
                            @endif
                        </dd>
                    </div>
                </dl>
                <p class="text-base-content/60 text-xs">{{ __('Runway divides total cash across accessible accounts (in base currency) by days until payday. Set payday in Settings.') }}</p>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm lg:mt-8">
        <div class="card-body gap-4 p-4 sm:p-6">
            <div>
                <h2 class="card-title text-base sm:text-lg">{{ __('Spending by category') }}</h2>
                <p class="text-base-content/60 text-xs">{{ __('This month’s expenses in :currency (base), by category.', ['currency' => $budgetBaseCurrency]) }}</p>
            </div>
            <div
                wire:key="cat-donut-{{ md5(json_encode($categoryExpenseBreakdown)) }}"
                wire:ignore
                class="bb-category-donut min-h-[240px] w-full max-w-xl mx-auto"
                data-labels='@json(array_column($categoryExpenseBreakdown, 'name'))'
                data-values='@json(array_map(fn (array $r): float => (float) $r['total'], $categoryExpenseBreakdown))'
                data-currency="{{ e($budgetBaseCurrency) }}"
                data-empty-message="{{ e(__('No expense data for this month yet.')) }}"
            ></div>
            <div>
                <h3 class="text-sm font-semibold text-base-content/80">{{ __('Top categories') }}</h3>
                <div class="overflow-x-auto overscroll-x-contain rounded-lg mt-2">
                    <table class="table table-zebra table-sm md:table-md min-w-[18rem]">
                        <thead>
                            <tr>
                                <th>{{ __('Category') }}</th>
                                <th class="text-end">{{ __('%') }}</th>
                                <th class="text-end">{{ __('Amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->topCategoryExpenseRows as $row)
                                <tr wire:key="cat-{{ $row['category_id'] }}">
                                    <td>{{ $row['name'] }}</td>
                                    <td class="text-end font-mono text-sm {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format($row['percent'], 1) }}</td>
                                    <td class="text-end font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">{{ number_format((float) $row['total'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-base-content/60">{{ __('No expenses with categories this month yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($this->hasMoreCategoryRowsThanTop)
                    <p class="mt-3 text-center text-sm">
                        <a href="{{ route('budget.planner') }}" wire:navigate class="link link-primary">{{ __('See full breakdown in Budget planner') }}</a>
                    </p>
                @endif
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm lg:mt-8">
        <div class="card-body gap-3 p-4 sm:p-6">
            <h2 class="card-title text-base sm:text-lg">{{ __('Recent transactions') }}</h2>
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="table table-zebra table-sm md:table-md min-w-[42rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Who') }}</th>
                            <th>{{ __('Account') }}</th>
                            <th>{{ __('Category') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTransactions as $tx)
                            <tr wire:key="tx-{{ $tx->id }}">
                                <td class="whitespace-nowrap">{{ $tx->occurred_on->format('Y-m-d') }}</td>
                                <td class="text-sm">{{ $tx->user->name }}</td>
                                <td>{{ $tx->bankAccount->name }}</td>
                                <td>{{ $tx->category->name }}</td>
                                <td>
                                    <span @class(['badge badge-sm', 'badge-success' => $tx->type === \App\Enums\LedgerEntryType::Income, 'badge-error' => $tx->type === \App\Enums\LedgerEntryType::Expense])>
                                        {{ $tx->type->value }}
                                    </span>
                                </td>
                                <td class="text-end font-mono {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                                    {{ number_format((float) $tx->amount, 2) }} {{ $tx->currency_code }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-base-content/60">
                                    {{ __('No transactions yet. Add one with Quick add,') }}
                                    <a href="{{ route('transactions.import') }}" wire:navigate class="link link-primary">{{ __('import') }}</a>{{ __(', or open ') }}
                                    <a href="{{ route('budget.planner') }}" wire:navigate class="link link-primary">{{ __('Budget planner') }}</a>.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal {{ $showQuickAdd ? 'modal-open' : '' }} p-4 sm:p-0" role="dialog" aria-modal="true">
        <div class="modal-box bb-modal-box">
            <h3 class="font-bold text-lg">{{ __('Quick add transaction') }}</h3>
            <form wire:submit="saveQuickTransaction" class="mt-4 flex flex-col gap-4">
                <label class="form-control w-full">
                    <span class="label-text">{{ __('Account') }}</span>
                    <select class="select select-bordered w-full" wire:model="quick_bank_account_id">
                        <option value="">{{ __('Choose…') }}</option>
                        @foreach ($bankAccounts as $acc)
                            <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->currency_code }})</option>
                        @endforeach
                    </select>
                    @error('quick_bank_account_id')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="form-control w-full">
                        <span class="label-text">{{ __('Type') }}</span>
                        <select class="select select-bordered w-full" wire:model.live="quick_type">
                            <option value="income">{{ __('Income') }}</option>
                            <option value="expense">{{ __('Expense') }}</option>
                        </select>
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text">{{ __('Category') }}</span>
                        <select class="select select-bordered w-full" wire:model="quick_category_id">
                            <option value="">{{ __('Choose…') }}</option>
                            @foreach ($this->categoriesForType() as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('quick_category_id')
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="form-control w-full">
                        <span class="label-text">{{ __('Amount') }}</span>
                        <input type="text" inputmode="decimal" class="input input-bordered w-full font-mono" wire:model="quick_amount" placeholder="0.00" />
                        @error('quick_amount')
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        @enderror
                    </label>
                    <label class="form-control w-full">
                        <span class="label-text">{{ __('Date') }}</span>
                        <input type="date" class="input input-bordered w-full" wire:model="quick_occurred_on" />
                        @error('quick_occurred_on')
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <label class="form-control w-full">
                    <span class="label-text">{{ __('Note') }}</span>
                    @if ($this->smartMode === \App\Enums\SmartMode::ZeroBased)
                        <span class="label-text-alt text-base-content/70">{{ __('Required — describe what this money is for.') }}</span>
                    @elseif ($this->smartMode === \App\Enums\SmartMode::Survival)
                        <span class="label-text-alt text-base-content/70">{{ __('Large expenses need a short explanation in this mode.') }}</span>
                    @endif
                    <textarea
                        class="textarea textarea-bordered w-full"
                        wire:model="quick_description"
                        rows="2"
                        placeholder="{{ $this->smartMode === \App\Enums\SmartMode::ZeroBased ? __('e.g. Groceries for the week') : __('Optional') }}"
                    ></textarea>
                    @error('quick_description')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>

                <div class="modal-action flex-col gap-2 sm:flex-row">
                    <button type="button" class="btn btn-ghost w-full sm:w-auto" wire:click="closeQuickAdd">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveQuickTransaction">{{ __('Save') }}</span>
                        <span wire:loading wire:target="saveQuickTransaction" class="loading loading-spinner loading-sm"></span>
                    </button>
                </div>
            </form>
        </div>
        <button type="button" class="modal-backdrop" wire:click="closeQuickAdd" aria-label="{{ __('Close') }}"></button>
    </div>
</div>
