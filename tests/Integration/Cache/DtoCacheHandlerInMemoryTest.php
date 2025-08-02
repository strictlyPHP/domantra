<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Integration\Cache;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInMemory;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerPredis;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;

class DtoCacheHandlerInMemoryTest extends TestCase
{
    protected DtoCacheHandlerInMemory $cacheHandlerInMemory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheHandlerInMemory = new DtoCacheHandlerInMemory();
    }

    public function testGetSetDelete(): void
    {
        $cacheKey = 'test_key';
        $model = $this->getMockBuilder(AbstractAggregateRoot::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $model->method('getCacheKey')
            ->willReturn($cacheKey);

        $class = get_class($model);

        // Set the DTO in cache
        $this->cacheHandlerInMemory->set($model);

        /** @var AbstractAggregateRoot $cachedDto */
        $cachedDto = $this->cacheHandlerInMemory->get($cacheKey, $class);

        // Assert that the cached DTO is not null and matches the original DTO
        $this->assertNotNull($cachedDto);
        $this->assertEquals($model->getCacheKey(), $cachedDto->getCacheKey());

        // Delete the DTO from cache
        $this->cacheHandlerInMemory->delete($cacheKey, $class);

        // Try to get the deleted DTO
        $deletedDto = $this->cacheHandlerInMemory->get($cacheKey, $class);

        // Assert that the deleted DTO is null
        $this->assertNull($deletedDto);
    }
}
