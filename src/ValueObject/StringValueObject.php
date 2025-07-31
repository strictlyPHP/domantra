<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\ValueObject;

use JsonSerializable;
use Stringable;

interface StringValueObject extends ValueObject, Stringable, JsonSerializable
{
    public function jsonSerialize(): string;
}
