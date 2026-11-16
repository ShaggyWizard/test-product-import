<?php

declare(strict_types=1);

namespace App\Service\Import;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Скачивает картинки товаров в public/media.
 *
 * - Один URL = один файл: имя — хэш ссылки, поэтому одинаковые фото разных товаров качаются один раз
 *   (в файле-примере 40 товаров делят 9 наборов фото), а повторный импорт не качает заново.
 * - Картинки одного товара качаются параллельно.
 * - Ошибка скачивания не роняет импорт: товар сохраняется, ссылка остаётся без локальной копии,
 *   причина уходит в предупреждения отчёта.
 * - HTTP-клиент обёрнут в NoPrivateNetworkHttpClient: ссылка из файла не может заставить сервер
 *   ходить во внутреннюю сеть (localhost, 10.x, 192.168.x и т.д.).
 */
final class ImageDownloader
{
    /** Допустимые типы картинок => расширение файла. */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /** @var array<string, string> URL => путь, уже скачанные в этом процессе */
    private array $memo = [];

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $publicDir,
        private readonly int $maxBytes,
    ) {
    }

    /**
     * paths — URL => путь относительно public/ (null — не скачалось); failures — URL => причина.
     *
     * @param list<string> $urls
     *
     * @return array{paths: array<string, string|null>, failures: array<string, string>}
     */
    public function download(array $urls): array
    {
        $paths = [];
        $failures = [];
        /** @var array<string, ResponseInterface> $pending */
        $pending = [];

        foreach ($urls as $url) {
            $cached = $this->memo[$url] ?? $this->findExisting($url);
            if ($cached !== null) {
                $paths[$url] = $this->memo[$url] = $cached;
                continue;
            }
            try {
                // Запросы стартуют сразу и идут параллельно; ждём их ниже.
                $pending[$url] = $this->http->request('GET', $url);
            } catch (HttpException $e) {
                $paths[$url] = null;
                $failures[$url] = $this->reason($e);
            }
        }

        foreach ($pending as $url => $response) {
            try {
                $paths[$url] = $this->memo[$url] = $this->save($url, $response);
            } catch (ImageDownloadException|HttpException $e) {
                $response->cancel();
                $paths[$url] = null;
                $failures[$url] = $this->reason($e);
            }
        }

        return ['paths' => $paths, 'failures' => $failures];
    }

    private function save(string $url, ResponseInterface $response): string
    {
        $status = $response->getStatusCode();
        if ($status !== 200) {
            throw new ImageDownloadException(\sprintf('сервер ответил %d', $status));
        }

        $length = (int) ($response->getHeaders(false)['content-length'][0] ?? 0);
        if ($length > $this->maxBytes) {
            throw new ImageDownloadException($this->tooBig());
        }

        // Читаем потоком и обрываем, как только превысили лимит: Content-Length может врать или отсутствовать.
        $body = '';
        foreach ($this->http->stream($response) as $chunk) {
            $body .= $chunk->getContent();
            if (\strlen($body) > $this->maxBytes) {
                throw new ImageDownloadException($this->tooBig());
            }
        }

        // Тип определяем по содержимому, а не по заголовку или расширению в ссылке.
        $mime = (new \finfo(\FILEINFO_MIME_TYPE))->buffer($body);
        $extension = \is_string($mime) ? (self::TYPES[$mime] ?? null) : null;
        if ($extension === null || @getimagesizefromstring($body) === false) {
            throw new ImageDownloadException(\sprintf('это не картинка (%s)', \is_string($mime) ? $mime : 'тип не определён'));
        }

        $relative = $this->relativePath($url, $extension);
        $absolute = $this->publicDir . '/' . $relative;
        $dir = \dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new ImageDownloadException('не удалось создать каталог для картинок');
        }

        // Пишем во временный файл и переименовываем: читатель не увидит недописанную картинку.
        $tmp = $absolute . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $body) === false || !@rename($tmp, $absolute)) {
            @unlink($tmp);
            throw new ImageDownloadException('не удалось сохранить файл');
        }

        return $relative;
    }

    /**
     * Уже скачанный ранее файл (например, прошлым импортом).
     */
    private function findExisting(string $url): ?string
    {
        foreach (self::TYPES as $extension) {
            $relative = $this->relativePath($url, $extension);
            if (is_file($this->publicDir . '/' . $relative)) {
                return $relative;
            }
        }

        return null;
    }

    /** media/ab/abcdef….jpg — подкаталог по первым символам хэша, чтобы не держать тысячи файлов в одной папке. */
    private function relativePath(string $url, string $extension): string
    {
        $hash = sha1($url);

        return \sprintf('media/%s/%s.%s', substr($hash, 0, 2), $hash, $extension);
    }

    private function tooBig(): string
    {
        return \sprintf('файл больше %d МБ', intdiv($this->maxBytes, 1024 * 1024));
    }

    private function reason(\Throwable $e): string
    {
        if ($e instanceof ImageDownloadException) {
            return $e->getMessage();
        }
        if (str_contains($e->getMessage(), 'private') || str_contains($e->getMessage(), 'is blocked')) {
            return 'адрес во внутренней сети запрещён';
        }
        if (str_contains(strtolower($e->getMessage()), 'timeout') || str_contains($e->getMessage(), 'Idle timeout')) {
            return 'сервер не ответил вовремя';
        }

        return 'не удалось скачать: ' . mb_strimwidth($e->getMessage(), 0, 120, '…');
    }
}
