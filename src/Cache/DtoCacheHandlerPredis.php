<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Cache;

use Predis\Client;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class DtoCacheHandlerPredis extends AbstractDtoCacheHandler
{
    public function __construct(
        private Client $client
    ) {
    }

    public static function create(
        string $host,
        int $port = 6379,
    ): self {
        $client = new Client([
            'host' => $host,
            'port' => $port,
        ]);
        return new self($client);
    }

    /**
     * @param class-string $class
     */
    public function get(string $cacheKey, string $class): ?AbstractAggregateRoot
    {
        $generatedKey = $this->getKey($cacheKey, $class);
        $data = $this->client->get($generatedKey);

        if ($data === null) {
            return null;
        }

        return unserialize($data);
    }

    public function set(AbstractAggregateRoot $dto, ?int $ttl = null): void
    {
        $generatedKey = $this->getKey($dto->getCacheKey(), get_class($dto));
        $data = serialize($dto);
        $this->client->set(
            $generatedKey,
            $data,
            'EX',
            $ttl ?? random_int(300, 600)
        );
    }

    public function delete(string $id, string $class): void
    {
        $pattern = $this->getKey($id, $class, '*');
        $cursor = '0';
        do {
            [$cursor, $keys] = $this->client->scan($cursor, [
                'match' => $pattern,
            ]);
            foreach ($keys as $key) {
                $this->client->del($key);
            }
        } while ($cursor !== '0');
    }
}
