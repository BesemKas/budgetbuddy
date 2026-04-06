<?php

namespace App\Services\BankStatement;

use App\Enums\LedgerEntryType;
use App\Services\BankStatementImportService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GenericCsvParser
{
    /**
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseSignedAmount(string $absolutePath): array
    {
        return $this->parseSignedWithProfile($absolutePath, $this->defaultSignedAliases());
    }

    /**
     * FNB / similar online banking CSV (single signed Amount column).
     *
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseFnb(string $absolutePath): array
    {
        return $this->parseSignedWithProfile($absolutePath, $this->fnbCapitecSignedAliases());
    }

    /**
     * Capitec Bank app / web CSV (same layout as many SA banks: Date, Description, Amount).
     *
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseCapitec(string $absolutePath): array
    {
        return $this->parseSignedWithProfile($absolutePath, $this->fnbCapitecSignedAliases());
    }

    /**
     * Standard Bank style: separate Debit Amount / Credit Amount columns.
     *
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseStandardBank(string $absolutePath): array
    {
        return $this->parseDebitCreditWithProfile($absolutePath, $this->standardBankDebitCreditAliases());
    }

    /**
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseDebitCredit(string $absolutePath): array
    {
        return $this->parseDebitCreditWithProfile($absolutePath, $this->defaultDebitCreditAliases());
    }

    /**
     * Parse CSV or Excel rows (first row = headers) using bank import format codes.
     *
     * @param  list<list<string|int|float|null>>  $matrix
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseImportFormatMatrix(array $matrix, string $format): array
    {
        $normalized = [];
        foreach ($matrix as $row) {
            $normalized[] = $this->normalizeMatrixRow(is_array($row) ? $row : []);
        }

        return match ($format) {
            BankStatementImportService::FORMAT_SIGNED => $this->parseSignedMatrix($normalized, $this->defaultSignedAliases()),
            BankStatementImportService::FORMAT_DEBIT_CREDIT => $this->parseDebitCreditMatrix($normalized, $this->defaultDebitCreditAliases()),
            BankStatementImportService::FORMAT_FNB => $this->parseSignedMatrix($normalized, $this->fnbCapitecSignedAliases()),
            BankStatementImportService::FORMAT_CAPITEC => $this->parseSignedMatrix($normalized, $this->fnbCapitecSignedAliases()),
            BankStatementImportService::FORMAT_STANDARD_BANK => $this->parseDebitCreditMatrix($normalized, $this->standardBankDebitCreditAliases()),
            default => throw new InvalidArgumentException(__('Unknown import format.')),
        };
    }

    /**
     * @return list<list<string>>
     */
    public function readCsvFile(string $absolutePath): array
    {
        return $this->readCsvRows($absolutePath);
    }

    /**
     * @param  array<string, list<string>>  $aliases
     */
    private function parseSignedWithProfile(string $absolutePath, array $aliases): array
    {
        return $this->parseSignedMatrix($this->readCsvRows($absolutePath), $aliases);
    }

    /**
     * @param  list<list<string>>  $matrix
     * @param  array<string, list<string>>  $aliases
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    private function parseSignedMatrix(array $matrix, array $aliases): array
    {
        if ($matrix === []) {
            return [];
        }

        $rows = $matrix;
        $header = array_shift($rows);
        $map = $this->mapHeaders($header, $aliases);

        if (! isset($map['date'], $map['amount'])) {
            throw new InvalidArgumentException(__('Could not find required columns. Include headers for Date and Amount (or use another import format).'));
        }

        $out = [];

        foreach ($rows as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $dateStr = $this->cell($row, $map['date'] ?? null);
            $amountStr = $this->cell($row, $map['amount'] ?? null);
            $description = $this->optionalCell($row, $map['description'] ?? null);

            if ($dateStr === null || $amountStr === null) {
                continue;
            }

            $amount = $this->normalizeMoney($amountStr);
            if (bccomp($amount, '0', 4) === 0) {
                continue;
            }

            $occurredOn = $this->parseDate($dateStr);

            if (bccomp($amount, '0', 4) > 0) {
                $out[] = [
                    'occurred_on' => $occurredOn,
                    'type' => LedgerEntryType::Income,
                    'amount' => $amount,
                    'description' => $description,
                ];
            } else {
                $out[] = [
                    'occurred_on' => $occurredOn,
                    'type' => LedgerEntryType::Expense,
                    'amount' => ltrim($amount, '-'),
                    'description' => $description,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, list<string>>  $aliases
     */
    private function parseDebitCreditWithProfile(string $absolutePath, array $aliases): array
    {
        return $this->parseDebitCreditMatrix($this->readCsvRows($absolutePath), $aliases);
    }

    /**
     * @param  list<list<string>>  $matrix
     * @param  array<string, list<string>>  $aliases
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    private function parseDebitCreditMatrix(array $matrix, array $aliases): array
    {
        if ($matrix === []) {
            return [];
        }

        $rows = $matrix;
        $header = array_shift($rows);
        $map = $this->mapHeaders($header, $aliases);

        if (! isset($map['date'], $map['debit'], $map['credit'])) {
            throw new InvalidArgumentException(__('Could not find required columns. Include headers for Date, Debit, and Credit (or use another import format).'));
        }

        $out = [];

        foreach ($rows as $row) {
            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $dateStr = $this->cell($row, $map['date'] ?? null);
            if ($dateStr === null) {
                continue;
            }

            $description = $this->optionalCell($row, $map['description'] ?? null);
            $debit = $this->normalizeMoney($this->cell($row, $map['debit'] ?? null) ?? '0');
            $credit = $this->normalizeMoney($this->cell($row, $map['credit'] ?? null) ?? '0');

            if (bccomp($debit, '0', 4) > 0 && bccomp($credit, '0', 4) > 0) {
                continue;
            }

            $occurredOn = $this->parseDate($dateStr);

            if (bccomp($debit, '0', 4) > 0) {
                $out[] = [
                    'occurred_on' => $occurredOn,
                    'type' => LedgerEntryType::Expense,
                    'amount' => $debit,
                    'description' => $description,
                ];
            } elseif (bccomp($credit, '0', 4) > 0) {
                $out[] = [
                    'occurred_on' => $occurredOn,
                    'type' => LedgerEntryType::Income,
                    'amount' => $credit,
                    'description' => $description,
                ];
            }
        }

        return $out;
    }

    /**
     * @param  list<string|int|float|null>  $row
     * @return list<string>
     */
    private function normalizeMatrixRow(array $row): array
    {
        return array_map(fn (mixed $c): string => $c === null ? '' : trim((string) $c, " \t\n\r\0\x0B\xEF\xBB\xBF"), array_values($row));
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultSignedAliases(): array
    {
        return [
            'date' => ['date', 'transaction date', 'posted', 'posting date', 'value date'],
            'amount' => ['amount', 'amt', 'value'],
            'description' => ['description', 'narrative', 'details', 'memo', 'payee', 'reference'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function fnbCapitecSignedAliases(): array
    {
        return [
            'date' => ['date', 'transaction date', 'trans date', 'posted', 'posting date', 'value date'],
            'amount' => ['amount', 'amt', 'value', 'transaction amount', 'amount (zar)', 'amt (zar)', 'amount zar'],
            'description' => ['description', 'narrative', 'details', 'memo', 'payee', 'reference', 'transaction description'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function defaultDebitCreditAliases(): array
    {
        return [
            'date' => ['date', 'transaction date', 'posted'],
            'description' => ['description', 'narrative', 'details', 'memo'],
            'debit' => ['debit', 'withdrawal', 'out', 'debits'],
            'credit' => ['credit', 'deposit', 'in', 'credits'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function standardBankDebitCreditAliases(): array
    {
        return [
            'date' => ['date', 'transaction date', 'posted', 'value date'],
            'description' => ['description', 'narrative', 'details', 'memo', 'reference'],
            'debit' => ['debit', 'debit amount', 'withdrawal', 'withdrawals', 'out', 'debits'],
            'credit' => ['credit', 'credit amount', 'deposit', 'deposits', 'in', 'credits'],
        ];
    }

    /**
     * @return list<list<string>>
     */
    private function readCsvRows(string $absolutePath): array
    {
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException(__('Could not read the file.'));
        }

        $rows = [];

        try {
            while (($data = fgetcsv($handle)) !== false) {
                $rows[] = array_map(function (mixed $c): string {
                    if (! is_string($c)) {
                        return '';
                    }

                    return trim($c, " \t\n\r\0\x0B\xEF\xBB\xBF");
                }, $data);
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  list<string>  $header
     * @param  array<string, list<string>>  $aliases
     * @return array<string, int>
     */
    private function mapHeaders(array $header, array $aliases): array
    {
        $normalized = [];
        foreach ($header as $i => $h) {
            $normalized[Str::lower(trim($h))] = $i;
        }

        $map = [];

        foreach ($aliases as $key => $names) {
            foreach ($names as $name) {
                $lower = Str::lower($name);
                if (array_key_exists($lower, $normalized)) {
                    $map[$key] = $normalized[$lower];

                    break;
                }
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $row
     */
    private function cell(array $row, ?int $index): ?string
    {
        if ($index === null || ! array_key_exists($index, $row)) {
            return null;
        }

        $v = trim($row[$index]);

        return $v === '' ? null : $v;
    }

    /**
     * @param  list<string>  $row
     */
    private function optionalCell(array $row, ?int $index): ?string
    {
        return $this->cell($row, $index);
    }

    /**
     * @param  list<string>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeMoney(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '0';
        }

        $clean = preg_replace('/[^\d\.\-]/', '', str_replace(',', '', $value));

        if ($clean === null || $clean === '' || $clean === '-' || $clean === '.') {
            return '0';
        }

        if (! is_numeric($clean)) {
            return '0';
        }

        return number_format((float) $clean, 4, '.', '');
    }

    private function parseDate(string $value): string
    {
        return Carbon::parse($value)->toDateString();
    }
}
