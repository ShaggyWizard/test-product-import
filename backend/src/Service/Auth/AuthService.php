<?php

declare(strict_types=1);

namespace App\Service\Auth;

use App\Config\Settings;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Вход единственного администратора (логин и пароль из .env) и проверка JWT.
 * Для тестового задания этого достаточно; таблица пользователей здесь была бы лишней.
 */
final readonly class AuthService
{
    private const ALGORITHM = 'HS256';
    private const ISSUER = 'product-import';

    public function __construct(private Settings $settings) {}

    /**
     * @return array{token: string, expiresAt: int}|null null — неверный логин или пароль
     */
    public function login(string $login, string $password): ?array
    {
        // hash_equals — сравнение за постоянное время, без утечки длины совпавшего префикса.
        $ok = hash_equals($this->settings->adminLogin, $login)
            & hash_equals($this->settings->adminPassword, $password);
        if ($ok !== 1 || $this->settings->adminPassword === '') {
            return null;
        }

        $now = time();
        $expiresAt = $now + $this->settings->jwtTtl;
        $token = JWT::encode([
            'iss' => self::ISSUER,
            'sub' => $login,
            'iat' => $now,
            'exp' => $expiresAt,
        ], $this->secret(), self::ALGORITHM);

        return ['token' => $token, 'expiresAt' => $expiresAt];
    }

    /**
     * @return string|null логин из токена; null — токен неверный или истёк
     */
    public function verify(string $token): ?string
    {
        try {
            $claims = JWT::decode($token, new Key($this->secret(), self::ALGORITHM));
        } catch (\Throwable) {
            return null;
        }

        return ($claims->iss ?? null) === self::ISSUER && \is_string($claims->sub ?? null) ? $claims->sub : null;
    }

    private function secret(): string
    {
        if (\strlen($this->settings->jwtSecret) < 32) {
            throw new \LogicException('JWT_SECRET должен быть не короче 32 символов.');
        }

        return $this->settings->jwtSecret;
    }
}
