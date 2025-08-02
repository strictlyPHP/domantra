<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

class UserQuery
{
    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = 10,
    ) {
    }
}
