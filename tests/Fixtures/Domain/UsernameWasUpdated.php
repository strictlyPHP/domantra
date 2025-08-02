<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

use StrictlyPHP\Domantra\Command\EventInterface;

class UsernameWasUpdated implements EventInterface
{
    public function __construct(
        public readonly string $username
    ) {
    }
}
