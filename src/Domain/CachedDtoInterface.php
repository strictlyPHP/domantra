<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

interface CachedDtoInterface extends \JsonSerializable
{
    public function getCacheKey(): string;

    public function getTtl(): int;

    public function jsonSerialize(): object;
}
