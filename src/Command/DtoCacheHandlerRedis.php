<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class DtoCacheHandlerRedis
{
    public function __construct(
        private \Redis $redis,
    ) {
    }

    public function get(string $id, string $key): ?\stdClass
    {
        $generatedKey = $this->getKey($id, $key);

        return $this->redis->get($generatedKey);
    }

    public function set(\stdClass $dto, string $id, string $key, ?int $ttl = null): void
    {
        $generatedKey = $this->getKey($id, $key);
        $this->redis->set(
            $generatedKey,
            $dto,
            $ttl ?? random_int(300, 600)
        );
    }

    public function delete(string $id, AbstractAggregateRoot $model): void
    {
        $pattern = sprintf('%s:*', $this->getKey($id, get_class($model)));
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

    protected function getKey(
        string $id,
        string $key,
    ): string {
        if (class_exists($key)) {
            $version = $this->fingerprintClassProperties($key);
        } else {
            $version = '1';
        }

        return sprintf(
            '%s:%s:%s:%s',
            'resource-key',
            $key,
            $id,
            $version
        );
    }

    protected function fingerprintClassProperties(string $class): string
    {
        if (! class_exists($class)) {
            throw new \InvalidArgumentException("Class $class does not exist.");
        }

        $ref = new \ReflectionClass($class);
        $properties = [];

        foreach ($ref->getProperties() as $prop) {
            $type = $prop->getType();
            $typeName = '';

            if ($type instanceof \ReflectionNamedType) {
                $typeName = ($type->allowsNull() ? '?' : '') . $type->getName();
            } elseif ($type instanceof \ReflectionUnionType) {
                $typeName = ($type->allowsNull() ? '?' : '') .
                    implode('|', array_map(fn ($t) => $t->getName(), $type->getTypes()));
            }

            $properties[$prop->getName()] = $typeName;
        }

        ksort($properties); // Sort keys for consistent hashing
        $json = json_encode($properties, JSON_UNESCAPED_UNICODE);

        return hash('sha256', $json);
    }
}
