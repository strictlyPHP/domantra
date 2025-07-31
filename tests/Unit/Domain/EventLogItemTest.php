<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Domain\EventLogItem;

class EventLogItemTest extends TestCase
{
    public function testEventLogItemCreation(): void
    {
        $event = $this->createMock(\StrictlyPHP\Domantra\Command\EventInterface::class);
        $happenedAt = new \DateTimeImmutable('2023-10-01 12:00:00');
        $dto = new \stdClass();
        $eventLogItem = new EventLogItem(
            event: $event,
            happenedAt: $happenedAt,
            dto: $dto
        );

        self::assertSame($event, $eventLogItem->event);
        self::assertSame($happenedAt, $eventLogItem->happenedAt);
        self::assertSame($dto, $eventLogItem->dto);
    }
}
