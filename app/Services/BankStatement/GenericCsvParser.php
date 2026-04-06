<?php

namespace App\Services\BankStatement;

use App\Enums\LedgerEntryType;
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
        $rows = $this->readCsvRows($absolutePath);
        if ($rows === []) {
            return [];
        }

        $header = array_shift($rows);
        $map = $this->mapHeaders($header, ['date' => ['date', 'transaction date', 'posted', 'posting date'], 'amount' => ['amount', 'amt'], 'description' => ['description', 'narrative', 'details', 'memo', 'payee']]);

        if (! isset($map['date'], $map['amount'])) {
            throw new InvalidArgumentException(__('Could not find required columns. Include headers named Date and Amount (optionally Description).'));
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
     * @return list<array{occurred_on: string, type: LedgerEntryType, amount: string, description: ?string}>
     */
    public function parseDebitCredit(string $absolutePath): array
    {
        $rows = $this->readCsvRows($absolutePath);
        if ($rows === []) {
            return [];
        }

        $header = array_shift($rows);
        $map = $this->mapHeaders($header, [
            'date' => ['date', 'transaction date', 'posted'],
            'description' => ['description', 'narrative', 'details', 'memo'],
            'debit' => ['debit', 'withdrawal', 'out'],
            'credit' => ['credit', 'deposit', 'in'],
        ]);

        if (! isset($map['date'], $map['debit'], $map['credit'])) {
            throw new InvalidArgumentException(__('Could not find required columns. Include headers named Date, Debit, and Credit (optionally Description).'));
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
                $rows[] = array_map(fn (mixed $c): string => is_string($c) ? trim($c) : '', $data);
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
        $v = $this->cell($row, $index);

        return $v;
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
