<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Query;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserDto;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;

class CachedDtoHandlerTest extends TestCase
{
    private DtoCacheHandlerInterface & MockObject $cacheHandler;

    protected CachedDtoHandler $handler;

    protected function setUp(): void
    {
        $this->cacheHandler = $this->createMock(DtoCacheHandlerInterface::class);
        $this->handler = new CachedDtoHandler($this->cacheHandler);
    }

    public function testHandleReturnsCachedDto(): void
    {
        $query = $this->createMock(\Stringable::class);
        $query->method('__toString')->willReturn('test-id');

        $dto = new UserDto(
            new UserId('test-id'),
            'Test',
            'test@example.com',
        );

        $this->cacheHandler->expects($this->once())
            ->method('get')
            ->with('test-id', UserDto::class)
            ->willReturn($dto);

        $result = $this->handler->handle(
            $query,
            new class($dto) implements DtoHandlerHandlerInterface {
                public function __construct(
                    private UserDto $dto
                ) {
                }

                public function __invoke(object $query): UserDto
                {
                    return $this->dto;
                }
            },
            null
        );

        $this->assertEquals((object) [
            'id' => new UserId('test-id'),
            'username' => 'Test',
            'email' => 'test@example.com',
        ], $result);
    }

    public function testHandleLoadsAndCachesWhenNotInCache(): void
    {
        $query = $this->createMock(\Stringable::class);
        $query->method('__toString')->willReturn('test-id');

        $dto = new UserDto(
            new UserId('test-id'),
            'Test',
            'test@example.com',
        );

        $this->cacheHandler->expects($this->once())
            ->method('get')
            ->with('test-id', UserDto::class)
            ->willReturn(null);

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($dto);

        $result = $this->handler->handle(
            $query,
            new class($dto) implements DtoHandlerHandlerInterface {
                public function __construct(
                    private UserDto $dto
                ) {
                }

                public function __invoke(object $query): UserDto
                {
                    return $this->dto;
                }
            },
            null
        );

        $this->assertEquals((object) [
            'id' => new UserId('test-id'),
            'username' => 'Test',
            'email' => 'test@example.com',
        ], $result);
    }
}
