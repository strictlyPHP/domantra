<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class DtoCacheHandlerRedis extends AbstractDtoCacheHandler
{
    public function __construct(
        private \Redis $redis,
    ) {
    }

    public static function create(
        string $host,
        int $port = 6379,
        int $timeout = 0,
        int $serializer = \Redis::SERIALIZER_PHP,
    ): self {
        $redis = new \Redis();
        $redis->connect(
            $host,
            $port,
            $timeout
        );
        $redis->setOption(\Redis::OPT_SERIALIZER, $serializer);
        return new self($redis);
    }

    /**
     * @param class-string $class
     */
    public function get(string $cacheKey, string $class): ?CachedDtoInterface
    {
        $generatedKey = $this->getKey($cacheKey, $class);

        return $this->redis->get($generatedKey) ?: null;
    }

    public function set(CachedDtoInterface $dto): void
    {
        $generatedKey = $this->getKey($dto->getCacheKey(), get_class($dto));
        $this->redis->set(
            $generatedKey,
            $dto,
            $dto->getTtl()
        );
    }

    public function delete(string $id, string $class): void
    {
        $pattern = $this->getKey($id, $class, '*');
        $cursor = null;
        do {
            $result = $this->redis->scan($cursor, $pattern);
            if ($result !== false) {
                foreach ($result as $key) {
                    $this->redis->del($key);
                }
            }
        } while ($cursor > 0);
    }
}
