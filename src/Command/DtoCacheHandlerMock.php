<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class DtoCacheHandlerMock implements DtoCacheHandlerInterface
{
    public function __construct(
    ) {
    }

    public function get(string $id, string $key): ?\stdClass
    {
        return null;
    }

    public function set(\stdClass $dto, string $id, string $key, ?int $ttl = null): void
    {
    }

    public function delete(string $id, AbstractAggregateRoot $model, ?string $keyPrefix): void
    {
    }
}
