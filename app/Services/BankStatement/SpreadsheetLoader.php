<?php

namespace App\Services\BankStatement;

use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class SpreadsheetLoader
{
    /**
     * First worksheet as string rows (for bank import).
     *
     * @return list<list<string>>
     */
    public function matrixFromUploadedFile(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new InvalidArgumentException(__('Could not read the upload.'));
        }

        $spreadsheet = IOFactory::load($path);
        $raw = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }

            $out[] = array_map(fn (mixed $c): string => $c === null ? '' : trim((string) $c, " \t\n\r\0\x0B\xEF\xBB\xBF"), array_values($row));
        }

        return $out;
    }
}
