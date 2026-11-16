<?php

declare(strict_types=1);

namespace App\Service\Import;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Хранилище загруженных файлов импорта (var/uploads). Общий том для app и worker.
 */
final readonly class UploadStorage
{
    public function __construct(private string $directory)
    {
    }

    /**
     * Копирует файл в хранилище под случайным именем: имя от пользователя в путь не попадает.
     *
     * @return string имя файла внутри хранилища
     */
    public function store(string $sourcePath): string
    {
        $this->ensureDirectory();
        $name = Uuid::v7()->toRfc4122() . '.xlsx';
        if (!copy($sourcePath, $this->path($name))) {
            throw new \RuntimeException('Не удалось сохранить файл импорта.');
        }

        return $name;
    }

    /**
     * Переносит загруженный файл в хранилище под случайным именем.
     *
     * @return string имя файла внутри хранилища
     */
    public function storeUploaded(UploadedFileInterface $file): string
    {
        $this->ensureDirectory();
        $name = Uuid::v7()->toRfc4122() . '.xlsx';
        $file->moveTo($this->path($name));

        return $name;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Не удалось создать каталог для загрузок.');
        }
    }

    public function path(string $name): string
    {
        return $this->directory . '/' . basename($name);
    }
}
