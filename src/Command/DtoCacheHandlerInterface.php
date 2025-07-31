<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

interface DtoCacheHandlerInterface
{
    public function get(string $id, string $key): ?\stdClass;

    public function set(\stdClass $dto, string $id, string $key, ?int $ttl = null): void;
}
