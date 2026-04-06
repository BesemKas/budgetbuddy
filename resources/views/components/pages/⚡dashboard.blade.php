<?php

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Category;
use App\Models\Transaction;
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

    public function mount(LedgerCurrencyService $ledger, CurrentBudget $currentBudget): void
    {
        $this->privacyBlur = session('dashboard_privacy_blur', false);
        $this->quick_occurred_on = now()->toDateString();
        $this->recentTransactions = collect();
        $this->bankAccounts = collect();
        $this->refreshData($ledger, $currentBudget);
    }

    public function refreshData(LedgerCurrencyService $ledger, CurrentBudget $currentBudget): void
    {
        $user = auth()->user();
        $budget = $currentBudget->current();
        $this->budgetBaseCurrency = $budget->base_currency;
        $this->monthTotals = $ledger->currentMonthTotals($budget);
        $this->recentTransactions = Transaction::query()
            ->where('budget_id', $budget->id)
            ->with(['bankAccount', 'category', 'user'])
            ->latest('occurred_on')
            ->latest('id')
            ->limit(15)
            ->get();

        $this->bankAccounts = BankAccount::query()
            ->where('budget_id', $budget->id)
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
        $this->bankAccounts = BankAccount::query()
            ->where('budget_id', $budget->id)
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
        $this->refreshData($ledger, $currentBudget);
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
};
?>

<div class="mx-auto max-w-5xl px-4 py-6">
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
