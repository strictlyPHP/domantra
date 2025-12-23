<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Integration\Cache;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerRedis;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserDto;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;

class DtoCacheHandlerRedisTest extends TestCase
{
    protected DtoCacheHandlerRedis $cacheHandlerRedis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheHandlerRedis = DtoCacheHandlerRedis::create(
            host: 'domantra.redis'
        );
    }

    public function testGetSetDelete(): void
    {
        $cacheKey = 'test-id';
        $dto = new UserDto(
            new UserId($cacheKey),
            'test_username',
            'test_email'
        );

        $class = get_class($dto);

        // Set the DTO in cache
        $this->cacheHandlerRedis->set($dto);

        /** @var UserDto $cachedDto */
        $cachedDto = $this->cacheHandlerRedis->get($cacheKey, $class);

        // Assert that the cached DTO is not null and matches the original DTO
        $this->assertNotNull($cachedDto);
        $this->assertEquals($dto->getCacheKey(), $cachedDto->getCacheKey());

        // Delete the DTO from cache
        $this->cacheHandlerRedis->delete($cacheKey, $class);

        // Try to get the deleted DTO
        $deletedDto = $this->cacheHandlerRedis->get($cacheKey, $class);

        // Assert that the deleted DTO is null
        $this->assertNull($deletedDto);
    }
}
