<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\QueryBus;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\Id;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserQuery;

class QueryBusTest extends TestCase
{
    protected AggregateRootHandler & MockObject $aggregateRootHandler;

    protected QueryBus $queryBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregateRootHandler = $this->createMock(AggregateRootHandler::class);
        $this->queryBus = new QueryBus($this->aggregateRootHandler);
    }

    public function testHandleWithNoRegisteredHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for query: ' . Id::class);

        $query = new Id('test-id');
        $this->queryBus->handle($query);
    }

    public function testHandleWithUnStringableQuery(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query must implement Stringable when the return type is StrictlyPHP\Domantra\Domain\AbstractAggregateRoot');

        $query = new \stdClass(); // Not a Stringable object
        $handler = $this->createMock(SingleHandlerInterface::class);

        $this->queryBus->registerHandler(\stdClass::class, $handler);
        $this->queryBus->handle($query);
    }

    public function testHandleWithRegisteredHandler(): void
    {
        $query = new Id('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $dto = (object) [
            'id' => $query,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(Id::class, $handler);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($query, $handler)
            ->willReturn($dto);

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'item' => $dto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithRegisteredPaginatedHandler(): void
    {
        $query = new UserQuery();
        $id = new Id('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $paginatedHandler = $this->createMock(PaginatedHandlerInterface::class);
        $dto = (object) [
            'id' => $id,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(Id::class, $handler);
        $this->queryBus->registerHandler(UserQuery::class, $paginatedHandler);

        $paginatedHandler->expects($this->once())
            ->method('__invoke')
            ->with($query)
            ->willReturn(new PaginatedIdCollection(ids: [$id], page: 1, perPage: 10, totalItems: 1));

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($id, $handler)
            ->willReturn($dto);

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'items' => [$dto],
            'page' => 1,
            'perPage' => 10,
            'totalItems' => 1,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }
}
