<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Query;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\TestDto;

/**
 * Test handler that returns a specific DTO type
 */
class TestDtoHandler implements DtoHandlerHandlerInterface
{
    public function __construct(
        private TestDto $dto
    ) {
    }

    public function __invoke(object $query): TestDto
    {
        return $this->dto;
    }
}

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

        $dto = new TestDto();

        $this->cacheHandler->expects($this->once())
            ->method('get')
            ->with('test-id', TestDto::class)
            ->willReturn($dto);

        $result = $this->handler->handle(
            $query,
            new class($dto) implements DtoHandlerHandlerInterface {
                public function __construct(
                    private TestDto $dto
                ) {
                }

                public function __invoke(object $query): TestDto
                {
                    return $this->dto;
                }
            }
        );

        $this->assertEquals((object) [
            'id' => 'test-id',
            'name' => 'Test',
        ], $result);
    }

    public function testHandleLoadsAndCachesWhenNotInCache(): void
    {
        $query = $this->createMock(\Stringable::class);
        $query->method('__toString')->willReturn('test-id');

        $dto = new TestDto();

        $this->cacheHandler->expects($this->once())
            ->method('get')
            ->with('test-id', TestDto::class)
            ->willReturn(null);

        $this->cacheHandler->expects($this->once())
            ->method('set')
            ->with($dto);

        $result = $this->handler->handle(
            $query,
            new class($dto) implements DtoHandlerHandlerInterface {
                public function __construct(
                    private TestDto $dto
                ) {
                }

                public function __invoke(object $query): TestDto
                {
                    return $this->dto;
                }
            }
        );

        $this->assertEquals((object) [
            'id' => 'test-id',
            'name' => 'Test',
        ], $result);
    }
}
