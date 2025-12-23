<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;

readonly class UserDto implements CachedDtoInterface
{
    public function __construct(
        public UserId $id,
        public string $username,
        public string $email,
    ) {
    }

    public function getCacheKey(): string
    {
        return (string) $this->id;
    }

    public function getTtl(): int
    {
        return 5;
    }
}
