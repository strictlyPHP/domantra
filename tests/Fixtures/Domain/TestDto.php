<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class TestDto implements CachedDtoInterface
{
    public function jsonSerialize(): object
    {
        return (object) [
            'id' => 'test-id',
            'name' => 'Test',
        ];
    }

    public function getCacheKey(): string
    {
        return 'test-id';
    }

    public function getTtl(): int
    {
        return 3;
    }
}
