<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class DtoCacheHandlerInMemory extends AbstractDtoCacheHandler
{
    /**
     * @var array<string, CachedDtoInterface>
     */
    private array $cache = [];

    /**
     * @param class-string $class
     */
    public function get(string $cacheKey, string $class): ?CachedDtoInterface
    {
        $generatedKey = $this->getKey($cacheKey, $class);
        return $this->cache[$generatedKey] ?? null;
    }

    public function set(CachedDtoInterface $dto): void
    {
        $generatedKey = $this->getKey($dto->getCacheKey(), get_class($dto));
        $this->cache[$generatedKey] = $dto;
    }

    public function delete(string $id, string $class): void
    {
        $pattern = $this->getKey($id, $class, '*');
        foreach ($this->cache as $key => $value) {
            if (fnmatch($pattern, $key)) {
                unset($this->cache[$key]);
            }
        }
    }
}
