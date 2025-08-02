<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

interface DtoCacheHandlerInterface
{
    public function get(string $cacheKey, string $class): ?AbstractAggregateRoot;

    public function set(AbstractAggregateRoot $dto, ?int $ttl = null): void;

    public function delete(string $id, string $class): void;
}
