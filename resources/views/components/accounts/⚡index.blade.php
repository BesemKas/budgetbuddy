<?php

use App\Enums\BankAccountKind;
use App\Models\BankAccount;
use App\Services\BudgetAccountAccess;
use App\Services\CurrentBudget;
use App\Services\ExchangeRateService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $currency_code = 'ZAR';

    public string $exchange_rate = '';

    public string $kind = 'liquid';

    public function openCreate(CurrentBudget $currentBudget): void
    {
        $this->authorize('create', BankAccount::class);
        $this->resetValidation();
        $this->editingId = null;
        $this->name = '';
        $this->currency_code = $currentBudget->current()->base_currency;
        $this->exchange_rate = '';
        $this->kind = BankAccountKind::Liquid->value;
        $this->showModal = true;
    }

    public function openEdit(int $id, CurrentBudget $currentBudget): void
    {
        $budgetId = $currentBudget->current()->id;
        $account = BankAccount::query()->where('budget_id', $budgetId)->findOrFail($id);
        $this->authorize('update', $account);
        $this->resetValidation();
        $this->editingId = $account->id;
        $this->name = $account->name;
        $this->currency_code = $account->currency_code;
        $this->exchange_rate = $account->exchange_rate !== null ? (string) $account->exchange_rate : '';
        $this->kind = $account->kind->value;
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function fetchSpotRate(ExchangeRateService $exchangeRateService, CurrentBudget $currentBudget): void
    {
        $this->validate([
            'currency_code' => ['required', 'string', 'size:3'],
        ]);

        $base = $currentBudget->current()->base_currency;
        $from = strtoupper($this->currency_code);
        $to = strtoupper($base);

        if ($from === $to) {
            $this->exchange_rate = '';

            return;
        }

        $rate = $exchangeRateService->fetchRate($from, $to);

        if ($rate === null) {
            $this->addError('exchange_rate', __('Could not fetch a rate. Enter it manually (base per 1 unit of account currency).'));

            return;
        }

        $this->resetErrorBag('exchange_rate');
        $this->exchange_rate = $rate;
    }

    public function save(CurrentBudget $currentBudget): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', 'string', Rule::enum(BankAccountKind::class)],
            'currency_code' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.00000001'],
        ]);

        $user = auth()->user();
        $budget = $currentBudget->current();
        $code = strtoupper($this->currency_code);
        $base = strtoupper($budget->base_currency);

        $rate = null;
        if ($code !== $base) {
            $rate = $this->exchange_rate !== '' ? $this->exchange_rate : null;
            if ($rate === null) {
                $this->addError('exchange_rate', __('Set an exchange rate or fetch one, unless this account uses your base currency.'));

                return;
            }
        }

        if ($this->editingId === null) {
            $this->authorize('create', BankAccount::class);
            BankAccount::query()->create([
                'user_id' => $user->id,
                'budget_id' => $budget->id,
                'name' => $this->name,
                'kind' => BankAccountKind::from($this->kind),
                'currency_code' => $code,
                'balance' => '0',
                'exchange_rate' => $rate !== null ? number_format((float) $rate, 8, '.', '') : null,
            ]);
        } else {
            $account = BankAccount::query()->where('budget_id', $budget->id)->findOrFail($this->editingId);
            $this->authorize('update', $account);
            $account->update([
                'name' => $this->name,
                'kind' => BankAccountKind::from($this->kind),
                'currency_code' => $code,
                'exchange_rate' => $rate !== null ? number_format((float) $rate, 8, '.', '') : null,
            ]);
        }

        $this->showModal = false;
    }

    public function delete(int $id, CurrentBudget $currentBudget): void
    {
        $budgetId = $currentBudget->current()->id;
        $account = BankAccount::query()->where('budget_id', $budgetId)->findOrFail($id);
        $this->authorize('delete', $account);
        $account->delete();
    }

    public function getAccountsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        $budget = app(CurrentBudget::class)->current();
        $ids = app(BudgetAccountAccess::class)->accessibleBankAccountIds(auth()->user(), $budget);

        return BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $ids)
            ->latest('id')
            ->get();
    }

    public function getBudgetBaseCurrencyProperty(): string
    {
        return app(CurrentBudget::class)->current()->base_currency;
    }
};
?>

<div class="bb-page max-w-5xl">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">{{ __('Bank accounts') }}</h1>
            <p class="text-base-content/70 mt-1 text-sm">{{ __('Manage accounts and FX rates to your base currency (:c).', ['c' => $this->budgetBaseCurrency]) }}</p>
        </div>
        @can('create', BankAccount::class)
            <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate">{{ __('Add account') }}</button>
        @endcan
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body p-0">
            <div class="overflow-x-auto overscroll-x-contain">
                <table class="table table-zebra table-sm md:table-md min-w-[36rem]">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Currency') }}</th>
                            <th class="text-end">{{ __('Rate to base') }}</th>
                            <th class="text-end">{{ __('Balance') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->accounts as $account)
                            <tr wire:key="acc-{{ $account->id }}">
                                <td class="font-medium">{{ $account->name }}</td>
                                <td>
                                    @if ($account->kind === \App\Enums\BankAccountKind::Credit)
                                        <span class="badge badge-warning badge-sm">{{ __('Credit') }}</span>
                                    @else
                                        <span class="badge badge-ghost badge-sm">{{ __('Liquid') }}</span>
                                    @endif
                                </td>
                                <td><span class="badge badge-ghost badge-sm">{{ $account->currency_code }}</span></td>
                                <td class="text-end font-mono text-sm">
                                    @if (strtoupper($account->currency_code) === strtoupper($this->budgetBaseCurrency))
                                        —
                                    @else
                                        {{ $account->exchange_rate !== null ? rtrim(rtrim(number_format((float) $account->exchange_rate, 8, '.', ''), '0'), '.') : '—' }}
                                    @endif
                                </td>
                                <td class="text-end font-mono">{{ number_format((float) $account->balance, 2) }}</td>
                                <td class="text-end">
                                    @can('update', $account)
                                        <div class="join join-horizontal">
                                            <button type="button" class="btn btn-ghost btn-xs join-item" wire:click="openEdit({{ $account->id }})">{{ __('Edit') }}</button>
                                            <button
                                                type="button"
                                                class="btn btn-ghost btn-xs text-error join-item"
                                                wire:click="delete({{ $account->id }})"
                                                wire:confirm="{{ __('Delete this account and its transactions?') }}"
                                            >
                                                {{ __('Delete') }}
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-base-content/50 text-xs">—</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-base-content/60">{{ __('No accounts yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="modal {{ $showModal ? 'modal-open' : '' }} p-4 sm:p-0" role="dialog" aria-modal="true">
        <div class="modal-box bb-modal-box">
            <h3 class="font-bold text-lg">{{ $editingId ? __('Edit account') : __('New account') }}</h3>
            <form wire:submit="save" class="mt-4 flex flex-col gap-4">
                <label class="form-control w-full">
                    <span class="label-text">{{ __('Name') }}</span>
                    <input type="text" class="input input-bordered w-full" wire:model="name" />
                    @error('name')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-control w-full">
                    <span class="label-text">{{ __('Account type') }}</span>
                    <select class="select select-bordered w-full" wire:model="kind">
                        <option value="{{ BankAccountKind::Liquid->value }}">{{ __('Liquid (cheque, savings, cash)') }}</option>
                        <option value="{{ BankAccountKind::Credit->value }}">{{ __('Credit card (debt, not available cash)') }}</option>
                    </select>
                    @error('kind')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>

                <label class="form-control w-full">
                    <span class="label-text">{{ __('Currency') }}</span>
                    <select class="select select-bordered w-full" wire:model.live="currency_code">
                        @foreach (config('budgetbuddy.currency_codes', ['ZAR', 'USD', 'EUR']) as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>
                </label>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label class="form-control w-full grow">
                        <span class="label-text">{{ __('Exchange rate (base per 1 :ccy)', ['ccy' => $currency_code]) }}</span>
                        <input
                            type="text"
                            inputmode="decimal"
                            class="input input-bordered w-full font-mono"
                            wire:model="exchange_rate"
                            placeholder="{{ __('Manual or fetch') }}"
                        />
                        @error('exchange_rate')
                            <span class="label-text-alt text-error">{{ $message }}</span>
                        @enderror
                    </label>
                    <button type="button" class="btn btn-outline btn-sm shrink-0" wire:click="fetchSpotRate">
                        {{ __('Fetch') }}
                    </button>
                </div>

                <div class="modal-action flex-col gap-2 sm:flex-row">
                    <button type="button" class="btn btn-ghost w-full sm:w-auto" wire:click="closeModal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary w-full sm:w-auto" wire:loading.attr="disabled">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
        <button type="button" class="modal-backdrop" wire:click="closeModal" aria-label="{{ __('Close') }}"></button>
    </div>
</div>
