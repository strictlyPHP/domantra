<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Fixtures\Domain;

abstract class Id implements \Stringable, \JsonSerializable
{
    public function __construct(
        private readonly string $id
    ) {
    }

    public function __toString(): string
    {
        return $this->id;
    }

    public function jsonSerialize(): string
    {
        return $this->id;
    }
}
