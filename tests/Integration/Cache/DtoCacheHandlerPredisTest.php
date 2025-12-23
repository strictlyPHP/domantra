<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Integration\Cache;

use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerPredis;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserDto;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;

class DtoCacheHandlerPredisTest extends TestCase
{
    protected DtoCacheHandlerPredis $cacheHandlerPredis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheHandlerPredis = DtoCacheHandlerPredis::create(
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
        $this->cacheHandlerPredis->set($dto);

        /** @var UserDto $cachedDto */
        $cachedDto = $this->cacheHandlerPredis->get($cacheKey, $class);

        // Assert that the cached DTO is not null and matches the original DTO
        $this->assertNotNull($cachedDto);
        $this->assertEquals($dto->getCacheKey(), $cachedDto->getCacheKey());

        // Delete the DTO from cache
        $this->cacheHandlerPredis->delete($cacheKey, $class);

        // Try to get the deleted DTO
        $deletedDto = $this->cacheHandlerPredis->get($cacheKey, $class);

        // Assert that the deleted DTO is null
        $this->assertNull($deletedDto);
    }
}
