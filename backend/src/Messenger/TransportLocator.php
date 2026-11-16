<?php

declare(strict_types=1);

namespace App\Messenger;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Мини-локатор «имя => объект» для Messenger: транспорты и стратегии повторов по имени транспорта (async).
 * Объекты создаются лениво, при первом обращении.
 */
final class TransportLocator implements ContainerInterface
{
    /** @var array<string, object> */
    private array $resolved = [];

    /**
     * @param array<string, \Closure(): object> $factories
     */
    public function __construct(private readonly array $factories) {}

    public function get(string $id): object
    {
        if (!isset($this->factories[$id])) {
            throw new class (\sprintf('Transport "%s" is not configured.', $id)) extends \InvalidArgumentException implements NotFoundExceptionInterface {};
        }

        return $this->resolved[$id] ??= ($this->factories[$id])();
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]);
    }
}
