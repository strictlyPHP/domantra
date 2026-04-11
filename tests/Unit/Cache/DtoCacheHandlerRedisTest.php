<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Cache;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerRedis;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;

class DtoCacheHandlerRedisTest extends TestCase
{
    private \Redis & MockObject $redis;

    private DtoCacheHandlerRedis $handler;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(\Redis::class);
        $this->handler = new DtoCacheHandlerRedis($this->redis);
    }

    public function testSetDoesNotCallRedisWhenTtlIsZero(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(0);

        $this->redis->expects($this->never())->method('set');

        $this->handler->set($dto);
    }

    public function testSetDoesNotCallRedisWhenTtlIsNegative(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(-1);

        $this->redis->expects($this->never())->method('set');

        $this->handler->set($dto);
    }

    public function testSetCallsRedisWhenTtlIsPositive(): void
    {
        $dto = $this->createMock(CachedDtoInterface::class);
        $dto->method('getTtl')->willReturn(60);
        $dto->method('getCacheKey')->willReturn('some-key');

        $this->redis->expects($this->once())
            ->method('set')
            ->with(
                $this->isType('string'),
                $dto,
                60
            );

        $this->handler->set($dto);
    }
}
