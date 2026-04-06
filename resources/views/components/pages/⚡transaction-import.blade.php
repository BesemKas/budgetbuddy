<?php

use App\Models\BankAccount;
use App\Models\Transaction;
use App\Services\BankStatementImportService;
use App\Services\BudgetAccountAccess;
use App\Services\CurrentBudget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads;

    /** @var mixed */
    public $csvFile = null;

    public ?int $bank_account_id = null;

    public string $format = BankStatementImportService::FORMAT_SIGNED;

    public function mount(CurrentBudget $currentBudget): void
    {
        $this->authorize('create', Transaction::class);
        $budget = $currentBudget->current();
        $ids = app(BudgetAccountAccess::class)->accessibleBankAccountIds(auth()->user(), $budget);
        $first = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->first();
        $this->bank_account_id = $first?->id;
    }

    public function import(BankStatementImportService $importService, CurrentBudget $currentBudget): void
    {
        $this->authorize('create', Transaction::class);

        $this->validate([
            'csvFile' => ['required', 'file', 'max:5120'],
            'bank_account_id' => ['required', 'integer', 'exists:bank_accounts,id'],
            'format' => ['required', 'in:'.BankStatementImportService::FORMAT_SIGNED.','.BankStatementImportService::FORMAT_DEBIT_CREDIT],
        ], [], [
            'csvFile' => __('CSV file'),
        ]);

        $budget = $currentBudget->current();
        $account = BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereKey($this->bank_account_id)
            ->firstOrFail();

        $this->authorize('view', $account);

        $accessible = app(BudgetAccountAccess::class)->accessibleBankAccountIds(auth()->user(), $budget);
        if (! in_array((int) $account->id, array_map('intval', $accessible), true)) {
            abort(403);
        }

        try {
            $result = $importService->importCsv(
                $this->csvFile,
                $account,
                $budget,
                auth()->user(),
                $this->format,
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('csvFile', $e->getMessage());

            return;
        }

        $this->reset('csvFile');
        session()->flash('status', __('Imported :count row(s). Skipped :skipped.', [
            'count' => $result['imported'],
            'skipped' => $result['skipped'],
        ]));
    }

    public function getAccountsProperty(): Collection
    {
        $budget = app(CurrentBudget::class)->current();
        $ids = app(BudgetAccountAccess::class)->accessibleBankAccountIds(auth()->user(), $budget);

        return BankAccount::query()
            ->where('budget_id', $budget->id)
            ->whereIn('id', $ids)
            ->orderBy('name')
            ->get();
    }
};
?>

<div class="mx-auto max-w-lg px-4 py-6">
    @if (session('status'))
        <div role="status" class="alert alert-success alert-soft mb-4 text-sm">{{ session('status') }}</div>
    @endif

    <h1 class="text-2xl font-semibold tracking-tight">{{ __('Import transactions') }}</h1>
    <p class="text-base-content/70 mt-1 text-sm">
        {{ __('Upload a CSV bank export. The first row must be a header row. Formats:') }}
    </p>
    <ul class="text-base-content/80 mt-2 list-inside list-disc text-sm">
        <li>{{ __('Signed amount — columns Date, Amount (negative for spending, positive for income), optional Description.') }}</li>
        <li>{{ __('Debit / Credit — columns Date, Debit, Credit, optional Description.') }}</li>
    </ul>

    @if ($this->accounts->isEmpty())
        <div role="status" class="alert alert-warning mt-6 text-sm">
            {{ __('Add a bank account first, then you can import transactions into it.') }}
        </div>
    @endif

    <form wire:submit="import" class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm @if ($this->accounts->isEmpty()) opacity-60 @endif">
        <div class="card-body gap-4">
            <label class="form-control w-full">
                <span class="label-text">{{ __('Account') }}</span>
                <select class="select select-bordered w-full" wire:model="bank_account_id" @disabled($this->accounts->isEmpty())>
                    @foreach ($this->accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->currency_code }})</option>
                    @endforeach
                </select>
            </label>

            <label class="form-control w-full">
                <span class="label-text">{{ __('Format') }}</span>
                <select class="select select-bordered w-full" wire:model="format">
                    <option value="{{ \App\Services\BankStatementImportService::FORMAT_SIGNED }}">{{ __('Signed amount') }}</option>
                    <option value="{{ \App\Services\BankStatementImportService::FORMAT_DEBIT_CREDIT }}">{{ __('Debit / Credit') }}</option>
                </select>
            </label>

            <label class="form-control w-full">
                <span class="label-text">{{ __('CSV file') }}</span>
                <input type="file" class="file-input file-input-bordered w-full" wire:model="csvFile" accept=".csv,.txt,text/csv,text/plain" @disabled($this->accounts->isEmpty()) />
                <div wire:loading wire:target="csvFile" class="label-text-alt">{{ __('Uploading…') }}</div>
                @error('csvFile')
                    <span class="label-text-alt text-error">{{ $message }}</span>
                @enderror
            </label>

            <div class="card-actions justify-end">
                <button type="submit" class="btn btn-primary btn-sm" wire:loading.attr="disabled" @disabled($this->accounts->isEmpty())>
                    <span wire:loading.remove wire:target="import">{{ __('Import') }}</span>
                    <span wire:loading wire:target="import" class="loading loading-spinner loading-sm"></span>
                </button>
            </div>
        </div>
    </form>
</div>
