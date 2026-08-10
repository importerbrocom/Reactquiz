<?php

declare(strict_types=1);

namespace App\Services\Import;

use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Reads XLSX or CSV files using OpenSpout and yields rows as associative arrays.
 *
 * - Header row is normalised (case-insensitive, underscores/spaces collapsed).
 * - Unknown columns are ignored.
 * - Blank rows are skipped.
 * - Aborts after max_blank_rows consecutive blank rows.
 */
class SpreadsheetReader
{
    /** Normalise a header name: lowercase, strip spaces/underscores, trim */
    public static function normaliseHeader(string $header): string
    {
        return strtolower(trim(str_replace([' ', '_'], '', $header)));
    }

    /**
     * Read rows from a spreadsheet file.
     *
     * @return \Generator<int, array<string, string|null>>
     */
    public static function read(string $filePath, string $mimeType): \Generator
    {
        $maxBlankRows = (int) config('quiz.import.max_blank_rows', 20);

        $reader = self::createReader($filePath, $mimeType);
        $reader->open($filePath);

        $headers = [];
        $blankStreak = 0;

        foreach ($reader->getSheetIterator() as $sheet) {
            // Only read the first sheet (the 'questions' sheet)
            if ($sheet->getIndex() !== 0) {
                continue;
            }

            $rowNumber = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowNumber++;
                $cells = $row->toArray();

                // First row = header
                if ($rowNumber === 1) {
                    $headers = array_map(
                        fn ($cell) => self::normaliseHeader((string) ($cell ?? '')),
                        $cells,
                    );
                    continue;
                }

                // Check if row is blank
                $nonEmpty = array_filter($cells, fn ($c) => $c !== null && trim((string) $c) !== '');
                if (empty($nonEmpty)) {
                    $blankStreak++;
                    if ($blankStreak >= $maxBlankRows) {
                        break; // Abort — too many consecutive blank rows
                    }
                    continue;
                }

                $blankStreak = 0;

                // Map cells to headers
                $mapped = [];
                foreach ($headers as $i => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $mapped[$header] = isset($cells[$i]) ? trim((string) $cells[$i]) : null;
                }

                yield $rowNumber => $mapped;
            }

            break; // Only first sheet
        }

        $reader->close();
    }

    private static function createReader(string $filePath, string $mimeType): CsvReader|XlsxReader
    {
        if (str_contains($mimeType, 'csv') || str_contains($mimeType, 'text/plain')) {
            return new CsvReader();
        }

        return new XlsxReader();
    }
}
