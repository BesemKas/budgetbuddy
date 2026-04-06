<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\BudgetInvitation;
use App\Models\Category;
use App\Models\Transaction;
use App\Services\BudgetAccountAccess;
use App\Services\BudgetAnalyticsService;
use App\Services\CurrentBudget;
use App\Services\LedgerCurrencyService;
use Illuminate\Support\Collection;
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
        $accessibleAccountIds = app(BudgetAccountAccess::class)->accessibleBankAccountIds($user, $budget);
        $this->budgetBaseCurrency = $budget->base_currency;
        $this->monthTotals = $ledger->currentMonthTotals($budget, $accessibleAccountIds);

        $chartMonths = (int) config('budgetbuddy.dashboard_chart_months', 6);
        $this->monthlyTrend = $analytics->monthlyTrend($budget, $chartMonths, $accessibleAccountIds);

        $short = (int) config('budgetbuddy.rolling_average_months.short', 3);
        $long = (int) config('budgetbuddy.rolling_average_months.long', 6);
        $this->avgExpense3 = $analytics->rollingAverageMonthlyExpense($budget, $short, $accessibleAccountIds);
        $this->avgExpense6 = $analytics->rollingAverageMonthlyExpense($budget, $long, $accessibleAccountIds);
        $this->avgIncome3 = $analytics->rollingAverageMonthlyIncome($budget, $short, $accessibleAccountIds);

        $this->daysUntilPayday = $analytics->daysUntilPayday($user->payday_day);
        $next = $analytics->nextPayday($user->payday_day);
        $this->nextPaydayLabel = $next->translatedFormat('j M Y');
        $runway = $analytics->dailyRunway($budget, $accessibleAccountIds, $user);
        $this->dailyRunway = $runway;
        $this->recentTransactions = Transaction::query()
            ->where('budget_id', $budget->id)
            ->whereIn('bank_account_id', $accessibleAccountIds)
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

        $this->authorize('create', Transaction::class);

        $user = auth()->user();
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
};
?>

<div class="mx-auto max-w-5xl px-4 py-6">
    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mb-4 text-sm">{{ session('status') }}</div>
    @endif
    @if ($this->hasPendingTeamInvitation)
        <div role="status" class="alert alert-info mb-4 text-sm">
            {{ __('You have a pending budget invitation. Open the link in the email we sent to :email to join the team. You can keep using your personal budget until you accept.', ['email' => auth()->user()->email]) }}
        </div>
    @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Dashboard') }}</h1>
            <p class="text-base-content/70 mt-1 text-sm">
                {{ __('This month (:start – :end) in :currency.', [
                    'start' => now()->startOfMonth()->toFormattedDateString(),
                    'end' => now()->endOfMonth()->toFormattedDateString(),
                    'currency' => $budgetBaseCurrency,
                ]) }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <label class="swap swap-rotate btn btn-ghost btn-sm">
                <input type="checkbox" wire:model.live="privacyBlur" />
                <span class="swap-off">{{ __('Privacy off') }}</span>
                <span class="swap-on">{{ __('Privacy on') }}</span>
            </label>
            <button type="button" class="btn btn-primary btn-sm" wire:click="openQuickAdd">
                {{ __('Quick add') }}
            </button>
        </div>
    </div>

    <div class="stats stats-vertical mt-6 w-full shadow-sm lg:stats-horizontal">
        <div class="stat bg-base-100 rounded-box border border-base-300/60">
            <div class="stat-title">{{ __('Income') }}</div>
            <div class="stat-value text-success {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                {{ number_format((float) $monthTotals['income'], 2) }}
            </div>
            <div class="stat-desc">{{ $budgetBaseCurrency }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box border border-base-300/60">
            <div class="stat-title">{{ __('Expenses') }}</div>
            <div class="stat-value text-error {{ $privacyBlur ? 'blur-sm select-none' : '' }}">
                {{ number_format((float) $monthTotals['expense'], 2) }}
            </div>
            <div class="stat-desc">{{ $budgetBaseCurrency }}</div>
        </div>
        <div class="stat bg-base-100 rounded-box border border-base-300/60">
            <div class="stat-title">{{ __('Surplus') }}</div>
            <div @class([
                'stat-value',
                'text-success' => (float) $monthTotals['net'] >= 0,
                'text-warning' => (float) $monthTotals['net'] < 0,
                'blur-sm select-none' => $privacyBlur,
            ])>
                {{ number_format((float) $monthTotals['net'], 2) }}
            </div>
            <div class="stat-desc">{{ __('Income minus expenses (base currency)') }}</div>
        </div>
    </div>

    <div class="mt-8 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <div class="card bg-base-100 border border-base-300/60 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-lg">{{ __('Income & expenses by month') }}</h2>
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
            <div class="card-body gap-3">
                <h2 class="card-title text-lg">{{ __('Averages & runway') }}</h2>
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

    <div class="card bg-base-100 mt-8 border border-base-300/60 shadow-sm">
        <div class="card-body gap-2">
            <h2 class="card-title text-lg">{{ __('Recent activity') }}</h2>
            <div class="overflow-x-auto">
                <table class="table table-zebra">
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
                                <td colspan="6" class="text-base-content/60">{{ __('No transactions yet. Add one with Quick add.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal {{ $showQuickAdd ? 'modal-open' : '' }}" role="dialog" aria-modal="true">
        <div class="modal-box max-w-lg">
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
                    <textarea class="textarea textarea-bordered w-full" wire:model="quick_description" rows="2" placeholder="{{ __('Optional') }}"></textarea>
                </label>

                <div class="modal-action">
                    <button type="button" class="btn btn-ghost" wire:click="closeQuickAdd">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="saveQuickTransaction">{{ __('Save') }}</span>
                        <span wire:loading wire:target="saveQuickTransaction" class="loading loading-spinner loading-sm"></span>
                    </button>
                </div>
            </form>
        </div>
        <button type="button" class="modal-backdrop" wire:click="closeQuickAdd" aria-label="{{ __('Close') }}"></button>
    </div>
</div>
