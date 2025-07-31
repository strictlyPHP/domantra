<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use StrictlyPHP\Domantra\Domain\EventLogItem;

interface EventBusInterface
{
    public function dispatch(EventLogItem $eventLogItem): void;
}
