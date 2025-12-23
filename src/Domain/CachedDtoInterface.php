<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

interface CachedDtoInterface
{
    public function getCacheKey(): string;

    public function getTtl(): int;
}
