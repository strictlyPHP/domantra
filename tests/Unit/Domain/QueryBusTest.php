<?php

declare(strict_types=1);

namespace StrictlyPHP\Tests\Domantra\Unit\Domain;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use StrictlyPHP\Domantra\Domain\PaginatedIdCollection;
use StrictlyPHP\Domantra\Query\AggregateRootHandler;
use StrictlyPHP\Domantra\Query\CachedDtoHandler;
use StrictlyPHP\Domantra\Query\Handlers\DtoHandlerHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\PaginatedHandlerInterface;
use StrictlyPHP\Domantra\Query\Handlers\SingleHandlerInterface;
use StrictlyPHP\Domantra\Query\QueryBus;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\ProfileId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserId;
use StrictlyPHP\Tests\Domantra\Fixtures\Domain\UserQuery;

class QueryBusTest extends TestCase
{
    protected AggregateRootHandler & MockObject $aggregateRootHandler;

    protected CachedDtoHandler & MockObject $cachedDtoHandler;

    protected QueryBus $queryBus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->aggregateRootHandler = $this->createMock(AggregateRootHandler::class);
        $this->cachedDtoHandler = $this->createMock(CachedDtoHandler::class);
        $this->queryBus = new QueryBus(
            $this->aggregateRootHandler,
            $this->cachedDtoHandler
        );
    }

    public function testHandleWithNoRegisteredHandler(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler registered for query: ' . UserId::class);

        $query = new UserId('test-id');
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

    public function testExpandDtoWithInvalidHandlerType(): void
    {
        $queryBus = new class($this->aggregateRootHandler, $this->cachedDtoHandler) extends QueryBus {
            public function exposeExpandDto(object $dto): object
            {
                return $this->expandDto($dto, null);
            }
        };
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf(
                'Handler %s must be an instance of %s or %s',
                'stdClass',
                'StrictlyPHP\\Domantra\\Query\\Handlers\\DtoHandlerHandlerInterface',
                'StrictlyPHP\\Domantra\\Query\\Handlers\\SingleHandlerInterface'
            )
        );

        // Create a mock handler that doesn't implement any of the required interfaces
        $invalidHandler = new class() {};

        // Register the invalid handler for stdClass
        $reflection = new \ReflectionClass(QueryBus::class);
        $handlersProperty = $reflection->getProperty('handlers');
        $handlersProperty->setAccessible(true);
        $handlers = $handlersProperty->getValue($queryBus);
        $handlers['stdClass'] = $invalidHandler;
        $handlersProperty->setValue($queryBus, $handlers);

        // Create a test DTO with a property that will use the invalid handler
        $dto = (object) [
            'test' => (object) [
                'id' => 'test',
            ], // This will be processed by the invalid handler
        ];

        // Call the protected method through our test class
        $queryBus->exposeExpandDto($dto);
    }

    public function testHandleWithRegisteredHandler(): void
    {
        $query = new UserId('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $dto = (object) [
            'id' => $query,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler);

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

    public function testHandleWithCachedDtoHandler(): void
    {
        $userId = new UserId('test-id');
        $profileId = new ProfileId('profile-id');

        $userHandler = $this->createMock(SingleHandlerInterface::class);
        $userDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profile' => $profileId,
        ];

        $profileHandler = $this->createMock(DtoHandlerHandlerInterface::class);
        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'Test Bio',
        ];

        $this->queryBus->registerHandler(UserId::class, $userHandler);
        $this->queryBus->registerHandler(ProfileId::class, $profileHandler);

        $this->aggregateRootHandler->expects($this->once())
            ->method('handle')
            ->with($userId, $userHandler)
            ->willReturn($userDto);

        $this->cachedDtoHandler->expects($this->once())
            ->method('handle')
            ->with($profileId, $profileHandler)
            ->willReturn($profileDto);

        $response = $this->queryBus->handle($userId);

        $expandedDto = (object) [
            'id' => $userId,
            'name' => 'Test Name',
            'profile' => (object) [
                'id' => $profileId,
                'bio' => 'Test Bio',
            ],
        ];
        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }

    public function testHandleWithRegisteredPaginatedHandler(): void
    {
        $query = new UserQuery();
        $id = new UserId('test-id');
        $handler = $this->createMock(SingleHandlerInterface::class);
        $paginatedHandler = $this->createMock(PaginatedHandlerInterface::class);
        $dto = (object) [
            'id' => $id,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler);
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

    public function testHandleExpandedWithRegisteredHandler(): void
    {
        $query = new UserId('test-id');
        $profileId = new ProfileId('profile-id');
        $handler1 = $this->createMock(SingleHandlerInterface::class);
        $handler2 = $this->createMock(SingleHandlerInterface::class);
        $dto = (object) [
            'id' => $query,
            'profile' => $profileId,
            'name' => 'Test Name',
        ];

        $profileDto = (object) [
            'id' => $profileId,
            'bio' => 'hello world',
        ];

        $expandedDto = (object) [
            'id' => $query,
            'profile' => $profileDto,
            'name' => 'Test Name',
        ];

        $this->queryBus->registerHandler(UserId::class, $handler1);
        $this->queryBus->registerHandler(ProfileId::class, $handler2);

        $expectedCalls = [
            [
                'args' => [$query, $handler1],
                'return' => $dto,
            ],
            [
                'args' => [$profileId, $handler2],
                'return' => $profileDto,
            ],
        ];

        $callCount = 0;

        $this->aggregateRootHandler->expects($this->exactly(2))
            ->method('handle')
            ->with($this->callback(function ($arg1) use (&$callCount, $expectedCalls) {
                // Adjust argument check depending on your method signature
                $expectedArgs = $expectedCalls[$callCount]['args'];
                $isValid = $arg1 === $expectedArgs[0]; // example check
                return $isValid;
            }))
            ->willReturnCallback(function () use (&$callCount, $expectedCalls) {
                $returnValue = $expectedCalls[$callCount]['return'];
                $callCount++;
                return $returnValue;
            });

        $response = $this->queryBus->handle($query);

        $expected = (object) [
            'item' => $expandedDto,
        ];
        $this->assertEquals($expected, $response->jsonSerialize());
    }
}
