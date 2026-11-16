<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Картинку скачать не удалось; сообщение — причина для отчёта импорта.
 */
final class ImageDownloadException extends \RuntimeException
{
}
