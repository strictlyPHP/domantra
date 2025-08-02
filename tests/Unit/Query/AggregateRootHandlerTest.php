<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Query;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Cache\DtoCacheHandlerInterface;
use StrictlyPHP\Domantra\Domain\AbstractAggregateRoot;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\Id;
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

        $this->cacheHandler
            ->expects($this->once())
            ->method('get')
            ->with($query, UserModel::class)
            ->willReturn($model);

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
            }
        );

        $this->assertEquals($model->jsonSerialize(), $result);
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
            ->with($query, UserModel::class)
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
            }
        );

        $this->assertEquals($model->jsonSerialize(), $result);
    }
}
