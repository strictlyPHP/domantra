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
            name: 'fooBar',
            event: $event,
            happenedAt: $happenedAt,
            dto: $dto
        );

        $this->assertSame($event, $eventLogItem->event);
        $this->assertSame($happenedAt, $eventLogItem->happenedAt);
        $this->assertSame($dto, $eventLogItem->dto);
        $this->assertNull($eventLogItem->previousDto);
    }

    public function testEventLogItemCreationWithPreviousDto(): void
    {
        $event = $this->createMock(\StrictlyPHP\Domantra\Command\EventInterface::class);
        $happenedAt = new \DateTimeImmutable('2023-10-01 12:00:00');
        $dto = new \stdClass();
        $previousDto = new \stdClass();
        $eventLogItem = new EventLogItem(
            name: 'fooBar',
            event: $event,
            happenedAt: $happenedAt,
            dto: $dto,
            previousDto: $previousDto
        );

        $this->assertSame($event, $eventLogItem->event);
        $this->assertSame($happenedAt, $eventLogItem->happenedAt);
        $this->assertSame($dto, $eventLogItem->dto);
        $this->assertSame($previousDto, $eventLogItem->previousDto);
    }
}
