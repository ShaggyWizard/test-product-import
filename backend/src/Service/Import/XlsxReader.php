<?php

declare(strict_types=1);

namespace App\Service\Import;

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

/**
 * Читает первый лист .xlsx и отдаёт строки как массивы «заголовок => значение».
 * Столбцы ищутся по названию, а не по номеру: порядок столбцов в файле может быть любым.
 */
final class XlsxReader
{
    public function read(string $path): Sheet
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ImportFileException('Файл импорта не найден.');
        }

        try {
            $reader = new Xlsx();
            // Только значения: без стилей и форматов, так быстрее и меньше памяти.
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);
            $spreadsheet = $reader->load($path);
        } catch (\Throwable $e) {
            throw new ImportFileException('Файл не читается как .xlsx.', 0, $e);
        }

        // Сырые значения ячеек: без пересчёта формул и без форматирования чисел.
        $raw = $spreadsheet->getSheet(0)->toArray(null, false, false, false);
        $spreadsheet->disconnectWorksheets();

        $headerRow = array_shift($raw);
        if ($headerRow === null) {
            throw new ImportFileException('Файл пустой: нет строки заголовков.');
        }

        $headers = [];
        foreach ($headerRow as $index => $value) {
            $title = $this->toString($value);
            if ($title !== '') {
                $headers[$index] = $title;
            }
        }
        if ($headers === []) {
            throw new ImportFileException('Файл пустой: нет строки заголовков.');
        }

        $rows = [];
        foreach ($raw as $offset => $cells) {
            $row = [];
            $empty = true;
            foreach ($headers as $index => $title) {
                $value = $this->toString($cells[$index] ?? null);
                $row[$title] = $value;
                $empty = $empty && $value === '';
            }
            if (!$empty) {
                // +2: первая строка файла — заголовки, нумерация в Excel с единицы.
                $rows[$offset + 2] = $row;
            }
        }

        return new Sheet(array_values($headers), $rows);
    }

    /**
     * В файле числа лежат то числом, то текстом («1» и «'0'»), поэтому всё приводим к строке.
     */
    private function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value) || $value instanceof \Stringable) {
            return trim((string) $value);
        }

        return '';
    }
}
