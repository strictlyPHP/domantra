<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

interface DtoCacheHandlerInterface
{
    public function get(string $cacheKey, string $class): ?CachedDtoInterface;

    public function set(CachedDtoInterface $dto): void;

    public function delete(string $id, string $class): void;
}
