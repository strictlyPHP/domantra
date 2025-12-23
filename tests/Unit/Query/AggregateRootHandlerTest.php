<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Query;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Domain\CachedDtoInterface;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\Id;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserDto;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserModel;

class AggregateRootHandlerTest extends TestCase
{
    private DtoCacheHandlerInterface & MockObject $cacheHandler;

    protected AggregateRootHandler $handler;

    protected function setUp(): void
    {
        $this->cacheHandler = $this->createMock(DtoCacheHandlerInterface::class);
        $this->handler = new AggregateRootHandler($this->cacheHandler);
    }

    public function testHandleReturnsCachedModel(): void
    {
        $query = new UserId('test-id');
        $model = UserModel::create(
            id: $query,
            username: 'Test User',
            email: 'test@example.com',
            createdAt: new \DateTimeImmutable(),
        );
        $dto = $model->getDto();

        $this->cacheHandler
            ->expects($this->once())
            ->method('get')
            ->with($query, UserDto::class)
            ->willReturn($dto);

        $result = $this->handler->handle(
            $query,
            new class($model) implements SingleHandlerInterface {
                public function __construct(
                    private UserModel $model
                ) {
                }

                public function __invoke(object $query): UserModel
                {
                    return $this->model;
                }
            },
            null
        );

        $this->assertEquals(json_decode(json_encode($model->getDto())), $result);
    }

    public function testHandleCallsHandlerWhenCacheMiss(): void
    {
        $query = new UserId('test-id');
        $model = UserModel::create(
            id: $query,
            username: 'Test User',
            email: 'test@example.com',
            createdAt: new \DateTimeImmutable(),
        );

        $this->cacheHandler
            ->expects($this->once())
            ->method('get')
            ->with($query, UserDto::class)
            ->willReturn(null);

        $result = $this->handler->handle(
            $query,
            new class($model) implements SingleHandlerInterface {
                public function __construct(
                    private UserModel $model
                ) {
                }

                public function __invoke(object $query): UserModel
                {
                    return $this->model;
                }
            },
            null
        );

        $this->assertEquals(json_decode(json_encode($model->getDto())), $result);
    }

    public function testThrowsExceptionWithWrongReturnType(): void
    {
        $model = new class() extends AbstractAggregateRoot {
            public function __construct()
            {
                parent::__construct();
            }

            public function getDto(): CachedDtoInterface
            {
                return new class() implements CachedDtoInterface {
                    public function getCacheKey(): string
                    {
                        return 'foo';
                    }

                    public function getTtl(): int
                    {
                        return 1;
                    }
                };
            }
        };

        $query = new UserId('test-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                '%s is not allowed as a return type for %s. Use the class of the Dto instead.',
                CachedDtoInterface::class,
                AbstractAggregateRoot::class
            )
        );
        $result = $this->handler->handle(
            $query,
            new class($model) implements SingleHandlerInterface {
                public function __construct(
                    private AbstractAggregateRoot $model
                ) {
                }

                public function __invoke(object $query): AbstractAggregateRoot
                {
                    return $this->model;
                }
            },
            null
        );
    }
}
