<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

#[\Attribute]
readonly class UseTimestamps
{
    public function __construct(
        public bool $softDelete = false,
    ) {
    }
}
