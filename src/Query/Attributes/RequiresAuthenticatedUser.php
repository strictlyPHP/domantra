<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Query\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class RequiresAuthenticatedUser
{
    /**
     * @param array<string> $roles
     */
    public function __construct(
        public array $roles = []
    ) {
    }
}
