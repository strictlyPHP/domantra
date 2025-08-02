<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use StrictlyPHP\Domantra\Command\EventInterface;

class UserWasCreated implements EventInterface
{
    public function __construct(
        public readonly Id $id,
        public readonly string $username,
        public readonly string $email
    ) {
    }
}
