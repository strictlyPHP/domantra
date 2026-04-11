<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerPredis;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class DtoCacheHandlerPredisTest extends TestCase
{
    private Client & MockObject $client;

    private DtoCacheHandlerPredis $handler;

    protected function setUp(): void
    {
        $this->client = $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->addMethods(['set'])
            ->getMock();
        $this->handler = new DtoCacheHandlerPredis($this->client);
    }

    public function testSetDoesNotCallClientWhenTtlIsZero(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(0);

        $this->client->expects($this->never())->method('set');

        $this->handler->set($dto);
    }

    public function testSetDoesNotCallClientWhenTtlIsNegative(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(-1);

        $this->client->expects($this->never())->method('set');

        $this->handler->set($dto);
    }

    public function testSetCallsClientWhenTtlIsPositive(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(60);
        $dto->method('getCacheKey')->willReturn('some-key');

        $this->client->expects($this->once())
            ->method('set')
            ->with(
                $this->isType('string'),
                $this->isType('string'),
                'EX',
                60
            );

        $this->handler->set($dto);
    }
}
