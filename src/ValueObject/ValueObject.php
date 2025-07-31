<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\ValueObject;

interface ValueObject
{
    /**
     * @template T of ValueObject
     *
     * @param T $other
     */
    public function equals(ValueObject $other): bool;
}
