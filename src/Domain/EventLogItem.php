<?php

declare(strict_types=1);

namespace StrictlyPHP\Domantra\Domain;

use StrictlyPHP\Domantra\Command\EventInterface;

readonly class EventLogItem
{
    public function __construct(
        public EventInterface $event,
        public \DateTimeImmutable $happenedAt,
        public \stdClass $dto,
    ) {
    }
}
