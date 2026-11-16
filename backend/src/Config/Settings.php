<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Все настройки приложения из переменных окружения в одном типизированном объекте.
 */
final readonly class Settings
{
    public function __construct(
        public string $env,
        public string $dbHost,
        public int $dbPort,
        public string $dbName,
        public string $dbUser,
        public string $dbPassword,
        public string $messengerDsn,
        public int $importMaxFileSize,
        public int $importRateLimit,
        public string $importRateInterval,
        public int $imageDownloadTimeout,
        public int $imageMaxSize,
        public string $jwtSecret,
        public int $jwtTtl,
        public string $adminLogin,
        public string $adminPassword,
    ) {}

    public static function fromEnv(): self
    {
        return new self(
            env: self::str('APP_ENV', 'prod'),
            dbHost: self::str('DB_HOST', 'db'),
            dbPort: self::int('DB_PORT', 5432),
            dbName: self::str('DB_NAME', 'products'),
            dbUser: self::str('DB_USER', 'app'),
            dbPassword: self::str('DB_PASSWORD', ''),
            messengerDsn: self::str('MESSENGER_TRANSPORT_DSN', 'in-memory://'),
            importMaxFileSize: self::int('IMPORT_MAX_FILE_SIZE', 10 * 1024 * 1024),
            importRateLimit: self::int('IMPORT_RATE_LIMIT', 5),
            importRateInterval: self::str('IMPORT_RATE_INTERVAL', '1 minute'),
            imageDownloadTimeout: self::int('IMAGE_DOWNLOAD_TIMEOUT', 10),
            imageMaxSize: self::int('IMAGE_MAX_SIZE', 5 * 1024 * 1024),
            jwtSecret: self::str('JWT_SECRET', ''),
            jwtTtl: self::int('JWT_TTL', 3600),
            adminLogin: self::str('ADMIN_LOGIN', 'admin'),
            adminPassword: self::str('ADMIN_PASSWORD', ''),
        );
    }

    public function isDev(): bool
    {
        return $this->env === 'dev' || $this->env === 'test';
    }

    private static function str(string $name, string $default): string
    {
        $value = $_ENV[$name] ?? getenv($name);

        return \is_string($value) && $value !== '' ? $value : $default;
    }

    private static function int(string $name, int $default): int
    {
        $value = self::str($name, (string) $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
