<?php

namespace App\Services;

use App\Enums\LedgerEntryType;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BankStatement\GenericCsvParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BankStatementImportService
{
    public const FORMAT_SIGNED = 'signed';

    public const FORMAT_DEBIT_CREDIT = 'debit_credit';

    public function __construct(
        private GenericCsvParser $parser,
        private LedgerCurrencyService $ledger,
    ) {}

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importCsv(
        UploadedFile $file,
        BankAccount $account,
        Budget $budget,
        User $user,
        string $format,
    ): array {
        if ($account->budget_id !== $budget->id) {
            throw new InvalidArgumentException(__('Account does not belong to this budget.'));
        }

        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException(__('Could not read the upload.'));
        }

        $rows = match ($format) {
            self::FORMAT_SIGNED => $this->parser->parseSignedAmount($path),
            self::FORMAT_DEBIT_CREDIT => $this->parser->parseDebitCredit($path),
            default => throw new InvalidArgumentException(__('Unknown import format.')),
        };

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $account, $budget, $user, &$imported, &$skipped): void {
            $rate = $this->ledger->effectiveRateToBase($account, $budget);

            foreach ($rows as $row) {
                $category = $this->resolveCategory($budget, $row['type']);

                if ($category === null) {
                    $skipped++;

                    continue;
                }

                Transaction::query()->create([
                    'user_id' => $user->id,
                    'budget_id' => $budget->id,
                    'bank_account_id' => $account->id,
                    'category_id' => $category->id,
                    'amount' => $row['amount'],
                    'type' => $row['type'],
                    'currency_code' => $account->currency_code,
                    'exchange_rate' => $rate,
                    'occurred_on' => $row['occurred_on'],
                    'description' => $row['description'],
                ]);
                $imported++;
            }
        });

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function resolveCategory(Budget $budget, LedgerEntryType $type): ?Category
    {
        $name = $type === LedgerEntryType::Income ? 'Imported income' : 'Imported';

        return Category::query()
            ->visibleToBudget($budget)
            ->where('name', $name)
            ->where('type', $type)
            ->first();
    }
}
