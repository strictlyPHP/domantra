<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Time;

class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
