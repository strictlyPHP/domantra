<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Time;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
