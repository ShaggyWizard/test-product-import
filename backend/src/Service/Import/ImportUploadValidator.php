<?php

declare(strict_types=1);

namespace App\Service\Import;

use App\Http\ValidationException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Проверка загружаемого файла до постановки в очередь: расширение, размер и тип по содержимому.
 * Имя и Content-Type от клиента не считаются доказательством — смотрим в сам файл.
 */
final readonly class ImportUploadValidator
{
    /** .xlsx — это zip; finfo в разных версиях libmagic отдаёт один из этих типов. */
    private const ALLOWED_MIME = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/zip',
        'application/octet-stream',
    ];

    public function __construct(private int $maxBytes)
    {
    }

    public function validate(?UploadedFileInterface $file): void
    {
        if ($file === null || $file->getError() === \UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['file' => 'Прикрепите .xlsx-файл в поле file.']);
        }
        if (\in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
            throw new ValidationException(['file' => $this->tooBig()]);
        }
        if ($file->getError() !== \UPLOAD_ERR_OK) {
            throw new ValidationException(['file' => 'Файл не загрузился, попробуйте ещё раз.']);
        }

        $name = (string) $file->getClientFilename();
        if (strtolower(pathinfo($name, \PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new ValidationException(['file' => 'Нужен файл с расширением .xlsx.']);
        }

        $size = (int) $file->getSize();
        if ($size === 0) {
            throw new ValidationException(['file' => 'Файл пустой.']);
        }
        if ($size > $this->maxBytes) {
            throw new ValidationException(['file' => $this->tooBig()]);
        }

        $path = $file->getStream()->getMetadata('uri');
        $mime = \is_string($path) ? (new \finfo(\FILEINFO_MIME_TYPE))->file($path) : false;
        if (!\is_string($mime) || !\in_array($mime, self::ALLOWED_MIME, true) || !$this->isWorkbook((string) $path)) {
            throw new ValidationException(['file' => 'Файл не похож на таблицу Excel (.xlsx).']);
        }
    }

    /** Внутри xlsx-архива обязательно есть xl/workbook.xml. */
    private function isWorkbook(string $path): bool
    {
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) {
            return false;
        }
        $ok = $zip->locateName('xl/workbook.xml') !== false;
        $zip->close();

        return $ok;
    }

    private function tooBig(): string
    {
        return \sprintf('Файл больше %d МБ.', intdiv($this->maxBytes, 1024 * 1024));
    }
}
