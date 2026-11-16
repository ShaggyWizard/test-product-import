<?php

declare(strict_types=1);

namespace App\Logging;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Логи в stderr контейнера — их видно в `docker compose logs`. Для тестового задания этого достаточно.
 */
final class StderrLogger extends AbstractLogger
{
    private const LEVELS = [
        LogLevel::DEBUG => 0, LogLevel::INFO => 1, LogLevel::NOTICE => 2, LogLevel::WARNING => 3,
        LogLevel::ERROR => 4, LogLevel::CRITICAL => 5, LogLevel::ALERT => 6, LogLevel::EMERGENCY => 7,
    ];

    public function __construct(private readonly string $minLevel = LogLevel::INFO) {}

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $level = (string) $level;
        if ((self::LEVELS[$level] ?? 0) < (self::LEVELS[$this->minLevel] ?? 1)) {
            return;
        }

        $replace = [];
        foreach ($context as $key => $value) {
            if (\is_scalar($value) || $value instanceof \Stringable) {
                $replace['{' . $key . '}'] = (string) $value;
            }
        }

        file_put_contents('php://stderr', \sprintf(
            "[%s] %s: %s\n",
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            strtoupper($level),
            strtr((string) $message, $replace),
        ));
    }
}
