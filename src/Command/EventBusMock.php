<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Command;

use StrictlyPHP\Domantra\Domain\EventLogItem;

class EventBusMock implements EventBusInterface
{
    public function dispatch(EventLogItem $eventLogItem): void
    {
    }
}
